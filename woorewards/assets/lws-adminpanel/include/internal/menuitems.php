<?php
namespace LWS\Adminpanel\Internal;
if( !defined( 'ABSPATH' ) ) exit();

/** At least, add a menu item that manage shortcodes. */
class MenuItems
{
	const MENU_ID = 'lws-menuitems';
	const MENU_TYPE = 'lws-admpnl-menuitem';

	protected $slug = '';
	protected $extraClass = 'lws_input_extra_data';

	protected function getContent($content, $item, $depth, $args)
	{
		return $content;
	}

	/** default is able to hide menu item based on class and logged status of visitor. */
	protected function isVisible($item)
	{
		if ($item->classes && \in_array('lws-admpnl-logged-only', $item->classes)) {
			if (!\get_current_user_id())
				return false;
		} elseif ($item->classes && \in_array('lws-admpnl-guest-only', $item->classes)) {
			if (\get_current_user_id())
				return false;
		}
		return true;
	}

	/** To add classes in item or change content ($item->post_title)... */
	protected function finalizeSetup(&$item, $meta, $subtype='')
	{
	}

	/** Add input or text in metabox */
	protected function getForm($index, $inputClass)
	{
		return '';
	}

	/** List of meta to read as array of [key => (bool)must_be_saved]
	 *	Meta associated with a FALSE are read during creation but not saved. */
	protected function acceptedMeta()
	{
		return array();
	}

	/** Clean meta value during creation. */
	protected function sanitizeMeta($value, $key)
	{
		return \trim(\wp_unslash($value));
	}

	/** if several choice (radio) are available in metabox.
	 *	See posts or categories menu item metabox for exemple. */
	protected function hasSelectAll($yes)
	{
		return $yes;
	}

	/** Bloc title in admin page
	 *	@parem $item (false|object) if false, title of the metabox,
	 *	else the title of the menu graph element. */
	protected function getTitle($item=false)
	{
		return __('Custom item', 'lws-adminpanel');
	}

	/** How to embed our menu item. */
	protected function getSurrounding($item)
	{
		return array('<span class="lws-admpnl-item-content">', '</span>');
	}

	/** meta are stored in input uses the css class 'lws_input_extra_data' */
	protected function getMeta($item, $key)
	{
		$meta = \get_post_meta($item->ID, 'menu-item-lws-meta', true);
		if ($meta && isset($meta[$key])) {
			return $meta[$key];
		} else {
			return false;
		}
	}

	static public function install()
	{
		$me = new self();
		// add menu item creation entry
		\add_action('admin_init', function() {
			foreach (\LWS\Adminpanel\Internal\MenuItems::getInstances() as $slug => $instance) {
				\add_meta_box(
					'add-menu-section-' . $slug,
					$instance->getTitle(),
					array($instance, 'echoMetabox'),
					'nav-menus', 'side', 'low'
				);
			}
			// grab our menu item
			\add_action('wp_add_nav_menu_item', array('\LWS\Adminpanel\Internal\MenuItems', 'saveItemType'), 10, 3);
			\add_action('wp_update_nav_menu_item', array('\LWS\Adminpanel\Internal\MenuItems', 'updateItem'), 10, 3);
			// change item box in admin setup
			\add_filter('wp_setup_nav_menu_item', array('\LWS\Adminpanel\Internal\MenuItems', 'updateSetupNavItem'), 10, 1);
			//\add_action('wp_nav_menu_item_custom_fields_customize_template', array('\LWS\Adminpanel\Internal\MenuItems', 'editionTemplate'));
		});
		\add_action('wp', function() {
			// filter out items
			\add_filter('wp_nav_menu_objects', array('\LWS\Adminpanel\Internal\MenuItems', 'filterItems'), 10, 2);
			// replace our item content
			\add_filter('walker_nav_menu_start_el', array('\LWS\Adminpanel\Internal\MenuItems', 'updateContent'), 10, 4);
		});
	}

	public static function updateContent($content, $item, $depth, $args)
	{
		$instance = self::findInstance($item);
		if ($instance) {
			$surrounding = $instance->getSurrounding($item);
			if ($surrounding) {
				$content = \preg_replace(array('@^<a>@i', '@</a>$@i'), $surrounding, $content);
			}
			$content = \do_shortcode($instance->getContent($content, $item, $depth, $args));
		}
		return $content;
	}

	private static function getInstances()
	{
		static $instances = false;
		if (false === $instances) {
			$instances = \apply_filters('lws_admimpanel_menuitem_types', array());
			foreach ($instances as $slug => &$instance)
				$instance->slug = $slug;
		}
		return $instances;
	}

	private static function findInstance($slug)
	{
		if (!$slug)
			return false;
		if (\is_object($slug)) {
			$len = 1 + \strlen(self::MENU_TYPE);
			if ((self::MENU_TYPE . '-') != \substr($slug->type, 0, $len))
				return false;
			$slug = substr($slug->type, $len);
		}
		$instances = self::getInstances();
		return isset($instances[$slug]) ? $instances[(string)$slug] : false;
	}

