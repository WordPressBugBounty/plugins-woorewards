<?php
namespace LWS\Adminpanel\Pages\Screens;
if( !defined( 'ABSPATH' ) ) exit();


/** Must be defined as first page of the array given to \lws_register_pages()
 *	Must declare an array index 'resume' => true */
class Resume extends \LWS\Adminpanel\Pages\Page
{
	public $pages = array();

	/** Echo page content
	 *	Declaration of all pages can be found in $this->pages array
	 *	Page header in getHead() */
	public function content()
	{
		\wp_enqueue_style('lws-resume-page');
		echo "<div class='lws-admin-page'>";
		echo "<div class='lws-resume-grid'>";

		foreach($this->pages as $page)
		{
			if(isset($page->description) && $page->description)
			{
				if( \is_array($page->description) )
					$page->description = \LWS\Adminpanel\Tools\Conveniences::array2html($page->description);
				$link = \esc_attr(\admin_url('admin.php?page='.$page->id));
				$title = $page->getTitle();
				$style = '';
				$icon = '';
				if(isset($page->color) && $page->color)
				{
					$colorString = \lws_get_theme_colors('--group-color', $page->color);
					$style = " style='{$colorString}'";
				}
				if(isset($page->image) && $page->image)
				{
					$icon .= "<div class='resume-item-icon'><img src='{$page->image}'/></div>";
				}

				$tabs = $this->getTabsFromPage($page);
				if (count($tabs) > 1) {
					$buttons = "<div class='resume-tabs-buttons-line'>";
					foreach ($tabs as &$tab) {
						$url = \esc_attr(\add_query_arg(array('page' => $page->id, 'tab' => $tab['id'],), admin_url('admin.php')));
						$buttons .= "<a href='{$url}' class='resume-tab-button'>" . $tab['title'] . "</a>";
					}
					$buttons .= "</div>";
					echo <<<EOT
					<div class='resume-item'$style>
						<a href='$link' class='resume-top'>
							$icon
							<div class='resume-item-title'>$title</div>
						</a>
						<div class='resume-content'>
							$page->description
							$buttons
						</div>
					</div>
EOT;
				} else {
					echo <<<EOT
					<a href='$link' class='resume-item'$style>
						<div class='resume-top'>
							$icon
							<div class='resume-item-title'>$title</div>
						</div>
						<div class='resume-content'>
							$page->description
						</div>
					</a>
EOT;
				}
			}
		}
		echo "</div></div>";
	}

	protected function getTabsFromPage($page)
	{
		$tabs = array();
		if (isset($page->data['tabs'])) {
			foreach ($page->data['tabs'] as &$tab) {
				if (!(isset($tab['hidden']) && $tab['hidden']))
					$tabs[] =& $tab;
			}
		}
		return $tabs;
	}

	protected function prepare()
	{}

	public function isResume()
	{
		return true;
	}

	public function getType()
	{
		return 'resume';
	}

	/** @param $pages array of Page instances */
	public function setAllPagesData($pages)
	{
		$this->pages = $pages;
	}

	public function allowSubmit()
	{
		return false;
	}

	/** @return bool */
	public function getGroups()
	{
		return false;
	}
}
