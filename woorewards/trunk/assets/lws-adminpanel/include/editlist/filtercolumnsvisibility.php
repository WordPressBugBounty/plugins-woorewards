<?php

namespace LWS\Adminpanel\EditList;

if (!defined('ABSPATH')) exit();


/** A filter that allows to show or hide columns of the editlist */
class FilterColumnsVisibility extends Filter
{
	protected $name = '';
	protected $title = '';
	protected $extra = array();

	/** @param $name you will get the filter value in $_GET[$name]. */
	function __construct($name, $title, $extra = array())
	{
		parent::__construct();
		$this->_class = "lws-editlist-filter-search lws-editlist-filter-column-visibility";
		$this->name = $name;
		$this->title = $title;
		$this->extra = $extra;
	}

	/** In the editlist's labels() function, set the third element of the item's array to true to allow show/hide
	 *  $labels['key'] = array('column name', 'grid column definition', true);
	 */
	function input($above = true, $columns = array())
	{
		$retour = "<div class='lws-editlist-filter-box end'><div class='lws-editlist-filter-box-title'>{$this->title}</div>";
		$retour .= "<div class='visibility-cb-line'>";
		foreach ($columns as $key => $value)
		{
			if (is_array($value) && isset($value[2]) && $value[2])
			{
				$name = $this->name . '_' . $key;
				$retour .= <<<EOT
<div class='visibility-cb-wrapper'>
	<label class='lws-checkbox-wrapper'>
		<input type='checkbox' class='lws-ignore-confirm editlist_cb_visibility' name='$name' data-name='$key' checked />
		<div class='lws-checkbox small'></div>
	</label>
	<div class='visibility-cs-wrapper'>{$value[0]}</div>
</div>
EOT;
			}
		}
		$retour .= "</div></div>";
		return $retour;
	}
}
