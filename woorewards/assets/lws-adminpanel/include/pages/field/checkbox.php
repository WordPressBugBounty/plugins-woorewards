<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();

class Checkbox extends \LWS\Adminpanel\Pages\Field
{
	public static function compose($id, $extra=null)
	{
		$me = new self($id, '', $extra);
		return $me->html();
	}

	public function input()
	{
		echo $this->html();
	}

	private function html()
	{
		$name = $this->m_Id;
		$value = '';
		$option = false;

		if( isset($this->extra['value']) )
		{
			$option = boolval($this->extra['value']);
		}
		else if( isset($this->extra['checked']) )
		{
			$option = boolval($this->extra['checked']);
		}
		else
		{
			$option = get_option($this->m_Id, false);
			if( $option === false && $this->hasExtra('default') )
				$option = boolval($this->extra['default']);
		}
		if( $option )
			$value = "checked='checked'";

		$class = $this->getExtraCss('class', 'class', false, $this->style);
		$layout = $this->getExtraValue('layout', '');
		$size = $this->getExtraValue('size', '');
		if ($size) $size =  ' ' . $size;

		/* Retrocompatibility */
		if (strpos($class, 'lws_switch') !== false) {
			$class = str_replace('lws_switch', '', $class);
			$layout = 'switch';
		}
		if (strpos($class, 'lws_checkbox') !== false) {
			$class = str_replace('lws_checkbox', '', $class);
			$layout = '';
		}
		/* Extra data for colors and labels */
		$data = $this->getExtraValue('data', array());
		$colorstring = '';
		if (isset($data['colorleft'])) {
			$colorstring .= \lws_get_theme_colors('--left-color', $data['colorleft']);
		}
		if (isset($data['colorright'])) {
			$colorstring .= \lws_get_theme_colors('--right-color', $data['colorright']);
		}
		if ($colorstring) {
			$colorstring = " style='" . $colorstring . "'";
		}
		$leftlabel = isset($data['left']) ? "<div class='switch-left'>" . $data['left'] . "</div>" : '';
		$rightlabel = isset($data['right']) ? "<div class='switch-right'>" . $data['right'] . "</div>" : '';
		$leftright = $leftlabel ? ' leftright' : '';

		$disabled = '';
		$disableclass = '';
		if ($this->getExtraValue('disabled', false)) {
			$disableclass = " disabled";
			$disabled = "  disabled onclick='return false;'";
		}

		$id = $this->getExtraAttr('id', 'id');

		$attrs = $this->getExtraValue('attributes', array());
		if ($attrs && \is_array($attrs)) {
			foreach ($attrs as $attr => $val) {
				$attrs[$attr] = sprintf(" data-%s='%s'", $attr, \esc_attr($val));
			}
			$attrs = \implode('', $attrs);
		} else {
			$attrs = '';
		}

		$ac = '';
		if (isset($this->extra['autocomplete'])) {
			$ac = sprintf('  autocomplete="%s"', $this->extra['autocomplete'] ? 'on' : 'off');
		}

		switch ($layout) {
			case 'switch':
				$checkbox = <<<EOT
				<div class='lws-switch-bigwrapper{$disableclass}'$colorstring>
					$leftlabel
					<label class='lws-switch-wrapper$leftright'>
						<input type='checkbox' name='$name' $value$class$disabled$id$attrs$ac />
						<div class='knobs'></div>
						<div class='layer'></div>
					</label>
					$rightlabel
				</div>
EOT;
				break;
			case 'toggle':
				$checkbox = <<<EOT
				<div class='lws-toggle-wrapper'$colorstring>
					<label class='lws-toggle{$disableclass}'>
						<input type='checkbox' name='$name' $value$class$disabled$id$attrs$ac />
						<div class='inner'><span class='bullet'></span></div>
					</label>
				</div>
EOT;
				break;
			default:
				$checkbox = <<<EOT
				<label class='lws-checkbox-wrapper{$disableclass}'>
					<input type='checkbox' name='$name' $value$class$disabled$id$attrs$ac />
					<div class='lws-checkbox$size'></div>
				</label>
EOT;
				break;
		};

		return $checkbox;
	}
}
