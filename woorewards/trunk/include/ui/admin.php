<?php

namespace LWS\WOOREWARDS\Ui;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Create the backend menu and settings pages. */
class Admin
{
	const NOTICE_NO_POOL = 'lws-wre-pool-nothing-loaded';

	public function __construct()
	{
		// comes after the addon, let that part to it if any
		\add_filter('lws_adminpanel_make_page_' . LWS_WOOREWARDS_PAGE . '.customers', array($this, 'addSponsorshipTab'), 2000);

		/** @param array, the fields settings array. @param Pool */
		\add_filter('lws_woorewards_admin_pool_general_settings', array($this, 'getPoolGeneralSettings'), 10, 2);

		lws_register_pages($this->managePages());
		\add_action('admin_enqueue_scripts', array($this, 'scripts'));

		// replace usual notice by a badge teaser
		if (!defined('LWS_WOOREWARDS_ACTIVATED') || !LWS_WOOREWARDS_ACTIVATED)
			\add_filter('pre_set_transient_settings_errors', array($this, 'noticeSettingsSaved'));

		$this->checkCouponsEnabled();
	}

	protected function getCurrentPage()
	{
		if (isset($_REQUEST['page']) && ($current = \sanitize_text_field($_REQUEST['page'])))
			return $current;
		if (isset($_REQUEST['option_page']) && ($current = \sanitize_text_field($_REQUEST['option_page'])))
			return $current;
		return false;
	}

	protected function checkCouponsEnabled()
	{
		if (defined('DOING_AJAX') && DOING_AJAX)
			return;

		if (function_exists('\wc_coupons_enabled') && !\wc_coupons_enabled() && !\get_option('lws_woorewards_ignore_woocommerce_disable_coupons')) {
			$ignore = false;
			if (isset($_GET['lws_wr_wc_coupons_enable']) && in_array($_GET['lws_wr_wc_coupons_enable'], array('yes', 'ignore'))) {
				if ($_GET['lws_wr_wc_coupons_enable'] == 'yes') {
					\update_option('woocommerce_enable_coupons', 'yes');
					\update_option('lws_woorewards_ignore_woocommerce_disable_coupons', '');
				} else
					\update_option('lws_woorewards_ignore_woocommerce_disable_coupons', 'yes');
				$ignore = true;
			}

			if (!$ignore) {
				$message = array(
					__("WooCommerce Coupons are disabled. Several MyRewards features will be broken without Coupons.", 'woorewards-lite'),
					__("You can check your WooCommerce General Settings and look for : <b>Enable coupons</b>.", 'woorewards-lite'),
					sprintf(
						__('%1$s or %2$s this warning at your own risks.', 'woorewards-lite'),
						sprintf(
							"<a href='%s' class='button primary'>%s</a>",
							\esc_attr(\add_query_arg('lws_wr_wc_coupons_enable', 'yes')),
							__("Click here to resolve the problem immediately", 'woorewards-lite')
						),
						sprintf(
							"<a href='%s' class=''>%s</a>",
							\esc_attr(\add_query_arg('lws_wr_wc_coupons_enable', 'ignore')),
							__("ignore", 'woorewards-lite')
						)
					),
				);
				\lws_admin_add_notice_once('woocommerce_enable_coupons', implode('<br/>', $message), array('level' => 'error'));
			}
		}
	}

	public function scripts($hook)
	{
		// Force the menu icon with lws-icons font
		\wp_enqueue_style('wr-menu-icon', LWS_WOOREWARDS_CSS . '/menu-icon.css', array(), LWS_WOOREWARDS_VERSION);

		\wp_register_script('lws_wre_system_selector', LWS_WOOREWARDS_JS . '/poolsettings.js', array('lws-base64'), LWS_WOOREWARDS_VERSION, true);
		\wp_register_style('lws_wre_system_selector', LWS_WOOREWARDS_CSS . '/poolsettings.css', array(), LWS_WOOREWARDS_VERSION);

		if (false !== ($ppos = strpos($hook, LWS_WOOREWARDS_PAGE))) {
			$page = substr($hook, $ppos);
			$tab = isset($_GET['tab']) ? \sanitize_text_field($_GET['tab']) : '';

			if (!defined('LWS_WOOREWARDS_ACTIVATED') || !LWS_WOOREWARDS_ACTIVATED) {
				// let badge teaser replace the notice
				\wp_enqueue_style('lws-wre-notice', LWS_WOOREWARDS_CSS . '/notice.css', array(), LWS_WOOREWARDS_VERSION);
			}

			if ($page == LWS_WOOREWARDS_PAGE || false !== strpos($page, 'customers')) {
				// labels displayed in points history
				$labels = array(
					'hist' => __("Points History", 'woorewards-lite'),
					'desc' => __("Description", 'woorewards-lite'),
					'date' => __("Date", 'woorewards-lite'),
					'points' => __("Points", 'woorewards-lite'),
					'total' => __("Total", 'woorewards-lite'),
				);
				// enqueue editlist column folding script
				foreach (($deps = array('jquery', 'lws-tools')) as $dep)
					\wp_enqueue_script($dep);

				\wp_register_script('lws-wre-userspoints', LWS_WOOREWARDS_JS . '/userspoints.js', $deps, LWS_WOOREWARDS_VERSION, true);
				\wp_localize_script('lws-wre-userspoints', 'lws_wr_userspoints_labels', $labels);
				\wp_enqueue_script('lws-wre-userspoints');
				\wp_enqueue_style('lws-wre-userspoints', LWS_WOOREWARDS_CSS . '/userspoints.css', array(), LWS_WOOREWARDS_VERSION);

				\do_action('lws_adminpanel_enqueue_lac_scripts', array('select'));
				\do_action('lws_woorewards_ui_userspoints_enqueue_scripts', $hook, $tab);
			} else if (false !== strpos($page, 'loyalty')) {
				\do_action('lws_adminpanel_enqueue_lac_scripts', array('select'));
			} else if (false !== strpos($page, 'appearance')) {
				\wp_enqueue_style('lws_wr_pointsoncart_hard', LWS_WOOREWARDS_CSS . '/pointsoncart.css', array(), LWS_WOOREWARDS_VERSION);
			}

			\wp_enqueue_script('lws-wre-coupon-edit', LWS_WOOREWARDS_JS . '/couponedit.js', array('jquery'), LWS_WOOREWARDS_VERSION, true);
		}
	}

	/** Push an achievement teaser instead our usual notice at setting save. */
	public function noticeSettingsSaved($value)
	{
		if (!empty($value) && isset($_POST['option_page']) && false !== strpos($_POST['option_page'], LWS_WOOREWARDS_PAGE)) {
			$val = \current($value);
			if (isset($val['type']) && $val['type'] == 'updated' && isset($val['code']) && $val['code'] == 'settings_updated') {
				$teasers = array(
					__("Add fun and achievements for your customers with the <a>Pro Version</a>", 'woorewards-lite'),
					__("Try the <a>Pro Version</a> for free for 30 days", 'woorewards-lite'),
					__("The <a>Pro Version</a> adds Events and Levelling systems. Try <a>it</a>", 'woorewards-lite')
				);
				\LWS_WooRewards::achievement(array(
					'title'   => __("Your settings have been saved.", 'woorewards-lite'),
					'message' => str_replace(
						'<a>',
						"<a href='https://plugins.longwatchstudio.com/product/woorewards/' target='_blank'>",
						$teasers[rand(0, count($teasers) - 1)]
					)
				));
			}
		}
		return $value;
	}

	protected function managePages()
	{
		$pages = array();
		$pages['wr_resume'] = $this->getResumePage();
		$pages['wr_customers'] = $this->getCustomerPage();
		if (false === ($pages['wr_settings'] = \apply_filters('lws_woorewards_ui_settings_page_get', false))) {
			$pages['wr_settings'] = $this->getSettingsPage();
		}
		if (defined('LWS_WIZARD_SUMMONER')) {
			$pages['wr_wizard'] = $this->getWizardPage();
		}
		//$pages['wr_features'] = $this->getFeaturesPage();
		$pages['wr_appearance'] = $this->getAppearancePage();
		$pages['wr_system'] = $this->getSystemPage();

		if (!\apply_filters('lws-ap-release-woorewards', ''))
			$pages['wr_proversion'] = $this->getProVersionPage();

		return $pages;
	}

