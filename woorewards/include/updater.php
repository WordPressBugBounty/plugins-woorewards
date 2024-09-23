<?php
namespace LWS\WOOREWARDS;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** satic class to manage activation and version updates. */
class Updater
{
	private static $defaultStackId = 'default';

	/** First use */
	static function activate()
	{
		// repeated here since WooCommerce could be activated afterward
		// and re-activate this will resolve WC role missing capacity problem
		self::addCapacity();
	}

	static function checkUpdate()
	{
		$wpInstalling = \wp_installing();
		\wp_installing(true); // should force no cache

		$oldVersion = \get_option('lws_woorewards_version', '0');
		if( version_compare($oldVersion, LWS_WOOREWARDS_VERSION, '<') )
		{
			\wp_suspend_cache_invalidation(false);
			self::update($oldVersion, LWS_WOOREWARDS_VERSION);
			update_option('lws_woorewards_version', LWS_WOOREWARDS_VERSION);
		}

		\wp_installing($wpInstalling);
	}

	/** Update
	 * @param $fromVersion previously registered version.
	 * @param $toVersion actual version. */
	static function update($fromVersion, $toVersion)
	{
		global $wpdb;

		if( version_compare($fromVersion, '2.6.6', '<') )
		{
			self::addCapacity();
			\update_option('lws_woorewards_redirect_to_licence', 1);
		}

		self::updateDatabaseTables();

		if( version_compare($fromVersion, '3.0.0', '<') )
		{
			\wp_clear_scheduled_hook('lws_woorewards_coupon_reminder_event'); // replaced by 'lws_woorewards_daily_event'

			if( !(defined('LWS_WIZARD_SUMMONER') && LWS_WIZARD_SUMMONER) )
				self::addDefaultPools();

			self::databaseMigrationv2v3();
			self::optionMigrationv2v3();

			// Convert each shop_order postmeta 'lws_woorewards_validate_order' to new event <once> mark
			foreach( array('lws_woorewards_events_firstorder', 'lws_woorewards_events_orderamount', 'lws_woorewards_events_ordercompleted') as $meta ) {
				// let as it is for hpos support, in a new install with WC 7.8+, we can expect a WR 5.0+ fresh install too.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) SELECT s.post_id, %s, s.meta_value FROM {$wpdb->postmeta} AS s WHERE s.meta_key='lws_woorewards_validate_order'", $meta));
			}
		}

		if( version_compare($fromVersion, '3.13.2', '<') )
		{
			self::updateNamesWPML();
		}

		if( version_compare($fromVersion, '4.0.0', '<') )
		{
			self::updateProductReviewEvent(); // product review is now in free
			self::updateDefaultPoolEvents();

			\do_action('lws_adminpanel_licenses_migration', 'woorewards');

			// badly named option mirgated from bool to list
			$status = \get_option('lws_woorewards_points_distribution_status', false);
			if( false === $status )
			{
				$status = array('processing', 'completed');
				if( \get_option('lws_woorewards_coupon_state' , false) ) // checked: Points on 'Complete' order only
					$status = array('completed');
				\update_option('lws_woorewards_points_distribution_status', $status);
			}

			self::eventPoolNameToId();
		}

		// fresh install
		if (!$fromVersion) {
			if (false === \get_option('lws_woorewards_cart_content_side', false)) {
				\update_option('lws_woorewards_cart_content_side', '<div class="cart-collaterals cross-sells">[wr_points_on_cart showall="yes"]</div>');
			}
		}

		if (!\get_option('lws_woorewards_install_version')) {
			if ($fromVersion && \version_compare($fromVersion, '1.0.0', '>')) {
				// update (fromVersion greater than 0)
				\update_option('lws_woorewards_install_version', '4.0.0');
			} else {
				// fresh install (from version is '0')
				\update_option('lws_woorewards_install_version', LWS_WOOREWARDS_VERSION);
			}
		}

		if ($fromVersion && \version_compare($fromVersion, '4.6.4', '<')) {
			$couponPrefixLength = \get_option('lws_woorewards_coupon_code_length', false);
			if (!$couponPrefixLength && (false !== $couponPrefixLength)) {
				// saved but empty, previously give 8 (since default was 10)
				\update_option('lws_woorewards_coupon_code_length', 8);
			}
		}

		if ($fromVersion && \version_compare($fromVersion, '1.0.0', '>') && \version_compare($fromVersion, '4.9.1', '<')) {
			self::setOldMailEnabledDefaultValue();
		}

		if ($fromVersion && \version_compare($fromVersion, '5.0.0', '<')) {
			// moved from pro
			$type = array(
				'lws_woorewards_pro_events_sponsoredorderamount' => 'lws_woorewards_events_sponsoredorderamount',
				'lws_woorewards_pro_events_sponsoredfirstorder'  => 'lws_woorewards_events_sponsoredorder',
			);
			foreach ($type as $src => $dst) {
				$wpdb->query("UPDATE {$wpdb->postmeta} SET meta_value='{$dst}' WHERE meta_key='wre_event_type' AND meta_value='{$src}'");
			}
			self::addV5PrefabEvents();
		}

		$defaultOptions = array(
			'lws_woorewards_show_loading_order_and_priority' => '',
			'lws_woorewards_hide_internal_meta' => 'on',
			'lws_woorewards_event_enabled_sponsorship' => 'on',
			'lws_woorewards_referral_back_give_sponsorship' => 'on',
			'lws_woorewards_sponsorship_tinify_enabled' => '',
			'lws_woorewards_sponsorship_short_url' => '',
			'lws_woorewards_points_distribution_status' => array('processing', 'completed'),
		);
		foreach ($defaultOptions as $optName => $optValue) {
			if (false === \get_option($optName, false)) {
				\update_option($optName, $optValue);
			}
		}

		// woorewards is based on woocommerce coupons, so enable them
		\update_option('woocommerce_enable_coupons', 'yes');
		\update_option('lws_woorewards_ignore_woocommerce_disable_coupons', '');
	}

