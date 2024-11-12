<?php
namespace LWS\WOOREWARDS\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** manage form with several screens */
abstract class MultiFormList extends \LWS\Adminpanel\EditList\Source
{
	const ROW_ID = 'post_id';

	protected $pool = null;

	/** @return array of key => category. category is an array with [label, color, icon]
	 *	for key @see Event::getCategories() */
	abstract protected function getGroups();
	/// @return an array of Event|Unlockable instances
	abstract protected function loadChoices();
	/** @return string|array with step information
	 * icon : step icon
	 * title : step title */
	abstract protected function getStepInfo();

	function __construct(\LWS\WOOREWARDS\Core\Pool $pool=null)
	{
		$this->pool = $pool;
	}

	public function defaultValues()
	{
		$values = array();
		foreach( $this->loadChoices()->asArray() as $choice )
			$values = array_merge($values, $choice->getData());

		return array_merge($values, array(
			self::ROW_ID => '', // it is important that id is reset and first for javascript purpose
			'wre_type'   => ''
		));
	}

	protected function getHiddenInputs()
	{
		$rowId = static::ROW_ID;
		return "<input type='hidden' name='{$rowId}' class='lws_woorewards_system_id' />";
	}

	protected function getGroupTitles()
	{
		return array(
			array(
				'idle' 		=> __("First Step", 'woorewards-lite'),
				'selected' 	=> __("Step : ", 'woorewards-lite')
			),
			array(
				'idle' 		=> __("Second Step", 'woorewards-lite'),
				'selected' 	=> __("Step : ", 'woorewards-lite')
			)
		);
	}

	/** radio-grid */
	protected function optionGroups()
	{
		$groups = $this->getGroups();
		if( !isset($groups['']) )
			$groups[''] = array('label'=>'', 'color' => false, 'icon' => false);
		foreach( $groups as &$group )
		{
			$group['items'] = array();
			if( !$group['color'] ) $group['color'] = '#a9a9a9';
			if( !$group['icon'] )  $group['icon']  = 'lws-icon-show-more';
			if( !isset($group['descr']) ) $group['descr'] = '';
		}

		foreach( $this->loadChoices()->asArray() as $choice )
		{
			$sort = '';
			foreach( $choice->getCategories() as $cat => $name )
			{
				if( isset($groups[$cat]) )
				{
					$sort = $cat;
					break;
				}
			}
			$type = \esc_attr($choice->getType());
			$info = $choice->getInformation();
			if( !$info['color'] ) $info['color'] = $groups[$sort]['color'];
			if( !$info['icon'] )  $info['icon']  = $groups[$sort]['icon'];
			$groups[$sort]['items'][$type] = $info;
		}

		return $groups;
	}

	protected function groupsToRadioGrid($groups)
	{
		$html = '';
		$groupTitles = $this->getGroupTitles();
		$maingrid = '';
		$itemsgrids = '';
		$colors = array();
		foreach( $groups as $key => $group )
		{
			if( !$group['items'] ) continue;

			$colorstring = " style='" . \lws_get_theme_colors('--radiogrid-group-color', $group['color']) . "'";
			$maingrid .= "<div class='radiogrid-item main lws_radiobutton_radio'{$colorstring} data-key='{$key}'>";
			$maingrid .= "<div class='inner-background'></div>";
			$maingrid .= "<div class='icon {$group['icon']}'></div>";
			$maingrid .= "<div class='label'>{$group['label']}</div>";
			$maingrid .= "<div class='descr'>{$group['descr']}</div>";
			$maingrid .= "</div>";

			$itemsgrids .= "<div class='lws_woorewards_system_type_choices radiogrid-container big-items hidden'{$colorstring}' data-key='{$key}'>";
			foreach( $group['items'] as $type => $info )
			{
				$info['group'] = $group['label'];
				$colors[$type] = $colorstring;
				$data = \base64_encode(\json_encode($info));
				$itemsgrids .= <<<EOT
				<div class='radiogrid-item lws_radiobutton_radio lws_wre_system_selector_item'{$colorstring} value='{$type}' data-info='{$data}' tabindex='0'>
					<div class='inner-background'></div>
					<div class='icon lws-icons {$info['icon']}'></div>
					<div class='label'>{$info['label']}</div>
					<div class='descr'>{$info['short']}</div>
				</div>
EOT;
			}
			$itemsgrids .= "</div>";
		}
		$html = <<<EOT
		<div class='lws-editlist-group-wrapper first_group canfold'>
			<div class='group-header-line'>
				<div class='header' data-selected='{$groupTitles[0]['selected']}' data-idle='{$groupTitles[0]['idle']}'>{$groupTitles[0]['idle']}</div>
				<div class='fold-button'></div>
			</div>
			<div class='radiogrid-container big-items'>
				$maingrid
			</div>
		</div>
		<div class='lws-editlist-group-wrapper second_group canfold hidden'>
			<div class='group-header-line'>
				<div class='header' data-selected='{$groupTitles[1]['selected']}' data-idle='{$groupTitles[1]['idle']}'>{$groupTitles[1]['idle']}</div>
				<div class='fold-button'></div>
			</div>
			$itemsgrids
		</div>
EOT;
		return array(
			'html' => $html,
			'colors' => $colors,
		);
	}

	/** no edition, use bulk action */
	function input()
	{
		\wp_enqueue_script('lws_wre_system_selector');
		\wp_enqueue_style('lws_wre_system_selector');

		$divs = array();
		$uppergroups = $this->groupsToRadioGrid($this->optionGroups());
		foreach( $this->loadChoices()->asArray() as $choice )
		{
			if (null != $this->pool) {
				$choice->setPool($this->pool);
			}
			$type = \esc_attr($choice->getType());
			$colorstring = $uppergroups['colors'][$type];
			$divs[] = "<div data-type='$type' class='lws_woorewards_system_choice editlist-content-grid hidden $type'$colorstring>"
				. $choice->getForm('editlist')
				. "</div>";
		}

		$divs = implode("\n\t", $divs);
		$hiddens = $this->getHiddenInputs();
		$stepInfo = $this->getStepInfo();
		return <<<EOT
		<div class='lws-woorewards-system-edit lws_woorewards_system_master'>
			{$hiddens}
			<input class='lws_woorewards_system_type' name='wre_type' type='hidden'>
			{$uppergroups['html']}
			<div class='lws_woorewards_system_screens lws-editlist-group-wrapper third_group canfold hidden'>
				<div class='group-header-line'>
					<div class='header'>{$stepInfo}</div>
					<div class='fold-button'></div>
				</div>
				$divs
			</div>
		</div>
EOT;
	}
}
