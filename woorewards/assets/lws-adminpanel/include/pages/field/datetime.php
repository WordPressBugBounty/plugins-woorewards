<?php
namespace LWS\Adminpanel\Pages\Field;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Input Date, time, is_UTC.
 *	Since input type="datetime" is poorly supported yet,
 *	and it seems people does not care. */
class DateTime extends \LWS\Adminpanel\Pages\Field
{
	/** @return string field html. */
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
		\wp_enqueue_script('lws-adm-datetime', LWS_ADMIN_PANEL_JS.'/controls/datetime.js', array('jquery'/*, 'lws-checkbox'*/), LWS_ADMIN_PANEL_VERSION, true);

		$id = $this->id();
		$value = $this->readOption(false);
		$tz = $this->getTimezoneOffset();
		$text = '';
		$date = '';
		$time = '';
		$utc  = false;

		if ($value) {
			if (\is_string($value)) {
				$text = $value;
				$value = \date_create($value);
			} else {
				$text = $value->format(DATE_W3C);
			}
			if ($value) {
				$utc = true;
				if (\intval($tz)) {
					$matches = false;
					if (\preg_match('/.{10}\s*(Z|[+-]\d+(?::\d+)?)?$/', $text, $matches)) { // at least a date, then an offset
						$utc = (isset($matches[1]) ? !\intval(\str_replace(':', '', $matches[1])) : true);
					}
				}
				$value->setTimeZone($utc ? new \DateTimeZone('UTC') : \wp_timezone());
				$date = $value->format('Y-m-d');
				$time = $value->format('H:i:s');
			} else {
				$text = '';
			}
		}
		$class = $this->ignoreConfirm('lws_adm_datetime');

		if (false !== $tz) {
			$checked = ($utc ? ' checked="checked"' : '');
			$utc = "&nbsp;(UTC<input class='{$class} sub utc' data-for='{$id}' type='checkbox'{$checked}>)";
		} else {
			$utc = '';
		}

		// looks better with the small box finally
		// ... build a time picker?
		return <<<EOT
<div class='lws-editlist-opt-multi lws-field-datetime'>
	<input class="lws-input {$class} sub date" data-for="{$id}" type="date" value="{$date}">
	&nbsp;–&nbsp;
	<input class="lws-input {$class} sub time" data-for="{$id}" type="text" size="8" value="{$time}" placeholder="hh:mm:ss">
	{$utc}
	<input class="{$class} sub offset" data-for="{$id}" type="hidden" value="{$tz}">
	<input class="{$class} master" type="hidden" name="{$id}" value="{$text}">
</div>
EOT;
	}

	/** since WP 5.3 return a string +/-offset_in_sec
	 *	before WP 5.3, return false. */
	function getTimezoneOffset()
	{
		static $offset = null;
		if (null === $offset) {
			$offset = false;
			if (\function_exists('wp_timezone')) {
				$tz = \wp_timezone();
				if (!$tz) {
					$offset = '+0';
				} else {
					$offset = sprintf('%+d', \date_create('now', $tz)->getOffset());
				}
			}
		}
		return $offset;
	}
}