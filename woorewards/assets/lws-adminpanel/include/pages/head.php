<?php

namespace LWS\Adminpanel\Pages;

if (!defined('ABSPATH')) exit();


/**  */
class Head
{
	const YOUTUBE = 'https://www.youtube.com/channel/UCM3iPTIcjnJzfEYxMo5hLvg';
	const CHAT    = 'https://discord.gg/TMeQ3KX4Bf';
	const MAILTO  = 'support@longwatchstudio.com';

	public $id = '';
	public $data = array();
	public $page = null;
	public $resumePage = false;
	public $others = false;

	function __construct(\LWS\Adminpanel\Pages\Page &$current, $resume = false, $others=false)
	{
		$this->id = $current->getId();
		$this->data = $current->getData();
		$this->page = &$current;
		$this->resumePage = $resume;
		$this->others = $others;
	}

	function getMainId()
	{
		return $this->resumePage ? $this->resumePage->getID() : $this->page->getId();
	}

	function getCurrentId()
	{
		return $this->page->getId();
	}

	function getMainTitle()
	{
		$data = $this->resumePage ? $this->resumePage->getData() : $this->data;
		if (isset($data['title']) && $data['title'])
			return $data['title'];
		elseif (isset($data['subtitle']) && $data['subtitle'])
			return $data['subtitle'];
		elseif (isset($data['pagetitle']) && $data['pagetitle'])
			return $data['pagetitle'];
		else
			return \get_admin_page_title();
	}

	/** @return array object, each entry contains:
	 *  * id (string) the slug
	 *  * title (string) human readable title
	 *  * url (string) link to that screen
	 * A level is ignored if title is exactly the same than the level up on it.
	 * A Resume page always stop at first level. */
	function getBreadcrumbs($prefixWithResumePage = true)
	{
		$breadcrumbs = array();

		$currentIsResume = ($this->resumePage && ($this->resumePage->getId() == $this->page->getId()));
		if( $prefixWithResumePage && !$currentIsResume && $this->resumePage )
		{
			$breadcrumbs[] = $this->formatLevelEntry($this->resumePage);
		}

		$format = $this->formatLevelEntry($this->page);
		$lastTitle = $format->title;
		$format->siblings = $this->getSiblings();
		$breadcrumbs[] = $format;

		if( $currentIsResume )
		{
			return $breadcrumbs;
		}

		// dig inside path
		$level = $this->page->getData();
		$path = '';
		foreach ($this->page->getPath() as $slug)
		{
			if ($local = $this->page->getNextLevel($level, $slug))
			{
				$path .= $path ? ('.' . $slug) : $slug;
				$format = $this->formatLevelEntry($local, $path);
				if( $lastTitle != $format->title )
				{
					$breadcrumbs[] = $format;
				}
				$lastTitle = $format->title;
				$level = $local;
			} else
				break;
		}

		return $breadcrumbs;
	}

	/** @param array Keys (array of string) the tab indexes to merge,
	 *	if false use a default set.
	 *	@return array last tab with option flatten along the path. */
	function getFlattenTab($keys=false)
	{
		if (!$keys) {
			$keys = array(
				'summary' => true,
				'rights'  => true,
				'action'  => true,
				'toc'     => true,
				'nosave'  => true,
				'icon'    => true,
				'vertnav' => true,
				'hidden'  => true,
			);
		} else {
			$keys = array_fill_keys($keys, true);
		}

		// first data
		$level = $this->page->getData();
		$merge = array_intersect_key($level, $keys);
		// dig data
		foreach ($this->page->getPath() as $slug) {
			$local = $this->page->getNextLevel($level, $slug);
			if (!$local)
				break;
			// override
			$merge = array_merge($merge, array_intersect_key($local, $keys));
		}
		return $merge;
	}