	/** keep old default value since it became Off by default */
	private static function setOldMailEnabledDefaultValue()
	{
		$enabledMails = array(
			'wr_new_reward' => 'on',
			'wr_achieved' => 'on',
			'wr_available_unlockables' => 'on',
		);
		foreach ($enabledMails as $template => $value) {
			if (false === \get_option('lws_woorewards_enabled_mail_' . $template, false)) {
				\update_option('lws_woorewards_enabled_mail_' . $template, $value);
			}
		}
	}

	private static function eventPoolNameToId()
	{
		global $wpdb;
		$sql = <<<EOT
SELECT p.post_name, m.post_id
FROM {$wpdb->postmeta} as m
INNER JOIN {$wpdb->posts} as e ON m.post_id=e.ID
INNER JOIN {$wpdb->posts} as p ON p.ID=e.post_parent
WHERE m.`meta_key`='wre_event_type'
AND m.`meta_value` = 'lws_woorewards_events_productreview'
EOT;
		$events = $wpdb->get_results($sql);
		foreach( $events as $event )
		{
			$up = <<<EOT
UPDATE {$wpdb->usermeta}
SET `meta_key`='lws_wre_event_review_{$event->post_id}'
WHERE `meta_key`='lws_wre_event_review_{$event->post_name}'
EOT;
			$wpdb->query($up); // phpcs:ignore WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	public static function log($msg)
	{
		if( !empty($msg) )
			error_log($msg);
	}

	private static function updateProductReviewEvent()
	{
		global $wpdb;
		$wpdb->query("UPDATE {$wpdb->postmeta} SET meta_value = 'lws_woorewards_events_productreview' WHERE meta_value = 'lws_woorewards_pro_events_productreview'");
	}

	private static function updateDefaultPoolEvents()
	{
		$pools = self::loadStandardPool(true);
		if( $pools->count() <= 0 )
			return;
		$pool = $pools->get(0);
		if( 1 != $pool->getEvents()->filter(function($item){return $item->getType() == 'lws_woorewards_events_productreview';})->count() )
		{
			require_once LWS_WOOREWARDS_INCLUDES.'/events/productreview.php';
			$event = new \LWS\WOOREWARDS\Events\ProductReview();
			$pool->addEvent($event, 0);
			$pool->save();
		}
	}

	private static function updateDatabaseTables()
	{
		global $wpdb;
		/// Alter table historic: Add stack field
		$thistoric = $wpdb->prefix.'lws_wr_historic';
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE $thistoric (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			stack varchar(255) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '' COMMENT 'Each pool can have its own or share point stack',
			mvt_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			points_moved int(10) NULL DEFAULT NULL,
			new_total int(20) NULL DEFAULT NULL,
			commentar text NOT NULL,
			`origin` tinytext NOT NULL DEFAULT '' COMMENT 'eg. unlockable/event post id (max 255 char)',
			`origin2` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Additionnal info. eg. user_id that trigger the event origin',
			`order_id` INT(20) NULL DEFAULT NULL COMMENT 'If about a wc_order = post.ID',
			`blog_id` INT(20) NULL DEFAULT NULL COMMENT 'For multisite, the current blog during operation',
			PRIMARY KEY id  (id),
			KEY `user_id` (`user_id`),
			KEY `stack` (`stack`)
			) $charset_collate;";

		$tSuccess = $wpdb->prefix.'lws_wr_achieved_log';
		$sqlAchieved = "CREATE TABLE $tSuccess (
			`id` bigint(30) NOT NULL AUTO_INCREMENT,
			`user_id` bigint(20) NOT NULL COMMENT 'recipient user id',
			`creation` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`display` TIMESTAMP NULL DEFAULT NULL,
			`popup` int(1) NOT NULL DEFAULT 1 COMMENT 'Is the achievement must pop to the user page or (false) only for log purpose',
			`title` text NOT NULL DEFAULT '',
			`message` text NOT NULL DEFAULT '',
			`image` text NOT NULL DEFAULT '' COMMENT 'Achievement icon, if no url given, a default image is picked',
			`background` text NOT NULL DEFAULT '' COMMENT 'Achievement icon background, if no url given, a default image is picked',
			`badge_id` bigint(20) NULL DEFAULT NULL COMMENT 'The source badge if any',
			`origin` tinytext NOT NULL DEFAULT '' COMMENT 'source of the achievement',
			PRIMARY KEY id  (id),
			KEY `user_id` (`user_id`),
			KEY `display` (`display`)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		ob_start(array(__CLASS__, 'log')); // dbDelta could write on standard output
		dbDelta( $sql );
		dbDelta( $sqlAchieved );
		ob_end_flush();
	}

	/** Point history table migration. */
	private static function databaseMigrationv2v3()
	{
		global $wpdb;
		$thistoric = $wpdb->prefix.'lws_wr_historic';

		$default = !empty(self::$defaultStackId) ? self::$defaultStackId : 'default';

		$wpdb->query("ALTER TABLE {$thistoric} CHANGE points_moved points_moved SMALLINT(10) NULL DEFAULT NULL;"); // phpcs:ignore WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query($wpdb->prepare("UPDATE {$thistoric} SET stack=%s WHERE stack=''", $default)); // phpcs:ignore WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$tmeta = $wpdb->usermeta;
		$mkey = \LWS\WOOREWARDS\Core\PointStack::MetaPrefix;
		// this query only works on MySql, we should only update last entry per user but it is ok like this
		$copyPts = "UPDATE $thistoric INNER JOIN $tmeta ON $thistoric.user_id=$tmeta.user_id AND $tmeta.meta_key='lws_wr_points' SET new_total=$tmeta.meta_value";
		if( false !== $wpdb->query($copyPts) ) // phpcs:ignore WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		{
			// point usermeta key renamed
			$wpdb->query($wpdb->prepare("UPDATE {$tmeta} SET meta_key=%s WHERE meta_key='lws_wr_points'", $mkey . $default)); // phpcs:ignore WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	private static function optionMigrationv2v3()
	{
		// mail common settings
		$prefix = 'lws_mail_'.'woorewards'.'_attribute_';
		\add_option($prefix.'headerpic', \get_option('lws_woorewards_mail_attribute_headerpic', ''));
		\add_option($prefix.'footer',    \get_option('lws_woorewards_mail_attribute_footertext', ''));

		// mail 'wr_new_reward'
		$suffix = 'wr_new_reward';
		\add_option('lws_mail_subject_' .$suffix, \get_option('lws_woorewards_mail_subject_newcoupon', ''));
		\add_option('lws_mail_title_'   .$suffix, \get_option('lws_woorewards_mail_title_newcoupon', ''));
		\add_option('lws_mail_header_'  .$suffix, \get_option('lws_woorewards_mail_header_newcoupon', ''));
		\add_option('lws_mail_template_'.$suffix, \get_option('lws_woorewards_mail_template_newcoupon', ''));
	}

	/** Add 'manage_rewards' capacity to 'administrator' and 'shop_manager'. */
	private static function addCapacity()
	{
		$cap = 'manage_rewards';
		foreach( array('administrator', 'shop_manager') as $slug )
		{
			$role = \get_role($slug);
			if( !empty($role) && !$role->has_cap($cap) )
			{
				$role->add_cap($cap);
			}
		}
	}

	/** Add pool "loyalty system -> standard" */
	public static function addDefaultPools()
	{
		$pools = self::loadStandardPool(false);

		if( $pools->count() <= 0 )
		{
			$name = 'default';
			if( \is_multisite() )
				$name .= \get_current_blog_id();
			// create the default pool for free version
			$pool = $pools->create($name)->last();
			$pool->setOptions(array(
				'type'      => \LWS\WOOREWARDS\Core\Pool::T_STANDARD,
				'disabled'  => true,
				'title'     => __("Standard System", 'woorewards-lite'),
				'whitelist' => array(\LWS\WOOREWARDS\Core\Pool::T_STANDARD)
			));

			// order amount
			require_once LWS_WOOREWARDS_INCLUDES.'/events/orderamount.php';
			$event = new \LWS\WOOREWARDS\Events\OrderAmount();
			$event->setDenominator(\get_option('lws_woorewards_value', 1));
			$pool->addEvent($event, intval(\get_option('lws_woorewards_points', 0)));

			// order completed
			require_once LWS_WOOREWARDS_INCLUDES.'/events/ordercompleted.php';
			$event = new \LWS\WOOREWARDS\Events\OrderCompleted();
			$pool->addEvent($event, intval(\get_option('lws_woorewards_rewards_orders', 0)));
			// first order
			require_once LWS_WOOREWARDS_INCLUDES.'/events/firstorder.php';
			$event = new \LWS\WOOREWARDS\Events\FirstOrder();
			$pool->addEvent($event, intval(\get_option('lws_woorewards_rewards_orders_first', 0)));
			// product review
			require_once LWS_WOOREWARDS_INCLUDES.'/events/productreview.php';
			$event = new \LWS\WOOREWARDS\Events\ProductReview();
			$pool->addEvent($event, 0);

			// coupon
			require_once LWS_WOOREWARDS_INCLUDES.'/unlockables/coupon.php';
			$coupon = new \LWS\WOOREWARDS\Unlockables\Coupon();
			$coupon->setValue(\get_option('lws_woorewards_value_coupon', ''));
			$coupon->setTimeout(\get_option('lws_woorewards_expiry_days', ''));
			$pool->addUnlockable($coupon, intval(\get_option('lws_woorewards_stage', 0)));

			if( $fromV2 = (false !== \get_option('lws_woorewards_value', false)) )
				$pool->setOption('public', \LWS\Adminpanel\Tools\Conveniences::isWC());

			$pool->save();
			if( !empty($pool->getId()) ) // not deletable
			{
				\clean_post_cache($pool->getId());
				\update_post_meta($pool->getId(), 'wre_pool_prefab', 'yes');
				\update_option('lws_wr_default_pool_name', $pool->getName());
				self::$defaultStackId = $pool->getStackId();

				if($fromV2)
				{
					// remove outdated settings
					\delete_option('lws_woorewards_points');
					\delete_option('lws_woorewards_value');
					\delete_option('lws_woorewards_rewards_orders');
					\delete_option('lws_woorewards_rewards_orders_first');
					\delete_option('lws_woorewards_value_coupon');
					\delete_option('lws_woorewards_expiry_days');
					\delete_option('lws_woorewards_stage');
				}
			}

			if($fromV2)
			{
				\lws_admin_add_notice(
					'up-lws-v2-settings',
					__("Your settings have been migrated during MyRewards update.
We tried our best to conserve the same behavior as before but we advise you to check them anyway.", 'woorewards-lite'),
					array(
						'level' => 'success',
						'once' => false,
						'forgettable' => true
					)
				);
			}
		}
	}

	/** Could be on purpose after a downgrade from trial to free version. */
	public static function isMissingPrefabEventsAndUnlockables()
	{
		$pools = self::loadStandardPool(true);
		if( $pools->count() <= 0 )
			return true;
		$pool = $pools->get(0);
		if( 1 != $pool->getEvents()->filter(function($item){return $item->getType() == 'lws_woorewards_events_orderamount';})->count() )
			return true;
		if( 1 != $pool->getEvents()->filter(function($item){return $item->getType() == 'lws_woorewards_events_ordercompleted';})->count() )
			return true;
		if( 1 != $pool->getEvents()->filter(function($item){return $item->getType() == 'lws_woorewards_events_firstorder';})->count() )
			return true;
		if( 1 != $pool->getEvents()->filter(function($item){return $item->getType() == 'lws_woorewards_events_productreview';})->count() )
			return true;
		if( 1 != $pool->getUnlockables()->filter(function($item){return $item->getType() == 'lws_woorewards_unlockables_coupon';})->count() )
			return true;
		return false;
	}

	/** @return bool dirty status (if something changed in pool) */
	public static function addV5PrefabEvents($pool=false, $saveIfDirty=true)
	{
		$dirty = false;
		if (!$pool) {
			$pools = self::loadStandardPool(true);
			if ($pools->count())
				$pool = $pools->get(0);
		}

		if ($pool) {

			$e = $pool->getEvents()->filter(function($item){return $item->getType() == 'lws_woorewards_events_sponsoredorderamount';});
			if ($e->count() <= 0) {
				require_once LWS_WOOREWARDS_INCLUDES . '/events/sponsoredorderamount.php';
				$event = new \LWS\WOOREWARDS\Events\SponsoredOrderAmount();
				$pool->addEvent($event, 0);
				$dirty = true;
			}

			$e = $pool->getEvents()->filter(function($item){return $item->getType() == 'lws_woorewards_events_sponsoredorder';});
			if ($e->count() <= 0) {
				require_once LWS_WOOREWARDS_INCLUDES . '/events/sponsoredorder.php';
				$event = new \LWS\WOOREWARDS\Events\SponsoredOrder();
				$event->setFirstOrderOnly(false);
				$pool->addEvent($event, 0);
				$dirty = true;
			}

			if ($dirty && $saveIfDirty) {
				$pool->save();
			}
		}
		return $dirty;
	}

	/** Look at pool prefabs type='standard',
	 * add missing orderCompleted, firstOrder, OrderAmount and Coupon.
	 *
	 * Could be on purpose after a downgrade from trial to free version. */
	public static function addMissingPrefabEventsAndUnlockables()
	{
		$pools = self::loadStandardPool(true);
		if( $pools->count() <= 0 )
		{
			self::addDefaultPools();
		}
		else
		{
			$pool = $pools->get(0);
			$dirty = false;

			$e = $pool->getEvents()->filter(function($item){return $item->getType() == 'lws_woorewards_events_orderamount';});
			while( $e->count() > 1 )
			{
				$item = $e->last();
				$e->remove($item);
				$pool->removeEvent($item);
				$item->delete();
			}
			if( $e->count() <= 0 )
			{
				require_once LWS_WOOREWARDS_INCLUDES.'/events/orderamount.php';
				$event = new \LWS\WOOREWARDS\Events\OrderAmount();
				$pool->addEvent($event, 0);
				$dirty = true;
			}

			$e = $pool->getEvents()->filter(function($item){return $item->getType() == 'lws_woorewards_events_ordercompleted';});
			while( $e->count() > 1 )
			{
				$item = $e->last();
				$e->remove($item);
				$pool->removeEvent($item);
				$item->delete();
			}
			if( $e->count() <= 0 )
			{
				require_once LWS_WOOREWARDS_INCLUDES.'/events/ordercompleted.php';
				$pool->addEvent(new \LWS\WOOREWARDS\Events\OrderCompleted(), 0);
				$dirty = true;
			}

			$e = $pool->getEvents()->filter(function($item){return $item->getType() == 'lws_woorewards_events_firstorder';});
			while( $e->count() > 1 )
			{
				$item = $e->last();
				$e->remove($item);
				$pool->removeEvent($item);
				$item->delete();
			}
			if( $e->count() <= 0 )
			{
				require_once LWS_WOOREWARDS_INCLUDES.'/events/firstorder.php';
				$pool->addEvent(new \LWS\WOOREWARDS\Events\FirstOrder(), 0);
				$dirty = true;
			}

			$e = $pool->getEvents()->filter(function($item){return $item->getType() == 'lws_woorewards_events_productreview';});
			while( $e->count() > 1 )
			{
				$item = $e->last();
				$e->remove($item);
				$pool->removeEvent($item);
				$item->delete();
			}
			if( $e->count() <= 0 )
			{
				require_once LWS_WOOREWARDS_INCLUDES.'/events/productreview.php';
				$pool->addEvent(new \LWS\WOOREWARDS\Events\ProductReview(), 0);
				$dirty = true;
			}

			$u = $pool->getUnlockables()->filter(function($item){return $item->getType() == 'lws_woorewards_unlockables_coupon';});
			while( $e->count() > 1 )
			{
				$item = $u->last();
				$u->remove($item);
				$pool->removeUnlockable($item);
				$item->delete();
			}
			if( $u->count() <= 0 )
			{
				require_once LWS_WOOREWARDS_INCLUDES.'/unlockables/coupon.php';
				$pool->addUnlockable(new \LWS\WOOREWARDS\Unlockables\Coupon(), 0);
				$dirty = true;
			}
			if (self::addV5PrefabEvents($pool, false)) {
				$dirty = true;
			}

			if( $dirty )
			{
				$pool->save();
			}
		}
	}

	/** @return \LWS\WOOREWARDS\Collections\Pools pool collection */
	protected static function loadStandardPool($deep=false)
	{
		// if not already exists (prefabs are not deletable)
		$pools = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array(
			'numberposts' => 1,
			'meta_query'  => array(
				array(
					'key'     => 'wre_pool_prefab',
					'value'   => 'yes', // This cannot be empty because of a bug in WordPress
					'compare' => 'LIKE'
				),
				array(
					'key'     => 'wre_pool_type',
					'value'   => \LWS\WOOREWARDS\Core\Pool::T_STANDARD,
					'compare' => 'LIKE'
				)
			),
			'deep' => $deep
		));
		return $pools;
	}

	protected static function updateNamesWPML()
	{
		global $wpdb;
		$icl = $wpdb->prefix . 'icl_strings';

		if( $wpdb->get_col("SHOW TABLES LIKE '{$icl}'") )
		{
			$template = 'Woorewards mail - %s - %s';
			$suffixes = array('Subject', 'Preheader', 'Title', 'Header');
			$strings = array(
				"New Reward" => array(
					"Nouvelle récompense",
					"Nueva recompensa",
					"Neue Belohnung",
					"Uusi palkkio",
					"新的奖励",
				),
				"Achievement" => array(
					"Succès",
					"Erfolg",
					"成就",
					"Logros",
				),
				"Sponsorship" => array(
					"Patrocinio",
					"引荐",
					"Parrainage",
					"Sponsorointi",
				),
				"Reward Choice" => array(
					"Eleccion de recompensa",
					"可选奖励",
					"Belohnungsauswahl",
					"Choix de récompense",
					"Palkinnon valinta",
				),
				"Expiry Reminder" => array(
					"Recordatorio de caducidad",
					"过期提示",
					"Erinnerung für Verfallsdatum",
					"Rappel d'expiration",
					"Päättymisen muistutin",
				),
				"Points Expiry Reminder" => array(
					"Recordatorio de caducidad de puntos",
					"积分过期提醒",
					"Erinnerung für Punkteverfall",
					"Rappel d’expiration de points",
					"Päättymisen muistutin",
				),
			);

			foreach( $suffixes as $suffix )
			{
				foreach( $strings as $dst => $src )
				{
					$where = array();
					foreach( $src as $trad )
						$where[] = $wpdb->prepare('`name`=%s', sprintf($template, $trad, $suffix)); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

					$sql = sprintf(
						"UPDATE `{$icl}` SET `name`='{$template}' WHERE %s",
						$dst, $suffix, implode(' OR ', $where)
					);
					$wpdb->query($sql); // phpcs:ignore WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
				}
			}
		}
	}
}