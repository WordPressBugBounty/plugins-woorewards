<?php
namespace LWS\WOOREWARDS\Core{

	// don't call the file directly
	if( !defined( 'ABSPATH' ) ) exit();

	/** Sponsorship helper. */
	class Sponsorship
	{

		/* register hook to react about:
		 * * add sponsored (ajax).
		 * * sponsored register.
		 * * sponsored first order. */
		static public function register()
		{
			$me = new self();
			\add_filter('wpml_user_language', array($me, 'guessSponsoredLanguage'), 10, 2);

			if( !empty(\get_option('lws_woorewards_event_enabled_sponsorship', 'on')) )
			{
				\add_action('user_register', array($me, 'onCustomerRegister'), 65535, 1 );
			}
			\add_action('woocommerce_checkout_order_processed', array($me, 'legacySaveOrderSponsor'), 10, 3);
			\add_action('woocommerce_store_api_checkout_order_processed', array($me, 'saveOrderSponsor'), 10, 1);
		}

		/** if email does not belong to a register user, see for a sponsored guy.
		 * Then look for sponsor language. */
		function guessSponsoredLanguage($lang, $email)
		{
			if( !\get_user_by('email', $email) )
			{
				if( $sponsorId = $this->getSponsorIdFor($email) )
				{
					$l = \get_user_meta($sponsorId, 'icl_admin_language', true);
					if( $l )
						$lang = $l;
				}
			}
			return $lang;
		}

		protected function createReward($email, $sponsor=false)
		{
			$unlockable = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->load(array(
				'numberposts' => 1,
				'meta_query'  => array(
					array(
						'key'     => 'wre_sponsored_reward',
						'value'   => 'yes',
						'compare' => 'LIKE'
					)
				)
			))->last();

			if( empty($unlockable) )
				return array('sponsor' => $sponsor, 'type' => '', 'unlockable' => null, 'reward' => array());

			$user = new \WP_User(0);
			$user->user_email = $email;
			$reward = $unlockable->createReward($user);

			if( false === $reward )
				return array('sponsor' => $sponsor, 'type' => '', 'unlockable' => null, 'reward' => array());
			else
				return array('sponsor' => $sponsor, 'type' => $unlockable->getType(), 'unlockable' => $unlockable, 'reward' => $reward);
		}

		/** @param $user (int|\WP_User) the sponsored guy */
		function forceUserSponsor($user, $sponsorId, $origin='manual', $newUser=false)
		{
			if( !\is_a($user, '\WP_User') )
				$user = \get_user_by('ID', $user);

			if( $user && $user->ID )
			{
				$oldSonpsorId = \get_user_meta($user->ID, 'lws_woorewards_sponsored_by', true);
				if( $user->user_email )
				{
					global $wpdb;
					$wpdb->delete($wpdb->usermeta, array('meta_key' => 'lws_wooreward_used_sponsorship', 'meta_value' => $user->user_email));
					if( $sponsorId )
						\add_user_meta($sponsorId, 'lws_wooreward_used_sponsorship', $user->user_email, false);
				}
				\update_user_meta($user->ID, 'lws_woorewards_sponsored_by', $sponsorId);
				\update_user_meta($user->ID, 'lws_woorewards_sponsored_origin', $origin);
				\do_action('lws_woorewards_sponsored_registration', $sponsorId, $user, $oldSonpsorId, $origin, $newUser);
			}
			else
				error_log("Force referral on unknown user.");
		}