	/** a graph with each node:
	 * * id
	 * * title
	 * * url
	 * * active (bool) selected menu entry
	 * * depth (int) root is zero
	 * * visible (bool)
	 * * children (array of node) */
	function getMenuGraph($cursor = false)
	{
		if (false === $cursor)
			$cursor = (object) array('level' => $this->data, 'route' => array(), 'path' => $this->page->getPath());

		$graph = array();
		if (isset($cursor->level['tabs'])) {
			$tail = $cursor->path;
			$slug = $tail ? array_shift($tail) : false;
			foreach ($cursor->level['tabs'] as $tab) {
				$depth = count($cursor->route);
				$route = array_merge($cursor->route, array($tab['id']));

				$obj = $this->formatLevelEntry($tab, $this->page->getPathAsString($route));
				$obj->depth    = $depth;
				$obj->icon     = isset($tab['icon']) ? $tab['icon'] : '';
				$obj->active   = ($tab['id'] == $slug);
				$obj->visible  = !(isset($tab['hidden']) && boolval($tab['hidden']));
				$obj->children = $this->getMenuGraph((object) array(
					'level' => $tab,
					'route' => $route,
					'path'  => $obj->active ? $tail : array(),
				));
				$graph[] = $obj;
			}
		}
		return $graph;
	}

	/** @param $level (array|Page) tab array or Page instance
	 *	@return (object) with [id, titel, url] */
	protected function formatLevelEntry(&$level, $tabPath = '')
	{
		if (\is_a($level, '\LWS\Adminpanel\Pages\Page')) {
			return (object) array(
				'id'       => $level->getId(),
				'title'    => $level->getPageTitle(),
				'url'      => $level->getLink($tabPath),
				'siblings' => array(),
			);
		} else {
			return (object) array(
				'id'       => $level['id'],
				'title'    => $level['title'],
				'url'      => $this->page->getLink($tabPath),
				'siblings' => array(),
			);
		}
	}

	function getSiblings()
	{
		$siblings = array();
		if ($this->others) {
			foreach ($this->others as $id => $other) {
				if ($id == $this->getMainId()) continue;
				if ($id == $this->getCurrentId()) continue;
				$siblings[$id] = (object)array(
					'id'       => $id,
					'title'    => $other->getPageTitle(),
					'url'      => \esc_attr($other->getLink()),
				);
			}
		}
		return $siblings;
	}

	/** Echo transient notices if any */
	function showTransientNotices()
	{
		$notices = \LWS\Adminpanel\Pages\Notices::instance()->getNotices('transient');
		$html = '';
		foreach($notices as $notice)
		{
			/// take care admin-interface.js uses classes below
			// * the full bloc: lws-adminpanel-transient-notices
			// * the close button: lws-notice-dismiss
			// * the notice row: lws-adminpanel-notice
			$html .= \LWS\Adminpanel\Pages\Notices::instance()->itemToHTML($notice);
		}

		if( $html )
		{
			echo "<div class='lws-adminpanel-transient-notices'>{$html}</div>";
		}
	}

