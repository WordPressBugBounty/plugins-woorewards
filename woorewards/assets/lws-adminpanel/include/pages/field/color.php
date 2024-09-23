<?php

namespace LWS\Adminpanel\Pages\Field;

if (!defined('ABSPATH')) exit();

class Color extends \LWS\Adminpanel\Pages\Field
{
	public static function compose($id, $extra = null)
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
		$value = $this->readOption();

		$attrs = "type='text' value='{$value}' name='{$name}' data-alpha-enabled='true' data-alpha-color-type='hex'"
		. $this->getExtraCss('class', 'class', 'lws-color-picker', $this->style)
		. $this->getExtraAttr('placeholder', 'placeholder')
		. $this->getExtraAttr('pattern', 'pattern')
		. $this->getExtraAttr('id', 'id')
		. ($this->getExtraValue('disabled', false) ? ' disabled' : '')
		. ($this->getExtraValue('readonly', false) ? ' readonly' : '');

		if (isset($this->extra['attrs']) && \is_array($this->extra['attrs'])) {
			foreach ($this->extra['attrs'] as $k => $v)
				$attrs .= sprintf(' %s="%s"', $k, \esc_attr($v));
		}
		return "<input {$attrs}>";
	}
}
