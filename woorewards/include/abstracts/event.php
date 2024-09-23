<?php
namespace LWS\WOOREWARDS\Abstracts;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOREWARDS_INCLUDES . '/core/pool.php';

/** Base class for each way to earn points.
 *	To be used, an Event must be declare by calling Event::register @see IRegistrable
 *
 *	The final purpose of an Event is to generate points @see addPoint()
 *
 *	Each pool is in charge to :
 * * install its selected events @see _install()
 * * save specific settings @ss _save()
 * * load specific data @see _fromPost()
 *
 *	Anyway, an event is available for information or selection and so can be instanciated from anywhere.
 *  */
abstract class Event implements ICategorisable, IRegistrable
{
	const POST_TYPE = 'lws-wre-event';
	private static $s_events = array();

	protected $pool          = null;
	protected $poolId        = false;
	protected $dataKeyPrefix = false;
	protected $cooldownInfo  = false;
	protected $rolling       = true;
	protected $resetDT       = false;
	protected $multiplier    = '1';
	protected $eventCreationDate = null;
	public $title            = '';
	public $id               = false;
	public $name             = false;
	public $gainAlt          = '';

	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	abstract protected function _fromPost(\WP_Post $post);
	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	abstract protected function _save($id);
	/** @return string a human readable type for UI */
	abstract public function getDisplayType();
	/** Add hook to grab events and add points. */
	abstract protected function _install();

	/** To be overridden.
	 *	@return (bool) if event support that rule.
	 *	Cooldown: do not earn points if the same event triggered
	 *	too many time in a period. */
	function isRuleSupportedCooldown() { return false; }

	/**	@param int userId
	 * 	@param $defaultUnset (bool) the value returned if no cooldown defined;
	 *	default is true, so no cooldown means that event is always available.
	 *	@return (bool) true if cooldown is not already full
	 *	for the given user. */
	function isCool($userId, $defaultUnset=true, $defaultNoUser=false)
	{
		if (!$this->isRuleSupportedCooldown())
			return $defaultUnset;
		if (!($ci = $this->getCooldownInfo(true)))
			return $defaultUnset;
		if (!$userId)
			return $defaultNoUser;

		global $wpdb;
		$req = \LWS\Adminpanel\Tools\Request::from($wpdb->lwsWooRewardsHistoric, 'h');
		$req->select('COUNT(h.id)');
		$req->where('h.`user_id`=%d AND h.`origin`=%s');
		$req->arg(array($userId, $this->getId()));

		$dt = $this->getCooldownResetDateTime();
		if ($dt) {
			// periodic range
			$dt = $this->updateCooldownResetDateTime($dt, $ci, true)->setTimeZone(new \DateTimeZone('UTC')); // history is stored UTC
			$req->where(sprintf("h.`mvt_date` >= '%s'", $dt->format('Y-m-d H:i:s')));
		} else {
			// rolling
			$req->where(sprintf("DATE_ADD(h.`mvt_date`, %s) >= NOW()", $ci->duration->getSqlInterval()));
		}

		$c = $req->getVar();
		if (false === $c)
			return false; // means error occured in db
		return ($c < $ci->count);
	}

	function getCooldownText()
	{
		if ($ci = $this->getCooldownInfo(true)) {
			$period = $ci->duration->getPeriodText();
			if ($ci->duration->getCount() > 1)
				$period = sprintf(_x('%1$s %2$s', 'cooldown period eg. "3 Months"', 'woorewards-lite'), $ci->duration->getCount(), $period);
			if (1 == $ci->count)
				return sprintf(_x('Max. once every %1$s', 'cooldown display', 'woorewards-lite'), $period);
			else
				return sprintf(_x('Max. %2$s times per %1$s', 'cooldown display', 'woorewards-lite'), $period, $ci->count);
		}
		return '';
	}

	/** @return object|false or object with properties {count, period[, interval]} */
	function getCooldownInfo($withDateInterval=false)
	{
		if (!$this->isRuleSupportedCooldown())
			return false;
		if (!$this->cooldownInfo)
			return false;
		if (0 == $this->cooldownInfo->count || !\trim($this->cooldownInfo->period))
			return false;
		if ($withDateInterval && !$this->cooldownInfo->duration) {
			$this->cooldownInfo->duration = \LWS\Adminpanel\Tools\Duration::fromString($this->cooldownInfo->period);
			if ($this->cooldownInfo->duration->isNull()) {
				$this->cooldownInfo->count    = 0;
				$this->cooldownInfo->period   = '';
				return false;
			}
		}
		return $this->cooldownInfo;
	}

	function setCooldownInfo($count=0, $period=0.0)
	{
		$this->cooldownInfo = (object)array(
			'count'    => \absint($count),
			'period'   => $period,
			'duration' => false,
		);
		if (0 == $this->cooldownInfo->count || !\trim($this->cooldownInfo->period)) {
			$this->cooldownInfo->count  = 0;
			$this->cooldownInfo->period = '';
		}
	}

	/** Rolling period or periodic date range */
	public function isCooldownRolling()
	{
		return (bool)$this->rolling;
	}

	public function setCooldownRolling($yes=true)
	{
		$this->rolling = \boolval($yes);
		return $this;
	}

	/** If format is false, return a DateTimeImmutable.
	 *	Else return a string with date representation.
	 *	Or return false if no date set or cooldown is rolling. */
	public function getCooldownResetDateTime($format = false, $timezone=false)
	{
		if ($this->isCooldownRolling()) {
			return false;
		} elseif ($this->resetDT) {
			$dt = ($timezone ? $this->resetDT->setTimezone($timezone) : $this->resetDT);
			return ($format ? $dt->format($format) : $dt);
		} else {
			return false;
		}
	}

