<?php
namespace LWS\Adminpanel;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Real plugin implementation. */
class Admin
{
	private $version = '1';
	private $page = null;

	static function instanciate($version, $mainFile)
	{
		$me = new self($version, $mainFile);
	}

	private function __construct($version, $mainFile)
	{
		$this->defineConstants($version, $mainFile);

		if (\is_admin()) {
			\add_filter('lws_format_copypast', function($text){
				return "<span class='lws-group-descr-copy lws_ui_value_copy'><span class='lws-group-descr-copy-text content' tabindex='0'>{$text}</span><span class='lws-group-descr-copy-icon lws-icon lws-icon-copy copy'></span></span>";
			});

			/// when user cannot dismiss a notice (other plugin crash JS), let show the button as a form submit.
			/// So add URL argument &lws_adminpanel_notice_dismiss_force_reload=yes
			if (isset($_GET, $_GET['lws_adminpanel_notice_dismiss_force_reload']) && \current_user_can('manage_options')) {
				$noticeMode = \sanitize_key($_GET['lws_adminpanel_notice_dismiss_force_reload']);
				if ('yes' == $noticeMode)
					\update_option('lws_adminpanel_notice_dismiss_force_reload', 'on');
				elseif ('no' == $noticeMode)
					\update_option('lws_adminpanel_notice_dismiss_force_reload', '');
			}
		}

		\add_action('after_setup_theme', function() {
			\load_plugin_textdomain('lws-adminpanel', FALSE, \basename(dirname(LWS_ADMIN_PANEL_FILE)) . '/languages/');
		});

		$this->install();

		if (\is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
			\add_action('after_setup_theme', array($this, 'registerAdminPages'), 15);
		}

		if (\is_admin() && !(defined('DOING_AJAX') && DOING_AJAX)) {
			\add_action('in_admin_header', array($this, 'adminPageHeader'));
			\add_filter('admin_body_class', array($this, 'adminBodyClass'));
		}

		$this->registerPlugins();

		if (\is_admin() && !(defined('DOING_AJAX') && DOING_AJAX)) {
			\add_action('init', array($this, 'wizard'), 11); // need to be still time to redirect if needed, but let WC go first
		}
	}

	public function v()
	{
		return $this->version;
	}

	/**
	 * Define the plugin constants
	 *
	 * @return void
	 */
	private function defineConstants($version, $mainFile)
	{
		define('LWS_ADMIN_PANEL_VERSION', $version );
		define('LWS_ADMIN_PANEL_FILE',    $mainFile);
		define('LWS_ADMIN_PANEL_DOMAIN', 'lws-adminpanel');

		define('LWS_ADMIN_PANEL_PATH',     \dirname(LWS_ADMIN_PANEL_FILE));
		define('LWS_ADMIN_PANEL_INCLUDES', LWS_ADMIN_PANEL_PATH . '/include');
		define('LWS_ADMIN_PANEL_SNIPPETS', LWS_ADMIN_PANEL_PATH . '/snippets');
		define('LWS_ADMIN_PANEL_ASSETS',   LWS_ADMIN_PANEL_PATH . '/assets');

		define('LWS_ADMIN_PANEL_URL', \plugins_url('', LWS_ADMIN_PANEL_FILE));
		define('LWS_ADMIN_PANEL_JS',  \plugins_url('/js', LWS_ADMIN_PANEL_FILE));
		define('LWS_ADMIN_PANEL_CSS', \plugins_url('/styling/css', LWS_ADMIN_PANEL_FILE));

		define('LWS_WIZARD_SUMMONER', 'lwswizard-');
	}

