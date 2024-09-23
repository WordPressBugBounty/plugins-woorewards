<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


class Help extends \LWS\Adminpanel\Pages\Field
{
	private $content = '';

	public function __construct($id='', $title='', $extra=null)
	{
		parent::__construct($id, $title, $extra);
		$this->gizmo = true;

		$this->content = $this->getExtraValue('help');
		if( isset($this->extra['help']) )
			unset($this->extra['help']);
	}

	public function input()
	{
		$class = 'field-text';
		switch($this->getExtraValue('type'))
		{
			case 'youtube':
				$icon = 'lws-icon-youtube';
				$class .= ' lws-youtube';
				break;
			case 'pub':
				$icon = 'lws-icon-billboard';
				$class .= ' lws-pub';
				break;
			default:
				$icon = 'lws-icon-bulb';
				$class .= ' lws-help';
		}
		if (\is_array($this->content))
			$this->content = \LWS\Adminpanel\Tools\Conveniences::array2html($this->content);

		$id = \esc_attr(empty($this->id()) ? \md5($this->content) : $this->id());
		echo <<<EOT
<div class='{$class}' id='{$id}'>
	<div class='drop-cap lws-icon {$icon}'></div>
	<div class='content'>{$this->content}</div>
</div>
EOT;
	}
}