		/** new customer, look for sponsor. */
		function onCustomerRegister($user_id)
		{
			if( empty($user = \get_user_by('ID', $user_id)) )
				return;
			if( empty($email = trim($user->user_email)) )
				return;
			if( \get_user_meta($user->ID, 'lws_woorewards_at_registration_sponsorship', true) == 'done' )
				return;
			\update_user_meta($user->ID, 'lws_woorewards_at_registration_sponsorship', 'done');

			$sponsor = (object)array('id'=>false, 'origin'=>false);
			$sponsor = \apply_filters('lws_woorewards_fresh_user_sponsored_by', $sponsor, $user, $email);
			if( !$sponsor->id )
			{
				$sponsor->id = $this->getSponsorIdFor($email);
				$sponsor->origin = 'sponsor';
			}

			// is customer sponsored and by who?
			if( $sponsor->id && $this->userCan($sponsor->id) )
			{
				$oldSonpsorId = \get_user_meta($user->ID, 'lws_woorewards_sponsored_by', true);
				global $wpdb;
				$wpdb->delete($wpdb->usermeta, array('meta_key' => 'lws_wooreward_used_sponsorship', 'meta_value' => $email));
				\add_user_meta($sponsor->id, 'lws_wooreward_used_sponsorship', $email, false);

				\update_user_meta($user->ID, 'lws_woorewards_sponsored_by', $sponsor->id);
				\update_user_meta($user->ID, 'lws_woorewards_sponsored_origin', $sponsor->origin);
				\do_action('lws_woorewards_sponsored_registration', $sponsor->id, $user, $oldSonpsorId, $sponsor->origin, true);
			}
		}

		function getReferralKeys()
		{
			return (object)array(
				'id' => 'lws_wre_refid_'.COOKIEHASH,
				'hash' => 'lws_wre_refha_'.COOKIEHASH,
				'origin' => 'lws_wre_refor_'.COOKIEHASH,
			);
		}

		/** @return (object) {user_id, hash} */
		function getCurrentReferral()
		{
			$key = $this->getReferralKeys();
			$ref = (object)array(
				'user_id' => isset($_COOKIE[$key->id]) ? \absint($_COOKIE[$key->id]) : false,
				'hash'    => isset($_COOKIE[$key->hash]) ? \sanitize_key($_COOKIE[$key->hash]) : false,
				'origin'  => isset($_COOKIE[$key->origin]) ? \sanitize_key($_COOKIE[$key->origin]) : false,
			);
			if (\get_current_user_id() == $ref->user_id)
				$ref = (object)array('user_id'=>false,'origin'=>false,'hash'=>false);
			return $ref;
		}

		/** @param $userId (int) referral id, the user that shared a post or posted a link.
		 *  @param $origin (string) 'referral', or the social network key.
		 *  @param $timeount (int) delay until cookie expiry, default is 12h = 60*60*12
		 *  @see https://www.php.net/manual/fr/function.setcookie.php */
		function setCurrentReferral($userId, $hash, $origin, $expires=false)
		{
			if (\get_current_user_id() == $userId)
				return;

			if( false === $expires )
				$expires = \absint(\get_option('lws_woorewards_referral_cookie_timeout', 60*60*12));
			if( $expires )
				$expires += \time();
			$key = $this->getReferralKeys();
			\setcookie($key->id, $userId, $expires, COOKIEPATH, COOKIE_DOMAIN);
			\setcookie($key->hash, \sanitize_key($hash), $expires, COOKIEPATH, COOKIE_DOMAIN);
			\setcookie($key->origin, \sanitize_key($origin), $expires, COOKIEPATH, COOKIE_DOMAIN);
		}

		/** @param $return faky argument, provided to be callable as a filter.
		 * @return $return argument */
		function clearCurrentReferral($return=true)
		{
			$key = $this->getReferralKeys();
			$expires = time() - 3600;
			\setcookie($key->id, '', $expires, COOKIEPATH, COOKIE_DOMAIN);
			\setcookie($key->hash, '', $expires, COOKIEPATH, COOKIE_DOMAIN);
			\setcookie($key->origin, '', $expires, COOKIEPATH, COOKIE_DOMAIN);
			return $return;
		}

		function legacySaveOrderSponsor($orderId, $postedData, $order)
		{
			$this->saveOrderSponsor($order);
		}

