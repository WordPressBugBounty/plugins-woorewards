<?php
namespace LWS\Adminpanel\EditList;
if( !defined( 'ABSPATH' ) ) exit();


/** A simple select field with a button.
 * Look for $_GET[$name] in your EditListSource::read implemention. */
class FilterSimpleSelect extends Filter
{
	protected $name = '';
	protected $source = array();
	protected $extra = array();
	protected $placeholder = '';
	protected $filterLabel = '';
	protected $buttonLabel = '';
	protected $defaultValue = false;

	/** @param $name you will get the filter value in $_GET[$name].
	 *	@param $options is a LAC-select formated source array or an ajax action string. */
	function __construct($name, $options, $filterLabel='', $placeholder='', $buttonLabel='', $default=false, $extra = array())
	{
		parent::__construct();
		$this->_class = "lws-editlist-filter-search lws-editlist-filter-" . strtolower($name);
		$this->name = $name;
		$this->source = $options;
		$this->extra = $extra;
		$this->placeholder = $placeholder;
		$this->filterLabel = $filterLabel;
		$this->buttonLabel = $buttonLabel;
		$this->defaultValue = $default;
	}

	function input($above=true)
	{
		$search = '';
		if (isset($_GET[$this->name]) && \strlen(\trim($_GET[$this->name])))
			$search = \esc_attr(\trim(\sanitize_text_field($_GET[$this->name])));

		$title = $this->filterLabel ? $this->filterLabel : __('Narrow your search', 'lws-adminpanel');
		$button = $this->buttonLabel ? $this->buttonLabel : __('Search', 'lws-adminpanel');
		$input = '';
		foreach ($this->extra as $name => $value) {
			$input .= "<input type='hidden' name='{$name}' value='{$value}'>";
		}
		$select = array(
			'noconfirm'   => true,
			'placeholder' => $this->placeholder,
		);
		if ($search)
			$select['value'] = $search;
		if (\is_array($this->source))
			$select['source'] = $this->source;
		else
			$select['ajax'] = $this->source;
		if ($this->defaultValue)
			$select['default'] = $this->defaultValue;
		$input .= \LWS\Adminpanel\Pages\Field\LacSelect::compose($this->name, $select);

		return <<<EOT
<div class='lws-editlist-filter-box end'>
	<div class='lws-editlist-filter-box-title'>{$title}</div>
	<div class='lws-editlist-filter-box-content'>
		{$input}<button class='lws-adm-btn lws-editlist-filter-btn'>{$button}</button>
	</div>
</div>
EOT;
	}
}