	/** store a datetimeimmutable
	 *	@param mixed $datetime DateTime|DateTimeImmutable|string|int
	 *	An integer is assumed UTC timestamp,
	 *	A string is assumed in website timestamp. */
	public function setCooldownResetDateTime($datetime)
	{
		if (!$datetime) {
			$this->resetDT = false;
		} elseif (\is_object($datetime)) {
			if (\is_a($datetime, 'DateTime'))
				$this->resetDT = \DateTimeImmutable::createFromMutable($datetime);
			elseif (\is_a($datetime, 'DateTimeImmutable'))
				$this->resetDT = $datetime;
		} elseif (\is_numeric($datetime)) {
			$this->resetDT = new \DateTimeImmutable();
			$this->resetDT = $this->resetDT->setTimestamp((int)$datetime);
		} else {
			$again = false;
			try{
				$this->resetDT = new \DateTimeImmutable($datetime, \wp_timezone());
			} catch (\Exception $e) {
				$again = \substr($datetime, 0, 9);
			}
			if ($again) { // fix bad datetime format
				try{
					$this->resetDT = new \DateTimeImmutable($again, \wp_timezone());
				} catch (\Exception $e) {
					$this->resetDT = false;
				}
			}
		}
		return $this;
	}

	public function updateCooldownResetDateTime($datetime, $cooldownInfo, $save=false)
	{
		if ($datetime && $cooldownInfo) {
			$now = \date_create_immutable();
			$interval = $cooldownInfo->duration->toInterval();
			$changed = false;

			if ($datetime <= $now) {
				// start in past (stay in past but as near as possible)
				$next = $datetime->add($interval);
				while ($next < $now) {
					$changed = true;
					$datetime = $next;
					$next = $datetime->add($interval);
				}
			} else {
				// start in futur
				while ($datetime > $now) {
					$changed = true;
					$datetime = $datetime->sub($interval);
				}
			}

			// maybe save the meta
			if ($changed && $save) {
				$this->setCooldownResetDateTime($datetime);
				\update_post_meta($this->id, 'wre_event_reset_date', $this->getCooldownResetDateTime('Y-m-d\TH:i:s e'));
			}
		}
		return $datetime;
	}

	/** If additionnal info should be displayed in settings form. */
	protected function getCooldownTooltips($text)
	{
		return $text;
	}

	static public $recurrentAllowed = false;
	protected $repeatInfo           = false;

	function isRepeatAllowed() {
		return self::$recurrentAllowed;
	}

	/** @return false|object with properties {count, period[, interval]} */
	function getRepeatInfo($withDateInterval=false)
	{
		if (!$this->isRepeatAllowed())
			return false;
		if (!$this->repeatInfo)
			return false;
		if (0 == $this->repeatInfo->count || !\trim($this->repeatInfo->period))
			return false;
		if ($withDateInterval && !$this->repeatInfo->duration) {
			$this->repeatInfo->duration = \LWS\Adminpanel\Tools\Duration::fromString($this->repeatInfo->period);
			if ($this->repeatInfo->duration->isNull()) {
				$this->repeatInfo->count    = 0;
				$this->repeatInfo->period   = '';
				return false;
			}
		}
		return $this->repeatInfo;
	}

	function setRepeatInfo($count=0, $period=0.0)
	{
		$this->repeatInfo = (object)array(
			'count'    => \absint($count),
			'period'   => $period,
			'duration' => false,
		);
		if (0 == $this->repeatInfo->count || !\trim($this->repeatInfo->period)) {
			$this->repeatInfo->count  = 0;
			$this->repeatInfo->period = '';
		}
	}

	static public $delayAllowed = false;
	protected $delay            = false;
	protected $maxTriggers      = false;

	function isDelayAllowed() {
		return self::$delayAllowed;
	}

	/**	@return \LWS\Adminpanel\Tools\Duration|false Duration instance or false if no delay */
	public function getDelay()
	{
		if (!$this->isDelayAllowed())
			return false;
		if (!$this->delay)
			return false;
		return $this->delay->isNull() ? false : $this->delay;
	}

	/** @param mixed $days (false|string|Duration) string use a DateInterval format */
	public function setDelay($days=false)
	{
		if( empty($days) )
			$this->delay = \LWS\Adminpanel\Duration::void();
		else if( is_a($days, '\LWS\Adminpanel\Duration') )
			$this->delay = $days;
		else
			$this->delay = \LWS\Adminpanel\Duration::fromString($days);
		return $this;
	}

	/** Override to allow max triggers, default is false */
	public function isMaxTriggersAllowed()
	{
		return false;
	}

	public function getMaxTriggers()
	{
		if (!$this->isMaxTriggersAllowed()) {
			return false;
		} elseif ($this->maxTriggers) {
			return $this->maxTriggers;
		} else {
			return false;
		}
	}

	public function setMaxTriggers($maxTriggers)
	{
		$this->maxTriggers = \intval($maxTriggers);
		if (!$this->maxTriggers)
			$this->maxTriggers = false;
	}

	public function incrTriggerCount($userId)
	{
		if ($userId && $this->getMaxTriggers() && ($id = $this->getId())) {
			$key = ('lws_wr_triggered_' . $id);
			$count = \intval(\get_user_meta($userId, $key, true));
			\update_user_meta($userId, $key, 1 + $count);
		}
	}

	public function getTriggerCount($userId)
	{
		if (!($userId && $this->getMaxTriggers()))
			return 0;
		else
			return \intval(\get_user_meta($userId, 'lws_wr_triggered_' . $this->getId(), true));
	}

	/** if feature not enabled or used, always return true.
	 *	else, a user is required to trigger the event
	 *	so we can check the counter.
	 *	@param $options (array|\WP_User|int) the user earning points, an array have to include an entry 'user'.
	 *	@param $doIncrement (bool) if true and can be triggered, then increment the counter.
	 *	@return (bool) true if no restriction set or event trigger count is not reached. */
	public function canBeTriggered($options, $doIncrement=false)
	{
		$max = $this->getMaxTriggers();
		if (!$max) {
			return true;
		}
		$userId = 0;
		if (\is_array($options) && isset($options['user']))
			$userId = $options['user'];
		if ($userId && \is_object($userId))
			$userId = $userId->ID;
		if (!$userId) {
			return false;
		}
		if ($this->getTriggerCount($userId) < $max) {
			if ($doIncrement)
				$this->incrTriggerCount($userId);
			return true;
		} else {
			return false;
		}
	}