	protected function getResumePage()
	{
		$resumePage = array(
			'title'	    => __("MyRewards", 'woorewards-lite'),
			'id'	      => LWS_WOOREWARDS_PAGE,
			'rights'    => 'manage_rewards',
			'dashicons' => '',
			'index'     => 57,
			'resume'    => true,
			'tabs'	    => array(
				'wr_customers' => array(
					'title'  => __("Customers", 'woorewards-lite'),
					'id'     => 'resume_customers',
				)
			)
		);
		return $resumePage;
	}

	protected function getCustomerPage()
	{
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/editlists/userspoints.php';
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/editlists/userspointsbulkaction.php';
		$editlist = \lws_editlist(
			'userspoints',
			'user_id',
			new \LWS\WOOREWARDS\Ui\Editlists\UsersPoints(),
			\LWS\Adminpanel\EditList\Modes::FIX,
			\apply_filters('lws_woorewards_admin_userspoints_filters', array(
				'user_search' => new \LWS\Adminpanel\EditList\FilterSimpleField('usersearch', __('Search...', 'woorewards-lite')),
				'points_add'  => new \LWS\WOOREWARDS\Ui\Editlists\UsersPointsBulkAction('points_add')
			))
		);


		$cusPage = array(
			'title'    => __("Customers", 'woorewards-lite'),
			'id'       => LWS_WOOREWARDS_PAGE . '.customers',
			'rights'   => 'manage_rewards',
			'color'    => '#A8CE38',
			'image'		=> LWS_WOOREWARDS_IMG . '/r-customers.png',
			'description' => __("Use this page to manage your customers, see and edit their points and rewards", 'woorewards-lite'),
			'tabs'     => array(
				'wr_customers' => array(
					'title'    => __("Customers", 'woorewards-lite'),
					'id'       => 'wr_customers',
					'icon'     => 'lws-icon-users-wm',
					'groups'   => array(
						'customers_points' => array(
							'title'		=> __("Points Management", 'woorewards-lite'),
							'icon'		=> 'lws-icon-users',
							'color'		=> '#00768b',
							'text'		=> __("Here you can see and manage your customers reward points", 'woorewards-lite')
								. "<br/>" . __("You can view the points <b>history</b> by clicking the points total in the table", 'woorewards-lite'),
							'extra'    => array('doclink' => \LWS\WOOREWARDS\DocLinks::get('customers')),
							'editlist' => $editlist,
						)
					)
				)
			)
		);
		return $cusPage;
	}

	protected function getSettingsPage()
	{
		return array(
			'title'    => __("Settings", 'woorewards-lite'),
			'rights'   => 'manage_rewards',
			'id'       => LWS_WOOREWARDS_PAGE . '.loyalty',
			'color'    => '#526981',
			'image'    => LWS_WOOREWARDS_IMG . '/r-loyalty-systems.png',
			'description' => __("Use this page to manage your loyalty program, see and edit actions and rewards", 'woorewards-lite'),
			'tabs' => array(
				'wr_loyalty' => array(
					'title'  => __("Points and Rewards", 'woorewards-lite'),
					'id'     => 'wr_loyalty',
					'icon'	 => 'lws-icon-present',
					'groups' => $this->getLoyaltyGroups()
				),
				'wr_othersettings' => array(
					'title'  => __("Other Settings", 'woorewards-lite'),
					'id'     => 'wr_othersettings',
					'icon'	 => 'lws-icon-adv-settings',
					'groups' => $this->getSettingsGroups()
				)
			)
		);
	}

	protected function getWizardPage()
	{
		return array(
			'title'    => __("Wizard", 'woorewards-lite'),
			'subtitle' => __("Wizard", 'woorewards-lite'),
			'id'       => LWS_WIZARD_SUMMONER . LWS_WOOREWARDS_PAGE,
			'rights'   => 'manage_rewards',
			'color'    => '#00B7EB',
			'image'    => LWS_WOOREWARDS_IMG . '/r-wizard.png',
			'description' => __("The wizard page lets you setup your points and rewards program in a few minutes", 'woorewards-lite'),
		);
	}

	protected function getSettingsGroups()
	{
		return array(
			'settings' => array(
				'id'     => 'settings',
				'icon'	 => 'lws-icon-settings-gear',
				'title'  => __("General settings", 'woorewards-lite'),
				'text'   => __("Check the options below according to your needs. If you want to exclude shipping fees from points calculation, it can be done inside your loyalty systems.", 'woorewards-lite'),
				'extra'    => array('doclink' => \LWS\WOOREWARDS\DocLinks::get('adv-features')),
				'fields' => array(
					'inc_taxes' => array(
						'id'    => 'lws_woorewards_order_amount_includes_taxes',
						'title' => __("Includes taxes", 'woorewards-lite'),
						'type'  => 'box',
						'extra' => array(
							'layout' => 'toggle',
							'help' => __("If checked, taxes will be included in the points earned when spending money", 'woorewards-lite'),
						)
					),
					'c_prefix'  => array(
						'id'    => 'lws_woorewards_reward_coupon_code_prefix',
						'title' => __("Coupon prefix", 'woorewards-lite'),
						'type'  => 'text',
						'extra' => array(
							'size' => '10',
							'help' => __("Set a prefix code that will be added on all coupon codes generated by MyRewards", 'woorewards-lite'),
						)
					),
					'c_length'  => array(
						'id'    => 'lws_woorewards_coupon_code_length',
						'title' => __("Coupon code length", 'woorewards-lite'),
						'type'  => 'text',
						'extra' => array(
							'size' => '3',
							'default' => '10',
							'placeholder' => '10',
							'help' => __("Set the length of the generated coupon code. The length comes in addition to the code prefix.", 'woorewards-lite')
								. ' ' . sprintf(__("Minimum length is %s.", 'woorewards-lite'), \LWS\WOOREWARDS\Unlockables\Coupon::COUPON_CODE_MIN_LENGTH),
						)
					),
					'order_state' => array(
						'id'    => 'lws_woorewards_points_distribution_status',
						'title' => __("Order statuses for points", 'woorewards-lite'),
						'type'  => 'lacchecklist',
						'extra' => array(
							'ajax' => 'lws_adminpanel_get_order_status',
							'help' => __("Default state to get points is the processing order status.<br/>If you want to use other statuses instead (recommanded), select them here", 'woorewards-lite'),
						)
					),
					'deep_usersearch' => array(
						'id'    => 'lws_woorewards_admin_userspoints_deep_search',
						'title' => __("Deep customer search", 'woorewards-lite'),
						'type'  => 'box',
						'extra' => array(
							'layout' => 'toggle',
							'default' => 'on',
							'help'   => __("If you have troubles with searching in WooRewards Customers administration screen, disable this option. This will speed up the search but it will take less data into account.", 'woorewards-lite'),
						)
					),
					'show_priorities' => array(
						'id'    => 'lws_woorewards_show_loading_order_and_priority',
						'title' => __("Show loading orders", 'woorewards-lite'),
						'type'  => 'box',
						'extra' => array(
							'layout' => 'toggle',
							'help'   => __("For some advanced setups, managing loyalty system loading order and event trigger priority could be meaningful.", 'woorewards-lite'),
						)
					),
				)
			),
			'pointsoncart' => array(
				'id'     => 'pointsoncart',
				'icon'	 => 'lws-icon-coins',
				'title'  => __("Order statuses to consume used points", 'woorewards-lite'),
				'text'	 => __("Points used to get a discount are consumed when order status changes.", 'woorewards-lite'),
				'fields' => array(
					'order_state' => array(
						'id'    => 'lws_woorewards_pointdiscount_pay_order_status',
						'title' => __("Order statuses to pay discount", 'woorewards-lite'),
						'type'  => 'lacchecklist',
						'extra' => array(
							'ajax'    => 'lws_adminpanel_get_order_status',
							'default' => array('processing', 'completed'),
						)
					),
					'failure'     => array(
						'id'    => 'lws_woorewards_pointdiscount_pay_order_failure',
						'title' => __("Order status on payment failure", 'woorewards-lite'),
						'type'  => 'lacselect',
						'extra' => array(
							'mode'    => 'select',
							'source'  => array(
								array('value' => '_', 'label' => __("[do nothing]", 'woorewards-lite'))
							),
							'ajax'    => 'lws_adminpanel_get_order_status',
							'default' => 'failed',
							'help'    => __("Change order status if points cannot be paid after all.", 'woorewards-lite'),
						)
					),
				)
			),
			'sponsorship' => array(
				'id' 	=> 'sponsorship',
				'icon'	=> 'lws-icon-handshake',
				'color' => '#669876',
				'title'	=> __("Referral Features", 'woorewards-lite'),
				'text' 	=> __("Here, you'll find the different tools customers can use to refer their friends and the reward given to referred users.", 'woorewards-lite') .
					__("To reward the referrers, either use the dedicated wizard or select an appropriate earning method inside a points and rewards system.", 'woorewards-lite'),
				'extra' => array('doclink' => \LWS\WOOREWARDS\DocLinks::get('referral')),
				'fields' => array(
					'enable' => array(
						'id'    => 'lws_woorewards_event_enabled_sponsorship',
						'title' => __("Enable Referrals", 'woorewards-lite'),
						'type'  => 'box',
						'extra' => array(
							'layout' => 'toggle',
							'default' => 'on'
						)
					),
					'enableReferral' => array(
						'id'    => 'lws_woorewards_referral_back_give_sponsorship',
						'type'  => 'box',
						'title' => __("Allow referrals via referral link", 'woorewards-lite'),
						'extra' => array(
							'default' => 'on',
							'layout' => 'toggle',
							'help' => __("When a visitor comes from a referral link and registers, he will be referred by the user that posted the link.", 'woorewards-lite')
						)
					),
					'tinify' => array(
						'id'    => 'lws_woorewards_sponsorship_tinify_enabled',
						'title' => __("Try to shorten the referral URL", 'woorewards-lite'),
						'type'  => 'box',
						'extra' => array(
							'help' => __('Disable that feature if you encounter plugin conflicts or redirection problems. Disable that feature makes bigger and less readable QR codes.', 'woorewards-lite'),
							'class' => 'lws_checkbox',
							'default' => '',
							'id' => 'lws_woorewards_sponsorship_tinify_enabled',
						)
					),
					'tiny' => array(
						'id'    => 'lws_woorewards_sponsorship_short_url',
						'title' => __("Alternative Short Site URL", 'woorewards-lite'),
						'type'  => 'text',
						'extra' => array(
							'help' => __('To make the QR-Code as simple as possible, you can specify a shorter version of your site URL here that will be used as base for the image generation.', 'woorewards-lite'),
							'placeholder' => \site_url(),
						),
						'require' => array('selector' => '#lws_woorewards_sponsorship_tinify_enabled', 'value' => 'on'),
					),
					'max'    => array(
						'id' => 'lws_wooreward_max_sponsorship_count',
						'title' => __("Max referrals per customer", 'woorewards-lite'),
						'type' => 'text',
						'extra' => array(
							'pattern' => '\d+',
							'default' => '0',
							'help' => __("Set the maximum referrals allowed for users. No restriction on empty value or zero (0).", 'woorewards-lite')
						)
					),
				)
			),
		);
	}

