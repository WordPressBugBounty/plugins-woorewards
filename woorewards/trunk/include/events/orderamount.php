<?php
namespace LWS\WOOREWARDS\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points for each money spend on an order. */
class OrderAmount extends \LWS\WOOREWARDS\Abstracts\Event
{
	use \LWS\WOOREWARDS\Events\T_SponsorshipOrigin;

	protected $eventPriority   = 99;
	protected $afterDiscount   = false;
	protected $includeShipping = false;
	protected $includeFees     = false;
	protected $thresholdEffect = true;
	protected $denominator     = 1.00;

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-coins',
			'short' => __("The customer will receive points depending on the amount spent on the order", 'woorewards-lite'),
			'help'  => __("Most used method on MyRewards. Lots of options", 'woorewards-lite'),
		));
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix . 'after_discount'] = $this->getAfterDiscount() ? 'on' : '';
		$data[$prefix.'denominator']      = $this->getDenominator();
		$data[$prefix.'include_shipping'] = $this->getShipping() ? 'on' : '';
		$data[$prefix.'include_fees']     = $this->getFees() ? 'on' : '';
		$data[$prefix.'event_priority']   = $this->getEventPriority();
		$data = $this->filterSponsorshipData($data, $prefix);
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);

		$label = _x("Money spent", "Order Amount Event money diviser", 'woorewards-lite');
		$value = \esc_attr($this->getDenominator());
		$str = <<<EOT
	<div class='lws-$context-opt-title label pts-earning'>$label</div>
	<div class='lws-$context-opt-input value pts-earning'>
		<input type='text' id='{$prefix}denominator' name='{$prefix}denominator' value='$value' placeholder='1' pattern='\\d*' size='5' />
	</div>
EOT;
		$pht1 = $this->getFieldsetPlaceholder(true, 1);
		$form = str_replace($pht1, $pht1.$str, $form);

		// just hidden since we do not want to reset the value on save
		$noPri = (\get_option('lws_woorewards_show_loading_order_and_priority') ? '' : ' style="display: none;"');
		$label = __("Priority", 'woorewards-lite');
		$tooltip = __("Customer orders will run by ascending priority value.", 'woorewards-lite');
		$str = <<<EOT
		<div class='field-help'{$noPri}>$tooltip</div>
		<div class='lws-$context-opt-title label'{$noPri}>$label<div class='bt-field-help'>?</div></div>
		<div class='lws-$context-opt-input value'{$noPri}>
			<input type='text' id='{$prefix}event_priority' name='{$prefix}event_priority' placeholder='10' size='5' />
		</div>
