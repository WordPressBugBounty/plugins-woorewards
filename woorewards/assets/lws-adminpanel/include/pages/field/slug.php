<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


class Slug extends \LWS\Adminpanel\Pages\Field
{
	public function input()
	{
		$name = $this->m_Id;
		$value = $this->readOption();
		echo sprintf(
			"<input class='%s lws-input-slug' type='text' pattern='[a-z0-9]+(-[a-z0-9]+)*' name='%s' value='%s' placeholder='slug' />",
			\esc_attr($this->style),
			\esc_attr($name),
			\esc_attr($value)
		);
	}
}