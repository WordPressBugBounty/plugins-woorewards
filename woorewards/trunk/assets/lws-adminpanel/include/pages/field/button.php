<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();

/** Expect a 'callback' entry in extra which refers to a callable.
 * The callback get two arguments: button_id, array with all inputs of the group (id => value).
 * This callback should return false for failure.
 *
 *	A button can be a link to.
 *	All input in the same group will be send.
 *	Expect a 'link' entry in extra with an array with one item in it:
 * * ['post' => 'https://full-url.com'] or
 * * ['ajax'  => 'ajax-action-only']
 *
 * All button can be embeded inside a container by setting a extra['container'] = array('tag'=>'span', 'class'=>'my_css_class')
 *
 * On success, a string can be returned which will be displayed on html after the button. */
class Button extends \LWS\Adminpanel\Pages\Field
{
	public function __construct($id='', $title='', $extra=null)
	{
		parent::__construct($id, $title, $extra);
		$this->gizmo = true;
	}

	public function label()
	{
		if (isset($this->extra['text']) || isset($this->extra['html']))
			return parent::label();
		else
			return ""; /// title will be used as button text
	}

	public function title()
	{
		if (isset($this->extra['text']) || isset($this->extra['html']))
			return parent::title();
		else
			return ""; /// title will be used as button text
	}

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
		$text = $this->getExtraValue('html');
		if (!$text)
			$text = esc_attr($this->getExtraValue('text', $this->m_Title));
		$class = (isset($this->extra['class']) && is_string($this->extra['class']) ? " {$this->extra['class']}" : '');

		$triggable = (isset($this->extra['callback']) && is_callable($this->extra['callback']));
		if( $triggable )
			$class .= ' lws_adm_btn_trigger';

		$attrs = array();
		$submit = $this->getExtraValue('link');
		if( $submit )
		{
			$class .= ' lws_adm_btn_group_submit';
			$attrs['data-method'] = 'post';

			if (\is_array($submit)) {
				$method = \array_keys($submit)[0];
				$attrs['data-action'] = $submit[$method];
				if ('ajax' == \strtolower($method))
					$attrs['data-method'] = 'get';
			} else {
				$attrs['data-action'] = $submit;
			}
		}
		if ($this->getExtraValue('disabled', false))
			$attrs['disabled'] = 'disabled';

		if( $triggable || $submit )
			$class .= ' lws-adm-btn-trigger';

		$out = '';
		$tag = 'span';
		if( isset($this->extra['container']) )
		{
			$cc = '';
			if( is_array($this->extra['container']) )
			{
				if( isset($this->extra['container']['tag']) )
					$tag = $this->extra['container']['tag'];
				if( isset($this->extra['container']['class']) )
					$cc = $this->extra['container']['class'];
			}
			else
				$cc = $this->extra['container'];
			$out .= "<$tag class='$cc'>";
		}

		$attrs = $this->getDomAttributes($attrs);
		$out .= "<div class='lws-adm-btn$class' id='{$this->m_Id}' type='button'{$attrs}>$text</div>";
		if( $triggable || $submit ) // answer zone
			$out .= "<div class='lws-adm-btn-trigger-response'></div>";

		if( isset($this->extra['container']) )
			$out .= "</$tag>";
		return $out;
	}
}
