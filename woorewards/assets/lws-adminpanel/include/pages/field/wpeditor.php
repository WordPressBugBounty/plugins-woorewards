<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


class WPEditor extends \LWS\Adminpanel\Pages\Field
{
	public function input()
	{
		$name = $this->extra['name'] ?? $this->m_Id;
		$settings = $this->extra['settings'] ?? $this->extra;
		$value = $this->readOption(false);
		\wp_editor($value, $name, $settings);
	}
}