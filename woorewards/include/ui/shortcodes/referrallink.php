<?php
namespace LWS\WOOREWARDS\Ui\Shortcodes;

// don't call the file directly
if (!defined('ABSPATH')) exit();

class ReferralLink
{
	public static function install()
	{
		$me = new self();
		\add_shortcode('wr_referral_link', array($me, 'shortcode'));
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($me, 'registerScripts'));
		\add_filter('lws_woorewards_referral_shortcodes', array($me, 'admin'), 20);

		\add_filter('query_vars', array($me, 'varsReferral'));
		\add_action('parse_query', array($me, 'grabReferral'));
		\add_filter('lws_woorewards_fresh_user_sponsored_by', array($me, 'sponsorship'), 10, 3);

		\add_action('plugins_loaded', function() {
			if (\get_option('lws_woorewards_sponsorship_tinify_enabled', ''))
				\LWS\WOOREWARDS\Ui\Shortcodes\ReferralLink::tryDecodeTinyURl();
		});
	}

	function registerScripts()
	{
		\wp_register_script('wr-referral-link', LWS_WOOREWARDS_JS . '/shortcodes/referral-link.js', array('jquery'), LWS_WOOREWARDS_VERSION);
		\wp_register_style('wr-referral-link', LWS_WOOREWARDS_CSS . '/shortcodes/referral-link.min.css', array(), LWS_WOOREWARDS_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_script('lws-qrcode-js');
		\wp_enqueue_script('wr-referral-link');
		\wp_enqueue_style('wr-referral-link');
	}

	/** Get the shortcode admin */
	public function admin($fields)
	{
		$fields['referrallink'] = array(
			'id' => 'lws_woorewards_referral_link',
			'title' => __("Referral Link", 'woorewards-lite'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_referral_link]',
				'description' =>  __("Use this shortcode to show a referral link button.", 'woorewards-lite') . "<br/>" .
					__("Customers can use then share this link with their friends.", 'woorewards-lite'),
				'options' => array(
					'mode' => array(
						'option' => 'mode',
						'desc' => array(
							__("Select the type of referral link your customers will get :", 'woorewards-lite'),
							array(
								'tag' => 'ul',
								array('link', __("(Default Value) Customers will get an url link", 'woorewards-lite')),
								array('qrcode', __("Customers will get a QR Code link", 'woorewards-lite')),
							),
						),
						'example' => '[wr_referral_link mode="link"]',
					),
					'showlink' => array(
						'option' => 'showlink',
						'desc' => __("(Optional) If set, customers will see the referral link or QR-Code above the copy button", 'woorewards-lite'),
						'example' => '[wr_referral_link showlink="true"]',
					),
					'showbutton' => array(
						'option' => 'showbutton',
						'desc' => __("(Optional, default is 'true') Show or hide the copy button", 'woorewards-lite'),
						'example' => '[wr_referral_link showbutton="true"]',
					),
					'url' => array(
						'option' => 'url',
						'desc' => __("(Optional) By default, the shortcode shares the url of the page it’s displayed on. You can override that setting by setting an url in this option.", 'woorewards-lite'),
						'example' => '[wr_referral_link url="https://mywebsite.com/nameofapage"]',
					),
					'button' => array(
						'option' => 'button',
						'desc' => __("(Optional) Set the text customers will see on the copy button", 'woorewards-lite'),
						'example' => '[wr_referral_link button="Copy my referral link"]',
					),
					'copied' => array(
						'option' => 'copied',
						'desc' => __("(Optional) Set the text customers will see when the code is copied to the clipboard", 'woorewards-lite'),
						'example' => '[wr_referral_link copied="Your link has been copied"]',
					),
					'layout' => array(
						'option' => 'layout',
						'desc' => array(
							__("Select the type of referral link your customers will get :", 'woorewards-lite'),
							array(
								'tag' => 'ul',
								array('default', __("The default layout displays a block", 'woorewards-lite')),
								array('inline', __("Try to follow the text flow", 'woorewards-lite')),
							),
						),
					),
				),
			)
		);
		return $fields;
	}

	/** Referral Link button
	 * [wr_referral_link]
	 * @param $mode	 	→ Default: 'link'
	 * 					  link : Customers will get an url link
	 * 					  qrcode : Customers will get a QR Code link
	 * @param $showlink 	→ Default: 'false'
	 * 					  Defines the presentation of the wrapper.
	 * 					  4 possible values : grid, horizontal, vertical, none.
	 * @param $url	 	→ Default: ''
	 * 					 Sets the redirection url
	 * @param $button 	→ Default: ''
	 * 					 Set the text customers will see on the copy button
	 * @param $copied	→ Default: ''
	 * 					  Set the text customers will see when the code is copied to the clipboard
	 */
	public function shortcode($atts = array(), $content = '')
	{
		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\wp_parse_args($atts, array(
			'mode'       => 'link',
			'showlink'   => false,
			'showbutton' => true,
			'url'        => '',
			'button'     => '',
			'copied'     => '',
			'layout'     => '',
		)));
		$userId = \get_current_user_id();
		if (!$userId) {
			return \do_shortcode((string)$content);
		}
		$atts['layout'] = \strtolower($atts['layout']);
		$atts['mode']   = \strtolower($atts['mode']);
		if (!\in_array($atts['mode'], array('link', 'qrcode'), true)) {
			return \do_shortcode((string)$content);
		}
		$this->enqueueScripts();

		// LINK URL
		$url = '';
		if (isset($atts['url']) && $atts['url']) {
			$url = \add_query_arg('referral', $this->getOrCreateToken($userId), $atts['url']);
		} else // current page
		{
			$url = \add_query_arg('referral', $this->getOrCreateToken($userId), \LWS\Adminpanel\Tools\Conveniences::getCurrentPermalink());
		}
		if (\get_option('lws_woorewards_sponsorship_tinify_enabled', '')) {
			$url = self::tinifyUrl($url);
		}
		return $this->getContent($atts, $url);
	}

	protected function getContent($atts, $url)
	{
		$showlnk = (\LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['showlink']) ? '' : ' hiddenlink');
		$showbtn = (\LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['showbutton']) ? '' : ' hidden');

		if ($atts['mode'] == 'link') {
			// Link
			$link = \htmlentities($url);
			$content = "<div class='link-url url_to_copy{$showlnk} link' tabindex='0'>" . $link . "</div>";
			if (!$atts['button']) {
				$atts['button'] = __('Get your referral link', 'woorewards-lite');
			}
		} else {
			// QR Code
			$link = \esc_attr($url);
			$content = "<div class='link-url url_to_copy{$showlnk} qrcode' tabindex='0' data-qrcode='{$link}'></div>";
			if (!$atts['button']) {
				$atts['button'] = __('Get your referral QR Code', 'woorewards-lite');
			}
		}
		if (!$atts['copied']) {
			$atts['copied'] = __('Your code has been copied !', 'woorewards-lite');
		}
		$wrapper = 'wr-referral-code-wrapper';
		if ('i' === \substr($atts['layout'], 0, 1)) {
			$wrapper .= ' refinline';
		}

		return <<<EOT
		<div class='{$wrapper}'>
			{$content}
			<div class='link-button-wrapper wr_refl_button_copy{$showbtn}'>
				<div class='copy-button wr_refl_button_copy'>{$atts['button']}</div>
				<div class='copied-message'>{$atts['copied']}</div>
			</div>
		</div>
