<?php

namespace LWS\Adminpanel\Pages\Field;

if (!defined('ABSPATH')) {
	exit();
}


/** Designed to be used inside Wizard only.
 * Behavior is similar to a radio,
 * But choices looks like tiles with a grid layout. */
class CheckGrid extends \LWS\Adminpanel\Pages\Field
{
	public function input()
	{
		\wp_enqueue_script('lws-checkgrid');

		$name = \esc_attr($this->id());
		$value = $this->readOption(false);
		if(!$value){
			$value = $this->getExtraValue('source', array());
		}
		//error_log(print_r($value,true));
		$ddclass = ($this->getExtraValue('dragndrop')) ? 'lws_checkgrid_sortable' : '';
		echo "<div class='lws_checkgrid lws-checkgrid {$ddclass}' id='sort-{$name}'>";
		$rang = 0;
		foreach ($value as $opt) {
			$val = $opt['value'];
			$label = $opt['label'];
			$active = (isset($opt['active'])) ? $opt['active'] : '';
			$checkIcon = ($active) ? 'lws-icon-checkbox-checked' : 'lws-icon-checkbox-unchecked';
			$actClass = ($active) ? 'checked' : '';
			echo <<<EOT
<div class='lws_checkgrid_item checkgrid-item {$actClass}'>
	<input type='hidden' name='{$name}[value][]' value='{$val}'/>
	<input type='hidden' name='{$name}[label][]' value='{$label}'/>
	<input type='hidden' class='lws_cg_active' name='{$name}[active][]' value='{$active}'/>
	<div class='checkbox {$checkIcon}'></div>
	<div class='label'>$label</div>
</div>
EOT;
			$rang += 1 ;
		}
		echo "</div>";
	}
}