	private function isAdminPage()
	{
		if (\is_null($this->page)) {
			$this->page = false;
			if( function_exists('\get_current_screen') && !empty($screen = \get_current_screen())
			&& !empty($bars = \apply_filters('lws_adminpanel_topbars', array())) )
			{
				foreach( $bars as $id => $settings )
				{
					if( isset($settings['exact_id']) && $settings['exact_id'] )
						$isId = $screen->id === $id;
					else
						$isId = (false !== strpos($screen->id, $id));

					if( $isId )
						$this->page = (object)['id'=>$id, 'settings'=>$settings];
				}
			}
		}
		return $this->page;
	}

	/** allow display our topbar on any admin page.
	 * use filter 'lws_adminpanel_topbars' expect an array with
	 * key is at least a relevant part of screen id.
	 * value is an array with items:
	 * * exact_id if set and true, look for a perfect match between screen id and key.
	 * * for the rest of options @see \LWS\Adminpanel\Pages\Page::echoTopBar */
	function adminPageHeader()
	{
		if( $page = $this->isAdminPage() )
		{
			\wp_enqueue_style('lws-wp-override');
			\wp_enqueue_style('lws-admin-interface');
			\wp_enqueue_style('lws-admin-controls');
			\wp_enqueue_style('lws-editlist');
			\wp_enqueue_script('lws-admin-interface');
			\wp_enqueue_style('lws-notices');
			//~ \LWS\Adminpanel\Pages\Page::runNoticeGrabber();
			//~ \LWS\Adminpanel\Pages\Head::echoExternal($page->id, $page->settings);
		}
	}

	/** CSS classes of the body balise for our own pages.
	 * Our pages are those with our topbar, added via one of those
	 * * filter 'lws_adminpanel_topbars'
	 * * \lws_register_pages() */
	function adminBodyClass($classes)
	{
		if( $this->isAdminPage() )
			$classes .= ' lws-adminpanel-body';
		return $classes;
	}

	private function install()
	{
		spl_autoload_register(array($this, 'autoload'));

		require_once LWS_ADMIN_PANEL_INCLUDES . '/internal/mailer.php';
		\LWS\Adminpanel\Internal\Mailer::instance();
		require_once LWS_ADMIN_PANEL_INCLUDES . '/internal/ajax.php';
		\LWS\Adminpanel\Internal\Ajax::install();
		require_once LWS_ADMIN_PANEL_INCLUDES . '/internal/menuitems.php';
		require_once LWS_ADMIN_PANEL_INCLUDES . '/internal/menushortcode.php';
		\LWS\Adminpanel\Internal\MenuShortcode::register();
		\LWS\Adminpanel\Internal\MenuItems::install();

		require_once LWS_ADMIN_PANEL_INCLUDES . '/tools/pseudocss.php';
		\LWS\Adminpanel\Tools\PseudoCss::install();
		require_once LWS_ADMIN_PANEL_INCLUDES . '/tools/argparser.php';
		\LWS\Adminpanel\Tools\ArgParser::install();

		\add_action('woocommerce_blocks_loaded', function(){
			require_once LWS_ADMIN_PANEL_INCLUDES . '/internal/wc/cartnotice.php';
			\LWS\Adminpanel\Internal\WC\CartNotice::get()->install();
		}, 5);

		// obviously used
		require_once LWS_ADMIN_PANEL_INCLUDES . '/tools/conveniences.php';
		require_once LWS_ADMIN_PANEL_INCLUDES . '/tools/request.php';
		require_once LWS_ADMIN_PANEL_INCLUDES . '/tools/duration.php';
		require_once LWS_ADMIN_PANEL_INCLUDES . '/tools/shortcode.php';
		require_once LWS_ADMIN_PANEL_INCLUDES . '/tools/expression.php';
		\LWS\Adminpanel\Tools\Expression::install();

		if( !(defined('DOING_AJAX') && DOING_AJAX) )
		{
			if( \is_admin() )
				\add_action('admin_enqueue_scripts', array($this, 'registerScripts'), 0, 1);
			else
				\add_action('wp_enqueue_scripts', array($this, 'registerScripts'), 0);

			\add_action('admin_notices', array($this,'notices'));
			\add_action('admin_enqueue_scripts', array($this, 'enqueueScripts'));
		}
	}