EOT;

		$phb0 = $this->getFieldsetPlaceholder(false, 0);
		$form = str_replace($phb0, $str.$phb0, $form);

		$form .= $this->getFieldsetBegin(2, __("Options", 'woorewards-lite'));

		// compute points after discount
		$label   = _x("Use amount after discount", "Order Amount Event", 'woorewards-lite');
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'after_discount', array(
			'id'      => $prefix . 'after_discount',
			'layout'  => 'toggle',
		));
		$form .= "<div class='lws-$context-opt-input value'>{$toggle}</div>";

		$form .= $this->getFieldsetEnd(2);

		return $this->filterSponsorshipForm($form, $prefix, $context, 10);
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix . 'after_discount'   => 's',
				$prefix . 'denominator'      => 'F',
				$prefix . 'include_shipping' => 's',
				$prefix . 'include_fees'     => 's',
				$prefix . 'event_priority'   => 'd',
			),
			'defaults' => array(
				$prefix . 'after_discount'   => '',
				$prefix . 'denominator'      => '1',
				$prefix . 'include_shipping' => '',
				$prefix . 'include_fees'     => '',
				$prefix . 'event_priority'   => $this->getEventPriority(),
			),
			'labels'   => array(
				$prefix . 'after_discount' => __("After Discount", 'woorewards-lite'),
				$prefix . 'event_priority' => __("Event Priority", 'woorewards-lite'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if ($valid === true)
			$valid = $this->optSponsorshipSubmit($prefix, $form, $source);
		if( $valid === true )
		{
			$this->setDenominator    ($values['values'][$prefix.'denominator']);
			$this->setShipping       (boolval($values['values'][$prefix.'include_shipping']));
			$this->setFees           (boolval($values['values'][$prefix.'include_fees']));
			$this->setEventPriority  ($values['values'][$prefix.'event_priority']);
			$this->setAfterDiscount  (boolval($values['values'][$prefix . 'after_discount']));
		}
		return $valid;
	}

	/** Compute points on final order amount,
	 *	after fees and discounts applied.
	 *  @return bool */
	public function getAfterDiscount()
	{
		return $this->afterDiscount;
	}

	public function setAfterDiscount($yes = true)
	{
		$this->afterDiscount = $yes;
		return $this;
	}

	/** @return bool */
	public function getShipping()
	{
		return $this->includeShipping;
	}

	public function setShipping($yes=true)
	{
		$this->includeShipping = $yes;
		return $this;
	}

	/** @return bool */
	public function getFees()
	{
		return $this->includeFees;
	}

	public function setFees($yes=true)
	{
		$this->includeFees = $yes;
		return $this;
	}

	/** @return bool */
	public function getThresholdEffect()
	{
		return $this->thresholdEffect;
	}

	/** Points computed proportionaly or for each complet amount of money.
	 * Does we apply ceil. */
	public function setThresholdEffect($yes=true)
	{
		$this->thresholdEffect = $yes;
		return $this;
	}

	/** @return int */
	public function getDenominator()
	{
		return $this->denominator;
	}

	/** amount is divided by denominator before point earning. */
	public function setDenominator($value=1.00)
	{
		$this->denominator = max($value, 0);
		return $this;
	}

	public function getDisplayType()
	{
		return _x("Spend money", "getDisplayType", 'woorewards-lite');
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setAfterDiscount(boolval(\get_post_meta($post->ID, 'wre_event_after_discount',     true)));
		$this->setShipping       (boolval(\get_post_meta($post->ID, 'wre_event_include_shipping', true)));
		$this->setFees           (boolval(\get_post_meta($post->ID, 'wre_event_include_fees', true)));
		$this->setThresholdEffect(boolval(\get_post_meta($post->ID, 'wre_event_threshold_effect', true)));
		$this->setDenominator    (floatval(\get_post_meta($post->ID, 'wre_event_denominator',     true)));
		$this->setEventPriority($this->getSinglePostMeta($post->ID, 'wre_event_priority', $this->getEventPriority()));
		$this->optSponsorshipFromPost($post);
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_after_discount',   $this->getAfterDiscount());
		\update_post_meta($id, 'wre_event_include_shipping', $this->getShipping());
		\update_post_meta($id, 'wre_event_include_fees',     $this->getFees());
		\update_post_meta($id, 'wre_event_threshold_effect', $this->getThresholdEffect()?'on':'');
		\update_post_meta($id, 'wre_event_denominator',      $this->getDenominator());
		\update_post_meta($id, 'wre_event_priority', $this->getEventPriority());
		$this->optSponsorshipSave($id);
		return $this;
	}

	function getEventPriority()
	{
		return \intval($this->eventPriority);
	}

	public function setEventPriority($priority)
	{
		$this->eventPriority = \intval($priority);
		return $this;
	}

	protected function _install()
	{
		\add_filter('lws_woorewards_wc_order_done_'.$this->getPoolName(), array($this, 'orderDone'), $this->getEventPriority()); // priority later to let other use some order lines
	}

	function orderDone($order)
	{
		if (!($userId = $this->getPointsRecipient($order->order)))
			return $order;
		if (!$this->isValidOriginByOrder($order->order, $this->isGuestAllowed()))
			return $order;

		$amount = $this->getOrderAmount($order);
		$points = $this->getPointsForAmount($amount);

		if ($points > 0)
		{
			if ($points = \apply_filters('trigger_' . $this->getType(), $points, $this, $order->order))
			{
				$this->addPoint($this->getGainInfo(array(
					'user'  => $userId,
					'order' => $order->order,
				), $order), $this->getPointsReason($order->order, $amount), $points);
			}
		}
		return $order;
	}

	/** @param $order (\WC_Order)
	 * @return (int) user ID */
	function getPointsRecipient($order)
	{
		return \LWS\Adminpanel\Tools\Conveniences::getCustomerId(false, $order);
	}

	/** @param $info (array) */
	protected function getGainInfo($info, $order)
	{
		return $info;
	}

	/** @param $order (\WC_Order)
	 * @param $amount (float) computed amount
	 * @return (\LWS\WOOREWARDS\Core\Trace) a reason for history */
	function getPointsReason($order, $amount)
	{
		$price = \wp_kses(\wc_price($amount, array('currency' => $order->get_currency())), array());
		return \LWS\WOOREWARDS\Core\Trace::byOrder($order)
			->setProvider($order->get_customer_id('edit'))
			->setReason(array('Spent %1$s from order #%2$s', $price, $order->get_order_number()), 'woorewards-lite');
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__('Spent %1$s from order #%2$s', 'woorewards-lite');
	}

	function roundPrice($price)
	{
		return \wc_format_decimal(\floatval($price), \wc_get_price_decimals());
	}

	function getPointsForAmount($amount)
	{
		$points = 0;
		if( $amount > 0 )
		{
			$points = $amount / floatval($this->getDenominator());
			if ($this->getThresholdEffect()) $points = \floor($points);
		}
		return $points;
	}

	function getOrderAmount(&$order, $round=true)
	{
		$amount = $order->amount; // subtotal (with or without tax - option) before discount

		if ($this->getAfterDiscount()) {
			$amount -= $order->order->get_discount_total();
			if ($order->inc_tax)
				$amount -= $order->order->get_discount_tax();
		}

		if ($this->getShipping())
		{
			$amount += floatval($order->order->get_shipping_total('edit'));
			if( $order->inc_tax )
				$amount += floatval($order->order->get_shipping_tax('edit'));
		}

		if ($this->getFees())
		{
			$amount += floatval($order->order->get_total_fees());
		}

		$amount = max(0, $amount);
		return $round ? $this->roundPrice($amount) : $amount;
	}

	protected function shapeGain($value)
	{
		$amount = $this->getDenominator();
		$amount = \LWS\Adminpanel\Tools\Conveniences::isWC() ? \wc_price($amount) : \number_format_i18n($amount, 2);
		if ('=' == substr($value, 0, 1)) {
			$value = sprintf(_x('[%1$s] / %2$s', "Point per money spent", 'woorewards-lite'), $value, $amount);
		} elseif (\is_numeric($value) && $value > 0) {
			$points = \LWS_WooRewards::formatPointsWithSymbol($value, $this->getPoolName());
			$value = sprintf(_x('%1$s / %2$s', "Point per money spent", 'woorewards-lite'), $points, $amount);
		} else {
			$value = \str_replace('[spent]', $amount, $value);
		}
		return parent::shapeGain($value);
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'woocommerce' => __("WooCommerce", 'woorewards-lite'),
			'money' => __("Money", 'woorewards-lite'),
			'order' => __("Order", 'woorewards-lite')
		));
	}

	function isGuestAllowed()
	{
		return false;
	}
}