	/** Echo the sticky panel header */
	function showStickyPanel($type, $middle=false)
	{
		/** Top Line */
		echo "<div class='lws-sticky-panel'><div class='top-row'>";

		/**  BreadCrumbs */
		$breadcrumbs = $this->getBreadcrumbs();
		echo "<ul class='breadcrumb'>";
		foreach ($breadcrumbs as $crumb)
		{
			if( $crumb->title )
			{
				// duplicates url in data since href could be modified by JS scripts @see editlistfilters
				$link = "<a class='level' href='{$crumb->url}' data-id='{$crumb->id}' data-href='{$crumb->url}'>{$crumb->title}</a>";
				// submenu to sibling pages
				if ($crumb->siblings) {
					$link .= "<div class='lws-breadcrumb-siblings-icon lws-icon-nav-down'></div>";
					$link .= "<ul class='lws-breadcrumb-siblings'>";
					foreach ($crumb->siblings as $sibling) {
						$link .= "<li><a href='{$sibling->url}' data-id='{$sibling->id}' data-href='{$sibling->url}'>{$sibling->title}</a></li>";
					}
					$link .= "</ul>";
				}
				echo "<li class='particle'>{$link}</li>";
			}
		}
		echo "</ul>";

		\do_action('lws_adminpanel_after_breadcrums', $this);

		/** Admin Menu */
		$this->showAdminMenu();
		echo "</div>";

		if ($middle) {
			if (\is_array($middle))
				$middle = \LWS\Adminpanel\Tools\Conveniences::array2html($middle);
			echo $middle;
		}

		if ($type == 'admin') {
			/** Second Line */
			$secondLine = false;
			$submit = '';
			$expand = '';
			$hasTabs = ($this->getTabCount($this->page->getData()) > 1);
			$tabs = '';
			if ($this->page->allowSubmit()) {
				$submit = $this->getSubmitButtons();
				$secondLine = true;
			}
			if ($this->page->getGroups() && count($this->page->getGroups()) > 1) {
				$expand = $this->getExpandButton();
				$secondLine = true;
			}
			if ($hasTabs) {
				$tabs = "<div class='tab-menu'>";
				$tabs .= "<div class='small-screen-tabs lws-icon-menu'><div class='vertical-wrapper'></div></div>";
				$tabs .= $this->getTabs();
				$tabs .= "</div>";
				$secondLine = true;
			} else if ($secondLine) {
				$tabs = "<div class='tab-menu'></div>";
			}
			if ($secondLine) {
				echo "<div class='second-row'>";
				echo $tabs . $expand . $submit;
				echo "</div>";
			}
		}
		echo "</div>";
	}

