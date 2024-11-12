<?php

namespace LWS\Adminpanel\Pages\Field;

if (!defined('ABSPATH')) {
	exit();
}


/** Designed to be used inside Wizard only.
 * Behavior is similar to a radio,
 * But choices looks like tiles with a grid layout. */
class RadioGrid extends \LWS\Adminpanel\Pages\Field
{

	/** @return string field html. */
	public static function compose($id, $extra = null)
	{
		$me = new self($id, '', $extra);
		return $me->html();
	}

	public function input()
	{
		echo $this->html();
	}

	public function title()
	{
		$type = $this->getExtraValue('type');
		if (empty($type) || $type === 'big-icon' || $type === 'auto-cols') {
			return parent::title();
		}
		return '';
	}

	/** Several types available
	 * wizard-choice	→ Big items with opacity background, big icon and compatibility div
	 * large		 	→ Small items. Gradient background with opacity effect on mouse over
	 * big-icon			→ Medium items. Vertical display with big icon on top and small label at the bottom
	 */
	protected function getGridItem($type, $opt, $name, $value)
	{
		$opt = \array_merge(array(
			'value'      => '',
			'class'      => '',
			'icon'       => '',
			'image'		 => '',
			'label'      => '',
			'descr'      => '',
			'color'      => '',
			'attributes' => array(),
		), $opt);
		$icon  = ($opt['icon']  ? sprintf("<div class='icon %s'></div>", \esc_attr($opt['icon'])) : '');
		if ($opt['image']) {
			$icon = sprintf("<div class='image'><img src='%s' class='opt-image'/></div>", \esc_attr($opt['image']));
		}
		$descr = ($opt['descr'] ? "<div class='descr'>{$opt['descr']}</div>" : '');

		switch ($type) {
			case 'wizard-choice':
				$block = <<<EOT
<div class="container-background"></div>
{$icon}
<div class="text">
	<div class="title">{$opt['label']}</div>
	{$descr}
</div>
EOT;
				break;
			case 'large':
				$block = <<<EOT
<div class='inner-background'></div>
{$icon}
<div class='label'>{$opt['label']}</div>
{$descr}
EOT;
				break;
			case 'auto-cols':
				$block = <<<EOT
<div class='inner-background'></div>
{$icon}
<div class='label'>{$opt['label']}</div>
EOT;
				break;
			case 'big-icon':
				$block = <<<EOT
<div class='icon-background'></div>
{$icon}
<div class='label-background'></div>
<div class='label'>{$opt['label']}</div>
EOT;
				break;
			default:
				$block = "{$icon}<div class='label'>{$opt['label']}</div>";
				break;
		}

		$class = ('radiogrid-item lws_radiobutton_radio ' . $type . ($opt['value'] == $value ? ' selected' : ''));
		if ($opt['class'])
			$class .= (' ' . \esc_attr($opt['class']));
		$opt['value'] = \esc_attr($opt['value']);
		$color        = ($opt['color']  ? sprintf(" style='%s'", \lws_get_theme_colors('--grid-item-color', $opt['color'])) : '');
		$attributes   = $this->formatAttributes($opt['attributes']);
		return "<div class='$class'{$color} data-input='#{$name}' data-value='{$opt['value']}'{$attributes}>{$block}</div>";
	}

	public function html()
	{
		\wp_enqueue_script('lws-adm-radiogrid', LWS_ADMIN_PANEL_JS . '/controls/radiogrid.js', array('jquery'), LWS_ADMIN_PANEL_VERSION, true);

		$name    = \esc_attr($this->id());
		$type    = $this->getExtraValue('type');
		$columns = $this->getExtraValue('columns');
		$value   = $this->readOption(true);
		$class   = $this->getExtraCss();
		$attrs   = $this->getDomAttributes();
		$source  = $this->getExtraValue('source', array());
		$items   = '';
		foreach ($source as $opt) {
			$items .= $this->getGridItem($type, $opt, $name, $value);
		}

		$value = \esc_attr($value);
		$input = "<input id='{$name}' name='{$name}' value='{$value}' type='hidden'{$attrs}{$class}>";
		switch ($type) {
			case 'large':
				$title = parent::title();
				return <<<EOT
{$input}
<div class="radiogrid-large-container">
	<div class="large-opt-title">{$title}</div>
	<div class="large-grid">{$items}</div>
</div>
EOT;
			case 'big-icon':
				$cols = $columns ? $columns : \str_repeat(' 1fr', \count($source));
				return "{$input}<div class='radiogrid-big-icon-grid' style='grid-template-columns:{$cols};'>{$items}</div>";
			case 'auto-cols':
				$cols = $columns ? $columns : "grid-template-columns: repeat(auto-fit, minmax(150px, 1fr))";
				return "{$input}<div class='radiogrid-auto-grid' style='grid-template-columns:{$cols};'>{$items}</div>";
			default:
				return "{$input}<div class='radiogrid-shallow-container'>{$items}</div>";
		}
	}
}