		/** Since order status change can occured later,
		 *	Keep the sponsor at checkout time. */
		function saveOrderSponsor($order)
		{
			$sponsorship = $this->getUsersFromOrder($order, true);
			$order->update_meta_data('lws_woorewards_sponsor_at_checkout', $sponsorship);
			$order->save_meta_data();
		}

		/*	Same as getUsersFromOrder() but without an order
		 *	is not reliable for guest.
		 *	@return (Object) {sponsored_id, sponsor_id, sponsored_email}
		 *	sponsored_email is always FALSE
		 *	sponsored_id is false for not logged visitor
		 *	sponsor_id is false if no sponsor found or guest order but $guestAllowed is false. */
		function getCurrentUsers()
		{
			$users = (object)array(
				'sponsored_id'    => \get_current_user_id(),
				'sponsored_email' => false,
				'sponsor_id'      => false,
				'origin'          => false,
			);

			$ref = $this->getCurrentReferral();
			if( !$users->sponsor_id && $ref->user_id && $ref->hash && $ref->origin )
			{
				$users->sponsor_id = $ref->user_id;
				$users->origin = $ref->origin;
			}

			$users = \apply_filters('lws_woorewards_customer_sponsored_by', $users, false, true, $ref);
			if( !$users->sponsor_id )
			{
				if( $users->sponsored_id )
				{
					$users->sponsor_id = $this->getSponsorIdFor($users->sponsored_id);
					if( !$users->sponsor_id && ($user = \get_user_by('ID', $users->sponsored_id)) )
					{
						// backward compatibility
						$users->sponsor_id = $this->getSponsorIdFor($user->user_email);
					}
				}

				// read origin
				if ($users->sponsor_id) {
					if ($users->sponsored_id) {
						$users->origin = \sanitize_key(\get_user_meta($users->sponsored_id, 'lws_woorewards_sponsored_origin', true));
					}
					if (!$users->origin) {
						$users->origin = 'sponsor';
					}
				}
			}

			if ($users->sponsor_id == $users->sponsored_id)
				$users->sponsor_id = false;
			if (!$users->sponsor_id)
				$users->origin = false;
			return $users;
		}

		/** @param $order (\WC_Order)
		 *	@return object
		 *	* string sponsored_email is always feed
		 *	* int sponsored_id is false for guest order
		 *	* int sponsor_id is false if no sponsor found or guest order but $guestAllowed is false. */
		function getUsersFromOrder($order, $guestAllowed=false)
		{
			$users = array(
				'sponsored_id'    => ($userId = $order->get_customer_id()) ? $userId : false,
				'sponsored_email' => $order->get_billing_email(),
				'sponsor_id'      => false,
				'origin'          => false,
			);

			if( !($guestAllowed || $order->get_customer_id()) )
				return (object)$users;
			if (!$users['sponsored_id'])
				$users['sponsored_id'] = \LWS\Adminpanel\Tools\Conveniences::getCustomerId(false, $order);

			$meta = $order->get_meta('lws_woorewards_sponsor_at_checkout', true);
			if( $meta && is_object($meta) )
			{
				$users = array_merge($users, get_object_vars($meta));
			}
			$users = (object)$users;

			$ref = $this->getCurrentReferral();
			if( !$users->sponsor_id && $ref->user_id && $ref->hash && $ref->origin )
			{
				$users->sponsor_id = $ref->user_id;
				$users->origin = $ref->origin;
			}

			$users = \apply_filters('lws_woorewards_customer_sponsored_by', $users, $order, $guestAllowed, $ref);
			if( !$users->sponsor_id )
			{
				if( $users->sponsored_id )
				{
					$users->sponsor_id = $this->getSponsorIdFor($users->sponsored_id);
					if( !$users->sponsor_id && ($user = \get_user_by('ID', $users->sponsored_id)) )
					{
						// backward compatibility
						$users->sponsor_id = $this->getSponsorIdFor($user->user_email);
					}
				}
				else if( $guestAllowed )
				{
					$users->sponsor_id = $this->getSponsorIdFor($users->sponsored_email);
					// not themself
					if( $users->sponsor_id ) {
						$sponsor = \get_user_by('ID', $users->sponsor_id);
						if( !$sponsor || $sponsor->user_email == $users->sponsored_email ) {
							$users->sponsor_id = false;
						}
					}
					$sponsee = \get_user_by('email', $users->sponsored_email);
					if ($sponsee && $sponsee->ID) {
						$users->sponsored_id = $sponsee->ID;
					}
				}

				// read origin
				if ($users->sponsor_id) {
					if ($users->sponsored_id) {
						$users->origin = \sanitize_key(\get_user_meta($users->sponsored_id, 'lws_woorewards_sponsored_origin', true));
					}
					if (!$users->origin) {
						$users->origin = 'sponsor';
					}
				}
			}

			if ($users->sponsor_id == $users->sponsored_id)
				$users->sponsor_id = false;
			if (!$users->sponsor_id)
				$users->origin = false;
			return $users;
		}