	protected function getAppearancePage()
	{
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/adminscreens/styling.php';

		$appearancePage = array(
			'title'    => __("Appearance", 'woorewards-lite'),
			'subtitle' => __("Appearance", 'woorewards-lite'),
			'id'       => LWS_WOOREWARDS_PAGE . '.appearance',
			'rights'   => 'manage_rewards',
			'color'			=> '#4CBB41',
			'image'			=> LWS_WOOREWARDS_IMG . '/r-appearance.png',
			'description'	=> __("Use this page to display loyalty content to your customers on your website", 'woorewards-lite'),
			'tabs'     => array(
				'woocommerce' => $this->getWoocommerceTab(),
				'shortcodes'  => $this->getShortcodesTab(),
				'sty_mails'   => array(
					'id'     => 'sty_mails',
					'title'  => __("Emails", 'woorewards-lite'),
					'icon'	 => 'lws-icon-letter',
					'groups' => \lws_mail_settings(\apply_filters('lws_woorewards_mails', array()))
				),
				'styling'     => \LWS\WOOREWARDS\Ui\AdminScreens\Styling::getTab(true)
			)
		);

		if (\LWS\WOOREWARDS\Conveniences::instance()->isLegacyShown('4.7.0')) {
			$appearancePage['tabs']['legacy'] = $this->getLegacyTab();
		}
		return $appearancePage;
	}

	protected function getSystemPage()
	{
		$systemPage = array(
			'title'    		=> __("System", 'woorewards-lite'),
			'subtitle' 		=> __("System", 'woorewards-lite'),
			'id'       		=> LWS_WOOREWARDS_PAGE . '.system',
			'rights'   		=> 'manage_rewards',
			'color'			=> '#7958A5',
			'image'			=> LWS_WOOREWARDS_IMG . '/r-system.png',
			'description'	=> __("Export or import your customers points, process past orders in this page", 'woorewards-lite'),
			'delayedFunction' => array($this, 'showCronStatus'),
			'tabs'			=> array(
				'data_management' => array(
					'id'     => 'data_management',
					'title'  => __("Data Management", 'woorewards-lite'),
					'icon'   => 'lws-icon-components',
					'groups' => array(
						'wc_old_orders' => array(
							'id'     => 'wc_old_orders',
							'icon'   => 'lws-icon-repeat',
							'title'  => __("Give Points for Past orders", 'woorewards-lite'),
							'text'   => __("If you want to give points for orders that pre-existed your loyalty system, you can do it here", 'woorewards-lite') . '<br/>',
							'fields' => array(),
							'extra'  => array('doclink' => \LWS\WOOREWARDS\DocLinks::get('past-orders'))
						),
					)
				),
			)
		);

		$systemPage['tabs']['data_management']['groups']['wc_old_orders']['text'] .= __("This operation can take several minutes. Depending on your server configuration, you should run this operation several times on small subsets of orders.", 'woorewards-lite');
		if (\LWS\Adminpanel\Tools\Conveniences::isHPOS())
			$url = \add_query_arg('page', 'wc-orders', \admin_url('admin.php'));
		else
			$url = \add_query_arg('post_type', 'shop_order', \admin_url('edit.php'));

		$systemPage['tabs']['data_management']['groups']['wc_old_orders']['fields']['link'] = array(
			'id'    => 'redirect_to_order_bulk',
			'title' => '',
			'type'  => 'custom',
			'extra' => array(
				'gizmo'   => true,
				'content' => \LWS\Adminpanel\Tools\Conveniences::array2html(array(array(
					'tag' => 'ul',
					sprintf('<b style="color: #dd1a1a;">%s</b>', __("If your loyalty program is live, this could lead to lots of rewards being generated and lots of emails being sent", 'woorewards-lite')),
					sprintf('<b style="color: #dd1a1a;">%s</b>', __("Please make sure you reviewed all the settings before launching this procedure.", 'woorewards-lite')),
					sprintf('<b style="color: #dd1a1a;">%s</b>', __("Please ensure you agree to send emails to your customers about there activities in loyalty systems or deactivate relevant emails first.", 'woorewards-lite')),
					sprintf('<a style="color: #dd1a1a;" href="%s" target="_blank">%s</a>', \esc_attr(\add_query_arg(array(
						'page' => LWS_WOOREWARDS_PAGE . '.appearance',
						'tab'  => 'sty_mails',
					), \admin_url('admin.php'))), __("See WooRewards emails settings", 'woorewards-lite')),
				), array(
					sprintf(__("Go to the <a href='%s'>WooCommerce Orders list</a>.", 'woorewards-lite'), \esc_attr($url)),
					__("Check the box at left of the orders to process.", 'woorewards-lite'),
					sprintf(__("In the <b>Bulk Actions</b> drop-list, pick <b>%s</b>.", 'woorewards-lite'), \LWS\WOOREWARDS\Ui\Woocommerce\OrdersBulk::getLabel()),
					__("Then, press the <b>Apply</b> button.", 'woorewards-lite'),
				))),
			)
		);

		//if ((isset($_GET['legacy']) && 'yes' === $_GET['legacy']) || (isset($_POST['button']) && 'trigger_orders' === $_POST['button'])) {
			//$systemPage['tabs']['data_management']['groups']['wc_old_orders']['text'] .= __("This operation can take several minutes. Depending on your server configuration and date range, you should run this operation several times on short dates ranges.", 'woorewards-lite');

		$systemPage['tabs']['data_management']['groups']['wc_old_orders']['fields']['separator'] = array(
			'id'    => 'past_order_per_date_separator',
			'title' => '',
			'type'  => 'custom',
			'extra' => array(
				'gizmo'   => true,
				'separator' => true,
				'content' => \LWS\Adminpanel\Tools\Conveniences::array2html(array(
					__("Or use our date range selector", 'woorewards-lite'),
					array('tag' => 'small',
						__("(Don't be too greedy. Depending on your sales volume, cut the period into smaller ones or the procedure may stop in timeout. In any case, the same order will not be processed twice.)", 'woorewards-lite'),
					),
				)),
			)
		);

		$systemPage['tabs']['data_management']['groups']['wc_old_orders']['fields']['per_dates'] = array(
			'id'    => 'past_order_per_date_range',
			'title' => __("Date range", 'woorewards-lite'),
			'type'  => 'custom',
			'extra' => array(
				'gizmo'   => true,
				'content' => function(){
					return sprintf(
						"From %s to %s",
						\LWS\Adminpanel\Pages\Field\Input::compose('date_min', array(
							'type'     => 'date',
							'gizmo'    => true,
							'class'    => 'lws-ignore-confirm',
							'default'  => \date_create()->sub(new \DateInterval('P1M'))->format('Y-m-d'),
						)), \LWS\Adminpanel\Pages\Field\Input::compose('date_max', array(
							'type'     => 'date',
							'gizmo'    => true,
							'class'    => 'lws-ignore-confirm',
							'default'  => \date('Y-m-d'),
						))
					);
				},
			)
		);
		$systemPage['tabs']['data_management']['groups']['wc_old_orders']['fields']['trigger_orders'] = array(
			'id' => 'trigger_orders',
			'title' => __("Launch the procedure", 'woorewards-lite'),
			'type' => 'button',
			'extra' => array(
				'callback' => array($this, 'forceOldOrdersTrigger')
			),
		);
		//}

		require_once LWS_WOOREWARDS_INCLUDES . '/ui/adminscreens/pointsmanagement.php';
		\LWS\WOOREWARDS\Ui\AdminScreens\PointsManagement::mergeGroups($systemPage['tabs']['data_management']['groups']);

		$systemPage['tabs']['data_management']['groups']['delete'] = array(
			'id'    => 'delete',
			'title' => __("Delete all data", 'woorewards-lite'),
			'icon'  => 'lws-icon-delete-forever',
			'class' => 'half',
			'text'  => __("Remove all loyalty systems, user points and all MyRewards related data.", 'woorewards-lite')
				. '<br/>' . __("Use this feature with care since this action is <b>irreversible</b>.", 'woorewards-lite'),
			'fields' => array(
				'trigger_delete' => array(
					'id' => 'trigger_delete_all_woorewards',
					'title' => __("Delete All Data", 'woorewards-lite'),
					'type' => 'button',
					'extra' => array(
						'callback' => array($this, 'deleteAllData')
					),
				),
			)
		);

		return $systemPage;
	}