	/** autoload abstract classes in AdminPanel namespace. */
	public function autoload($class)
	{
		if (substr($class, 0, 15) != 'LWS\Adminpanel\\')
			return;

		$path = array_filter(explode('\\', substr($class, 15)));
		if ($path) {
			static $legacy = array(
				'EditList'  => 'legacy/editlist',
				'Duration'  => 'legacy/duration',
				'Wizard'    => 'legacy/wizard',
				'Request'   => 'legacy/request',
			);
			static $publicNamespaces = array(
				'EditList' => true,
				'Pages'    => true,
				'Tools'    => true,
			);

			$filepath = false;
			$rest = implode('/', $path);
			if( isset($legacy[$rest]) ) {
				$filepath = $legacy[$rest];
			} else if( isset($publicNamespaces[\reset($path)]) ) {
				if (count($path) > 1)
					$filepath = strtolower($rest);
			}

			if ($filepath) {
				$filepath = LWS_ADMIN_PANEL_INCLUDES . '/' . $filepath . '.php';
				include_once $filepath;
				return true;
			}
		}
	}

	function registerScripts($hook='')
	{
		/* Styles */
		\wp_register_style('lws-icons',           LWS_ADMIN_PANEL_CSS . '/lws_icons.css',          array(), LWS_ADMIN_PANEL_VERSION);
		\wp_register_style('lws-notices',         LWS_ADMIN_PANEL_CSS . '/lws-notices.min.css',    array(), LWS_ADMIN_PANEL_VERSION);
		\wp_register_style('lws-wp-override',     LWS_ADMIN_PANEL_CSS .'/wp-override.min.css',     array(), LWS_ADMIN_PANEL_VERSION);
		\wp_register_style('lws-admin-interface', LWS_ADMIN_PANEL_CSS .'/admin-interface.min.css', array(), LWS_ADMIN_PANEL_VERSION);
		\wp_register_style('lws-admin-page',      LWS_ADMIN_PANEL_CSS . '/admin-page.min.css',     array(), LWS_ADMIN_PANEL_VERSION);
		\wp_register_style('lws-singular-page',   LWS_ADMIN_PANEL_CSS .'/singular-page.min.css',   array(), LWS_ADMIN_PANEL_VERSION);
		\wp_register_style('lws-resume-page',     LWS_ADMIN_PANEL_CSS . '/resume-page.min.css',    array(), LWS_ADMIN_PANEL_VERSION);
		\wp_register_style('lws-admin-controls',  LWS_ADMIN_PANEL_CSS .'/admin-controls.min.css',  array(), LWS_ADMIN_PANEL_VERSION);
		\wp_register_style('lws-adminpanel-css',  LWS_ADMIN_PANEL_CSS .'/admin-general.css',       array(), LWS_ADMIN_PANEL_VERSION);
		\wp_register_style('lws-editlist',        LWS_ADMIN_PANEL_CSS . '/editlist.min.css',       array(), LWS_ADMIN_PANEL_VERSION);
		\wp_register_style('lws-popup',           LWS_ADMIN_PANEL_CSS . '/controls/lwsdial.min.css', array(), LWS_ADMIN_PANEL_VERSION);

		/* Scripts */
		\wp_register_script('lws-base64',            LWS_ADMIN_PANEL_JS . '/tools/objcvt.js', array(), LWS_ADMIN_PANEL_VERSION);
		\wp_register_script('lws-tools',             LWS_ADMIN_PANEL_JS . '/tools/tools.js',  array('jquery'), LWS_ADMIN_PANEL_VERSION);
		\wp_localize_script('lws-tools', 'lws_ajax', array('url' => admin_url('/admin-ajax.php'),));
		\wp_register_script('lws-md5',               LWS_ADMIN_PANEL_JS . '/resources/jquery.md5.js',      array('jquery'), LWS_ADMIN_PANEL_VERSION);
		\wp_register_script('lws-radio',             LWS_ADMIN_PANEL_JS . '/controls/radio.js',            array('jquery', 'jquery-ui-widget'), LWS_ADMIN_PANEL_VERSION, true);
		\wp_register_script('lws-icon-picker',       LWS_ADMIN_PANEL_JS . '/controls/iconpicker.js',       array('jquery'), LWS_ADMIN_PANEL_VERSION, true);
		\wp_register_script('lws-field-validation',  LWS_ADMIN_PANEL_JS . '/controls/fieldvalidation.js',  array('jquery', 'jquery-ui-widget'), LWS_ADMIN_PANEL_VERSION, true);
		\wp_register_script('lws-checkgrid',         LWS_ADMIN_PANEL_JS . '/controls/checkgrid.js',        array('jquery', 'jquery-ui-core', 'jquery-ui-mouse', 'jquery-ui-draggable', 'jquery-ui-droppable'), LWS_ADMIN_PANEL_VERSION, true);
		\wp_register_script('lws-popup',             LWS_ADMIN_PANEL_JS . '/controls/lwsdial.js',            array('jquery', 'jquery-ui-widget'), LWS_ADMIN_PANEL_VERSION);
		\wp_register_script('lws-admin-interface',   LWS_ADMIN_PANEL_JS . '/interface/admin-interface.js', array('jquery', 'lws-tools', 'lws-md5'), LWS_ADMIN_PANEL_VERSION, true);
		\wp_localize_script('lws-admin-interface', 'button_texts', array(
			'expand' => __("Expand All", 'lws-adminpanel'),
			'collapse' => __("Collapse All", 'lws-adminpanel'),
		));

		/* Fields */
		\wp_register_script('lws-lac-model',     LWS_ADMIN_PANEL_JS . '/controls/lac/lacmodel.js',     array('jquery', 'jquery-ui-core', 'jquery-ui-widget', 'lws-base64', 'lws-tools'), LWS_ADMIN_PANEL_VERSION, true );
		\wp_localize_script('lws-lac-model', 'lws_lac_model_data', array('number' => \wp_create_nonce('lws_lac_model')));
		\wp_register_script('lws-lac-select',    LWS_ADMIN_PANEL_JS . '/controls/lac/lacselect.js',    array('lws-lac-model'), LWS_ADMIN_PANEL_VERSION, true);
		\wp_register_script('lws-lac-checklist', LWS_ADMIN_PANEL_JS . '/controls/lac/lacchecklist.js', array('lws-lac-model'), LWS_ADMIN_PANEL_VERSION, true );
		\wp_register_script('lws-lac-taglist',   LWS_ADMIN_PANEL_JS . '/controls/lac/lactaglist.js',   array('lws-lac-model'), LWS_ADMIN_PANEL_VERSION, true );
		\wp_localize_script('lws-lac-taglist', 'lws_lac_taglist', array('value_unknown' => __("At least one value is unknown.", 'lws-adminpanel')));

		/** enqueue lac scripts, styles and dependencies. @param (array) lac basenames (eg. 'select'). */
		\add_action('lws_adminpanel_enqueue_lac_scripts', function($lacs=array()){
			foreach( array('jquery', 'jquery-ui-core', 'jquery-ui-widget', 'lws-base64', 'lws-tools') as $uid )
				\wp_enqueue_script($uid);
			\wp_enqueue_script('lws-lac-model');
			\wp_enqueue_style('lws-admin-controls');
			foreach($lacs as $lac){
				\wp_enqueue_script('lws-lac-'.$lac);
			}
		}, 10, 1);

		/** assets */
		\wp_register_script('lws-chart-js',  LWS_ADMIN_PANEL_JS . '/resources/chart.js/Chart.min.js', array(), '2.8.0', true );
		\wp_register_style('lws-chart-js',   LWS_ADMIN_PANEL_CSS. '/resources/chart.js/Chart.min.css', array(), '2.8.0');
		\wp_register_script('lws-qrcode-js', LWS_ADMIN_PANEL_JS . '/resources/qrcode.js/qrcode.js', array(), '1.0', true );

		if ('nav-menus.php' == $hook) {
			\wp_enqueue_script('lws-navmenu', LWS_ADMIN_PANEL_JS . '/navmenu.js', array('jquery', 'nav-menu'), LWS_ADMIN_PANEL_VERSION, true);
		}
		\wp_enqueue_style('lws-global', LWS_ADMIN_PANEL_CSS . '/lws.min.css', array(), LWS_ADMIN_PANEL_VERSION);
	}