	/** used in metabox */
	protected function getId()
	{
		return (self::MENU_ID . '-' . $this->slug);
	}

	/** to recognize our menu items */
	protected function getType()
	{
		return (self::MENU_TYPE . '-' . $this->slug);
	}

	/** for information */
	protected function getSlug()
	{
		return $this->slug;
	}

	/**	New nav menu item added.
	 *	Let's keep our data since WP always want to override it. */
	public static function saveItemType($menuId, $menuItemDbId, $args)
	{
		if (self::hasClass($args)) {
			$subtype = '';
			if (isset($args['menu-item-object']))
				$subtype = \sanitize_key($args['menu-item-object']);
			if ('custom' != $subtype) {
				\update_post_meta($menuItemDbId, '_menu_item_subtype', $subtype);
			}
		}
	}

	/** Override menu setup. */
	public static function updateItem($menuId, $menuItemDbId, $args)
	{
		if (self::hasClass($args)) {
			$item = \get_post($menuItemDbId);
			if ($item) {
				$subtype = \get_post_meta($menuItemDbId, '_menu_item_subtype', true);
				$data = self::getPost($subtype);
				if ($data) {
					// complete properties
					$item->title       = $item->post_title;
					$item->attr_title  = $item->post_excerpt;
					$item->description = $item->post_content;
					$item->classes     = \get_post_meta($item->ID, '_menu_item_classes', true);
					$item->xfn         = \get_post_meta($item->ID, '_menu_item_xfn', true);
					$item->target      = \get_post_meta($item->ID, '_menu_item_target', true);
					$item->url         = \get_post_meta($item->ID, '_menu_item_url', true);
					// finalize
					$meta = $data->instance->readMeta($data->meta, $item);
					$data->instance->finalizeSetup($item, $meta, $data->subtype);
					// save
					\wp_update_post(array(
						'ID'           => $item->ID,
						'post_title'   => $item->title,
						'post_excerpt' => $item->attr_title,
						'post_content' => $item->description,
						'meta_input' => array(
							'_menu_item_type'    => $data->instance->getType(),
							'_menu_item_classes' => $item->classes,
							'_menu_item_xfn'     => $item->xfn,
							'_menu_item_target'  => $item->target,
							'_menu_item_url'     => $item->url,
							'menu-item-lws-meta' => \array_intersect_key(
								$meta, // save only those flagged to be
								\array_filter($data->instance->acceptedMeta())
							),
						),
					));
				}
			}
		}
	}

	/** read all posted values, keep relevants
	 *	and return those about given item. */
	private static function getPost(string $subtype)
	{
		static $post = false;
		if (false === $post) {
			$post = array();
			if (isset($_POST['menu-item'])) {
				// support only one kind of menu at a time
				foreach ((array)$_POST['menu-item'] as $data) { // WPCS: input var okay, CSRF ok.
					// get menuitems extended instance
					if (!isset($data['menu-item-slug'])) continue;
					$instance = self::findInstance(\sanitize_key($data['menu-item-slug']));
					if (!$instance) continue;
					// get sub selection
					$object = '';
					if (isset($data['menu-item-object'])) {
						$object = \sanitize_key($data['menu-item-object']);
					}
					// keep it
					$post[$object] = (object)array(
						'slug'     => $instance->getSlug(),
						'subtype'  => $object,
						'instance' => $instance,
						'meta'     => isset($data['menu-item-extra']) ? (array)$data['menu-item-extra'] : array(),
					);
				}
			}
		}

		return isset($post[$subtype]) ? $post[$subtype] : false;
	}

	protected function readMeta(array $data, $item)
	{
		$accepted = $this->acceptedMeta();
		$meta = \array_map('__return_false', $accepted);
		if ($item && $item->ID) {
			$read = \get_post_meta($item->ID, 'menu-item-lws-meta', true);
			if ($read && \is_array($read)) {
				$meta = \array_merge($meta, \array_intersect_key($read, $meta));
			}
		}
		foreach ($meta as $key => $ignored) {
			if (isset($data[$key])) {
				$meta[$key] = $this->sanitizeMeta($data[$key], $key);
			}
		}
		return $meta;
	}

	protected static function hasClass($item, $class = self::MENU_TYPE)
	{
		if (\is_object($item)) {
			if (isset($item->classes) && $item->classes && \is_array($item->classes)) {
				return \in_array($class, $item->classes);
			}
		} elseif (\is_array($item) && isset($item['menu-item-classes'])) {
			$classes = \is_array($item['menu-item-classes']) ? $item['menu-item-classes'] : \explode(' ', $item['menu-item-classes']);
			return \in_array($class, $classes);
		}
		return false;
	}

	/** Replace admin display bloc title */
	public static function updateSetupNavItem($item)
	{
		$instance = self::findInstance($item);
		if ($instance) {
			$item->type_label = $instance->getTitle($item);
		}
		return $item;
	}