	function showCronStatus()
	{
		$text = array();
		if ($next = \intval(\wp_next_scheduled('lws_woorewards_daily_event'))) {
			$d = \date_create('now', \LWS_WooRewards::getSiteTimezone())->setTimestamp($next);
			$text[] = sprintf(__('Next CRON action planned at %1$s (UTC%2$s).', 'woorewards-lite'), $d->format('Y-m-d H:i'), $d->format('P'));
		} else {
			$text[] = __('CRON action is not registered. To fix it, deactivate then re-activate this plugin.', 'woorewards-lite');
		}
		if ($last = \intval(\get_option('lws_woorewards_last_cron_time'))) {
			$d = \date_create('now', \LWS_WooRewards::getSiteTimezone())->setTimestamp($last);
			$text[] = sprintf(__('Last CRON action ran at %1$s (UTC%2$s).', 'woorewards-lite'), $d->format('Y-m-d H:i'), $d->format('P'));
		}
		$text = implode('<br/>', $text);
		echo "<div style='padding:20px;gap:20px;text-align:right;'><small>{$text}</small></div>";
	}

	protected function getProVersionPage()
	{
		$page = array(
			'title'    	=> __("Pro Version", 'woorewards-lite'),
			'subtitle' 	=> "<div style='padding:2px 10px 4px 10px;background-color:#526981;color:#fff;text-align:center;font-weight:bold'>" . __("Pro Version", 'woorewards-lite') . "</div>",
			'pagetitle' => __("Pro Version", 'woorewards-lite'),
			'id'       	=> LWS_WOOREWARDS_PAGE . '-proversion',
			'rights'   	=> 'manage_options',
			'color'		=> '#4f9bbf',
			'nosave'	=> true,
			'image'		=> LWS_WOOREWARDS_IMG . '/r-pro.png',
			'description' => __("Unlock this plugin's full potential by switching to the pro version. Check all the features and discover how to install it", 'woorewards-lite'),
		);

		if ($page['id'] != $this->getCurrentPage())
			return $page;

		$installurl = \esc_attr(\admin_url('plugin-install.php'));
		$install = <<<EOT
		<div class='teaser'>
			<div class='teaser-div'>
				Follow these instructions to install the pro version and activate it
			</div>
			<ul class='teaser-list'>
				<li>You received an email following your order with a download link.<b>Click the link</b> to download the plugin's zip file</li>
				<li>If you can't find the email, log into <a href='https://plugins.longwatchstudio.com/my-account' target='_blank'>your account</a> and go to the <b>Downloads</b> section. Download the zip file</li>
				<li>In your WordPress administration, go to <a href='{$installurl}' target='_blank'>the plugins installation page</a> and click the <b>Upload Plugin</b> button. In the dialog, select the plugin's zip file and click <b>Install Now</b></li>
				<li>When the process is complete, choose to <b>Replace the existing version with the new one</b>, even if the version number is the same</li>
				<li>Activate the plugin</li>
				<li>Now, go to <b>WooRewards → System → License Management</b>, paste your license key and click on <b>Activate</b></li>
				<li>That's it, you now have the pro version installed and active.</li>
			</ul>
		</div>
EOT;

		$content = <<<EOT
	<div class='teaser'>
		<div class='teaser-div'>
			MyRewards Pro extends MyRewards features and offers a wide variety of new possibilities that will help you retain your existing customers and attract new ones.
		</div>
		<ul class='teaser-list'>
			<li><b>20+ Action to earn points</b><span> - Choose in a large variety of methods to earn points to engage your customers in a meaningful way.</span></li>
			<li><b>Infinite points and rewards systems</b><span> - You can create different loyalty programs for different purposes and even for different customers</span></li>
			<li><b>Ambassador System</b><span> - Transform your customers into real ambassadors by rewarding them for each new customer they bring or for each dollar spent by them</span></li>
			<li><b>Points expiration</b><span> - Choose between 3 different methods for points expiration : Inactivity, Periodical, Transactional</span></li>
			<li><b>Events</b><span> - Create timed loyalty programs for special occasions like Christmas, Easter, your website's anniversary ...</span></li>
			<li><b>Your Points, Your name</b><span> - For each loyalty program, you have the option to name the points how you want, or even use an image instead of a name</span></li>
			<li><b>WooCommerce integration</b><span> - Display loyalty information inside product pages, my account pages, cart and checkout pages and even in WooCommerce emails</span></li>
			<li><b>Wizards</b><span> - Earn time by creating quickly new events or loyalty programs thanks to our predefined wizards. They will guide you through all the process</span></li>
			<li><b>Points Import/Export</b><span> - You want to switch from another loyalty plugin ? No problem, MyRewards includes an import/export feature, even for other plugins</span></li>
			<li><b>Social Media</b><span> - Reward customers when they share your content on social media OR only reward them if it brings new visitors to your website</span></li>
			<li><b>Customers Management</b><span> - Manage your customers' points, rewards and levels</span></li>
			<li><b>REST API</b><span> - Want to connect a third party software ? It's possible with the included REST API</span></li>
			<li><b>WooCommerce Subscriptions compatibility</b><span> - Give points for initial subscriptions and for subscriptions renewals</span></li>
			<li><b>Shortcodes</b><span> - With more than 20 shortcodes to choose from, you always find the one you need to display the right information at the right place</span></li>
			<li><b>Widgets</b><span> - If you prefer to use widgets, then you will find all the necessary widgets for your needs</span></li>
			<li><b>Emails</b><span> - Decide which emails to send between 7 sorts and customize them</span></li>
			<li><b>Badges and Achievements</b><span> - Play with your customers' pride by adding badges and achievements to your website</span></li>
			<li><b>Sponsorship/Referral</b><span> - Let customers sponsor new customers through emails, QR Codes or social shares</span></li>
			<li><b>Order refunds</b><span> - Remove previously earned points when an order is cancelled or refunded</span></li>
		</ul>
		<div class='teaser-button'>
			<a class='teaser-link' href='https://plugins.longwatchstudio.com/product/woorewards/' target='_blank'>Discover MyRewards Pro</a>
		</div>
	</div>
EOT;

		$page['tabs'] = array(
			'pro_version' => array(
				'id'     => 'pro_version',
				'title'  => __("Pro Version", 'woorewards-lite'),
				'icon'   => 'lws-icon-cart-2',
				'groups' => array(
					'install' => array(
						'id'    => 'install',
						'title' => __("Pro Version - How to Install", 'woorewards-lite'),
						'icon'  => 'lws-icon-settings-gear',
						'color' => '#40aa8e',
						'text'  => __("If you already purchased the pro version, follow the instructions below to install and activate it", 'woorewards-lite'),
						'fields' => array(
							'install_instructions' => array(
								'id' => 'install_instructions',
								'type' => 'custom',
								'extra' => array(
									'gizmo'   => true,
									'content' => $install,
								),
							),
						)
					),
					'teaser'  => array(
						'id'    => 'teaser',
						'title' => __("Pro Version - Test if for free for 1 month !", 'woorewards-lite'),
						'icon'  => 'lws-icon-cart-2',
						'color' => '#408aae',
						'text'  => __("Discover the features of the pro version and how it will help your online store grow.", 'woorewards-lite'),
						'fields' => array(
							'pro_description' => array(
								'id' => 'pro_description',
								'type' => 'custom',
								'extra' => array(
									'gizmo'   => true,
									'content' => $content,
								),
							),
						)
					),
				)
			),
		);
		return $page;
	}