		/** @param $sponsored (int|string|\WP_User) user objet or user id.
		 *	@return (false|int) */
		function getSponsorIdFor($sponsored)
		{
			$sponsor_id = false;
			$userId = \intval(\is_object($sponsored) ? $sponsored->ID : $sponsored);
			if ($userId)
			{
				$sponsor_id = \intval(\get_user_meta($userId, 'lws_woorewards_sponsored_by', true));
				if ($sponsor_id == $userId)
					$sponsor_id = false;
			}
			else
			{
				global $wpdb;
				$sql ="SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='lws_wooreward_used_sponsorship' AND meta_value=%s";
				$sponsor_id = $wpdb->get_var($wpdb->prepare($sql, $sponsored));
			}
			return $sponsor_id;
		}

		/** @param $sponsored (int|string) email or user id.
		 *	@return (false|\WP_User) */
		function getSponsorFor($sponsored)
		{
			if( !($sponsor_id = $this->getSponsorIdFor($sponsored)) )
				return false;

			$sponsor = \get_user_by('ID', $sponsor_id);
			if( empty($sponsor) )
				error_log("Referral defined for '$sponsored' but referrer user cannot be found: $sponsor_id");
			return $sponsor;
		}

		/**	Bind sponsor and sponsored for later use.
		 *	Create a reward waiting for sponsored and send him a mail about it.
		 *	@param $sponsor (int|\WP_User)
		 *	@param $sponsored (string) email (or several emails, comma or semicolon separated)
		 *	@param $lazy (bool) do not test if guest that already ordered.
		 *	@param $override (bool) override previous sponsorship.
		 * @return mixed (bool|\WP_Error) */
		public function addRelationship($sponsor, $sponsored, $lazy=false, $override=false)
		{
			if( empty($sponsor) )
				return new \WP_Error('unauthorized', __("You must be logged in.", 'woorewards-lite'));
			if( !is_a($sponsor, 'WP_User') && empty($sponsor = \get_user_by('ID', $sponsor)) )
				return new \WP_Error('unknown', __("Unknown user.", 'woorewards-lite'));
			if( empty($sponsor->ID) )
				return new \WP_Error('unauthorized', __("You must be logged in.", 'woorewards-lite'));
			if( empty(trim(str_replace(array(',', ';'), '', $sponsored))) )
				return new \WP_Error('bad-argument', __("Referee email is empty.", 'woorewards-lite'));

			if( empty(\get_option('lws_woorewards_event_enabled_sponsorship', 'on')) )
			return new \WP_Error('disabled', __("Referral has been temporary disabled.", 'woorewards-lite'));

			if( !($max = $this->userCan($sponsor->ID)) )
			return new \WP_Error('locked', __("Maximum referrals reached.", 'woorewards-lite'));

			$emails = \preg_split('/\s*[;,]\s*/', $sponsored, -1, PREG_SPLIT_NO_EMPTY);
			if( !$emails )
				return new \WP_Error('split', __("An error occured during referees emails reading.", 'woorewards-lite'));

			foreach( $emails as $email )
			{
				if( \is_wp_error($email = $this->isEligible($email, $lazy, $override)) )
					return $email;
				if( $email == $sponsor->user_email )
					return new \WP_Error('forbidden', __("You cannot refer yourself.", 'woorewards-lite'));
				if( $max !== true && --$max < 0 )
					return new \WP_Error('locked', __("Some emails was omitted, maximum referrals reached.", 'woorewards-lite'));

				if ($override) {
					// remove any previous one
					global $wpdb;
					$wpdb->query($wpdb->prepare(
						"DELETE FROM {$wpdb->usermeta} WHERE meta_key='lws_wooreward_used_sponsorship' AND meta_value=%s",
						$email
					));
				}
				\add_user_meta($sponsor->ID, 'lws_wooreward_used_sponsorship', $email, false);
				\do_action('lws_woorewards_sponsorship_done', $sponsor, $email);

				\do_action('lws_mail_send', $email, 'wr_sponsored', $this->createReward($email, $sponsor));
			}
			return true;
		}