	/**	@return array of data to feed the form @see getForm.
	 *	Each key should be the name of an input balise. */
	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = array(
			$prefix . 'multiplier'   => $this->getGainRaw(),
			$prefix . 'gain_alt'     => $this->getGainAlt(false),
			$prefix . 'title'        => $this->title,
			$prefix . 'cooldown_c'   => '',
			$prefix . 'cooldown_p'   => '',
			$prefix . 'delay'        => '',
			$prefix . 'recurrent_c'  => '',
			$prefix . 'recurrent_p'  => '',
		);
		if ($this->isMaxTriggersAllowed()) {
			$data[$prefix . 'max_triggers'] = $this->getMaxTriggers();
		}
		if($delay = $this->getDelay()) {
			$data[$prefix.'delay'] = $delay->toString();
			if (!$data[$prefix.'delay'])
				$data[$prefix.'delay'] = '';
		}
		if ($this->isRuleSupportedCooldown()) {
			$data[$prefix . 'rolling'] = ($this->isCooldownRolling() ? 'on' : '');
			if ($ci = $this->getCooldownInfo()) {
				$data[$prefix.'cooldown_c'] = $ci->count;
				$data[$prefix.'cooldown_p'] = $ci->period;
				$tz = \wp_timezone();
				$data[$prefix . 'reset_cd_d'] = $this->getCooldownResetDateTime('Y-m-d', $tz);
				$data[$prefix . 'reset_cd_h'] = $this->getCooldownResetDateTime('H', $tz);
				$data[$prefix . 'reset_cd_i'] = $this->getCooldownResetDateTime('i', $tz);
			}
		}
		if ($ri = $this->getRepeatInfo()) {
			$data[$prefix.'recurrent_c'] = $ri->count;
			$data[$prefix.'recurrent_p'] = $ri->period;
		}
		return $data;
	}

	/**	Provided to be overriden.
	 *	@param $context usage of returned inputs, default is an edition in editlist.
	 *	@return (string) the inside of a form (without any form balise).
	 *	@notice in override, dedicated option name must be type specific @see getDataKeyPrefix()
	 *	dedicated DOM must declare css attribute for hidden/show editlist behavior
	 * 	@code
	 *		class='lws_woorewards_system_choice {$this->getType()}'
	 *	@endcode
	 *	You can use several placeholder balises to insert DOM in middle of previous form (take care to keep for anyone following).
	 *	For each fieldset (numbered from 0, 1...) @see str_replace @see getFieldsetPlaceholder()
	 *	@code
	 *	<!-- [fieldset-1-head:{$this->getType()}] -->
	 *	<!-- [fieldset-1-foot:{$this->getType()}] -->
	 *	@endcode */
	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$str = $this->getFieldsetBegin(0, __("Action to perform", 'woorewards-lite'), '', false);

		$str .= "<div class='lws-$context-opt-title label'>" . __("Action", 'woorewards-lite') . "</div>";
		$str .= "<div class='value simple-text large lws_woorewards_system_type_info'>" . $this->getDisplayType() . "</div>";
		$str .= $this->getFieldsetPlaceholder(true, 0); // type will always be first, so exceptionnaly put at second place

		// custom title
		$label = _x("Action title", "Event title", 'woorewards-lite');
		$placeholder = \esc_attr(\apply_filters('the_title', $this->getDisplayType(), $this->getId()));
		$str .= "<div class='lws-$context-opt-title label'>$label</div>";
		$str .= "<div class='value lws-$context-opt-input value'><input type='text' size='30' id='{$prefix}title' name='{$prefix}title' placeholder='$placeholder' /></div>";

		$str .= $this->getFieldsetEnd(0);
		$str .= $this->getFieldsetBegin(1, __("Points settings", 'woorewards-lite'), array('title' => 'pts-earning'));

		// multiplier
		$label = _x("Earned points", "Event point multiplier", 'woorewards-lite');
		$tooltip = sprintf(__("Expects an integer or an expression starting by %s", 'woorewards-lite'), '<b>=</b>');
		$str .= <<<EOT
<div class='field-help pts-earning'>$tooltip</div>
<div for='{$prefix}multiplier' class='lws-$context-opt-title label bold pts-earning'>
	$label<div class='bt-field-help'>?</div>
</div>
<div class='lws-$context-opt-input value pts-earning'>
	<input type='text' size='5' class='expression_trigger' id='{$prefix}multiplier' name='{$prefix}multiplier' placeholder='1' />
</div><!-- [field-after:multiplier] -->
EOT;

		$label = _x("Alternative points text", "Event point multiplier", 'woorewards-lite');
		$tooltip = __("What Earned points value is shown to your customer instead of the formula above when the expression cannot be resolved on the fly", 'woorewards-lite');
		$placeholder = \esc_attr($this->getGainAlt(true));
		$str .= <<<EOT
<div style='display: none;' class='hide_if_not_expression pts-earning'>
	<div class='field-help'>$tooltip</div>
	<div for='{$prefix}mul_alt' class='lws-$context-opt-title label'>
		$label<div class='bt-field-help'>?</div>
	</div>
	<div class='lws-$context-opt-input value'>
		<input type='text' size='6' id='{$prefix}gain_alt' name='{$prefix}gain_alt' placeholder='{$placeholder}'/>
	</div>