	protected function getWoocommerceTab()
	{
		$tab = array(
			'id'     => 'woocommerce',
			'title'  => __("WooCommerce", 'woorewards-lite'),
			'icon'   => 'lws-icon-cart-2',
			'groups' => array(
				'cart' => array(
					'id'     => 'wr_cart_content',
					'icon'	 => 'lws-icon-cart-2',
					'color' => '#425981',
					'title'  => __("Cart Page Content", 'woorewards-lite'),
					'text'	 => __("Select what and where you want to display content on the WooCommerce Cart Page. You can even display other plugins shortcodes", 'woorewards-lite'),
					'fields' => array(),
				),
				'checkout' => array(
					'id'     => 'wr_checkout_content',
					'icon'	 => 'lws-icon-checkmark',
					'color' => '#425981',
					'title'  => __("Checkout Page Content", 'woorewards-lite'),
					'text'	 => __("Select what and where you want to display content on the WooCommerce Checkout Page. You can even display other plugins shortcodes", 'woorewards-lite'),
					'fields' => array(),
				)
			)
		);

		$isCartUseBlocs = \LWS\Adminpanel\Tools\Conveniences::isCartUseBlocs();
		if ($isCartUseBlocs) {
			$tab['groups']['cart']['fields']['disclamer'] = array(
				'id'    => 'disclamer',
				'type'  => 'custom',
				'extra' => array(
					'gizmo'   => true,
					'content' => \LWS\Adminpanel\Tools\Conveniences::array2html(array(
						'tag' => 'b style="color:#0a41c6;"',
						__("Fields below are for legacy [woocommerce_cart] shortcode.", 'woorewards-lite'),
						__("It seems your Cart page is setup with Woocommerce blocks.", 'woorewards-lite'),
						__("Please edit the WooCommerce Cart page and insert our shortcodes directly in it.", 'woorewards-lite'),
					)),
				)
			);
		}
		$isCheckoutUseBlocs = \LWS\Adminpanel\Tools\Conveniences::isCheckoutUseBlocs();
		if ($isCheckoutUseBlocs) {
			$tab['groups']['checkout']['fields']['disclamer'] = array(
				'id'    => 'disclamer',
				'type'  => 'custom',
				'extra' => array(
					'gizmo'   => true,
					'content' => \LWS\Adminpanel\Tools\Conveniences::array2html(array(
						'tag' => 'b style="color:#0a41c6;"',
						__("Fields below are for legacy [woocommerce_checkout] shortcode.", 'woorewards-lite'),
						__("It seems your Checkout page is setup with Woocommerce blocks.", 'woorewards-lite'),
						__("Please edit the WooCommerce Checkout page and insert our shortcodes directly in it.", 'woorewards-lite'),
					)),
				)
			);
		}

		foreach (\LWS\WOOREWARDS\Ui\Woocommerce\CartCheckoutContent::getSettings() as $hook => $settings) {
			$field = array(
				'id'	  => $settings['option'],
				'title' => $settings['title'],
				'type'  => 'wpeditor',
				'extra' => array(
					'editor_height' => 30,
					'wpml'          => $settings['wpml'],
				)
			);

			if ($isCartUseBlocs && false !== \strpos($field['id'], '_cart_')) {
				$field['type'] = 'textarea';
				$field['extra']['disabled'] = true;
				$field['extra']['rows'] = 2;
			}
			if ($isCheckoutUseBlocs && false !== \strpos($field['id'], '_checkout_')) {
				$field['type'] = 'textarea';
				$field['extra']['disabled'] = true;
				$field['extra']['rows'] = 2;
			}

			$tab['groups'][$settings['page']]['fields'][$hook] = $field;
		}

		return $tab;
	}

	protected function getShortcodesTab()
	{
		$shortcodesTab = array(
			'id'     => 'shortcodes',
			'title'  => __("Shortcodes", 'woorewards-lite'),
			'icon'	=> 'lws-icon-shortcode',
			'groups' => array(
				'shortcodes' => array(
					'id'	=> 'shortcodes',
					'title'	=> __('Shortcodes', 'woorewards-lite'),
					'icon'	=> 'lws-icon-shortcode',
					'text'	=> __("In this section, you will find various shortcodes you can use on your website.", 'woorewards-lite'),
					'fields' => \apply_filters('lws_woorewards_referral_shortcodes',
						\apply_filters('lws_woorewards_shortcodes', array())
					)
				),
			)
		);
		return $shortcodesTab;
	}

