<?php
namespace LWS\WOOREWARDS\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();


/** Sponsor earns points for each money spend by Sponsored on an order.
 *	Extends usual order amount to only change point destination. */
class SponsoredOrderAmount extends \LWS\WOOREWARDS\Events\OrderAmount
{
	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-coins',
			'short' => __("The customer will earn points when a person he referred spends money on your shop.", 'woorewards-lite'),
			'help'  => __("This method will only reward the Referrer, not the Referee", 'woorewards-lite'),
		));
	}

	function getClassname()
	{
		return 'LWS\WOOREWARDS\Events\SponsoredOrderAmount';
	}

	public function getDisplayType()
	{
		return _x("Referee spends money", "getDisplayType", 'woorewards-lite');
	}

	/** override */
	function orderDone($order)
	{
		$sponsorship = new \LWS\WOOREWARDS\Core\Sponsorship();
		$this->sponsorship = $sponsorship->getUsersFromOrder($order->order, $this->isGuestAllowed());

		if( !$this->sponsorship->sponsor_id )
			return $order;
		return parent::orderDone($order);
	}

	/** @param $order (\WC_Order)
	 * @return (int) user ID */
	function getPointsRecipient($order)
	{
		if( $this->sponsorship && $this->sponsorship->sponsor_id )
			return $this->sponsorship->sponsor_id;
		else
			return false;
	}

	protected function getGainInfo($info, $order)
	{
		if ($this->sponsorship)
			$info['sponsee'] = $this->sponsorship->sponsored_id;
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
			->setReason(array(
				'Referred friend %3$s spent %1$s from order #%2$s',
					$price,
					$order->get_order_number(),
					$order->get_billing_email()
				), 'woorewards-lite'
			);
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__('Referred friend %3$s spent %1$s from order #%2$s', 'woorewards-lite');
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array(
			\LWS\WOOREWARDS\Core\Pool::T_STANDARD  => __("Standard", 'woorewards-lite'),
			\LWS\WOOREWARDS\Core\Pool::T_LEVELLING => __("Levelling", 'woorewards-lite'),
			'achievement' => __("Achievement", 'woorewards-lite'),
			'custom'      => __("Events", 'woorewards-lite'),
			'woocommerce' => __("WooCommerce", 'woorewards-lite'),
			'sponsorship' => __("Available for referred", 'woorewards-lite')
		);
	}
}