	/** Hide a menu branch if required */
	public static function filterItems($objects, $args)
	{
		$chunks = \array_combine(\array_column($objects, 'ID'), \array_keys($objects));
		$children = array();
		foreach ($objects as $index => $item) {
			if (!$item->menu_item_parent) continue;
			$p = $item->menu_item_parent;
			if (!isset($chunks[$p])) continue;
			$c = $chunks[$p];
			if (!isset($children[$c]))
				$children[$c] = array();
			$children[$c][] = $index;
		}

		$rem = array();
		foreach ($objects as $id => $item) {
			$instance = self::findInstance($item);
			if ($instance && !$instance->isVisible($item)) {
				self::recursiveMenuFlag($rem, $id, $children);
			}
		}

		foreach (\array_reverse($rem) as $id) {
			unset($objects[$id]);
		}
		return $objects;
	}

	/** Hide all submenu items with a parent. */
	protected static function recursiveMenuFlag(&$rem, $id, $children=array())
	{
		if (!isset($rem[$id])) {
			$rem[$id] = $id;
			if (isset($children[$id])) {
				foreach ($children[$id] as $child)
					self::recursiveMenuFlag($rem, $child, $children);
			}
		}
	}

	/** At least, add custom content menu. */
	public function echoMetabox()
	{
		global $_nav_menu_placeholder;
		$index = (0 > $_nav_menu_placeholder) ? --$_nav_menu_placeholder : -1;
		$texts = array(
			'id'    => \esc_attr($this->getId()),
			'class' => \esc_attr($this->slug),
			'type'  => self::MENU_TYPE,
			'slug'  => \esc_attr($this->slug),
		);

		$choices = $this->getChoices();
		$buttons = $this->getButtons(!empty($choices));
		$panel = '';
		foreach ($choices as $subtype => $choice) {
			$panel .= $this->formatChoice($subtype, $choice);
		}
		if ($panel) {
			$panel = <<<EOT
<div class="posttypediv">
	<div class="tabs-panel tabs-panel-active">
		<ul class="categorychecklist form-no-clear">{$panel}
		</ul>
	</div>
</div>
EOT;
		}

		$form = $this->getForm($index, $this->extraClass);
		echo <<<EOT
<div id="{$texts['id']}" class="lws_navmenu_metabox">
	<input type="hidden" class="menu-item-classes" name="menu-item[{$index}][menu-item-classes]" value="{$texts['class']}">
	<input type="hidden" class="menu-item-type" data-slug="{$texts['slug']}" name="menu-item[{$index}][menu-item-type]" value="{$texts['type']}">
	{$panel}{$form}{$buttons}
</div>
EOT;
	}

	protected function formatChoice($subtype, $choice)
	{
		$content = sprintf(
			"\n<li class='menu-choice-title lws-menu-choice-%s'><label><input type='checkbox' value='%s' class='lws_menu_subtype'/>",
			\esc_attr($subtype), \esc_attr($subtype)
		);
		$label = $subtype;
		if (\is_array($choice)) {
			if (isset($choice['label']))
				$label = $choice['label'];
			if (isset($choice['fields'])) {
				foreach ($choice['fields'] as $field => $value) {
					$content .= sprintf(
						'<input type="hidden" class="sub-menu-item-%s" value="%s"/>',
						\esc_attr($field), \esc_attr($value)
					);
				}
			}
		} else {
			$label = $choice;
		}
		return ($content . $label . '</li>');
	}

	/** Add menu button */
	protected function getButtons($withSelectAll=false)
	{
		$id = \esc_attr($this->getId());
		$select = __("Select All", 'lws-adminpanel');
		$submit = __("Add to Menu", 'lws-adminpanel');

		$selectAll = '';
		if ($this->hasSelectAll($withSelectAll)) {
			$selectAll = <<<EOT
<span class="list-controls hide-if-no-js">
	<input type="checkbox" id="selectall-{$id}" class="lws_select_all">
	<label for="selectall-{$id}">{$select}</label>
</span>
EOT;
		}
		return <<<EOT
<p class="button-controls">{$selectAll}
	<span class="add-to-menu">
		<input type="submit" class="button-secondary submit-add-to-menu right submit_lws_menuitems" value="{$submit}" name="add-lws-menu-item" id="submit-{$id}">
		<span class="spinner"></span>
	</span>
</p>
EOT;
	}

	/** @return array of (string)subtype => array
	 *	subtype array is [
	 *		'label' => (string)
	 * 		'fields' => [
	 * 			'title' => (string)
	 * 			'url' => (string)
	 * 			'classes' => (string)
	 * 			'attr-title' => (string)
	 * 			'description' => (string)
	 * 			'target' => (string)
	 * 			'xfn' => (string)
	 *		]
	 *	]
	 *	fields may be empty, all or part of described elements. */
	protected function getChoices()
	{
		return array();
	}
}