	protected function getLegacyTab()
	{
		return array(
			'id'     => 'legacy',
			'title'  => __("Legacy", 'woorewards-lite'),
			'icon'   => 'lws-icon-components',
			'groups' => array(
				'pointsoncart' => array(
					'id'     => 'pointsoncart',
					'icon'	 => 'lws-icon-coins',
					'title'  => __("Points On Cart", 'woorewards-lite'),
					'text'	 => __("Select where the Points on Cart tool will be displayed and how it will look", 'woorewards-lite'),
					'fields' => array(
						'cartdisplay' => array(
							'id'    => 'lws_woorewards_points_to_cart_pos',
							'title' => __("Cart Display", 'woorewards-lite'),
							'type'  => 'lacselect',
							'extra' => array(
								'mode'     => 'select',
								'notnull'  => true,
								'maxwidth' => '400px',
								'source'   => array(
									array('value' => 'not_displayed',    'label' => __("Not displayed at all", 'woorewards-lite')),
									array('value' => 'after_products',   'label' => __("Between products and totals", 'woorewards-lite')),
									array('value' => 'cart_collaterals', 'label' => __("Aside from cart totals", 'woorewards-lite')),
								),
								'default'  => 'not_displayed',
								'help'     => __("The following options are used to decide where and how the Points on Cart tool will be displayed in the cart page", 'woorewards-lite'),
							)
						),
						'cartreload' => array(
							'id'    => 'lws_woorewards_points_to_cart_reload',
							'title' => __("Reload cart page after amount modification", 'woorewards-lite'),
							'type'  => 'box',
							'extra' => array(
								'layout' => 'toggle',
								'tooltips' => __("By default, changing the amount will provoke a javascript (ajax) update. Check this box if the default behavior doesn't work.", 'woorewards-lite'),
							)
						),
						'checkoutdisplay' => array(
							'id'    => 'lws_woorewards_points_to_checkout_pos',
							'title' => __("Checkout Display", 'woorewards-lite'),
							'type'  => 'lacselect',
							'extra' => array(
								'mode'     => 'select',
								'notnull'  => true,
								'maxwidth' => '400px',
								'source'   => array(
									array('value' => 'not_displayed',   'label' => __("Not displayed at all", 'woorewards-lite')),
									array('value' => 'top_page',        'label' => __("Top of the page", 'woorewards-lite')),
									array('value' => 'before_customer', 'label' => __("Before customer details", 'woorewards-lite')),
									array('value' => 'before_review',   'label' => __("Before order review", 'woorewards-lite')),
								),
								'default'  => 'not_displayed',
								'help'     => __("The following options are used to decide where and how the Points on Cart tool will be displayed in the checkout page", 'woorewards-lite'),
							)
						),
						'checkoutreload' => array(
							'id'    => 'lws_woorewards_points_to_checkout_reload',
							'title' => __("Reload checkout page after amount modification", 'woorewards-lite'),
							'type'  => 'box',
							'extra' => array(
								'layout' => 'toggle',
								'tooltips' => __("By default, changing the amount will provoke a javascript (ajax) update. Check this box if the default behavior doesn't work.", 'woorewards-lite'),
							)
						),
						'pointsoncartheader' => array(
							'id' => 'lws_wooreward_points_cart_header',
							'title' => __("Tool Header", 'woorewards-lite'),
							'type' => 'text',
							'extra' => array(
								'placeholder' => __('Loyalty points discount', 'woorewards-lite'),
								'size' => '30',
								'wpml' => 'WooRewards - Points On Cart Action - Header',
							)
						),
						array(
							'id' => 'lws_woorewards_points_to_cart_style',
							'type' => 'stygen',
							'extra' => array(
								'purpose'  => 'filter',
								'template' => 'lws_woorewards_points_to_cart',
								'html'     => false,
								'css'      => LWS_WOOREWARDS_CSS . '/templates/pointsoncart.css',
								'help'     => __("Use the styling tool to change the tool's frontend appearance", 'woorewards-lite'),
								'subids'   => array(
									'lws_woorewards_points_to_cart_action_balance' => "WooRewards - Points On Cart Action - Balance",
									'lws_woorewards_points_to_cart_action_use'     => "WooRewards - Points On Cart Action - Use",
									'lws_woorewards_points_to_cart_action_update'  => "WooRewards - Points On Cart Action - Update",
									'lws_woorewards_points_to_cart_action_max'     => "WooRewards - Points On Cart Action - Max",
								),
							)
						)
					)
				),
				'showpoints' => array(
					'id' => 'showpoints',
					'icon' => 'lws-icon-components',
					'title' => __("Display Points Widget", 'woorewards-lite'),
					'extra'    => array('doclink' => \LWS\WOOREWARDS\DocLinks::get('disp-points')),
					'text' => "<strong>" . __("Legacy : This widget is no longer maintained or updated. Use the points balance shortcode instead.", 'woorewards-lite') . "</strong>",
					'fields' => array(
						'spunconnected' => array(
							'id' => 'lws_wooreward_showpoints_nouser',
							'title' => __("Text displayed if user not connected", 'woorewards-lite'),
							'type' => 'text',
							'extra' => array(
								'size' => '50',
								'placeholder' => __("Please log in if you want to see your loyalty points", 'woorewards-lite'),
							)
						),
						'showpoints' => array(
							'id' => 'lws_woorewards_displaypoints_template',
							'type' => 'stygen',
							'extra' => array(
								'purpose' => 'filter',
								'template' => 'wr_display_points',
								'html' => false,
								'css' => LWS_WOOREWARDS_CSS . '/templates/displaypoints.css',
								'help' => __("Here you can customize the look and displayed text of the shortcode/widget", 'woorewards-lite'),
								'subids' => array(
									'lws_woorewards_displaypoints_title' => "WooRewards Show Points - title", // no translation on purpose
									'lws_woorewards_button_more_details' => "WooRewards Show Points - details", // no translation on purpose
								)
							)
						),
					)
				),
				'shortcodes' => array(
					'id'	=> 'shortcodes',
					'title'	=> __('Shortcodes', 'woorewards-lite'),
					'icon'	=> 'lws-icon-shortcode',
					'text'	=> __("These shortcodes are deprecated and are kept here for compatibility. Try to replace them with other shortcodes", 'woorewards-lite'),
					'fields' => array(
						'simplepoints'    => array(
							'id' => 'lws_woorewards_sc_simple_points',
							'title' => __("Simple Points Display", 'woorewards-lite'),
							'type' => 'shortcode',
							'extra' => array(
								'shortcode' => '[wr_simple_points]',
								'description' =>  __("This simple shortcode is used to display the user's points with no decoration.", 'woorewards-lite') . "<br/>" .
									__("This is very convenient if you want to display points within a phrase for example.", 'woorewards-lite'),
								'options' => array(),
								'flags' => array('current_user_id'),
							)
						),
						'showpoints'    => array(
							'id'    => 'lws_woorewards_sc_show_points',
							'title' => __("Display Points", 'woorewards-lite'),
							'type'  => 'shortcode',
							'extra' => array(
								'shortcode'   => '[wr_show_points title="your title"]',
								'description' =>  __("This shortcode shows to customers the points they have on a loyalty system.", 'woorewards-lite'),
								'options'     => array(
									array(
										'option' => 'title',
										'desc' => __("The text displayed before the points.", 'woorewards-lite'),
									),
									array(
										'option' => 'show_currency',
										'desc' => __("(Optional) If set, the number of points displayed will show the points currency.", 'woorewards-lite'),
									),
								),
								'flags' => array('current_user_id'),
							)
						),
					),
				),
			)
		);
	}
	/** Tease about pro version.
	 * Display standand pool settings. */
	protected function getLoyaltyGroups()
	{
		$groups = array();

		if (!\LWS\Adminpanel\Tools\Conveniences::isWC() && (!defined('LWS_WOOREWARDS_ACTIVATED') || !LWS_WOOREWARDS_ACTIVATED)) {
			$groups['information'] = array(
				'id'    => 'information',
				'title' => __("Information", 'woorewards-lite'),
				'text'  => __(
					"MyRewards Standard uses WooCommerce <i>orders</i> and <i>coupons</i>.
							<br/>You should install <a href='https://wordpress.org/plugins/woocommerce/' target='_blank'>WooCommerce</a> to have them active.
							<br/>Or <a href='https://plugins.longwatchstudio.com/product/woorewards/' target='_blank'>upgrade <b>MyRewards</b> to the <b>Pro</b> version</a>
							and enjoy new ways to earn points (social media, sponsoring... with or without WooCommerce) and a lot of new reward types !",
					'woorewards-lite'
				)
			);
		}

		// load the default pool
		$poolInstance = \LWS\WOOREWARDS\Collections\Pools::instanciate();
		$pools = $poolInstance->load(array(
			'meta_query'  => array(
				array(
					'key'     => 'wre_pool_prefab',
					'value'   => 'yes',
					'compare' => 'LIKE'
				),
				array(
					'key'     => 'wre_pool_type',
					'value'   => \LWS\WOOREWARDS\Core\Pool::T_STANDARD,
					'compare' => 'LIKE'
				)
			)
		));

		if ($pools->count() <= 0) {
			$text = '';
			if (!(defined('LWS_WOOREWARDS_ACTIVATED') && LWS_WOOREWARDS_ACTIVATED)) {
				$allPools = $poolInstance->load(array('deep' => false));
				if ($allPools->count() > 0) {
					$text .= sprintf(
						"<p style='font-size:16px'>%s<br/><b>%s</b><br/><br/>%s</p>",
						__("You are now using the Free Version of WooRewards. However, we detect that you still have Premium Version settings that are not compatible with the Free Version.", 'woorewards-lite'),
						__("If you buy a Premium Version License and activate it, it will restore all the Premium Version data (points and rewards systems, user points, settings).", 'woorewards-lite'),
						__("If you prefer to only use the free version, please follow the instructions below :", 'woorewards-lite')
					);
				}
				if (defined('LWS_WIZARD_SUMMONER') && LWS_WIZARD_SUMMONER) {
					$url = \esc_attr(\add_query_arg(array('page' => LWS_WIZARD_SUMMONER . LWS_WOOREWARDS_PAGE), admin_url('admin.php')));
					$attr = " href='{$url}' class='lws-adm-btn'";
					$text .= sprintf(
						__("The WooRewards default points and rewards system does not exist. Please run the <a%s>wizard</a> tool to create one.", 'woorewards-lite'),
						$attr
					);
					\lws_admin_add_notice(self::NOTICE_NO_POOL, $text, array('level' => 'info', 'dismissible' => true, 'forgettable' => true));
				} else {
					$text .= __("The MyRewards default points and rewards system does not exist. Try to re-activate this plugin. If the problem persists, contact your administrator.", 'woorewards-lite');
					\lws_admin_add_notice(self::NOTICE_NO_POOL, $text, array('level' => 'info', 'dismissible' => true, 'forgettable' => true));
				}
			}
			$groups['failure'] = array(
				'id'    => 'failure',
				'title' => __("Loading failure", 'woorewards-lite'),
				'text'  => $text,
			);
		} else {
			$prefix = 'lws-wr-pool-option-';
			// let dedicated class create options
			$pool = $pools->get(0);
			$groups = array_merge($groups, array(
				'general'    => array(
					'id'       => 'general',
					'image'		=> LWS_WOOREWARDS_IMG . '/ls-settings.png',
					'color'		=> '#7958a5',
					'title'    => __("General Settings", 'woorewards-lite'),
					'fields'   => \apply_filters('lws_woorewards_admin_pool_general_settings', array(), $pool),
					'text'     => __("Before activating your loyalty program, make sure you've read the documentation. You will find links to the documentation on the top right of each group.", 'woorewards-lite'),
					'extra'    => array('doclink' => \LWS\WOOREWARDS\DocLinks::get('pools')),
				),
				'earning' => array(
					'id'		=> 'earning',
					'class'		=> 'half',
					'title'    	=> __("Points", 'woorewards-lite'),
					'image'		=> LWS_WOOREWARDS_IMG . '/ls-earning.png',
					'color'		=> '#38bebe',
					'text'     	=> __("Here you can manage how your customers earn loyalty points", 'woorewards-lite'),
					'extra'    => array('doclink' => \LWS\WOOREWARDS\DocLinks::get('points')),
					'editlist' 	=> \lws_editlist(
						'EventList',
						\LWS\WOOREWARDS\Ui\Editlists\MultiFormList::ROW_ID,
						new \LWS\WOOREWARDS\Ui\Editlists\EventList($pool),
						\LWS\Adminpanel\EditList\Modes::MOD
					)->setPageDisplay(false)->setCssClass('eventlist')->setRepeatHead(false)
				),
				'spending'   => array(
					'id'       => 'spending',
					'class'		=> 'half',
					'title'    => __("Rewards", 'woorewards-lite'),
					'image'		=> LWS_WOOREWARDS_IMG . '/ls-gift.png',
					'color'		=> '#526981',
					'text'     => __("Here you can manage the rewards for your customers. Rewards can either be automatically generated WooCommerce Coupons or points usable on cart for immediate discounts", 'woorewards-lite'),
					'extra'    => array('doclink' => \LWS\WOOREWARDS\DocLinks::get('rewards')),
					'fields' => array(
						'mode' => array(
							'id'    => $prefix . 'direct_reward_mode',
							'type'  => (\defined('LWS_WOOREWARDS_ACTIVATED') && LWS_WOOREWARDS_ACTIVATED) ? 'box' : 'hidden',
							'title' => __("Reward Type", 'woorewards-lite'),
							'extra' => array(
								'id'      => 'direct_reward_mode',
								'layout'  => 'switch',
								'checked' => $pool->getOption('direct_reward_mode'),
								'value'   => $pool->getOption('direct_reward_mode') ? 'on' : '',
								'data'    => array(
									'left'       => __("WooCommerce Coupon", 'woorewards-lite'),
									'right'      => __("Points on Cart", 'woorewards-lite'),
									'colorleft'  => '#425981',
									'colorright' => '#5279b1',
								),
							)
						),
						array(
							'id'    => $prefix . 'direct_reward_point_rate',
							'type'  => 'text',
							'title' => sprintf(__("Point Value in %s", 'woorewards-lite'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
							'extra' => array(
								'value'   => $pool->getOption('direct_reward_point_rate'),
								'help' => __("Each point spent on the cart will decrease the order total of that value", 'woorewards-lite')
							),
							'require' => array('selector' => '#direct_reward_mode', 'value' => 'on'),
						),
						array(
							'id'    => 'rewards',
							'type'  => 'editlist',
							'title' => __("Coupon", 'woorewards-lite'),
							'extra' => array(
								'editlist' => \lws_editlist(
									'UnlockableList',
									\LWS\WOOREWARDS\Ui\Editlists\MultiFormList::ROW_ID,
									new \LWS\WOOREWARDS\Ui\Editlists\UnlockableList($pool),
									\LWS\Adminpanel\EditList\Modes::MOD
								)->setPageDisplay(false)->setCssClass('unlockablelist')->setRepeatHead(false),
							),
							'require' => array('selector' => '#direct_reward_mode', 'value' => ''),
						),
					)
				)
			));

			if (\apply_filters('lwsdev_coupon_individual_use_solver_exists', false)) {
				$groups['spending']['fields']['discount_cats'] = array(
					'id'    => $prefix . 'direct_reward_discount_cats',
					'title' => __("Exclusive categories", 'woorewards-lite'),
					'type'  => 'lacchecklist',
					'extra' => array(
						'comprehensive' => true,
						'ajax'          => 'lwsdev_coupon_individual_use_solver_categories',
						'value'         => $pool->getOption('direct_reward_discount_cats'),
						'help'          => __("Exclusive categories that the coupon will be applied to. Extends the <i>“Individual use only”</i> rule.", 'woorewards-lite'),
					),
					'require' => array('selector' => '#direct_reward_mode', 'value' => 'on'),
				);
			}
		}

		return $groups;
	}

	/** For pool option in admin page:
	 * *	be sure field id starts with 'lws-wr-pool-option-' and Pool->setOption accept the id string rest as valid option name.
	 * *	be sure the page contains a <input> named 'pool' with relevant pool id.
	 * *	since field cannot read value in wp get_option, be sure to set the relevant value in extra array.
	 *
	 *	@param array $fields an array as required by 'fields' entry in admin group.
	 * 	@param $pool a Pool instance. */
	public function getPoolGeneralSettings($fields, \LWS\WOOREWARDS\Core\Pool $pool)
	{
		$poolOptionPrefix = 'lws-wr-pool-option-';

		$fields['pool'] = array(
			'id'    => 'lws-wr-pool-option',
			'type'  => 'hidden',
			'extra' => array(
				'value' => $pool->getId(),
				'id'    => 'lws_wr_pool_id',
			)
		);

		$fields['enabled'] = array(
			'id'    => $poolOptionPrefix . 'enabled', /// id starts with 'lws-wr-pool-option-', 'enabled' is accepted as Pool option
			'type'  => 'box',
			'title' => 'Status',
			'extra' => array(
				'noconfirm' => true,
				'layout'    => 'switch',
				'checked'   => $pool->getOption('enabled'), /// set field value here
				'data'      => array(
					'default' => _x("Off", "pool enabled switch", 'woorewards-lite'),
					'checked' => _x("On", "pool enabled switch", 'woorewards-lite')
				)
			)
		);

		$fields['title'] = array(
			'id'    => $poolOptionPrefix . 'title',
			'type'  => 'text',
			'title' => _x("Title", "Pool title", 'woorewards-lite'),
			'extra' => array(
				'required' => true,
				'value'    => $pool->getOption('title')
			)
		);

		return $fields;
	}

	/** Simulate the order status change for order in date range */
	function forceOldOrdersTrigger($btnId, $data = array())
	{
		if ($btnId != 'trigger_orders') return false;

		if (!(isset($data['orders_conf']) && \wp_verify_nonce($data['orders_conf'], 'processPastOrders'))) {
			$label = __("If you really want to process pre-existing orders, check this box and click on <i>'%s'</i> again.", 'woorewards-lite');
			$label = sprintf($label, __("Launch the procedure", 'woorewards-lite'));
			$warn = __("If your loyalty program is live, this could lead to lots of rewards being generated and lots of emails being sent", 'woorewards-lite');
			$tips = __("Please make sure you reviewed all the settings before launching this procedure.", 'woorewards-lite');

			$nonce = \esc_attr(\wp_create_nonce('processPastOrders'));
			$str = <<<EOT
<p>
	<input type='checkbox' class='lws-ignore-confirm' id='orders_conf' name='orders_conf' value='{$nonce}' autocomplete='off'>
	<label for='orders_conf'>{$label} <b style='color: red;'>{$warn}</b><br/>{$tips}</label>
</p>
EOT;
			return $str;
		}

		if (!isset($data['date_min']) || !($d1 = \date_create($data['date_min']))) return __("Dates are required", 'woorewards-lite');
		if (!isset($data['date_max']) || !($d2 = \date_create($data['date_max']))) return __("Dates are required", 'woorewards-lite');
		if ($d2 < $d1) {
			$tmp = $d2;
			$d2 = $d1;
			$d1 = $tmp;
		}
		$d1 = $d1->format('Y-m-d');
		$d2 = $d2->format('Y-m-d');

		$status = array_unique(\apply_filters('lws_woorewards_order_events', array('processing', 'completed')));
		$status = array_map(function ($s) {
			return 'wc-' . $s;
		}, $status);

		$shopKind = \apply_filters('lws_woorewards_order_backward_apply_shop_kind', array('shop_order'));
		$shopKind = implode("','", array_map('\esc_sql', $shopKind));

		$args = \apply_filters('lws_woorewards_process_past_orders_query', array(
			'limit'        => -1,
			'type'         => $shopKind,
			'status'       => $status,
			'date_created' => $d1 . '...' . $d2,
		));

		$count = 0;
		foreach ((array)\wc_get_orders($args) as $order) {
			\do_action('lws_woorewards_pool_on_order_done', $order->get_id(), $order);
			++$count;
		}

		return sprintf(__("<b>%s</b> order(s) processed.", 'woorewards-lite'), $count);
	}

	function deleteAllData($btnId, $data = array())
	{
		if ($btnId != 'trigger_delete_all_woorewards') return false;

		if (!(isset($data['del_conf']) && \wp_verify_nonce($data['del_conf'], 'deleteAllData'))) {
			$label = __("If you really want to reset all MyRewards data, check this box and click on <i>'%s'</i> again.", 'woorewards-lite');
			$label = sprintf($label, __("Delete All Data", 'woorewards-lite'));
			$warn = __("This operation is irreversible!", 'woorewards-lite');
			$tips = __("Consider making a backup of your database before continue.", 'woorewards-lite');

			$nonce = \esc_attr(\wp_create_nonce('deleteAllData'));
			$str = <<<EOT
<p>
	<input type='checkbox' class='lws-ignore-confirm' id='del_conf' name='del_conf' value='{$nonce}' autocomplete='off'>
	<label for='del_conf'>{$label} <b style='color: red;'>{$warn}</b><br/>{$tips}</label>
</p>
EOT;
			return $str;
		}

		$wpInstalling = \wp_installing();
		\wp_installing(true); // should force no cache
		\do_action('lws_woorewards_before_delete_all', $data);
		error_log("[MyRewards] Delete everything");

		foreach (array(
			'lws_wooreward_max_sponsorship_count',
			'lws_woorewards_version',
			'lws_woorewards_pointstack_timeout_delete',
		) as $opt) {
			\delete_option($opt);
		}

		global $wpdb;
		foreach (array('lws-wre-pool', 'lws-wre-event', 'lws-wre-unlockable') as $post_type) {
			$posts = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type=%s", $post_type)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter
			foreach ($posts as $post_id)
				\wp_delete_post($post_id, true);
		}

		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}lws_wr_historic");
		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}lws_wr_achieved_log");
		// do not drop {$wpdb->base_prefix}lws_wr_tinyurls since such url can be widely shared

		// user meta
		$ukeys = \implode("','", array(
			'lws_wre_unlocked_id',
			'lws_wre_pending_achievement',
			'lws_wooreward_used_sponsorship',
			'lws_woorewards_sponsored_by',
			'lws_woorewards_sponsored_origin',
			'lws_woorewards_at_registration_sponsorship',
		));
		$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('{$ukeys}')");
		$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'lws_wr_redeemed_%'");
		$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'lws_wre_points_%'"); /// @see \LWS\WOOREWARDS\Core\PointStack::MetaPrefix

		// post meta
		$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'lws_woorewards_%'");
		$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('reward_origin','reward_origin_id','wre_pool_point_stack')");
		// hpos order meta
		if ($wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}wc_orders_meta'")) {
			$wpdb->query("DELETE FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key LIKE 'lws_woorewards_%'");
		}