	/** enqueue on frontend */
	function enqueueScripts()
	{
		\wp_enqueue_style('lws-icons');
	}

	/** Run soon at init hook (5).
	 * include all requirement to use PageAdmin,
	 * declare few usefull global functions,
	 * provide a hook 'lws_adminpanel_register' which should be used
	 * to declare pages. */
	function registerAdminPages()
	{
		/* no exclusion (is_admin() or !defined('DOING_AJAX'))
		 * since plugins must define their editlist (if any) in any case
		 * to be able to answer an ajax request */

		require LWS_ADMIN_PANEL_INCLUDES . '/internal/pages.php';
		require LWS_ADMIN_PANEL_INCLUDES . '/internal/helpers/pages.php';
		require_once LWS_ADMIN_PANEL_INCLUDES . '/pages/field.php';
		require LWS_ADMIN_PANEL_INCLUDES . '/pages/group.php';
		require LWS_ADMIN_PANEL_INCLUDES . '/pages/page.php';

		/** it is where plugins will register pages. */
		do_action('lws_adminpanel_register');
	}

	/** Run soon at init hook (4).
	 * include all requirement to update/activate a plugin,
	 * declare few usefull global functions,
	 * provide a hook 'lws_adminpanel_plugins' which should be used. */
	function registerPlugins()
	{
		require LWS_ADMIN_PANEL_INCLUDES . '/internal/helpers/credits.php';
		\do_action('lws_adminpanel_plugins');
	}

