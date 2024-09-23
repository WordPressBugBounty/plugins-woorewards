<?php
namespace LWS\Adminpanel\Pages;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/**	The first class to manage frontend page.
 *	Need WooCommerce.
 *	Add a new tab in WC My-acconut.
 *	Content is a normal page.
 *  */
class Endpoint
{
	protected $options = array();
	private $elementorFilters = array();

	/** Override this method or set the same array to constructor */
	protected function getDefaultOptions()
	{
		return array(
			'prefix'  => '', /// not empty string, used for options names.
			'title'   => '', /// not empty string (human readable).
			'slug'    => '', /// not empty string (url).
			'enable'  => true, /// tab activated by default.
			'wpml'    => false, /// string with first letter case up.
			'content' => false, /// string (content text) or callable (returning a string), to use as default page content.
		);
	}

	/** Add the page in WooCommerce MyAccount */
	public static function install($options=false)
	{
		$me = new static($options);
		$slug = $me->getSlug();

		if ($me->isEnabled()) {
			\add_action('init', array($me, 'addRewriteRules'), 11);

			if ($me->currentUserCan()) {
				// register endpoint in WC my-account
				\add_filter('woocommerce_account_menu_items', array($me, 'getWCMyAccountTabs'));
				\add_filter('woocommerce_get_query_vars', array($me, 'getQueryVars'));

				// behavior in WC my-account
				\add_action('woocommerce_account_' . $slug . '_endpoint', array($me, 'printPage'));
				\add_filter('woocommerce_endpoint_' . $slug . '_title', array($me, 'getTitle'));
			}
		}

		// option update should force a rewrite rules
		\add_filter('pre_update_option_' . $me->options['prefix'] . '_slug', array($me, 'forceRules'), 10, 3);
		if ($me->isEnabled() && !\get_option($me->options['prefix'] . '_rflush')) {
			\add_action('shutdown', array($me, 'rewriteRules'));
		}
	}

	/** Get the settings fields to add in Admin page. */
	public static function getAdminFields($options=false, $fields=array())
	{
		$me = new static($options);

		if ($me->isEnabled() && !$me->hasPage()) {
			$me->_createPage();
		}

		$roles = array();
		foreach (\wp_roles()->get_names() as $value => $label) {
			$roles[] = array('value' => $value, 'label' => $label);
		}

		$fields['enable'] = array(
			'id'    => $me->options['prefix'] . '_enable',
			'title' => __("Enable", 'lws-adminpanel'),
			'type'  => 'box',
			'extra' => array(
				'layout'  => 'toggle',
				'default' => $me->options['enable'] ? 'on' : '',
			)
		);
		$fields['title']  = array(
			'id'    => $me->options['prefix'] . '_title',
			'title' => __("Tab title", 'lws-adminpanel'),
			'type'  => 'text',
			'extra' => array(
				'placeholder' => $me->options['title'],
				'wpml'        => $me->options['wpml'],
			)
		);
		$fields['slug']  = array(
			'id'    => $me->options['prefix'] . '_slug',
			'title' => __("Slug", 'lws-adminpanel'),
			'type'  => 'text',
			'extra' => array(
				'placeholder' => $me->options['slug'],
			)
		);
		$fields['roles']  = array(
			'id'    => $me->options['prefix'] . '_role',
			'title' => __("Role restriction", 'lws-adminpanel'),
			'type'  => 'lacchecklist',
			'extra' => array(
				'help'   => __("Restrict this tab to given roles. No roles selected means no restriction at all.", 'lws-adminpanel'),
				'source' => $roles,
			)
		);
		$fields['page'] = array(
			'id'    => $me->options['prefix'] . '_page',
			'title' => __("Content page", 'lws-adminpanel'),
			'type'  => 'lacselect',
			'extra' => array(
				'id'         => $me->options['prefix'] . '_page',
				'predefined' => 'page',
				'maxwidth'   => '22em',
			)
		);

		$pageId = \intval(\get_option($me->options['prefix'] . '_page'));
		if ($pageId) {
			$fields['see'] = array(
				'id'    => 'lws_adminpanel_myaccount_page_edit',
				'type'  => 'custom',
				'title' => __("Content Edition", 'lws-adminpanel'),
				'extra' => array(
					'content' => function()use($me, $pageId){
						$href = \get_edit_post_link($pageId, 'raw');
						if ($href) {
							return sprintf(
								'<a target="_blank" class="lws-adm-btn big" href="%s">%s</a>',
								\esc_attr($href),
								__('Edit Page', 'lws-adminpanel')
							);
						} else {
							return sprintf(
								'<strong id="%s" class="lws-warning">%s</strong>',
								$me->options['prefix'] . '_warning',
								__('Selected Page not found', 'lws-adminpanel')
							);
						}
					},
				)
			);
		}

		if ($me->options['content'] && \current_user_can('edit_pages') && \current_user_can('publish_pages')) {
			$fields['create'] = array(
				'id'    => $me->options['prefix'] . '_create',
				'title' => __("Default Content", 'lws-adminpanel'),
				'type'  => 'button',
				'extra' => array(
					'text' => __("Create a new default page", 'lws-adminpanel'),
					'callback' => array($me, 'createPage'),
				)
			);
		}

		return $fields;
	}

