<?php

namespace LWS\Adminpanel\Pages;

if (!defined('ABSPATH')) exit();

/**  */
class Group
{
	private $m_FieldArray = array();

	public $page                  = null;
	public $id                    = '';
	public $title                 = '';
	public $color                 = '';
	public $image                 = '';
	public $icon                  = '';
	public $class                 = '';
	public $collapsed             = false;
	public $helpBanner            = '';
	public $extra                 = array();
	public $customBehavior        = null;
	public $customDelayedBehavior = null;
	public $editlist              = null;
	public $editlistFirst         = false;
	public $advanced              = false;
	protected $requirement        = false;

	/** @param $data fulfill the Group::format */
	function __construct($data, $page)
	{
		$this->page = $page;
		$this->id = isset($data['id']) ? $data['id'] : '';
		$this->title = $data['title'];
		$this->color = isset($data['color']) ? $data['color'] : '';
		$this->image = isset($data['image']) ? $data['image'] : '';
		$this->icon = isset($data['icon']) ? $data['icon'] : '';
		$this->class = isset($data['class']) ? $data['class'] : '';
		$this->collapsed = isset($data['collapsed']) ? boolval($data['collapsed']) : false;
		$this->helpBanner = isset($data['text']) ? $data['text'] : '';
		$this->extra = isset($data['extra']) ? $data['extra'] : array();
		$this->customBehavior = isset($data['function']) ? $data['function'] : null;
		$this->customDelayedBehavior = isset($data['delayedFunction']) ? $data['delayedFunction'] : null;
		$this->editlist = isset($data['editlist']) ? $data['editlist'] : null;
		$this->editlistFirst = isset($data['editlist_first']) ? boolval($data['editlist_first']) : false;
		$this->advanced = isset($data['advanced']) ? boolval($data['advanced']) : false;
		if (isset($data['require']))
			$this->setRequirement($data['require']);

		if (isset($data['fields']))
		{
			foreach ($data['fields'] as $field)
			{
				if (\LWS\Adminpanel\Internal\Pages::test($field, self::fieldFormat(), "$page ... fields"))
					$this->addField($field);
			}
		}
	}

	/** @return array well formated format array for Pages::test()
	 * @see Pages::test() */
	public static function format()
	{
		return array(
			'title'				=> \LWS\Adminpanel\Internal\Pages::format('title', false, 'string', "Display a group title."),
			'id'				=> \LWS\Adminpanel\Internal\Pages::format('id', true, 'string', "Identify a group"),
			'rights'			=> \LWS\Adminpanel\Internal\Pages::format('rights', true, 'string', "User capacity required to access to this group. Usually 'manage_options'. A tab could be locally more restrictive"),
			'text'				=> \LWS\Adminpanel\Internal\Pages::format('text', true, '', "A free text displayed at top of the group, after the title. If array given, see \LWS\Adminpanel\Tools\Conveniences::array2html()"),
			'fields'			=> \LWS\Adminpanel\Internal\Pages::format('fields', true, 'array', "Option fields"),
			'extra'				=> \LWS\Adminpanel\Internal\Pages::format('extra', true, 'array', "Extra features as 'doclink'"),
			'image'				=> \LWS\Adminpanel\Internal\Pages::format('image', true, 'string', "Image to set on group header"),
			'color'				=> \LWS\Adminpanel\Internal\Pages::format('color', true, 'string', "special color for the group. Use hexa color format. eg. #ffffff"),
			'class'				=> \LWS\Adminpanel\Internal\Pages::format('class', true, 'string', "extra class to put on the group | Used for grid display"),
			'collapsed'			=> \LWS\Adminpanel\Internal\Pages::format('collapsed', true, 'bool', "default is false. Defines if a group is collapsed by default or not"),
			'icon'				=> \LWS\Adminpanel\Internal\Pages::format('icon', true, 'string', "Icon to set on group header | overrided if image set"),
			'editlist'			=> \LWS\Adminpanel\Internal\Pages::format('editlist', true, 'LWS\Adminpanel\EditList', "An editlist instance"),
			'editlist_first'	=> \LWS\Adminpanel\Internal\Pages::format('editlist_first', true, 'bool', "Default is false. Show editlist before the fields.'"),
			'function'			=> \LWS\Adminpanel\Internal\Pages::format('function', true, 'callable', "A function to echo a custom feature."),
			'delayedFunction'	=> \LWS\Adminpanel\Internal\Pages::format('delayedFunction', true, 'callable', "Same as function but executed after usual fields display."),
			'advanced'			=> \LWS\Adminpanel\Internal\Pages::format('advanced', true, 'bool', "Default is false. If true, the group is hidden by default."),
			'require' => \LWS\Adminpanel\Internal\Pages::format('require',	true, 'array', "An array with a css selector to an input and the required value ['selector' => '.example', 'value'=> 'yes']. If condition is not fullfilled, all the line is hidden."),
		);
	}