	/** Notice level are notice-error, notice-warning, notice-success, or notice-info.
	 *	Old-school display, hidden on our pages anyway.
	 *	@see wp hook 'admin_notices' */
	function notices()
	{
		require_once LWS_ADMIN_PANEL_INCLUDES . '/pages/notices.php';
		if( $notices = \LWS\Adminpanel\Pages\Notices::instance()->getNotices() )
		{
			\LWS\Adminpanel\Pages\Notices::instance()->enqueueScripts();
			foreach( $notices as $notice )
				echo \LWS\Adminpanel\Pages\Notices::instance()->itemToHTML($notice);
		}
	}

	/** Run a wizard if:
	 * * Admin URL contains argument page with concat of LWS_WIZARD_SUMMONER and wizard slug.
	 * * wizard slug is a key in the array returned by filter 'lws_adminpanel_wizards'
	 * * * the value must be the complete wizard classname (that extends \LWS\Adminpanel\Wizard)
	 * * * the class must be declared @see spl_autoload_register */
	function wizard()
	{
		$slug = false;
		if( isset($_GET['page']) )
		{
			$page = \sanitize_key($_GET['page']);
			if( 0 === strpos($page, LWS_WIZARD_SUMMONER) )
				$slug = substr($page, strlen(LWS_WIZARD_SUMMONER));
		}

		if( $slug )
		{
			$wizards = \apply_filters('lws_adminpanel_wizards', array());
			if( isset($wizards[$slug]) )
			{
				$wizard = new $wizards[$slug]($slug);
				$wizard->getData(); // some magic must be done now, before it is too late (redirect before header done)
			}
		}
	}
}
