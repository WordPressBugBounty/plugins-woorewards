<?php

/**
 * Plugin Name: MyRewards
 * Description: Improve your customers experience with Rewards, Levels and Achievements. Use it with WooCommerce to set up a loyalty program.
 * Plugin URI: https://plugins.longwatchstudio.com/product/woorewards/
 * Author: Long Watch Studio
 * Author URI: https://longwatchstudio.com
 * Version: 5.4.3
 * License: Copyright LongWatchStudio 2022
 * Text Domain: woorewards-lite
 * Domain Path: /languages
 * WC requires at least: 7.1.0
 * WC tested up to: 9.3
 *
 * Copyright (c) 2022 Long Watch Studio (email: plugins@longwatchstudio.com). All rights reserved.
 *
 *
 */


// don't call the file directly
if (!defined('ABSPATH')) exit();

/** That class holds the entire plugin. */
final class LWS_WooRewards
{

	public static function init()
	{
		static $instance = false;
		if (!$instance) {
			$instance = new self();
			$instance->defineConstants();
			\add_action('after_setup_theme', function() {
				\load_plugin_textdomain('woorewards-lite', FALSE, basename(dirname(__FILE__)) . '/languages/');
			});

			\add_action('before_woocommerce_init', function() { // HPOS support
				if (\class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
				}
			});

			\add_action('lws_adminpanel_register', array($instance, 'register'));

			if (\is_admin()) {
				add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($instance, 'extensionListActions'), 10, 2);
				add_filter('lws_adminpanel_purchase_url_woorewards', array($instance, 'addPurchaseUrl'), 10, 1);
				add_filter('lws_adm_trialend_msg', array($instance, 'getTrialEndMessage'), 10, 4);
				add_filter('lws_adm_trialstart_msg', array($instance, 'getTrialStartMessage'), 10, 3);
				foreach (array('', '.customers', '.loyalty', '.settings') as $page)
					add_filter('lws_adminpanel_plugin_version_' . LWS_WOOREWARDS_PAGE . $page, array($instance, 'addPluginVersion'), 10, 1);
				add_filter('lws_adminpanel_documentation_url_woorewards', array($instance, 'addDocUrl'), 10, 1);

				if (!(defined('DOING_AJAX') && DOING_AJAX)) {
					require_once LWS_WOOREWARDS_INCLUDES . '/updater.php';
					// piority as soon as possible, But sad bug from WP.
					// Trying to get property of non-object in ./wp-includes/post.php near line 3917: $feeds = $wp_rewrite->feeds;
					// cannot do it sooner.
					add_action('setup_theme', array('\LWS\WOOREWARDS\Updater', 'checkUpdate'), -100);
					add_action('setup_theme', array($instance, 'forceVisitLicencePage'), 0);
				}
			}

			add_action('lws_woorewards_daily_event', function () {
				\update_option('lws_woorewards_last_cron_time', \time());
			});

			$instance->install();

			register_activation_hook(__FILE__, 'LWS_WooRewards::activation');
		}
		return $instance;
	}

	function forceVisitLicencePage()
	{
		if (\get_option('lws_woorewards_redirect_to_licence', 0) > 0) {
			$page = array('page' => LWS_WOOREWARDS_PAGE);
			if (defined('LWS_WIZARD_SUMMONER')) {
				$page = array('page' => LWS_WIZARD_SUMMONER . LWS_WOOREWARDS_PAGE);
			}
			$page = \apply_filters('lws_woorewards_redirect_after_install_url', $page);
			\update_option('lws_woorewards_redirect_to_licence', 0);

			if ($page) {
				$exit = \wp_redirect(\add_query_arg($page, admin_url('admin.php')));
				if ($exit)
					exit;
			}
		}
	}

	public function v()
	{
		static $version = '';
		if (empty($version)) {
			if (!function_exists('get_plugin_data')) require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			$data = \get_plugin_data(__FILE__, false, false);
			$version = (isset($data['Version']) ? $data['Version'] : '0');
		}
		return $version;
	}

	/**
	 * Define the plugin constants
	 *
	 * @return void
	 */
	private function defineConstants()
	{
		define('LWS_WOOREWARDS_VERSION', '5.4.3');
		define('LWS_WOOREWARDS_FILE', __FILE__);
		define('LWS_WOOREWARDS_DOMAIN', 'woorewards-lite');
		define('LWS_WOOREWARDS_PAGE', 'woorewards');
		define('LWS_WOOREWARDS_UUID', '072f0512659060939ec5047e24ef2be3');

		define('LWS_WOOREWARDS_PATH', dirname(LWS_WOOREWARDS_FILE));
		define('LWS_WOOREWARDS_INCLUDES', LWS_WOOREWARDS_PATH . '/include');
		define('LWS_WOOREWARDS_SNIPPETS', LWS_WOOREWARDS_PATH . '/snippets');
		define('LWS_WOOREWARDS_ASSETS',   LWS_WOOREWARDS_PATH . '/assets');

		define('LWS_WOOREWARDS_URL', 		plugins_url('', LWS_WOOREWARDS_FILE));
		define('LWS_WOOREWARDS_JS',  		plugins_url('/js', LWS_WOOREWARDS_FILE));
		define('LWS_WOOREWARDS_CSS', 		plugins_url('/styling/css', LWS_WOOREWARDS_FILE));
		define('LWS_WOOREWARDS_IMG', 		plugins_url('/img', LWS_WOOREWARDS_FILE));

		global $wpdb;
		$wpdb->lwsWooRewardsHistoric = $wpdb->prefix . 'lws_wr_historic';
	}

	public function extensionListActions($links, $file)
	{
		$label = __('Settings'); // use standart wp sentence, no text domain
		$url = add_query_arg(array('page' => LWS_WOOREWARDS_PAGE . '.loyalty'), admin_url('admin.php'));
		array_unshift($links, "<a href='$url'>$label</a>");
		$label = __('Help'); // use standart wp sentence, no text domain
		$url = esc_attr($this->addDocUrl(''));
		$links[] = "<a href='$url'>$label</a>";
		return $links;
	}

	public function addPurchaseUrl($url)
	{
		return __("https://plugins.longwatchstudio.com/product/woorewards/", 'woorewards-lite');
	}

	public function addPluginVersion($url)
	{
		return '5.4.3';
	}

	public function addDocUrl($url)
	{
		return \LWS\WOOREWARDS\DocLinks::get('home');
	}

	function register()
	{
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/admin.php';
		new \LWS\WOOREWARDS\Ui\Admin();
	}

	/** Add elements we need on this plugin to work
	 * Run once at activation */
	public static function activation()
	{
		require_once dirname(__FILE__) . '/include/updater.php';
		\LWS\WOOREWARDS\Updater::activate();
		self::setupCron();
	}

	public static function setupCron()
	{
		$hook = 'lws_woorewards_daily_event';
		if (\wp_next_scheduled($hook)) // allows reset on re-activation
			\wp_clear_scheduled_hook($hook);

		$d = \date_create('now', self::getSiteTimezone())->setTime(0, 0);
		$d->add(new \DateInterval('P1D')); // start tomorow

		$ref = \intval(\get_option($hook . '_reference_hour', '1')); // prefer 1am by default
		if ($ref) {
			$shift = new \DateInterval('PT' . \abs($ref) . 'H');
			if ($ref > 0) $d->add($shift);
			else           $d->sub($shift);
		}

		\wp_schedule_event(
			$d->getTimestamp(),
			\apply_filters('lws_woorewards_main_event_period', 'daily'),
			$hook
		);
	}

	/** autoload WooRewards core and collection classes. */
	public function autoload($class)
	{
		if (substr($class, 0, 15) == 'LWS\WOOREWARDS\\') {
			$rest = substr($class, 15);
			$publicNamespaces = array(
				'Collections', 'Abstracts', 'Core', 'Unlockables', 'Events',
			);
			$publicClasses = array(
				'Ui\Editlists\MultiFormList',
				'Ui\Editlists\EventList', 'Ui\Editlists\UnlockableList',
				'Wizard', 'Wizards\Subwizard', 'Ui\Widget',
			);

			if (in_array(explode('\\', $rest, 2)[0], $publicNamespaces) || in_array($rest, $publicClasses)) {
				$basename = str_replace('\\', '/', strtolower($rest));
				$filepath = LWS_WOOREWARDS_INCLUDES . '/' . $basename . '.php';
				@include_once $filepath;
				return true;
			}
		}
	}

	/**	@deprecated use \LWS\Adminpanel\Tools\Conveniences::isWC() instead. */
	static public function isWC($false = false)
	{
		return function_exists('wc');
	}

	/** If another name/symbol should be used instead of point.
	 * @param $count to return singular or plural form. */
	static public function getPointSymbol($count = 1, $poolName = '')
	{
		$sym = \apply_filters('lws_woorewards_point_symbol_translation', false, $count, $poolName);
		if ($sym === false) // default symbol
		{
			$sym = ($count == 1 ? __("Point", 'woorewards-lite') : __("Points", 'woorewards-lite'));
		}
		return $sym;
	}

	static public function formatPointsWithSymbol($points, $poolName)
	{
		$sym = self::getPointSymbol($points, $poolName);
		return \apply_filters('lws_woorewards_point_with_symbol_format', sprintf("%s %s", $points, $sym), $points, $sym, $poolName);
	}

	static public function formatPoints($points, $poolName)
	{
		return \apply_filters('lws_woorewards_point_format', $points, $points, $poolName);
	}

	static public function symbolFilter($symbol = '', $count = 1, $poolName = '')
	{
		return self::getPointSymbol($count, $poolName);
	}

	public function blocksLoaded()
	{
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/blocks/integration.php';
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/blocks/extension.php';

		\add_action('woocommerce_blocks_cart_block_registration', function($integration_registry) {
			$integration_registry->register(new \LWS\WOOREWARDS\Ui\Blocks\Integration());
		});
		\add_action('woocommerce_blocks_checkout_block_registration', function($integration_registry) {
			$integration_registry->register(new \LWS\WOOREWARDS\Ui\Blocks\Integration());
		});
		\LWS\WOOREWARDS\Ui\Blocks\Extension::install();
	}

	/** Take care WP_Post manipulation is hazardous before hook 'setup_theme' (since global $wp_rewrite is not already set) */
	private function install()
	{
		spl_autoload_register(array($this, 'autoload'));
		\add_filter('lws_woorewards_point_symbol', array(__CLASS__, 'symbolFilter'), 10, 3);
		// include obviously required classes
		require_once LWS_WOOREWARDS_INCLUDES . '/doclinks.php';
		require_once LWS_WOOREWARDS_INCLUDES . '/conveniencies.php';
		require_once LWS_WOOREWARDS_INCLUDES . '/abstracts/icategorisable.php';
		require_once LWS_WOOREWARDS_INCLUDES . '/abstracts/iregistrable.php';
		require_once LWS_WOOREWARDS_INCLUDES . '/abstracts/collection.php';
		require_once LWS_WOOREWARDS_INCLUDES . '/abstracts/unlockable.php';
		require_once LWS_WOOREWARDS_INCLUDES . '/abstracts/event.php';
		require_once LWS_WOOREWARDS_INCLUDES . '/core/pointstack.php';
		require_once LWS_WOOREWARDS_INCLUDES . '/core/pool.php';

		require_once LWS_WOOREWARDS_INCLUDES . '/core/sponsorship.php';
		\LWS\WOOREWARDS\Core\Sponsorship::register();
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/shortcodes/referrallink.php';
		\LWS\WOOREWARDS\Ui\ShortCodes\ReferralLink::install();

		// register events and unlockables
		require_once LWS_WOOREWARDS_INCLUDES . '/registration.php';

		\add_action('init', function() {
			\add_image_size('lws_wr_thumbnail', 96, 96);
			\add_image_size('lws_wr_thumbnail_small', 42, 42);
		});
		\add_filter('image_size_names_choose', function ($sizes) {
			return array_merge($sizes, array(
				'lws_wr_thumbnail' => __("MyRewards Thumbnail", 'woorewards-lite'),
				'lws_wr_thumbnail_small' => __("MyRewards Thumbnail Small", 'woorewards-lite')
			));
		});

		\add_filter('lws_adminpanel_wizards', function ($wizards) {
			$wizards[LWS_WOOREWARDS_PAGE] = '\LWS\WOOREWARDS\Wizard';
			return $wizards;
		});

		// anyway, load all pools and install configured events. Do it as soon as possible but let anywho to hook before.
		\add_action('setup_theme', array(__CLASS__, 'installPool'));
		\add_action('init', array($this, 'registerPostTypes'));

		\add_filter('lws_woorewards_order_events', array($this, 'getOrderValidationStates'));

		\LWS\WOOREWARDS\Conveniences::install();
		require_once LWS_WOOREWARDS_INCLUDES . '/options.php';
		new \LWS\WOOREWARDS\Options();
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/achievement.php';
		new \LWS\WOOREWARDS\Ui\Achievement();
		require_once LWS_WOOREWARDS_INCLUDES . '/core/ajax.php';
		new \LWS\WOOREWARDS\Core\Ajax();
		require_once LWS_WOOREWARDS_INCLUDES . '/unlockables/coupon.php';
		\LWS\WOOREWARDS\Unlockables\Coupon::addUiFilters();

		// Shortcodes
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/shortcodes/pointsbalance.php';
		\LWS\WOOREWARDS\Ui\Shortcodes\PointsBalance::install();
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/shortcodes/availablecoupons.php';
		\LWS\WOOREWARDS\Ui\Shortcodes\AvailableCoupons::install();
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/shortcodes/pointsvalue.php';
		\LWS\WOOREWARDS\Ui\Shortcodes\PointsValue::install();
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/shortcodes/userhistory.php';
		\LWS\WOOREWARDS\Ui\Shortcodes\UserHistory::install();

		require_once LWS_WOOREWARDS_INCLUDES . '/pointdiscount.php';
		\LWS\WOOREWARDS\PointDiscount::install();

		require_once LWS_WOOREWARDS_INCLUDES . '/ui/widget.php';
		if (\LWS\WOOREWARDS\Conveniences::instance()->isLegacyShown('4.7.0')) {
			require_once LWS_WOOREWARDS_INCLUDES . '/ui/legacy/pointsdisplayer.php';
			\LWS\WOOREWARDS\Ui\Legacy\PointsDisplayer::install();
		}

		// WooCommerce
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/woocommerce/pointsoncart.php';
		\LWS\WOOREWARDS\Ui\Woocommerce\PointsOnCart::install();
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/woocommerce/cartcheckoutcontent.php';
		\LWS\WOOREWARDS\Ui\Woocommerce\CartCheckoutContent::install();
		require_once LWS_WOOREWARDS_INCLUDES . '/core/ordernote.php';
		\LWS\WOOREWARDS\Core\OrderNote::install();
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/woocommerce/ordernote.php';
		\LWS\WOOREWARDS\Ui\Woocommerce\OrderNote::install();
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/woocommerce/ordersbulk.php';
		\LWS\WOOREWARDS\Ui\Woocommerce\OrdersBulk::install();

		// blocks
		\add_action('woocommerce_blocks_loaded', [$this, 'blocksLoaded']);
		\add_action('block_categories_all', function($categories) {
			return \array_merge($categories, [[
				'slug'  => 'lws-wr-blocks',
				'title' => __("WooRewards Blocks", 'woorewards-lite'),
			]]);
		}, 10, 2);

		// Email template
		require_once LWS_WOOREWARDS_INCLUDES . '/mails/newreward.php';
		new \LWS\WOOREWARDS\Mails\NewReward();

		// Compatibility Classes
		\add_action('plugins_loaded', function () {
			if (\class_exists('UM')) {
				require_once LWS_WOOREWARDS_INCLUDES . '/compatibility/ultimatemember.php';
				\LWS\WOOREWARDS\Compatibility\UltimateMember::install();
			}
		});

		// styling
		\add_action('wp_enqueue_scripts', array($this, 'enqueueGlobalStyles'));
		\add_action('admin_enqueue_scripts', array($this, 'enqueueGlobalStyles'));
		\add_action('wp_head', function() {
			require_once LWS_WOOREWARDS_INCLUDES . '/ui/adminscreens/styling.php';
			if ($style = \LWS\WOOREWARDS\Ui\AdminScreens\Styling::getInline())
				echo $style;
		});

		// hide some meta from admin
		if (\get_option('lws_woorewards_hide_internal_meta', 'on')) {
			\add_action('is_protected_meta', function($protected, $key, $type) {
				if (!$protected && 'post' == $type && 'lws_' == \substr($key, 0, 4)) {
					if ('lws_woorewards_core_pool-' == \substr($key, 0, 25))
						$protected = true;
					elseif ('lws_woorewards_points_refunded' == $key)
						$protected = true;
				}
				return $protected;
			}, 10, 3);
		}

		// import/export
		require_once LWS_WOOREWARDS_INCLUDES . '/pointsflow/action.php';
		\LWS\WOOREWARDS\PointsFlow\Action::register();

		\do_action('lws_woorewards_init');
	}

	function enqueueGlobalStyles()
	{
		\wp_enqueue_style('wr-frontend-elements', LWS_WOOREWARDS_CSS . '/wr-elements.min.css', array(), LWS_WOOREWARDS_VERSION);
	}

	function getOrderValidationStates($status)
	{
		$status = \get_option('lws_woorewards_points_distribution_status', false);
		if (false === $status) {
			// legacy
			$status = array('processing', 'completed');
			if (\get_option('lws_woorewards_coupon_state', false)) // checked: Points on 'Complete' order only
				$status = array('completed');
		} else if ($status && !\is_array($status)) {
			$status = array('processing', 'completed');
		}
		return ($status && \is_array($status)) ? $status : array();
	}

	function registerPostTypes()
	{
		\register_post_type(\LWS\WOOREWARDS\Core\Pool::POST_TYPE, array(
			'show_in_rest' => true,
			'hierarchical' => true,
			'labels' => array(
				'name' => __("Loyalty Systems", 'woorewards-lite'),
				'singular_name' => __("Loyalty System", 'woorewards-lite')
			),
			'supports' => array('title', 'custom-fields'),
		));
		\register_post_type(\LWS\WOOREWARDS\Abstracts\Event::POST_TYPE, array(
			'show_in_rest' => true,
			'hierarchical' => true,
			'labels' => array(
				'name' => __("Earning Points Methods", 'woorewards-lite'),
				'singular_name' => __("Earning Points Method", 'woorewards-lite')
			),
			'supports' => array('title', 'custom-fields'),
		));
		\register_post_type(\LWS\WOOREWARDS\Abstracts\Unlockable::POST_TYPE, array(
			'show_in_rest' => true,
			'hierarchical' => true,
			'labels' => array(
				'name' => __("Rewards", 'woorewards-lite'),
				'singular_name' => __("Reward", 'woorewards-lite')
			),
			'supports' => array('title', 'custom-fields'),
		));

		$metas = \apply_filters('lws_woorewards_register_custom_post_metas', array(
			\LWS\WOOREWARDS\Core\Pool::POST_TYPE => array(
				'wre_pool_direct_reward_point_rate' => true,
			),
		));
		foreach ($metas as $type => $meta) {
			foreach ($meta as $key => $single) {
				\register_meta('post', $key, array(
					'object_subtype' => $type,
					'show_in_rest' => true,
					'single' => $single,
				));
			}
		}
	}

	static function installPool()
	{
		$pools = self::getPrefabPools()->install();
		self::getInstalledPool($pools->first());
		\do_action('lws_woorewards_pools_loaded', $pools);
	}

	static function getPrefabPools($enabledOnly=true)
	{
		$args = array(
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
		);
		if ($enabledOnly)
			$args['post_status'] = array('publish', 'private');
		return \LWS\WOOREWARDS\Collections\Pools::instanciate()->load($args);
	}

	static function getInstalledPool($_pool=false)
	{
		static $save = false;
		if ($_pool)
			$save = $_pool;
		return $save;
	}

	/** Should be called inside an admin_notice hook.
	 * Call only if a premium was installed but plugin downgraded to free. */
	function testPoolCompletion()
	{
		require_once LWS_WOOREWARDS_INCLUDES . '/updater.php';
		if (\LWS\WOOREWARDS\Updater::isMissingPrefabEventsAndUnlockables()) {
			$content = __("We detect a MyRewards licence downgrade: Click here to restore missing configuration.", 'woorewards-lite');
			$button = esc_attr(__("Restore", 'woorewards-lite'));
			$formId = 'lws-wr-missing-form';

			echo "<div class='notice notice-error lws-adminpanel-notice is-dismissible'><p>";
			echo "<form id='$formId' name='$formId' method='post'>";
			\wp_nonce_field($formId, 'lws-wr-missing-nonce', true, true);
			echo "<input type='hidden' name='lws-wr-missing-restore' value='restore'/>" . $content;
			echo "<input type='submit' value='$button' class='lws-adm-btn lws-wr-missing-restore-submit'/>";
			echo "</form></p></div>";
		}
	}

	/** is pool completion requested, then do it @see \LWS\WOOREWARDS\Updater::addMissingPrefabEventsAndUnlockables */
	function parseQueryForPoolCompletion()
	{
		$formId = 'lws-wr-missing-form';
		if (
			isset($_POST['lws-wr-missing-restore'])
			&& trim($_POST['lws-wr-missing-restore']) == 'restore'
			&& \check_admin_referer($formId, 'lws-wr-missing-nonce')
			&& \wp_verify_nonce($_POST['lws-wr-missing-nonce'], $formId)
		) {
			require_once LWS_WOOREWARDS_INCLUDES . '/updater.php';
			\LWS\WOOREWARDS\Updater::addMissingPrefabEventsAndUnlockables();
		}
	}

	/**	Display an achievement on the page (backend or frontend).
	 *	$param options (array)
	 *	or an array for custom achievement widget with:
	 *	* 'title' (string) At least a title is required for custom achievement.
	 *	* 'message' (string) optional, display a custom achievement with that message.
	 *	* 'image' (url) Achievement icon, if no url given, a default image is picked.
	 *	*	'user' (int) recipient user id.
	 *	* 'origin' (mixed, optional) source of the achievement. */
	public static function achievement($options = array())
	{
		require_once LWS_WOOREWARDS_INCLUDES . '/ui/achievement.php';
		\LWS\WOOREWARDS\Ui\Achievement::push($options);
	}

	/** That usefull function exists since 5.3
	 * But we keep a 4.9 compatibility. */
	static function getSiteTimezone()
	{
		if (function_exists('wp_timezone'))
			return \wp_timezone();
		else
			return new \DateTimeZone(self::getSiteTimezoneString());
	}

	/** That usefull function exists since 5.3
	 * But we keep a 4.9 compatibility. */
	static function getSiteTimezoneString()
	{
		if (function_exists('wp_timezone_string'))
			return \wp_timezone_string();

		$timezone_string = get_option('timezone_string');

		if ($timezone_string) {
			return $timezone_string;
		}

		$offset  = (float) get_option('gmt_offset');
		$hours   = (int) $offset;
		$minutes = ($offset - $hours);

		$sign      = ($offset < 0) ? '-' : '+';
		$abs_hour  = abs($hours);
		$abs_mins  = abs($minutes * 60);
		$tz_offset = sprintf('%s%02d:%02d', $sign, $abs_hour, $abs_mins);

		return $tz_offset;
	}

	function getTrialEndMessage($msg, $slug, $date, $link)
	{
		if ('woorewards' != $slug)
			return $msg;
		$msg  = "<h2>" . __('Your MyRewards Premium trial period is about to expire', 'woorewards-lite') . "</h2>";
		$msg .= "<p>" . sprintf(__('Thank you for trying MyRewards Premium. The trial period will end on <b>%s</b>', 'woorewards-lite'), $date) . "</p>";
		$msg .= "<h4><b>" . sprintf(__('If you want to keep using all Pro Features, please purchase a %s License', 'woorewards-lite'), $link) . "</b></h4>";
		$msg .= "<p>" . __('Premium Version features include :', 'woorewards-lite') . "</p>";
		$msg .= "<ul style='list-style-type:square;padding-left:20px;'><li>" . __('Referrals', 'woorewards-lite') . "</li>";
		$msg .= "<li>" . __('Free products rewards', 'woorewards-lite') . "</li>";
		$msg .= "<li>" . __('WooCommerce integration tools', 'woorewards-lite') . "</li>";
		$msg .= "<li>" . __('Multiple points and rewards systems', 'woorewards-lite') . "</li>";
		$msg .= "<li>" . __('Lots of widgets and shortcodes', 'woorewards-lite') . "</li>";
		$msg .= "<li>" . __('And a lot more ...', 'woorewards-lite') . "</li>";
		$msg .= "</ul>";
		$msg .= "<p>" . __('Purchase WooRewads Premium today to keep the most powerful and customizable loyalty program', 'woorewards-lite') . "</p>";
		return $msg;
	}

	function getTrialStartMessage($msg, $slug, $date)
	{
		if ('woorewards' != $slug)
			return $msg;
		$msg  = "<h2>" . __('Welcome to your MyRewards Premium trial', 'woorewards-lite') . "</h2>";
		$msg .= "<p>" . sprintf(__('Thank you for trying MyRewards Premium. The trial period will end on <b>%s</b>', 'woorewards-lite'), $date) . "</p>";
		$msg .= "<p>" . __('Premium Version features include :', 'woorewards-lite') . "</p>";
		$msg .= "<ul style='list-style-type:square;padding-left:20px;'><li>" . __('Referrals', 'woorewards-lite') . "</li>";
		$msg .= "<li>" . __('Free products rewards', 'woorewards-lite') . "</li>";
		$msg .= "<li>" . __('WooCommerce integration tools', 'woorewards-lite') . "</li>";
		$msg .= "<li>" . __('Multiple points and rewards systems', 'woorewards-lite') . "</li>";
		$msg .= "<li>" . __('Lots of widgets and shortcodes', 'woorewards-lite') . "</li>";
		$msg .= "<li>" . __('And a lot more ...', 'woorewards-lite') . "</li>";
		$msg .= "</ul>";
		$msg .= "<p>" . __('Try all these premium features and create the perfect loyalty program for your website', 'woorewards-lite') . "</p>";
		$msg .= "<h4><b>" . __('At the end of your trial, consider purchasing a MyRewards License', 'woorewards-lite') . "</b></h4>";
		return $msg;
	}
}

LWS_WooRewards::init(); {
	if (\file_exists($asset = (dirname(__FILE__) . '/assets/lws-adminpanel/lws-adminpanel.php')))
		include_once $asset;
	if (\file_exists($asset = (dirname(__FILE__) . '/modules/woorewards-pro/woorewards-pro.php')))
		include_once $asset;
}