	function createPage($btnId, $data=array())
	{
		if( $btnId != ($this->options['prefix'] . '_create') ) return false;
		return $this->_createPage();
	}

	function hasPage()
	{
		$pageId = \intval(\get_option($this->options['prefix'] . '_page'));
		if (!$pageId)
			return false;
		$post = \get_post($pageId);
		return $post && $post->ID;
	}

	function _createPage()
	{
		$pageId = \wp_insert_post(array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => \lws_get_option($this->options['prefix'] . '_title', $this->options['title']),
			'post_content' => $this->getDefaultContent(),
		), false);

		if ($pageId) {
			\update_option($this->options['prefix'] . '_page', $pageId);
			$href = \esc_attr(\get_edit_post_link($pageId, 'raw'));
			$link = __('Edit the page', 'lws-adminpanel');
			$text = __("Page created and setup as tab.", 'lws-adminpanel');
		//var pageElt = document.getElementById('{$eltId}');
		//pageElt.value = {$pageId};
		//pageElt.dispatchEvent(new Event('change'));
			return <<<EOT
	<script type='text/javascript'>
		jQuery( function($) {
			$('#{$this->options['prefix']}_page').val('{$pageId}').trigger('change');
			$('#{$this->options['prefix']}_warning').remove();
		});
	</script>
	<div class='notice notice-success'>
		<p>
			{$text}<br/>
			<a href='{$href}'>{$link}</a>
		</p>
	</div>
EOT;
		} else {
			return sprintf('<div class="notice notice-error"><p>%s</p></div>', __("An error occured during page creation.", 'lws-adminpanel'));
		}
	}

	protected function getDefaultContent()
	{
		if (!isset($this->options['content']))
			return '';
		elseif (!$this->options['content'])
			return '';
		elseif (\is_string($this->options['content'])) {
			/** @var string $this->options['content'] */
			return $this->options['content'];
		} elseif (\is_callable($this->options['content'])) {
			/** @var callable $this->options['content'] */
			return \call_user_func($this->options['content']);
		} else
			return '';
	}

	public function forceRules($value, $oldValue, $option)
	{
		\update_option($this->options['prefix'] . '_rflush', false);
		return $value;
	}

	public function rewriteRules()
	{
		$this->addRewriteRules();
		\flush_rewrite_rules(true);
		\update_option($this->options['prefix'] . '_rflush', \time());
	}

	public function addRewriteRules()
	{
		\add_rewrite_endpoint($this->getSlug(), EP_ROOT|EP_PAGES);
	}

	/**	@param $options (array) the endpoint definition
	 *	* 'key' (string) url endpoint slug
	 *	* 'title' page title or my-account tab title if WC activated. */
	function __construct($options=false)
	{
		$this->options = $options ? $options : $this->getDefaultOptions();
	}

	/** Add our tab key to WC Query Vars */
	function getQueryVars($vars)
	{
		$vars[$this->getSlug()] = $this->getSlug();
		return $vars;
	}

	function getSlug()
	{
		$slug = \get_option($this->options['prefix'] . '_slug', null);
		if (null === $slug) {
			// quicker having recorded in DB than running all path until default fallback each time.
			\update_option($this->options['prefix'] . '_slug', '');
		}
		return ($slug ? $slug : $this->options['slug']);
	}

	function isEnabled()
	{
		$enable = \get_option($this->options['prefix'] . '_enable', null);
		if (null === $enable) {
			$enable = $this->options['enable'];
			// quicker having recorded in DB than running all path until default fallback each time.
			\update_option($this->options['prefix'] . '_enable', $enable ? 'on' : '');
		}
		return $enable;
	}

	function currentUserCan()
	{
		$roles = \get_option($this->options['prefix'] . '_role', null);
		if (null === $roles) {
			// quicker having recorded in DB than running all path until default fallback each time.
			\update_option($this->options['prefix'] . '_role', '');
		}

		if ($roles && \is_array($roles)) {
			$user = \wp_get_current_user();
			if ($user && $user->exists()) {
				return (bool)\array_intersect($user->roles, $roles);
			} else {
				return false;
			}
		}
		return true;
	}

	/** @return string WC my-account tab */
	function getTitle($title)
	{
		$title = \get_option($this->options['prefix'] . '_title', null);
		if (null === $title) {
			// quicker having recorded in DB than running all path until default fallback each time.
			\update_option($this->options['prefix'] . '_title', '');
		}
		if (!$title)
			$title = $this->options['title'];
		if ($wpml = $this->getWPML())
			$title = \apply_filters('wpml_translate_single_string', $title, 'Widgets', $wpml);
		return $title;
	}

	/** get translation key */
	function getWPML()
	{
		return isset($this->options['wpml']) ? \ucfirst($this->options['wpml']) : false;
	}

	/** @return array WC my-account tab list including our. */
	function getWCMyAccountTabs($items=array())
	{
		$tab = array($this->getSlug() => $this->getTitle(''));
		if( empty($items) )
			$items = $tab;
		else // insert at penultimate place (-1), since last is usually "log off".
			$items = array_slice($items, 0, -1, true) + $tab + array_slice($items, -1, NULL, true);
		return $items;
	}

	/** echo the page for WC my-account page */
	function printPage()
	{
		if ($this->isEnabled() && !$this->hasPage()) {
			$this->_createPage();
		}

		$postId = \get_option($this->options['prefix'] . '_page');
		if ($postId) {
			$content = \get_the_content(null, false, $postId);
			$this->muteElementor();
			$content = \apply_filters('the_content', $content);
			$this->restoreElementor();
			$content = \str_replace(']]>', ']]&gt;', $content);
			echo $content;
		}
	}

	/** Elementor reacts to 'the_content' even if already
	 *	inside an Elementor template.
	 *	This produce a duplication of WC my-account nav bar.
	 *	So we remove elementor filters.
	 *	@see restoreElementor() */
	private function muteElementor()
	{
		$this->elementorFilters = array();
		global $wp_filter;
		if (isset($wp_filter['the_content'])) {
			foreach ($wp_filter['the_content']->callbacks as $priority => $filters) {
				foreach ($filters as $callback) {
					if (\is_array($callback['function']) && \is_object($callback['function'][0])) {
						if (false !== \strpos(\strtolower(\get_class($callback['function'][0])), 'elementor')) {
							$this->elementorFilters[] = array(
								'function' => $callback['function'],
								'accepted_args' => $callback['accepted_args'],
								'priority' => $priority,
							);
						}
					}
				}
			}
		}
		foreach ($this->elementorFilters as $filter) {
			\remove_filter('the_content', $filter['function'], $filter['priority']);
		}
	}

	/** @see muteElementor() */
	private function restoreElementor()
	{
		if ($this->elementorFilters) {
			foreach ($this->elementorFilters as $filter) {
				\add_filter('the_content', $filter['function'], $filter['priority'], $filter['accepted_args']);
			}
		}
	}
}
