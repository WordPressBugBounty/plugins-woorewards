<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();

class Text extends \LWS\Adminpanel\Pages\Field
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
		$value = '';
		$dft = '';
		$prop = '';
		$source = (isset($this->extra['source']) ? " data-source='{$this->extra['source']}'" : "");
		$type =  (isset($this->extra['type'])) ? $this->extra['type'] : "text";
		if( isset($this->extra['values']) )
		{
			foreach($this->extra['values'] as $k => $v)
			{
				$prop = esc_attr($k);
				$value = esc_attr($v);
				break;
			}
		}
		else
			$value = $this->readOption();

		if( isset($this->extra['defaults']) )
		{
			if( is_array($this->extra['defaults']) )
			{
				if( !empty($prop) )
				{
					if( isset($this->extra['defaults'][$prop]) )
						$dft = esc_attr($v);
				}
				else foreach($this->extra['defaults'] as $k => $v)
				{
					$prop = esc_attr($k);
					$dft = esc_attr($v);
					break;
				}
			}
			else if( is_string($this->extra['defaults']) )
				$prop = $this->extra['defaults'];
		}
		$mix = ((\is_string($value) ? strlen($value) : !empty($value)) ? $value : $dft);

		$size = '';
		if( isset($this->extra['size']) && is_numeric($this->extra['size']) && $this->extra['size'] > 0 )
			$size = " size='{$this->extra['size']}'";
		$maxlen = '';
		if( isset($this->extra['maxlength']) && is_numeric($this->extra['maxlength']) && $this->extra['maxlength'] > 0 )
			$maxlen = " maxlength='{$this->extra['maxlength']}'";
		$pattern = '';
		if( isset($this->extra['pattern']) && (\is_numeric($this->extra['pattern']) || (is_string($this->extra['pattern']) && strlen($this->extra['pattern']))) )
			$pattern = $this->getExtraAttr('pattern', 'pattern');
		$placeholder = '';
		if( isset($this->extra['placeholder']) && is_string($this->extra['placeholder']) && strlen($this->extra['placeholder']) )
			$placeholder = $this->getExtraAttr('placeholder', 'placeholder');
		$required = (isset($this->extra['required']) && boolval($this->extra['required'])) ? ' required' : '';
		$disabled = $this->getExtraValue('disabled', false) ? ' disabled' : '';
		$readonly = $this->getExtraValue('readonly', false) ? ' readonly' : '';
		$id = $this->getExtraAttr('id', 'id');

		$class = $this->getExtraCss('class', 'class', false, $this->style);
		$attrs = $this->getDomAttributes();

		if( empty($prop) )
		{
			return "<input {$class} type='{$type}' name='{$this->m_Id}' value='$mix'$size$maxlen$pattern$placeholder$required$disabled$readonly$id{$attrs} />";
		}
		else
		{
			return <<<EOT
<div class='lwss-css-inputs'>
	<input {$class} type='$type' data-css='$prop' data-lwss='$dft'$source value='$mix'$maxlen$pattern$placeholder$required$disabled$readonly$id{$attrs} />
	<input class='lwss-merge-css' type='hidden' name='{$this->m_Id}' value='$prop:$value' />
</div>
EOT;
		}
	}
}