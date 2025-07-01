<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


class WPEditor extends \LWS\Adminpanel\Pages\Field
{
	public function input()
	{
		$name = $this->extra['name'] ?? $this->m_Id;
		$settings = (array)($this->extra['settings'] ?? $this->extra);
		$rename = \str_replace(['[', ']'], '_', $name);
		if ($rename !== $name && !isset($settings['textarea_name'])) {
			$settings['textarea_name'] = $name;
		}
		$value = $this->readOption(false);
		\wp_editor($value, $rename, $settings);
	}
}