		// mails
		$prefix = 'lws_mail_' . 'woorewards' . '_attribute_';
		\delete_option($prefix . 'headerpic');
		\delete_option($prefix . 'footer');
		foreach (array('wr_new_reward') as $template) {
			\delete_option('lws_mail_subject_' . $template);
			\delete_option('lws_mail_preheader_' . $template);
			\delete_option('lws_mail_title_' . $template);
			\delete_option('lws_mail_header_' . $template);
			\delete_option('lws_mail_template_' . $template);
			\delete_option('lws_mail_bcc_admin_' . $template);
		}

		// clean options
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'lws_woorewards_%'");
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'rflush_lws_woorewards_%'");

		\do_action('lws_woorewards_after_delete_all', $data);
		\wp_installing($wpInstalling);
		return __("You can now create new Points and Rewards System for your customers or uninstall MyRewards.", 'woorewards-lite');
	}

	public function addSponsorshipTab($page)
	{
		$current = \LWS\Adminpanel\Tools\Conveniences::getCurrentAdminPage();
		if (false === \strpos($current, LWS_WOOREWARDS_PAGE))
			return $page;
		if (isset($page['tabs']['sponsorship']))
			return $page;

		$page['tabs']['sponsorship'] = array(
			'id'     => 'sponsorship',
			'title'  => __('Referrals', 'woorewards-lite'),
			'icon'   => 'lws-icon-b-check',
			'vertnav'=> true,
			'groups' => array(),
		);

		if ((LWS_WOOREWARDS_PAGE . '.customers') != $current)
			return $page;
		if (!(isset($_REQUEST['tab']) && 'sponsorship' == $_REQUEST['tab']))
			return $page;

		require_once LWS_WOOREWARDS_INCLUDES . '/ui/editlists/sponsorships.php';
		$page['tabs']['sponsorship']['groups']['sponsors'] = array(
			'id' 	     => 'sponsors_list',
			'title'    => __("Referrals Information", 'woorewards-lite'),
			'icon'	   => 'lws-icon-b-check',
			'color'    => '#6e96b5',
			'text'     => array('tag' => 'ul',
				__("You will find here a list of all customers who referred other people.", 'woorewards-lite'),
				sprintf(
					__("See %s to let your customer share a referral link and get referees.", 'woorewards-lite'),
					sprintf('<a href="%s">%s</a>',
						\esc_attr(\add_query_arg(array(
							'page' => LWS_WOOREWARDS_PAGE . '.appearance', 'tab' => 'shortcodes'
						), \admin_url('admin.php#lws_woorewards_referral_link'))),
						'[wr_referral_link]'
					)
				),
			),
			'editlist' => \LWS\WOOREWARDS\Ui\Editlists\Sponsorships::instanciate(),
		);

		return $page;
	}
}
