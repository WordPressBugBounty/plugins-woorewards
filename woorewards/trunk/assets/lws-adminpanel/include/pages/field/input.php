<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


class Input extends \LWS\Adminpanel\Pages\Field
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
		$value = $this->readOption();

		$class = ($this->style . ' lws-input-input');
		if( isset($this->extra['class']) && !empty($this->extra['class']) )
			$class .= (' ' . $this->extra['class']);

		$attrs = " class='".\esc_attr($class)."'";
		$attrs .= $this->getExtraAttr('placeholder', 'placeholder');
		$attrs .= $this->getExtraAttr('pattern', 'pattern');
		$attrs .= $this->getExtraAttr('type', 'type', 'text');
		$attrs .= $this->getExtraValue('disabled', false) ? ' disabled' : '';
		$attrs .= $this->getExtraValue('readonly', false) ? ' readonly' : '';

		$id = isset($this->extra['id']) ? (" id='".\esc_attr($this->extra['id'])."'") : '';

		$size = isset($this->extra['size']) ? (" size='" . \esc_attr($this->extra['size']) . "'") : '';

		if( isset($this->extra['attrs']) && is_array($this->extra['attrs']) )
		{
			foreach( $this->extra['attrs'] as $k => $v )
				$attrs .= " $k='".\esc_attr($v)."'";
		}
		$others = $this->getDomAttributes();

		return "<input name='$name' value='$value'$attrs{$others}$id$size>";
	}
}