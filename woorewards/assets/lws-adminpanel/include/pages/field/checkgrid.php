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
		$html = "<div class='lws_checkgrid lws-checkgrid {$ddclass}' id='sort-{$name}'>";
		$rang = 0;
		foreach ($value as $opt) {
			$val = $opt['value'];
			$label = $opt['label'];
			$active = (isset($opt['active'])) ? $opt['active'] : '';
			$checkIcon = ($active) ? 'lws-icon-checkbox-checked' : 'lws-icon-checkbox-unchecked';
			$actClass = ($active) ? 'checked' : '';
			$html .= "<div class='lws_checkgrid_item checkgrid-item " . esc_attr($actClass) . "'>"
				. "<input type='hidden' name='" . esc_attr($name) . "[value][]' value='" . esc_attr($val) . "'/>"
				. "<input type='hidden' name='" . esc_attr($name) . "[label][]' value='" . esc_attr($label) . "'/>"
				. "<input type='hidden' class='lws_cg_active' name='" . esc_attr($name) . "[active][]' value='" . esc_attr($active) . "'/>"
				. "<div class='checkbox " . esc_attr($checkIcon) . "'></div>"
				. "<div class='label'>" . esc_html($label) . "</div>"
				. "</div>";
			$rang += 1 ;
		}
		$html .= "</div>";
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}