	/** Echo the Header Admin Menu */
	function showAdminMenu()
	{
		$labels = \apply_filters('lws_adminpanel_topbar_labels_' . $this->id, array(
			'amenu'    => __("Admin Menu", 'lws-adminpanel'),
			'asettings'=> __("Advanced Settings", 'lws-adminpanel'),
			'support'  => __("Support", 'lws-adminpanel'),
			'tshooting'=> __("Troubleshooting", 'lws-adminpanel'),
			'chat'     => __("Live Chat", 'lws-adminpanel'),
			'doc'      => __("Documentation", 'lws-adminpanel'),
			'patch'    => __("Patch Notes", 'lws-adminpanel'),
			'lic'      => __("License Information", 'lws-adminpanel'),
			'trialtext'=> __("Try Premium for Free", 'lws-adminpanel'),
		));

		$settings = $this->getAdminMenuSettings();
		list($activecolor, $subcolor) = $this->getStatusBarsColors($settings);
		$licUrl = \apply_filters('lws_adm_menu_license_url', false, $this->getMainId(), $this->id);

		// Show Start Trial Button
		if( $licUrl && isset($settings['trial_available']) && $settings['trial_available'] )
			echo "<a href='{$licUrl}' class='start-trial-button'>{$labels['trialtext']}</a>";

		echo "<div class='admin-menu'>";
		echo <<<EOT
	<div id='lws_am_top_item' class='top-item'>
		<div class='item-icon lws-icon-menu'>
			<div class='notif-counter' style='display:none;' id='lws_am_top_notif_counter'></div>
		</div>
		<div class='item-name'>
			<div class='item-text'>{$labels['amenu']}</div>
			<div class='item-info'>
				<div class='info-icon $activecolor'></div><div class='info-icon $subcolor'></div>
			</div>
		</div>
	</div>
EOT;
		echo "<div id='lws_am_top_menu' class='top-menu'>";

		/** Plugin Name and version */
		echo <<<EOT
	<div class='top-menu-item separator no-pointer'>
		<div class='top-menu-item-icon lws-icon-version'></div>
		<div class='top-menu-item-text upper'>{$settings['title']} {$settings['version']}</div>
	</div>
EOT;

		/** Notifications */
		echo $this->getNoticeMenuItem(
			\LWS\Adminpanel\Pages\Notices::instance()->getNotices('persistant'),
			__("Plugin Notifications", 'lws-adminpanel'),
			'',
			'internal'
		);
		echo $this->getNoticeMenuItem(
			\LWS\Adminpanel\Pages\Page::getAdminNotices(),
			__("Other Notifications", 'lws-adminpanel'),
			'separator',
			'external'
		);

		/** Advanced Settings */
		//echo "<div id='lws_advanced_settings' class='top-menu-item separator'><div class='top-menu-item-icon lws-icon-adv-settings'></div>";
		//echo "<div class='top-menu-item-text'>{$labels['asettings']}</div></div>";

		/** Support */
		echo sprintf("<a href='%s' class='top-menu-item'>", \esc_attr($this->getSupportUrl($settings['mailto'])));
		echo "<div class='top-menu-item-icon lws-icon-support'></div>";
		echo "<div class='top-menu-item-text'>{$labels['support']}</div></a>";

		/** Troubleshooting */
		/*
		echo "<a href='{$settings['tshooting']}' class='top-menu-item'>";
		echo "<div class='top-menu-item-icon lws-icon-debug'></div>";
		echo "<div class='top-menu-item-text'>{$labels['tshooting']}</div></a>";
		*/

		/** Live Chat */
		echo "<a href='{$settings['chat']}' target='_blank' class='top-menu-item'>";
		echo "<div class='top-menu-item-icon lws-icon-discord'></div>";
		echo "<div class='top-menu-item-text'>{$labels['chat']}</div></a>";

		/** Live Chat */
		echo "<a href='{$settings['doc']}' target='_blank' class='top-menu-item separator'>";
		echo "<div class='top-menu-item-icon lws-icon-books'></div>";
		echo "<div class='top-menu-item-text'>{$labels['doc']}</div></a>";

		/* // Patch Notes
		echo "<div class='top-menu-item separator'>";
		$classeNotes = '';
		if (isset($patchNotes)) {
			$classeNotes = ' show_patchnotes';
			echo "<div class='top-menu-item-icon lws-icon-notes{$classeNotes}'><div class='notif-counter'></div></div>";
		} else {
			echo "<div class='top-menu-item-icon lws-icon-notes'></div>";
		}
		echo "<div class='top-menu-item-text{$classeNotes}'>{$labels['patch']}</div></div>";*/

		/** Manager information */
		if( $licUrl )
		{
			$licUrl = \esc_attr($licUrl);
			$licText  = "<a href='{$licUrl}' class='top-menu-item'><div class='top-menu-item-icon lws-icon-key'></div>";
			$licText .= "<div class='top-menu-item-text'>{$labels['lic']}</div></a>";
			echo $licText; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo "</div>";
		echo "</div>";
	}

	protected function getNoticeMenuItem($notices, $label='Notices', $class='', $type='')
	{
		if( $class )
			$class = (' ' . ltrim($class));
		$wrapper = '';
		if( $notices )
		{
			if( \is_array($notices) )
			{
				foreach($notices as $notice)
				{
					$close = '';
					if($notice->dismissible || $notice->forgettable)
					{
						$key = \esc_attr($notice->key);
						$text = __("Dismiss", 'lws-adminpanel');
						$close = "<div class='dismiss-btn' data-forget='{$key}'>{$text}</div>";
					}
					$wrapper .= "<div class='lws-notice {$notice->level}'><div class='text'>{$notice->message}</div>{$close}</div>";
				}
			}
			else
			{
				$wrapper = $notices;
			}
			$wrapper = "<div class='lws-adm-notices-wrapper'>{$wrapper}</div>";

			$class .= ' show_notices';
			$wrapper .= "<div class='top-menu-item-icon lws-icon-notifs-on show_notices'><div class='notif-counter'></div></div>";
		}
		else
		{
			$wrapper = "<div class='top-menu-item-icon lws-icon-notifs-off'></div>";
		}

		return <<<EOT
<div class='top-menu-item' data-type='{$type}'>
	{$wrapper}
	<div class='top-menu-item-text check_notices_count_at_start{$class}'>{$label}</div>
</div>
EOT;
	}

	/** Submit and Cancel buttons */
	function getSubmitButtons()
	{
		$buttons = "<button id='save_changes' class='second-row-button save' type='submit' form='{$this->id}'>";
		$buttons .= "<div class='button-icon lws-icon-floppy-disk-2'></div>";
		$buttons .= "<div class='button-text'>" . __('Save Changes', 'lws-adminpanel') . "</div></button>";
		return $buttons;
	}

	/** Groups Expand Button */
	function getExpandButton()
	{
		$buttons  = "<div id='expand_groups' class='second-row-button expand'>";
		$buttons .= "<div class='button-icon lws-icon-plus'></div>";
		$buttons .= "<div class='button-text'>".__('Expand All', 'lws-adminpanel')."</div></div>";
		return $buttons;
	}

	/** Tabs Menu */
	function getTabs()
	{
		$menu = $this->makeTabsMenu($this->getMenuGraph());
		return $menu;
	}

	/* Create multi level tabs menu depending on settings */
	private function makeTabsMenu($graph)
	{
		$menu = "";
		$cpt = 0;
		foreach ($graph as $tab) {
			// avoid hidden tab
			if( $tab->visible )
			{
				$count = count(array_filter(array_column($tab->children, 'visible')));
				$hasTab = $count > 1;
				$href = $hasTab ? '#' : $tab->url;

				$class = "tab-menu-item";
				if ($hasTab) $class .= " has-children";
				$classa = "item-level-{$tab->depth}";
				if ($tab->active) $classa .= " active";

				$arrow = '';
				if ($hasTab) {
					if ($tab->depth == 0)
						$arrow = "<div class='arrow-down'></div>";
					else
						// Define the Arrow on the third level if need be
						$title = "";
				}

				$menu .= "<div class='$class'>";
				$menu .= "<a id='{$tab->id}' class='$classa' href='{$href}'>";
				$menu .= "<div class='tab-menu-item-arrow'></div>";
				if(isset($tab->icon) && !empty($tab->icon))
				{
					$menu .= "<div class='menu-item-icon {$tab->icon}'></div>";
				}
				$menu .= "<div class='menu-item-text'>{$tab->title}</div>";
				$menu .= $arrow;
				$menu .= "</a>";
				if ($tab->active && !$hasTab && $this->page) {
					$menu .= $this->getGroupNav();
				}
				if ($hasTab) {
					$menu .= "<ul class='tab-submenu-grid grid-level-" . ($tab->depth + 1) . " data-depth='" . ($tab->depth + 1) . "'>";
					$menu .= $this->makemenu($tab->children);
					$menu .= "</ul>";
				}
				$menu .=	"</div>";
				$cpt++;
			}
		}
		return $menu;
	}

	protected function getGroupNav()
	{
		if (!$this->page->hasGroupNav())
			return '';

		$groups = $this->page->getGroups();
		if (!$groups || count($groups) <= 1)
			return '';

		return sprintf('<ul class="lws-group-nav">%s</ul>',
			\implode("\n", \array_map(function($group) {
				return sprintf("<li class='lws-group-nav-item lws_adm_scrollto'>%s</li>", $group->getSmallBar());
			}, $groups))
		);
	}

	protected function getAdminMenuSettings()
	{
		$id = $this->getMainId();
		$settings = array(
			'title'      => $this->getMainTitle(),
			'subtitle'   => isset($this->data['subtitle']) ? $this->data['subtitle'] : '',
			'pagetitle'   => isset($this->data['pagetitle']) ? $this->data['pagetitle'] : '',
			'url'        => __("https://plugins.longwatchstudio.com/", 'lws-adminpanel'),
			'version'    => \apply_filters('lws_adminpanel_plugin_version_'     . $id, '', $this->id),
			'origin'     => \apply_filters('lws_adminpanel_plugin_origin_'      . $id, array('LWS', 'Long Watch Studio'), $this->id),
			'doc'        => \apply_filters('lws_adminpanel_documentation_url_'  . $id, __('https://plugins.longwatchstudio.com/knowledge-base/', 'lws-adminpanel'), $this->id),
			'chat'       => \apply_filters('lws_adminpanel_plugin_chat_url_'    . $id, self::CHAT, $this->id),
			'mailto'     => \apply_filters('lws_adminpanel_plugin_support_email'. $id, self::MAILTO, $this->id),
			'purchase'   => false,
			'lite'       => true,
			'trial'      => false,
			'active'     => false,
			'expired'    => false,
		);
		$settings = \apply_filters('lws_adm_menu_license_status', $settings, $id, $this->id);
		return $settings;
	}

	/** @return array with 2 CSS colors. */
	private function getStatusBarsColors($settings)
	{
		$left = $right = 'color';

		if( $settings['active'] ) $left     .= ' premium'; // green
		else if( $settings['trial'] ) $left .= ' trial'; // orange
		else $left                          .= ' lite'; // grey

		if( isset($settings['soon']) )
		{
			if( $settings['soon'] <= 3 )     $right .= ' sooner'; // bright red
			elseif( $settings['soon'] <= 5 ) $right .= ' soon'; // bright orange
			else                             $right .= ' belatedly'; // green
		}
		elseif( $settings['expired'] )
			$right .= ' expired'; // red
		elseif( $settings['lite'] )
			$right .= ' idle'; // grey
		elseif( $settings['subscription'] )
			$right .= ' subscription'; // orange
		else
			$right .= ' running'; // green

		return array($left, $right);
	}

	/** To show our topbar on any WordPress admin page.
	 * See WooRewards-pro, the badge list screen. */
	static function echoExternal($pageId, $settings)
	{
		$data = array_merge(array(
			'id' => $pageId,
			'rights' => ''
		), $settings);
		$page = \LWS\Adminpanel\Pages\Page::create($pageId, $data);
		$head = new self($page);
		$head->showStickyPanel($pageId);
		$head->showTransientNotices();
	}

	/** @return (int) the count of tab at given level
	 *	Hidden tab are not counted.
	 *	@param $data (array) the page data (or a tab array) */
	function getTabCount(array $data)
	{
		$c = 0;
		if (isset($data['tabs']) && $data['tabs']) {
			foreach ($data['tabs'] as $tab) {
				if( !(isset($tab['hidden']) && boolval($tab['hidden'])) )
					++$c;
			}
		}
		return $c;
	}

	private function makemenu($graph)
	{
		$menu = "";
		$cpt = 0;
		foreach ($graph as $tab) {
			// avoid hidden tab and licence tab (except if not at first level)
			if( $tab->visible )
			{
				$count = count(array_filter(array_column($tab->children, 'visible')));
				$hasTab = $count > 1;
				$href = $hasTab ? '#' : $tab->url;

				$class = "item-level-{$tab->depth}";
				if ($tab->depth == 0 && $cpt != 0) $class .= " lws-mtab-menu-sep";
				if ($hasTab)                     $class .= " lws_mtab_hassub";
				$classa = "ui-tabs-anchor lws-theme-over-fg";
				if ($tab->active)           $classa .= " lws-mtab-active";

				$title = $tab->title;
				if ($hasTab) {
					if ($tab->depth == 0)
						$title .= "<span class='menu-item-icon lws-icon lws-icon-circle-down'></span>";
					else
						$title = "<div class='menu-item-text lws-mtab-submenu-label'>$title</div><div class='lws-mtab-menu-arrow lws-icon lws-icon-circle-right'></div>";
				}

				$menu .=	"<li id='{$tab->id}' class='$class' role='tab'>";
				$menu .=	"<a  class='$classa' role='presentation' href='{$href}'>{$title}</a>";
				if ($hasTab) {
					$menu .=	"<ul class='lws-top-menu lws-mtab-ul-" . ($tab->depth + 1) . " lws-mtab-menu-hidden' role='tablist' data-depth='" . ($tab->depth + 1) . "'>";
					$menu .= $this->makemenu($tab->children);
					$menu .= "</ul>";
				}
				$menu .=	"</li>";
				$cpt++;
			}
		}
		return $menu;
	}

	function getSupportUrl($default=false)
	{
		$url = \apply_filters('lws_adm_menu_support_url', false, $this->getMainId(), $this->id);
		if( !$url )
		{
			$url = $default;
			if( !\preg_match('/^mailto:/i', $url) && \is_email($url) )
				$url = 'mailto:'.$url;
		}
		return $url;
	}
}