EOT;
	}

	/** If a tiny URL is detected and decoded, redirect and die. */
	static protected function tryDecodeTinyURl()
	{
		if (\is_admin())
			return;
		if (!(isset($_GET, $_GET['~']) && $_GET['~']))
			return;

		$short = $_GET['~'];
		global $wpdb;
		$sql = "SELECT `longurl` FROM {$wpdb->base_prefix}lws_wr_tinyurls WHERE `shorturl` = %s";
		$redirect = $wpdb->get_var($wpdb->prepare($sql, $short));

		if ($redirect) {
			if (\wp_redirect($redirect))
				exit;
		}
	}

	static public function tinifyUrl($url)
	{
		$url = \remove_query_arg('~', $url);
		$ref = md5($url);
		$base = \get_option('lws_woorewards_sponsorship_short_url');
		if (!$base)
			$base = \site_url();

		global $wpdb;
		$sql = <<<EOT
SELECT `shorturl`
FROM {$wpdb->base_prefix}lws_wr_tinyurls
WHERE `longref` = %s AND `longurl` = %s
EOT;
		$short = $wpdb->get_var($wpdb->prepare($sql, $ref, $url));

		if (!$short) {
			$unique = $short = \LWS\Adminpanel\Tools\Conveniences::rebaseNumber(substr($ref, 0, 16), 16, 64);
			// unicity
			$sql = "SELECT COUNT(*) FROM {$wpdb->base_prefix}lws_wr_tinyurls WHERE `shorturl` = '%s'";
			$index = 0;
			while($wpdb->get_var(sprintf($sql, $short))) {
				$short = ($unique . $index++);
			}
			// keep it
			$wpdb->query($wpdb->prepare(
				"INSERT INTO {$wpdb->base_prefix}lws_wr_tinyurls (shorturl, longurl, longref) VALUES (%s, %s, %s)",
				$short, $url, $ref
			));
		}
		return \add_query_arg('~', $short, $base);
	}

	public function sponsorship($sponsor, $user, $email)
	{
		if( !$sponsor->id && \get_option('lws_woorewards_referral_back_give_sponsorship', 'on') )
		{
			$sponsorship = new \LWS\WOOREWARDS\Core\Sponsorship();
			$ref = $sponsorship->getCurrentReferral();
			if( $ref->user_id && $ref->hash && $ref->origin == 'referral' )
			{
				if( $ref->user_id != $user->ID && $ref->user_id == $this->getUserByReferral($ref->hash) )
				{
					$sponsor->id = $ref->user_id;
					$sponsor->origin = 'referral';
				}
			}
		}
		return $sponsor;
	}

	public function varsReferral($vars)
	{
		$vars[] = 'referral';
		return $vars;
	}

	/** Keep referral in session to let visitor continues without losing referral info.
	 * @see \LWS\WOOREWARDS\Core\Sponsorship::setCurrentReferral() */
	public function grabReferral(&$query)
	{
		$referral = isset($query->query['referral']) ? trim($query->query['referral']) : '';
		if( $referral )
		{
			$sponsorship = new \LWS\WOOREWARDS\Core\Sponsorship();
			$ref = $sponsorship->getCurrentReferral();
			if( $ref->hash != $referral || !$ref->user_id || $ref->origin != 'referral' )
			{
				$ref->user_id = $this->getUserByReferral($referral);
				$ref->hash = $referral;
				\do_action('lws_woorewards_referral_followed', $referral, $ref->user_id);
			}
			$sponsorship->setCurrentReferral($ref->user_id, $ref->hash, 'referral');
			if( \get_option('lws_woorewards_redirect_after_referral_grab', 'on') )
			{
				\wp_redirect(\remove_query_arg('referral'));
				exit();
			}
		}
	}

	protected function getUserByReferral($referral)
	{
		global $wpdb;
		$metakey = 'lws_woorewards_user_referral_token';
		$refId = $wpdb->get_var($wpdb->prepare(
			"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='{$metakey}' AND meta_value=%s",
			$referral
		));
		return $refId;
	}

	public function getOrCreateToken($userId)
	{
		$token = \get_user_meta($userId, 'lws_woorewards_user_referral_token', true);
		if (!$token) {
			$user = \get_user_by('ID', $userId);
			if ($user && $user->ID) {
				$token = \sanitize_key(\wp_hash(json_encode($user) . \rand()));
				\update_user_meta($userId, 'lws_woorewards_user_referral_token', $token);
			}
		}
		return $token;
	}
}