	/** @return array well formated format array for Pages::test()
	 * @see Pages::test() */
	public static function fieldFormat()
	{
		return array(
			'id'    => \LWS\Adminpanel\Internal\Pages::format('id',		false, 'string', "used with update_option and get_option."),
			'type'  => \LWS\Adminpanel\Internal\Pages::format('type',	false, 'string', "A known field type."),
			'title' => \LWS\Adminpanel\Internal\Pages::format('title',	true, 'string', "Field title."),
			'extra' => \LWS\Adminpanel\Internal\Pages::format('extra',	true, 'array', "type specific."),
			'require' => \LWS\Adminpanel\Internal\Pages::format('require',	true, 'array', "An array with a css selector to an input and the required value ['selector' => '.example', 'value'=> 'yes']. If condition is not fullfilled, all the line is hidden."),
		);
	}

	public function addField($data)
	{
		$extra = isset($data['extra']) ? $data['extra'] : array();
		$id = isset($data['id']) ? $data['id'] : '';
		$title = isset($data['title']) ? $data['title'] : '';
		$f = Field::create(strtolower($data['type']), $id, $title, $extra);
		if (isset($data['require']))
			$f->setRequirement($data['require']);

		if (!is_null($f))
			$this->m_FieldArray[] = $f->register($this->page);
		return $f;
	}

	public function title($maxlen = 0, $etc = '...')
	{
		if ($maxlen <= 0)
			return $this->title;

		$minTitle = \wp_kses($this->title, array());
		if (\strlen($minTitle) <= $maxlen)
			return $this->title;
		else
			return substr($minTitle, 0, ($maxlen - strlen($etc))) . $etc;
	}

	public function targetId()
	{
		return 'lws_group_targetable_' . $this->id;
	}

	public function titleId()
	{
		return 'lws_group_title_' . $this->id;
	}

	/** An advanced group is hidden by default (set advanced=>true as arguments)
	 * A group is also advanced if all its fields are advanced.
	 * Side effect: no field => not advanced. */
	public function isAdvanced()
	{
		if ($this->advanced)
			return true;
		if (empty($this->m_FieldArray))
			return false;
		if (!is_null($this->editlist) && is_a($this->editlist, '\LWS\Adminpanel\Internal\EditlistControler'))
			return false;

		foreach ($this->m_FieldArray as $field)
		{
			if (!$field->isAdvanced())
				return false;
		}
		$this->advanced = true;
		return true;
	}

	/** @param $require (array) An array with a css selector to an input and the required value ['selector' => '.example', 'value'=> 'yes'].
	 * * selector: css selector (required)
	 * * cmp: operator in '==', '!=', 'match' (default ==)
	 * * value: the value to compare to (default is '')
	 * * event: the event to react fo (default is 'change')
	 * (Managed in Group) If condition is not fullfilled, all the line is hidden. */
	public function setRequirement(array $require)
	{
		if (isset($require['selector']) && \is_string($require['selector']) && $require['selector'])
		{
			$this->requirement = array_merge(array('value' => '', 'cmp' => '=='), $require);
			if (!\in_array($this->requirement['cmp'], array('==', '!=', 'match')))
			{
				$this->requirement['cmp'] = '==';
				error_log("In field [{$this->id}], 'require.cmp' expect a string in [==, !=, match]. Default is ==.");
			}
		} else {
			error_log("In field [{$this->id}], 'require' expect an array with a css selector to an input and the required value ['selector' => '.example', 'value'=> 'yes']. If condition is not fullfilled, all the line is hidden.");
		}
	}

	public function getRequirementClass($prefix = ' ')
	{
		return ($this->requirement) ? ($prefix . 'lws_adm_field_require') : '';
	}

	public function getRequirementArgs($prefix = ' ')
	{
		if ($this->requirement)
		{
			$s = \esc_attr($this->requirement['selector']);
			$v = \esc_attr($this->requirement['value']);
			$c = \esc_attr($this->requirement['cmp']);
			$args = "{$prefix}data-selector='{$s}' data-value='{$v}' data-operator='{$c}'";
			if (isset($this->requirement['event']) && $this->requirement['event'])
				$args .= sprintf(" data-event='%s'", \esc_attr($this->requirement['event']));
			return $args;
		}
		else
			return '';
	}