		/** user can be sponsor and still have room for it.
		 * @param $sponsor (int|\WP_User)
		 * @return (bool|int) true if user can and no sponsorship limit.
		 * else return number of email the user can sponsored. */
		public function userCan($sponsor, $requestedCount=1)
		{
			if( empty($sponsor) )
				return false;
			if( empty($user_id = is_a($sponsor, 'WP_User') ? $sponsor->ID : intval($sponsor)) )
				return false;

			$max_sponsorship = intval(\get_option('lws_wooreward_max_sponsorship_count', '0'));
			if (!$max_sponsorship)
				return true;

			$used = count(\get_user_meta($user_id, 'lws_wooreward_used_sponsorship', false));
			return max(0, $max_sponsorship - $used);
		}

		/** never sponsored or registered customer.
		 * @param $sponsored (string) email
		 * @param $lazy (bool) do not test if guest that already ordered.
		 * @param $override (bool) do not test previous sponsorship.
		 * @return mixed (\WP_Error|string) the cleaned email if ok. */
		public function isEligible($sponsored, $lazy=false, $override=false)
		{
			$email = trim($sponsored);
			if( !\is_email($email) )
				return new \WP_Error('bad-format', sprintf(__("'%s' Email address is not valid.", 'woorewards-lite'), $email));

			global $wpdb;
			if (!$override && $wpdb->get_var($wpdb->prepare("SELECT COUNT(umeta_id) FROM {$wpdb->usermeta} WHERE meta_key='lws_wooreward_used_sponsorship' AND meta_value=%s LIMIT 0, 1", $email)) > 0)
				return new \WP_Error('already-sponsored', sprintf(__("%s is already referred.", 'woorewards-lite'), $email));

			if( !$lazy && \LWS\WOOREWARDS\Conveniences::getOrderCount($email, false, 'sponsorship', true) > 0 )
				return new \WP_Error('already-customer', sprintf(__("%s is already an active customer.", 'woorewards-lite'), $email));

			return $email;
		}

		/**	@deprecated user \LWS\WOOREWARDS\Conveniences::getOrderCount() instead */
		static function getOrderCountByEMail($email, $excludedOrderId=false)
		{
			return \LWS\WOOREWARDS\Conveniences::getOrderCount($email, $excludedOrderId, 'sponsorship', true);
		}

		/**	@deprecated user \LWS\WOOREWARDS\Conveniences::getOrderCount() instead */
		static function getOrderCountById($userId, $exceptOrderId=false)
		{
			return \LWS\WOOREWARDS\Conveniences::getOrderCount($userId, $exceptOrderId, 'sponsorship', false);
		}

	}
}

/**	@deprecated
 *	backward compatibility support (class moved since v5.0) */
namespace LWS\WOOREWARDS\PRO\Core{
	class Sponsorship extends \LWS\WOOREWARDS\Core\Sponsorship{}
}