</div><!-- [field-after:gain_alt] -->
EOT;

		// cooldown
		if ($this->isRuleSupportedCooldown()) {
			$label = _x("Cooldown", "Event cooldown", 'woorewards-lite');
			$tooltip = $this->getCooldownTooltips(__("Allow this event to be counted no more than X times per time interval or let it empty for no limit.", 'woorewards-lite'));
			$value = sprintf(
				_x('Max %1$s times / %2$s', 'cooldown edition', 'woorewards-lite'),
				"<input type='text' id='{$prefix}cooldown_c' name='{$prefix}cooldown_c' size='2'/>",
				\LWS\Adminpanel\Pages\Field\Duration::compose($prefix.'cooldown_p', array(
					'force'   => true,
					'periods' => array('M', 'D', 'W', 'H', 'I'),
				))
			);
			$str .= <<<EOT
<div class='field-help'>{$tooltip}</div>
<div class='lws-{$context}-opt-title label'>{$label}<div class='bt-field-help'>?</div></div>
<div class='lws-{$context}-opt-input value'><div class='lws-field-flex-wrap'>{$value}</div></div>
EOT;

			// default is rolling period
			$req = " data-selector='#{$prefix}cooldown_c' data-value='' data-operator='!='";
			$label = __("Cooldown rolling period", 'woorewards-lite');
			$tooltip = __("The default rolling period starts on the first trigger of a customer and so can be different for each customer.", 'woorewards-lite')
			. '<br/>' . __("Uncheck that box and define a reference date, such as first day of a month. The reference date will be periodically shift by the cooldown and the release date will be the same for each customers.", 'woorewards-lite');
			$h = \esc_attr(__("Hour", 'woorewards-lite'));
			$i = \esc_attr(__("Minute", 'woorewards-lite'));
			$str .= <<<EOT
<div class='field-help lws_adm_field_require'{$req}>{$tooltip}</div>
<div class='lws-{$context}-opt-title label lws_adm_field_require'{$req}>{$label}<div class='bt-field-help'>?</div></div>
<div class='lws-{$context}-opt-input value lws_adm_field_require'{$req} style="display: flex; flex-direction: row;">
	<input class='lws_checkbox' type='checkbox' id='{$prefix}rolling' name='{$prefix}rolling'/>
	<span class="lws_adm_field_require" data-selector="#{$prefix}rolling" data-value="on" data-operator="!=">
	-	<input class='lws-input' type='date' id='{$prefix}reset_cd_d' name='{$prefix}reset_cd_d'/>
	T <input class='lws-input' type='text' id='{$prefix}reset_cd_h' name='{$prefix}reset_cd_h' size="2" placeholder="04" title="{$h}"/>
	: <input class='lws-input' type='text' id='{$prefix}reset_cd_i' name='{$prefix}reset_cd_i' size="2" placeholder="00" title="{$i}"/>
	</span>
</div><!-- [field-after:cooldown] -->
EOT;
		}

		// Max triggers
		if ($this->isMaxTriggersAllowed()) {
			$label = _x("Max Triggers", "Event max triggers", 'woorewards-lite');
			$tooltip = __("Defines how many times this action can be triggered by each user. Leave empty for unlimited times or set an integer value", 'woorewards-lite');
			$str .= <<<EOT
<div class='field-help pts-earning'>$tooltip</div>
<div class='lws-{$context}-opt-title label pts-earning'>{$label}<div class='bt-field-help'>?</div></div>
<div class='lws-$context-opt-input value pts-earning'>
	<input type='text' size='5' id='{$prefix}max_triggers' name='{$prefix}max_triggers' placeholder='' />
</div><!-- [field-after:max_triggers] -->
EOT;
		}

		// Points delay
		if ($this->isDelayAllowed()) {
			$label = _x("Points delay", "Event points delay", 'woorewards-lite');
			$tooltip = __("If set, points will only be given after the delay you set here", 'woorewards-lite');
			$delay = \LWS\Adminpanel\Pages\Field\Duration::compose($prefix.'delay');
			$str .= <<<EOT
<div class='field-help'>{$tooltip}</div>
<div class='lws-{$context}-opt-title label'>{$label}<div class='bt-field-help'>?</div></div>
<div class='lws-{$context}-opt-input value'>{$delay}</div>
<!-- [field-after:delay] -->
EOT;
		}

		// Recurrent points
		if ($this->isRepeatAllowed()) {
			$label = _x("Repeated points", "Event points occurences", 'woorewards-lite');
			$tooltip = __("Set how many times points will be given and at what periodicity", 'woorewards-lite');
			$value = sprintf(
				_x('%1$s occurence(s) | one every %2$s', 'occurences edition', 'woorewards-lite'),
				"<input type='text' id='{$prefix}recurrent_c' name='{$prefix}recurrent_c' size='2'/>",
				\LWS\Adminpanel\Pages\Field\Duration::compose($prefix.'recurrent_p', array(
					'force'   => true,
				))
			);
			$str .= <<<EOT
<div class='field-help'>{$tooltip}</div>
<div class='lws-{$context}-opt-title label'>{$label}<div class='bt-field-help'>?</div></div>
<div class='lws-{$context}-opt-input value'><div class='lws-field-flex-wrap'>{$value}</div></div>
<!-- [field-after:recurrent_p] -->
EOT;
		}

		$str .= $this->getFieldsetEnd(1);
		return $str;
	}

	/** Provided to be overriden.
	 *	Back from the form, set and save data from @see getForm
	 *	@param $source origin of form values. Expect 'editlist' or 'post'. If 'post' we will apply the stripSlashes().
	 * 	@return mixed true if ok, (false|string|WP_Error) false or an error description on failure. */
	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix . 'multiplier'   => '=0',
				$prefix . 'gain_alt'     => 't',
				$prefix . 'title'        => 't',
				$prefix . 'max_triggers' => '0',
				$prefix . 'cooldown_c'   => '0',
				$prefix . 'cooldown_p'   => '/(P(\d+[DYMW])?(T\d+[HMS])?)?/i',
				$prefix . 'rolling'      => 'k',
				$prefix . 'reset_cd_d'   => '/(\d{4}-\d{2}-\d{2})?/',
				$prefix . 'reset_cd_h'   => '/([0-1]?\d|2[0-3])?/',
				$prefix . 'reset_cd_i'   => '/([0-5]?\d)?/',
				$prefix . 'delay'        => '/(P(\d+[DYMW])?(T\d+[HMS])?)?/i',
				$prefix . 'recurrent_c'  => '0',
				$prefix . 'recurrent_p'  => '/(P(\d+[DYMW])?(T\d+[HMS])?)?/i',
			),
			'defaults' => array(
				$prefix . 'multiplier'   => '0',
				$prefix . 'gain_alt'     => '',
				$prefix . 'title'        => '',
				$prefix . 'max_triggers' => '0',
				$prefix . 'cooldown_c'   => '0',
				$prefix . 'cooldown_p'   => '',
				$prefix . 'rolling'      => '',
				$prefix . 'reset_cd_d'   => '',
				$prefix . 'reset_cd_h'   => '4',
				$prefix . 'reset_cd_i'   => '0',
				$prefix . 'delay'        => '',
				$prefix . 'recurrent_c'  => '0',
				$prefix . 'recurrent_p'  => '',
			),
			'labels'   => array(
				$prefix . 'multiplier'   => __("Earned points", 'woorewards-lite'),
				$prefix . 'gain_alt'     => __("Earned points alternative text", 'woorewards-lite'),
				$prefix . 'title'        => __("Title", 'woorewards-lite'),
				$prefix . 'max_triggers' => __("Max Triggers", 'woorewards-lite'),
				$prefix . 'cooldown_c'   => __("Cooldown (action count)", 'woorewards-lite'),
				$prefix . 'cooldown_p'   => __("Cooldown (period)", 'woorewards-lite'),
				$prefix . 'delay'        => __("Delay", 'woorewards-lite'),
				$prefix . 'recurrent_c'  => __("Occurences", 'woorewards-lite'),
				$prefix . 'recurrent_p'  => __("Occurences (period)", 'woorewards-lite'),
				$prefix . 'rolling'      => __("Cooldown rolling period", 'woorewards-lite'),
				$prefix . 'reset_cd_d'   => __("Period reference date", 'woorewards-lite'),
				$prefix . 'reset_cd_h'   => __("Period reference hour", 'woorewards-lite'),
				$prefix . 'reset_cd_i'   => __("Period reference minute", 'woorewards-lite'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$this->setTitle($values['values'][$prefix.'title']);
		$this->setGain($values['values'][$prefix.'multiplier']);
		$this->setGainAlt($values['values'][$prefix.'gain_alt']);
		if ($this->isRuleSupportedCooldown()) {
			$this->setCooldownInfo($values['values'][$prefix.'cooldown_c'], $values['values'][$prefix.'cooldown_p']);
			$this->setCooldownRolling($values['values'][$prefix . 'rolling']);

			if ($this->isCooldownRolling()) {
				$this->setCooldownResetDateTime(false);
			} else {
				if (\trim($values['values'][$prefix . 'reset_cd_d'])) {
					$this->setCooldownResetDateTime(sprintf('%sT%d:%d:00',
						$values['values'][$prefix . 'reset_cd_d'],
						\intval($values['values'][$prefix . 'reset_cd_h']),
						\intval($values['values'][$prefix . 'reset_cd_i'])
					));
					$this->setCooldownResetDateTime($this->updateCooldownResetDateTime(
						$this->getCooldownResetDateTime(),
						$this->getCooldownInfo(true)
					));
				} else {
					$this->setCooldownResetDateTime(false);
				}
				if (!$this->getCooldownResetDateTime())
					return __("A date is required if the cooldown is not rolling.", 'woorewards-lite');
			}
		} else {
			$this->setCooldownInfo(0, '');
		}
		if ($this->isDelayAllowed()) {
			$this->setDelay($values['values'][$prefix.'delay']);
		} else {
			$this->setDelay('');
		}
		if ($this->isMaxTriggersAllowed()) {
			$this->setMaxTriggers($values['values'][$prefix . 'max_triggers']);
		} else {
			$this->setMaxTriggers('');
		}
		if ($this->isRepeatAllowed()) {
			$this->setRepeatInfo($values['values'][$prefix.'recurrent_c'], $values['values'][$prefix.'recurrent_p']);
		} else {
			$this->setRepeatInfo(0, '');
		}
		return true;
	}

	protected function getFieldsetBegin($index, $title='', $css='', $withPlaceholder=true)
	{
		$c = array(
			'fields' => 'fieldset fieldset-' . $index,
			'title'  => 'title',
			'grid'   => 'fieldset-grid',
		);
		if ($css) {
			if (\is_string($css)) {
				$c['fields'] .= (' ' . $css);
			} elseif (\is_array($css)) {
				foreach ($c as $k => $v) {
					if (isset($css[$k]) && $css[$k])
						$c[$k] .= (' ' . (\is_array($css[$k]) ? \implode(' ', $css[$k]) : $css[$k]));
				}
			}
		}
		$str = "<div class='{$c['fields']}'>";
		if ($title)
			$str .= "<div class='{$c['title']}'>{$title}</div>";
		$str .= "<div class='{$c['grid']}'>";
		if ($withPlaceholder)
			$str .= $this->getFieldsetPlaceholder(true, $index);
		return $str;
	}

	protected function getFieldsetEnd($index, $withPlaceholder=true)
	{
		$str = $withPlaceholder ? $this->getFieldsetPlaceholder(false, $index) : '';
		return $str . "</div></div>";
	}

	/** @see getForm insert that balise at top and bottom of each fieldset.
	 * @return (string) html */
	protected function getFieldsetPlaceholder($top, $index)
	{
		return "<!-- [fieldset-".intval($index)."-".($top?'head':'foot').":".$this->getType()."] -->";
	}

	protected function getDataKeyPrefix()
	{
		if (false === $this->dataKeyPrefix)
			$this->dataKeyPrefix = \esc_attr($this->getType()) . '_';
		return $this->dataKeyPrefix;
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getDescription($context='backend')
	{
		return $this->getDisplayType();
	}

	/** Purpose of an event: earn point for a pool.
	 *	Call this function when an earning event occurs.
	 *	@param $options (array|\WP_User|int) the user earning points, an array have to include an entry 'user'.
	 *	@param $reason (string|\LWS\WOOREWARDS\Core\Trace) the cause of the earning.
	 *	@param $pointCount (int) number of point earned, usually 1 since it is up to the pool to multiply it. */
	protected function addPoint($options, $reason='', $pointCount=1, $origin2=false)
	{
		$user = (\is_array($options) ? (isset($options['user']) ? $options['user'] : false) : $options);
		if ($this->getPool() && $user) {
			if ((\is_numeric($user) && $user > 0) || \is_a($user, 'WP_User')) {
				if($this->getPool()->userCan($user)) {
					// check values
					$userId = is_numeric($user) ? $user : $user->ID;
					$value = $pointCount;
					if (\is_array($options)) {
						$options['event'] = $this;
						$value = $this->getFinalGain($value, $options);
					} else {
						$value = $this->getFinalGain($value, array(
							'user'  => $user,
							'event' => $this,
						));
					}
					// apply
					if ($this->canBeTriggered($options, true)) {
						$this->getPool()->addPoints($userId, $value, $reason, $this, $origin2 ? $origin2 : $userId);
						$this->getPool()->tryUnlock($userId);
					}
				}
			} else {
				error_log("Try to add points to an undefined user: " . print_r($user, false));
			}
		}
	}

	public function install()
	{
		$this->_install();
		\do_action('lws_woorewards_abstracts_event_installed', $this);
	}

	static public function fromPost(\WP_Post $post)
	{
		$type = \get_post_meta($post->ID, 'wre_event_type', true);
		$event = static::instanciate($type);

		if( empty($event) )
		{
//			\lws_admin_add_notice_once('lws-wre-event-instanciate', __("Error occured during rewarding event instanciation.", 'woorewards-lite'), array('level'=>'error'));
		}
		else
		{
			$event->id = intval($post->ID);
			$event->name = $post->post_name;
			$event->title = $post->post_title;
			$event->setGain(\get_post_meta($post->ID, 'wre_event_multiplier', true));
			$event->setGainAlt(\get_post_meta($post->ID, 'wre_event_gain_alt', true));
			$event->poolId = intval($post->post_parent);
			$event->eventCreationDate = \date_create('0000' != substr($post->post_date_gmt, 0, 4) ? $post->post_date_gmt : $post->post_date);

			$event->cooldownInfo = false;
			if ($event->isRuleSupportedCooldown()) {
				$ci = \get_post_meta($post->ID, 'wre_event_cooldown', true);
				if ($ci) {
					$ci = explode('|', $ci);
					if (count($ci) < 2)
						\array_unshift($ci, 1);
					$event->setCooldownInfo($ci[0], $ci[1]);
				}

				$rollings = \get_post_meta($post->ID, 'wre_event_rolling', false);
				if ($rollings && \is_array($rollings))
					$event->setCooldownRolling(reset($rollings));
				else
					$event->setCooldownRolling(true);
				$event->setCooldownResetDateTime(\get_post_meta($post->ID, 'wre_event_reset_date', true));
			}

			$event->delay = false;
			if($event->isDelayAllowed()) {
				$delay = \get_post_meta($post->ID, 'wre_event_delay', true);
				if($delay) {
					$event->setDelay($delay);
				}
			}

			if ($event->isMaxTriggersAllowed()) {
				$event->setMaxTriggers(\get_post_meta($post->ID, 'wre_event_max_triggers', true));
			}

			$event->repeatInfo = false;
			if ($event->isRepeatAllowed()) {
				$ri = \get_post_meta($post->ID, 'wre_event_recurrent', true);
				if ($ri) {
					$ri = explode('|', $ri);
					if (count($ri) < 2)
						\array_unshift($ri, 1);
					$event->setRepeatInfo($ri[0], $ri[1]);
				}
			}

			$event->_fromPost($post);
		}
		return \apply_filters('lws_woorewards_abstracts_event_loaded', $event, $post);
	}

	/** @param $type (string|array) a registered type or an item of getRegistered(). */
	static function instanciate($type)
	{
		$instance = null;
		$registered = (is_string($type) ? static::getRegisteredByName($type) : $type);

		if( is_array($registered) && !empty($registered) )
		{
			try{
				require_once $registered[1];
				$instance = new $registered[0];
			}catch(\Exception $e){
				error_log("Cannot instanciate an woorewards Event: " . $e->getMessage());
			}
		}
		//~ else
			//~ error_log("Unknown wooreward event registered type from : ".print_r($type, true));

		return $instance;
	}

	public function save(\LWS\WOOREWARDS\Core\Pool &$pool)
	{
		$this->setPool($pool);
		$data = array(
			'ID'          => $this->id ? intval($this->id) : 0,
			'post_parent' => $pool->getId(),
			'post_type'   => self::POST_TYPE,
			'post_status' => $this->isValidGain() ? $this->getPoolStatus() : 'draft',
			'post_name'   => $this->getName($pool),
			'post_title'  => $this->title,
			'meta_input'  => array(
				'wre_event_multiplier'   => $this->getGainRaw(),
				'wre_event_gain_alt'     => $this->getGainAlt(false),
				'wre_event_type'         => $this->getType(),
				'wre_event_cooldown'     => '',
				'wre_event_delay'        => '',
				'wre_event_max_triggers' => '',
				'wre_event_recurrent'    => '',
			)
		);
		if ($this->isRuleSupportedCooldown() && ($ci = $this->getCooldownInfo())) {
			$data['meta_input']['wre_event_cooldown']   = ($ci->count . '|' . $ci->period);
			$data['meta_input']['wre_event_rolling']    = ($this->isCooldownRolling() ? 'on' : '');
			$data['meta_input']['wre_event_reset_date'] = $this->getCooldownResetDateTime('Y-m-d\TH:i:s e');
		}
		if ($this->isDelayAllowed()) {
			$data['meta_input']['wre_event_delay'] = $this->getDelay();
		}
		if ($this->isMaxTriggersAllowed()) {
			$data['meta_input']['wre_event_max_triggers'] = $this->getMaxTriggers();
		}
		if ($this->isRepeatAllowed() && ($ri = $this->getRepeatInfo())) {
			$data['meta_input']['wre_event_recurrent'] = ($ri->count . '|' . $ri->period);
		}

		$postId = $data['ID'] ? \wp_update_post($data, true) : \wp_insert_post($data, true);
		if( \is_wp_error($postId) )
		{
			error_log("Error occured during event saving: " . $postId->get_error_message());
			\lws_admin_add_notice_once('lws-wre-event-save', __("Error occured during rewarding event saving.", 'woorewards-lite'), array('level'=>'error'));
			return $this;
		}
		$this->id = intval($postId);
		if ($this->title)
			\do_action('wpml_register_string', $this->title, 'title', $this->getPackageWPML(true), __("Title", 'woorewards-lite'), 'LINE');
		if ($this->gainAlt)
			\do_action('wpml_register_string', $this->gainAlt, 'gain_alt', $this->getPackageWPML(true), __("Earned points alternative text", 'woorewards-lite'), 'LINE');

		$this->_save($this->id);
		\do_action('lws_woorewards_abstracts_event_save_after', $this);
		return $this;
	}

	/** @see https://wpml.org/documentation/support/string-package-translation
	 * Known wpml bug: kind first letter must be uppercase */
	function getPackageWPML($full=false)
	{
		$pack = array(
			'kind' => 'WooRewards Points Earning Method',//strtoupper(self::POST_TYPE),
			'name' => $this->getId(),
		);
		if( $full )
		{
			$title = ($this->title ? $this->title : ($this->getDisplayType() . '/' . $this->getId()));
			if( $pool = $this->getPool() )
				$title = ($pool->getOption('title') . ' - ' . $title);
			$pack['title'] = $title;
			$pack['edit_link'] = \add_query_arg(array('page'=>LWS_WOOREWARDS_PAGE.'.loyalty', 'tab'=>'wr_loyalty.wr_upool_'.$this->getPoolId()), admin_url('admin.php'));
		}
		return $pack;
	}

	/** @alias for getTitle(false, true) */
	public function getTitleAsReason()
	{
		return $this->getTitle(false, true);
	}

	public function getTitle($fallback=true, $forceTranslate=false)
	{
		$title = ($this->title ? $this->title : ($fallback ? $this->getDisplayType() : ''));
		if( $forceTranslate || !(is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) )
			$title = \apply_filters('wpml_translate_string', $title, 'title', $this->getPackageWPML());
		return \apply_filters('the_title', $title, $this->getId());
	}

	public function setTitle($title='')
	{
		$this->title = $title;
		return $this;
	}

	public function delete()
	{
		if( $this->id )
		{
			\do_action('lws_woorewards_abstracts_event_delete_before', $this);
			if( empty(\wp_delete_post($this->id, true)) )
				error_log("Failed to delete the rewarding event {$this->id}");
			else
			{
				$pack = $this->getPackageWPML();
				\do_action('wpml_delete_package_action', $pack['name'], $pack['kind']);

				$this->id = false;
			}
		}
		return $this;
	}

	/** Declare a new kind of event. */
	static public function register($classname, $filepath, $unregister=false, $typeOverride=false)
	{
		$id = empty($typeOverride) ? self::formatType($classname) : $typeOverride;
		if( $unregister )
		{
			if( isset(self::$s_events[$id]) )
				unset(self::$s_events[$id]);
		}
		else
			self::$s_events[$id] = array($classname, $filepath);
	}

	static public function getRegistered()
	{
		return self::$s_events;
	}

	static public function getRegisteredByName($name)
	{
		return isset(self::$s_events[$name]) ? self::$s_events[$name] : false;
	}

	public function unsetPool()
	{
		$this->pool = null;
		return $this;
	}

	public function setPool(&$pool)
	{
		$this->pool =& $pool;
		return $this;
	}

	/** @return \LWS\WOOREWARDS\PRO\Core\Pool|false */
	public function getPool()
	{
		return $this->pool ? $this->pool : false;
	}

	/** @return \LWS\WOOREWARDS\PRO\Core\Pool|false */
	public function getOrLoadPool()
	{
		if (null !== $this->pool) {
			return $this->pool;
		} else if ($this->poolId) {
			if (\class_exists('\LWS\WOOREWARDS\PRO\Core\Pool')) {
				$this->pool = \LWS\WOOREWARDS\PRO\Core\Pool::getOrLoad($this->poolId, false);
			} else {
				$pool = \apply_filters('lws_woorewards_get_pools_by_args', false, array(
					'system' => $this->poolId,
					'force'  => true,
				));
				if ($pool)
					$this->pool = $pool->last();
			}
			return $this->pool;
		} else {
			return false;
		}
	}

	public function getPoolId()
	{
		if( $this->pool )
			return $this->pool->getId();
		else if($this->poolId)
			return $this->poolId;
		else
			return false;
	}

	public function getPoolName()
	{
		return $this->pool ? $this->pool->getName() : '';
	}

	public function getPoolType()
	{
		return $this->pool ? $this->pool->getOption('type') : '';
	}

	public function getPoolStatus()
	{
		if ($this->pool) {
			if( $this->pool->getOption('public') )
				return 'publish';
			else if( $this->pool->getOption('private') )
				return 'private';
			else
				return 'draft';
		}
		return '';
	}

	public function getStackName()
	{
		return $this->pool ? $this->pool->getStackId() : '';
	}

	public function getId()
	{
		return $this->id ? intval($this->id) : false;
	}

	public function detach()
	{
		$this->id = false;
	}

	/** @param $classname full class with namespace. */
	public static function formatType($classname=false)
	{
		if( $classname === false )
			$classname = \get_called_class();
		return strtolower(str_replace('\\', '_', trim($classname, '\\')));
	}

	public function getType()
	{
		return static::formatType($this->getClassname());
	}

	function getClassname()
	{
		return \get_class($this);
	}

	public function setGainAlt($text)
	{
		$this->gainAlt = \trim($text);
	}

	/** @param $default (bool|null) true return the default/fallback text,
	 *	false return the user text, never the fallback,
	 *	null return try to translate and replace `[currency]` by the point symbol. */
	public function getGainAlt($default=null)
	{
		if (false === $default)
			return \trim($this->gainAlt);

		$alt = ((null === $default) ? \trim($this->gainAlt) : '');
		if (!\strlen($alt))
			$alt = _x("Variable", 'event points expression alternative', 'woorewards-lite');

		if (null === $default) {
			$alt = \apply_filters('wpml_translate_string', $alt, 'gain_alt', $this->getPackageWPML());
			$alt = \str_replace('[currency]', \LWS_WooRewards::getPointSymbol(2, $this->getPoolName()), $alt);
		}
		return $alt;
	}

	/** If only number, it should be greater than 0.
	 *	If expression, is have to be valid, regardless the result. */
	public function isValidGain($lazy=false)
	{
		$value = \ltrim($this->multiplier);
		if (\is_numeric($value)) {
			return ($value > 0);
		} elseif ('=' == \substr($value, 0, 1)) {
			if (!\strlen(\trim(\substr($value, 1))))
				return false;

			if ($lazy)
				return true;
			else
				return \apply_filters('lws_adminpanel_validate_expression', $value, array());
		} else {
			return false;
		}
	}

	/** Add decoration for display purpose, e.g. cooldown info. */
	protected function shapeGain($value)
	{
		if (\is_admin() || \get_option('lws_woorewards_show_event_cooldown') == 'on') {
			if ($this->isRuleSupportedCooldown()) {
				if ((\is_numeric($value) && $value > 0) || '=' == substr($value, 0, 1)) {
					if ($ci = $this->getCooldownText()) {
						$value .= sprintf('<sup>(%s)</sup>', $ci);
					}
				}
			}
		}
		return $value;
	}

	/** @return (string|number) value as set by the user.
	 *	No expression resolution.
	 *	@param $shape (bool) add decoration for display purpose, e.g. cooldown info. */
	public function getGainRaw($shape=false)
	{
		$value = $this->multiplier;
		if ($shape)
			$value = $this->shapeGain($value);
		return $value;
	}

	/** provided for convenience, resolve any expression, then multiply.
	 *	or return default (or zero) as fallback. */
	public function getFinalGain($multiply=1, $options=array(), $quiet=false)
	{
		$value = $this->getGain($options, $quiet);
		if (\is_numeric($value)) {
			return (int)\round($value * $multiply, 0, \apply_filters('lws_woorewards_event_points_round_mode', PHP_ROUND_HALF_UP, $this));
		} else {
			return isset($options['default']) ? $options['default'] : 0;
		}
	}

	/** @return mixed number, resolve expression if any.
	 *	Even for bad expression, zero is used as fallback.
	 *	If no user given, use the current user.
	 *	@param $quiet (bool) if true, no log if expression cannot be resolved. */
	public function getGain($options=array(), $quiet=false)
	{
		$value = \ltrim($this->multiplier);
		if ('=' == substr($value, 0, 1)) {
			if (!isset($options['user']))
				$options['user'] = \wp_get_current_user();
			$options['default'] = 0;
			$options['event'] = $this;
			if ($quiet)
				return \apply_filters('lws_adminpanel_expression_silent', $value, $options);
			else
				return \apply_filters('lws_adminpanel_expression', $value, $options);
		}
		return $value;
	}

	private $_gainTimestamp = null;
	protected $_gainCache = 0;

	/**	@see getGain
	 *	@param $timestamp anything else than null.
	 *	If timestamp changed since last call, compute gain again.
	 *	Else return last saved value. */
	public function getGainWithCache($options=array(), $quiet=false, $timestamp=false)
	{
		if ($this->_gainTimestamp !== $timestamp || null === $timestamp) {
			$this->_gainCache = $this->getGain($options, $quiet);
			if (false !== $timestamp)
				$this->_gainTimestamp = $timestamp;
		}
		return $this->_gainCache;
	}

	/** @return (string) to show to customers.
	 *	@param $altFallback )(bool) if true, if expression cannot be resolve,
	 *	return the alternative gain text. Else return the original value.
	 *	Try to resolve the expression as is.
	 *	But if it is not possible, fallback on a userdefined string
	 *	or a default text at last. */
	public function getGainForDisplay($options=array(), $altFallback=true)
	{
		$original = $value = \ltrim($this->multiplier);
		if ('=' == substr($value, 0, 1)) {
			if (!isset($options['user']))
				$options['user'] = \wp_get_current_user();
			$options['default'] = false;
			$options['event'] = $this;
			$value = \apply_filters('lws_adminpanel_expression_silent', $value, $options);
			if (false === $value)
				$value = ($altFallback ? $this->getGainAlt() : $original);
		}
		return $this->shapeGain($value);
	}

	public function setGain($value)
	{
		$this->multiplier = \trim($value);
		return $this;
	}

	/** Multiplier is registered by Pool, it is applied to the points generated by the event.
	 *	@deprecated 4.9 obsolete, @see getGain, @see getGainForDisplay */
	public function getMultiplier($context='edit')
	{
		\_deprecated_function(__FUNCTION__, '4.9', 'getGainForDisplay');
		$mul = $this->multiplier;
		if ('view' == $context)
			$mul = $this->shapeGain($mul);
		return $mul;
	}

	/** @deprecated 4.9 obsolete, @see setGain */
	public function setMultiplier($multiplier)
	{
		\_deprecated_function(__FUNCTION__, '4.9', 'setGain');
		$this->multiplier = $multiplier;
		return $this;
	}

	public function setName($name)
	{
		$this->name = $name;
	}

	public function getName($pool=null)
	{
		if ($this->name)
			return $this->name;
		else if( !empty($this->getPool()) )
			return $this->getPool()->getName() . '-' . $this->getType();
		else if( !empty($pool) )
			return $pool->getName() . '-' . $this->getType();
		else
			return $this->getType();
	}

	/** Provided for convenience.
	 * To get new properties with default value. */
	protected function getSinglePostMeta($postId, $meta, $default=false)
	{
		$values = \get_post_meta($postId, $meta, false);
		if( is_array($values) && count($values) )
			return reset($values);
		return $default;
	}

	/** To be overriden to provide choice to administrator. */
	function getInformation()
	{
		return array(
			'label' => $this->getDisplayType(),
			'icon'  => false, /// (string) html
			'color' => false, /// (string) css color format
			'short' => $this->getDescription(),
			'help'  => '',
		);
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array(
			\LWS\WOOREWARDS\Core\Pool::T_STANDARD  => __("Standard", 'woorewards-lite'),
			\LWS\WOOREWARDS\Core\Pool::T_LEVELLING => __("Leveling", 'woorewards-lite'),
			'achievement' => __("Achievement", 'woorewards-lite'),
			'custom'      => __("Events", 'woorewards-lite')
		);
	}
}