	/** echo the group content */
	public function eContent()
	{
		$txtid = $this->targetId();
		//$advanced = $this->isAdvanced() ? ' lws_advanced_option' : '';
		$class = $this->class ? (' ' . $this->class) : '';
		$class .= $this->getRequirementClass();
		$args = $this->getRequirementArgs();

		if (isset($this->extra['doclink']) && $this->extra['doclink']) {
			$class .= ' has-doc';
		}

		$colorStyle = '';
		if ($this->color)
		{
			$colorStyle = " style='" . \lws_get_theme_colors("--group-color", $this->color) . "'";
		}
		echo "<div class='lws-form-div group-item$class'$colorStyle id='{$txtid}'{$args}>";

		echo $this->groupTitleLine();
		if ($this->helpBanner)
		{
			if (\is_array($this->helpBanner))
				$this->helpBanner = \LWS\Adminpanel\Tools\Conveniences::array2html((array)$this->helpBanner);
			echo "<div class='group-help'>{$this->helpBanner}</div>";
		}

		echo "<div class='group-content fields-grid'>";

		if ($this->customBehavior != null && is_callable($this->customBehavior))
		{
			echo "<div class='group-spanned'>";
			call_user_func($this->customBehavior, $this->id);
			echo "</div>";
		}

		if ($this->editlistFirst)
		{
			if (!is_null($this->editlist) && is_a($this->editlist, '\LWS\Adminpanel\Internal\EditlistControler'))
			{
				echo "<div class='group-spanned'>";
				$this->editlist->display();
				echo "</div>";
			}
		}

		foreach ($this->m_FieldArray as $field)
		{
			if (!$field->isHidden())
			{
				echo sprintf("<div %s>", $field->getExtraCss('row_class', 'class', false, 'lws-group-row'));
				$id = esc_attr($field->id());
				$class = '';
				//$class .= ($field->isAdvanced() ? " lws_advanced_option" : '');
				$class .= $field->getRequirementClass();
				$args = $field->getRequirementArgs();

				if ($field->separator()) {
					echo "<div class='field-sep'></div>";
				}
				$help = $field->help();
				if (!$help)
					$help = $field->getTooltips();
				if ($help)
					echo "<div class='field-help'$args>{$help}</div>";

				if ($field->title())
				{
					$label = $field->label();
					echo sprintf(
						"<div class='%s'%s><label for='%s'>%s</label>",
						$field->addStrongClass('field-label' . $class),
						$args, $id, $label
					);
					if ($help)
						echo "<div class='bt-field-help'>?</div>"; // button to display help above
					echo "</div>";
				}
				else
				{
					$class .= ' group-spanned';
				}

				echo "<div class='field-input{$class}'$args>";
				$field->input();
				echo "</div>";
				echo "</div>";
			}
			else
				$field->input();
		}

		if (!$this->editlistFirst)
		{
			if (!is_null($this->editlist) && is_a($this->editlist, '\LWS\Adminpanel\Internal\EditlistControler'))
			{
				echo "<div class='group-spanned'>";
				$this->editlist->display();
				echo "</div>";
			}
		}

		if ($this->customDelayedBehavior != null && is_callable($this->customDelayedBehavior))
		{
			echo "<div class='group-spanned'>";
			call_user_func($this->customDelayedBehavior, $this->id);
			echo "</div>";
		}

		echo "</div>";
		if (isset($this->extra['doclink']) && $this->extra['doclink']) {
			$label = __("Documentation", 'lws-adminpanel');
			echo "<div class='doc-line'><div class='doc-left'></div><div class='doc-right'>";
			echo "<a href='{$this->extra['doclink']}'  target='_blank' class='group-doc'>{$label}</a>";
			echo "</div></div>";
		}

		echo "</div>";
	}

	protected function groupTitleLine()
	{
		if ($this->title) {
			$class = 'group-title-line';
			$icon = $this->getIcon('group-icon');
			if ($icon)
				$class .= ' has-icon';
			$expandIcon = ($this->collapsed ? 'lws-icon-plus' : 'lws-icon-minus');

			return <<<EOT
<div class='{$class}'>
	{$icon}<div class='group-title'>{$this->title}</div>
	<div class='group-expand {$expandIcon}'></div>
</div>
EOT;
		} else {
			return sprintf("<div id='%s'></div>", $this->titleId());
		}
	}

	protected function getIcon($css)
	{
		if ($this->icon) {
			return sprintf(
				"<div class='$css %s' data-lws-icon='%s'></div>",
				$this->icon, $this->icon
			);
		} else if ($this->image) {
			return sprintf(
				"<div class='$css'><img src='%s'/></div>",
				\esc_attr($this->image)
			);
		} else {
			return '';
		}
	}

	public function hasFields($excludeGizmo = false)
	{
		if ($excludeGizmo)
		{
			foreach ($this->m_FieldArray as $f)
			{
				if (!$f->isGizmo())
					return true;
			}
			return false;
		}
		else
			return !empty($this->m_FieldArray);
	}

	public function getFields()
	{
		return $this->m_FieldArray;
	}

	public function mergeFields(&$fields)
	{
		foreach ($this->m_FieldArray as $f)
			$fields[] = $f;
	}

	public function getSmallBar()
	{
		return sprintf(
			'<a href="#%s" class="navitem-group-link">%s<div class="title">%s</div></a>',
			\esc_attr($this->targetId()),
			$this->getIcon('icon'),
			$this->title()
		);
	}
}
