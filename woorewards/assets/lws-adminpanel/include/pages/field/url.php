<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


class URL extends \LWS\Adminpanel\Pages\Field
{
	public function input()
	{
		$name = $this->m_Id;
		$value = $this->readOption();
		echo sprintf(
			"<input class='%s' type='url' name='%s' value='%s' placeholder='URL' />",
			\esc_attr($this->style),
			\esc_attr($name),
			\esc_attr($value)
		);
	}
}