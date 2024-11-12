<?php

namespace LWS\WOOREWARDS;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Fake a WC_Coupon to consume points. */
class PointDiscount
{
	const COUPON_ERR_CODE = 57490; // abitrary code
	const CODE_PREFIX = 'wr_points_on_cart';

	static function install()
	{
		$me = new self();
		// simulate coupons
		\add_filter('woocommerce_cart_totals_coupon_label', array($me, 'asLabel'), 20, 2);
		\add_filter('woocommerce_order_item_get_code', array($me, 'asCode'), 20, 2);
		\add_filter('woocommerce_coupon_error', array($me, 'asError'), 20, 3);
		\add_filter('woocommerce_get_shop_coupon_data', array($me, 'asData'), PHP_INT_MAX - 8, 3);
		// save coupon meta
		\add_action('woocommerce_checkout_create_order_coupon_item', array($me, 'createOrderItem'), 10, 4);
		// release points
		\add_filter('woocommerce_removed_coupon', array($me, 'remove'), 10, 2);
		\add_action('woocommerce_cart_emptied', array($me, 'emptied'));
		// Test points amount during checkout
		\add_action('woocommerce_checkout_order_processed', array($me, 'legacyCheck'), PHP_INT_MAX - 9, 3);
		\add_action('woocommerce_store_api_checkout_order_processed', array($me, 'check'), PHP_INT_MAX - 9, 1);
		// Substract points after payment
		foreach ($me->getPayOrderStatus() as $s)
			\add_action('woocommerce_order_status_' . $s, array($me, 'pay'), 10, 2);
		\add_action('woocommerce_checkout_order_processed', array($me, 'legacyMaybePay'), PHP_INT_MAX - 8, 3);
		\add_action('woocommerce_store_api_checkout_order_processed', array($me, 'maybePay'), PHP_INT_MAX - 8, 1);
		// prevent add discount with enough points
		\add_filter('woocommerce_coupon_is_valid', array($me, 'preventAddDiscount'), 10, 3);
		// set used point in cart
		\add_action('wp_ajax_lws_woorewards_pointsoncart_reserve_amount', array($me, 'reserveAmount'));
	}

	protected function getPayOrderStatus()
	{
		$status = \get_option('lws_woorewards_pointdiscount_pay_order_status', false);
		if (false === $status || !\is_array($status))
			$status = array('processing', 'completed');
		return $status;
	}

	function asLabel($label, $coupon)
	{
		if (!$coupon)
			return $label;

		$name = $this->getLabel($coupon->get_code());
		if ($name) {
			$label = $name;
		} elseif ($data = self::getDiscountMeta($coupon)) {
			$label = sprintf(
				_x('Reward from %s', 'Reward label', 'woorewards-lite'),
				$this->getTitle($data)
			);
		}
		return $label;
	}

	function asError($msg, $err, $coupon)
	{
		if ($msg && self::COUPON_ERR_CODE == $err)
			return $msg;

		$label = false;
		if ($coupon) {
			if ($data = self::getDiscountMeta($coupon))
			{
				$label = $this->getTitle($data);
				$label = \wp_kses($label, array());
			}
			else
				$label = $this->getLabel($coupon->get_code());
		}

		if ($label) {
			$fake = clone $coupon;
			$fake->delete_meta_data('wr_discount_data');
			$fake->set_code($label);
			$msg = $fake->get_coupon_error($err);
		}
		return $msg;
	}

	/** Use hook 'woocommerce_get_shop_coupon_data'
	 *	to create coupon without db. */
	function asData($coupon, $data, $instance)
	{
		if (!($data && \is_string($data)))
			return $coupon;

		$discount = $this->fromCode($data);
		if (!$discount)
			return $coupon;

		$data = array(
			'code'                 => $discount['code'],
			'description'          => $this->getTitle($discount),
			'discount_type'        => 'fixed_cart',
			'amount'               => $discount['value'],
		);
		if (isset($discount['cats']) && $discount['cats'])
			$data['_lws_coupon_virtual_taxonomy'] = $discount['cats'];
		$data = \apply_filters('lws_woorewards_pointdiscount_as_coupon_data', $data, $discount, $discount['code']);

		if ($data)
		{
			if (isset($data['_lws_coupon_virtual_taxonomy']) && $data['_lws_coupon_virtual_taxonomy'])
				$instance->update_meta_data('_lws_coupon_virtual_taxonomy', $data['_lws_coupon_virtual_taxonomy']);
			$instance->update_meta_data('wr_discount_data', $discount);
			return $data;
		}
		else
			return $coupon;
	}

	public static function getDiscountMeta($coupon)
	{
		/** @var \WC_Coupon $coupon */
		$meta = $coupon->get_meta('wr_discount_data', true);
		if ($meta) return $meta;
		else return $coupon->wr_discount_data ?? false;
	}

	/** Coupon title, default use Pool title. */
	protected function getTitle($discount)
	{
		$pool = self::getPool($discount);
		if ($pool)
			return \apply_filters('lws_woorewards_pointdiscount_title', $pool->getOption('display_title'), $discount);
		else
			return $discount['pool_name'];
	}

	protected function getLabel($code, $default=false)
	{
		$refs = \explode('-', $code, 2);
		if (2 != count($refs))
			return $default;

		if (self::CODE_PREFIX != $refs[0])
			return $default;

		$pool = \apply_filters('lws_woorewards_get_pools_by_args', false, array('system' => $refs[1]), \get_current_user_id())->last();
		if (!$pool)
			return $default;
		else
			return $pool->getOption('display_title');
	}

	protected function fromCode($code)
	{
		/// point discount flag - pool name
		$refs = \explode('-', $code, 2);
		if (2 != count($refs))
			return false;

		if (self::CODE_PREFIX != $refs[0])
			return false;

		$userId = \get_current_user_id();
		if (!$userId)
			return false;

		$pool = \apply_filters('lws_woorewards_get_pools_by_args', false, array('system' => $refs[1]), $userId)->last();
		if (!$pool)
			return false;
		if (!$pool->getOption('direct_reward_mode'))
			return false;

		$stackId = $pool->getStackId();
		$points = \intval(\get_user_meta($userId, 'lws_wr_points_on_cart_' . $stackId, true));
		if ($points <= 0)
			return false;

		$points = \min($points, $pool->getPoints($userId));
		if ($points <= 0)
			return false;

		$rate = $pool->getOption('direct_reward_point_rate');
		if ($rate == 0.0)
			return false;

		$discount = array(
			'code'      => $code,
			'pool'      => false,
			'pool_name' => $pool->getName(),
			'stack_id'  => $stackId,
			'user_id'   => $userId,
			'points'    => $points,
			'rate'      => $rate,
			'value'     => (float)$points * $rate,
			'paid'      => false,
			'cats'      => $pool->getOption('direct_reward_discount_cats'),
		);

		// clamp to cart total equivalent
		if (\WC()->cart)
		{
			$cart = \WC()->cart;
			$total = (float)$cart->get_subtotal();
			$incTax = ('yes' === \get_option('woocommerce_prices_include_tax'));
			if ($incTax) //if( \WC()->cart->display_prices_including_tax() )
				$total += (float)$cart->get_subtotal_tax();
			foreach ($cart->get_applied_coupons() as $otherCode)
			{
				if (strpos($otherCode, self::CODE_PREFIX) === false) {
					$value = (float)$cart->get_coupon_discount_amount($otherCode, !$incTax);
					$total -= $value;
				}
			}
			$currencyRate = \LWS\Adminpanel\Tools\Conveniences::getCurrencyPrice(1, false, false);
			if ($currencyRate && \is_numeric($currencyRate))
				$total =  $total / (float)$currencyRate;
			$max = \ceil((float)$total / $rate);
			if ($max < $points) {
				$discount['points'] = $max;
				$discount['value'] = $max * $rate;
			}
		}
		return \apply_filters('lws_woorewards_pointdiscount_from_code', $discount, $code);
	}

	/** Hook 'woocommerce_coupon_is_valid' to check if user can use that discount.
	 *	Prevent using from different pool using the same stack. */
	function preventAddDiscount($valid, $coupon, $wcDiscounts)
	{
		if (!$valid)
			return $valid;
		// does it matter for us
		if (!$coupon)
			return $valid;
		$data = self::getDiscountMeta($coupon);
		if (!$data)
			return $valid;
		// get cart
		$cart = false;
		if ($wcDiscounts && \is_a($wcDiscounts->get_object(), '\WC_Cart'))
			$cart = $wcDiscounts->get_object();
		if (!$cart && \WC()->cart)
			$cart = &\WC()->cart;
		if (!$cart)
			return $valid;

		if ($cart->has_discount($data['code']))
			return $valid;

		foreach ($cart->get_coupons() as $applied)
		{
			$appData = self::getDiscountMeta($applied);
			if ($appData && $appData['stack_id'] == $data['stack_id']) {
				$msg = sprintf(
					__('%2$s Conflict. Reward from %1$s already uses the same points reserve.', 'woorewards-lite'),
					$this->getTitle($appData),
					$this->getTitle($data)
				);
				throw new \Exception($msg, self::COUPON_ERR_CODE); // message thrown not used by WC :'('
			}
		}
		return $valid;
	}

	/** Extract point discount from order.
	 *	Get relevant pools and point stack info.
	 *	@return false|array if no point payment required.
	 *	Else return an array with one entry per point stack:
	 *	* pools
	 *	* needs
	 *	* max */
	protected function getPaymentFromOrder($order, $throwException=true)
	{
		if (!$order)
			return false;

		// get discounts in coupons list
		$data = \array_filter(\array_map(array($this, 'fromOrderItem'), $order->get_coupons()));
		if (!$data)
			return false;

		// group by stack
		$stacks = array();
		foreach ($data as $discount)
		{
			if (!isset($discount['paid']) || $discount['paid']) // before 4.1.1 'paid' was not set but payment done since checkout
				continue;

			$pool = self::getPool($discount);
			if (!$pool) {
				if (!$throwException) {
					$this->setOrderFailed($order, sprintf(__('The Reward "%s" is unknown.', 'woorewards-lite'), $discount['pool_name']));
					return false;
				} else {
					throw new \Exception(sprintf(__('The Reward "%s" in your cart is unknown.', 'woorewards-lite'), $discount['pool_name']));
				}
			}
			if (!$pool->getOption('direct_reward_mode')) {
				if (!$throwException) {
					$this->setOrderFailed($order, sprintf(__('"%s" does not support this kind of reward anymore.', 'woorewards-lite'), $pool->getOption('display_title')));
					return false;
				} else {
					throw new \Exception(sprintf(__('"%s" does not support this kind of reward anymore.', 'woorewards-lite'), $pool->getOption('display_title')));
				}
			}

			$stack = $pool->getStackId();
			if (!isset($stacks[$stack])) {
				$stacks[$stack] = array(
					'stack'     => $stack,
					'ref'       => $pool,
					'needs'     => 0,
					'max'       => 0,
					'discounts' => array(),
				);
			}
			$stacks[$stack]['discounts'][] = $discount;
		}
		if (!$stacks)
			return false;

		$userId = $order->get_customer_id('edit');
		if (!$userId)
		{
			$need = \reset($stacks);
			if (!$throwException) {
				$this->setOrderFailed($order, sprintf(
					__('At least one coupon requires %s. Guest order is not supported.', 'woorewards-lite'),
					\LWS_WooRewards::getPointSymbol(0, $need['ref']->getName())
				));
				return false;
			} else {
				throw new \Exception(sprintf(
					__('At least one coupon requires %s. You must log in to continue.', 'woorewards-lite'),
					\LWS_WooRewards::getPointSymbol(0, $need['ref']->getName())
				));
			}
		}

		// compute point usage on each stack
		foreach ($stacks as $stackId => &$stack)
		{
			$stack['needs'] = \array_sum(\array_column($stack['discounts'], 'points'));
			$stack['max'] = $stack['ref']->getPoints($userId);
		}

		return $stacks;
	}

	/** @return bool if order status changed */
	protected function setOrderFailed(&$order, $text=false)
	{
		if ($text)
			$order->add_order_note($text);

		$status = \get_option('lws_woorewards_pointdiscount_pay_order_failure', 'failed');
		if( \trim($status, '_') ) {
			if( !\in_array($status, $this->getPayOrderStatus()) ){
				$order->update_status($status);
				return true;
			}
		}
		return false;
	}

	function legacyCheck($orderId, $postedData, $order)
	{
		$this->check($order);
	}

	/** For each discount in order, check the points cost */
	function check($order)
	{
		$stacks = $this->getPaymentFromOrder($order, true);
		if( false === $stacks )
			return;

		// check user get enough to pay
		foreach ($stacks as $stackId => &$need)
		{
			if ($need['needs'] > $need['max'])
			{
				throw new \Exception(sprintf(
					__('You do not have enough %1$s to purchase the reward %2$s.', 'woorewards-lite'),
					\LWS_WooRewards::getPointSymbol(0, $need['ref']->getName()),
					$need['ref']->getOption('display_title')
				));
			}
		}
	}

	/**	@see maybePay */
	function legacyMaybePay($orderId, $postedData, $order)
	{
		$this->maybePay($order);
	}

	/**	To be hooked at order creation.
	 *	If the initial order status is already one of the selected one.
	 *	Since it will trigger no action on change. */
	public function maybePay($order)
	{
		if (\in_array($order->get_status(), $this->getPayOrderStatus())) {
			$this->pay($order->get_id(), $order);
		}
	}

	/** For each discount in order, pay the points cost */
	function pay($orderId, $order)
	{
		$stacks = $this->getPaymentFromOrder($order, false);
		if( false === $stacks )
			return;

		// check user get enough to pay
		$failed = array();
		foreach ($stacks as $stackId => &$need) {
			if ($need['needs'] > $need['max'])
				$failed[] = $need;
		}
		if ($failed) {
			if ($this->setOrderFailed($order)) {
				foreach ($failed as &$need) {
					$order->add_order_note(sprintf(
						__('Customer does not have enough %1$s to purchase the reward %2$s.', 'woorewards-lite'),
						\LWS_WooRewards::getPointSymbol(0, $need['ref']->getName()),
						$need['ref']->getOption('display_title')
					));
				}
				return; // if not marked with a status, use points and go down to negative amounts
			}
		}

		// keep total cost with order
		$order->add_meta_data('lws_woorewards_pointdiscount_costs', \array_column($stacks, 'needs', 'stack'));
		$order->save_meta_data();

		$userId = $order->get_customer_id('edit');
		foreach ($stacks as $stackId => &$need)
		{
			foreach ($need['discounts'] as &$discount)
			{
				$pool = self::getPool($discount);
				$title = $pool->getOption('display_title');

				// pay points
				$reason = \LWS\WOOREWARDS\Core\Trace::byReason(
					array('Reward from %1$s on Order #%2$s', $title, $order->get_order_number()),
					'woorewards-lite'
				)->setOrigin(self::CODE_PREFIX . $pool->getName())->setOrder($orderId);

				$pool->usePoints($userId, $discount['points'], $reason);
				$discount['paid'] = true;
				$discount['item']->update_meta_data('wr_discount_data', \array_merge(
					$discount, array('pool' => false, 'item' => false,)
				));
				$discount['item']->save_meta_data();

				// keep note on order
				\LWS\WOOREWARDS\Core\OrderNote::add($order, sprintf(
					_x('Use <i>%1$s</i> from <i>%2$s</i> to get a discount on this order', 'order note', 'woorewards-lite'),
					\LWS_WooRewards::formatPointsWithSymbol($discount['points'], $pool->getName()),
					$title
				), $pool);
			}
		}
	}

	/* If a coupon is removed and it's a one of us, clear assigned points */
	function remove($code)
	{
		/// point discount flag - pool name
		$refs = \explode('-', $code, 2);
		if (2 != count($refs))
			return;

		if (self::CODE_PREFIX != $refs[0])
			return;

		$userId = \get_current_user_id();
		if (!$userId)
			return;

		if (!\WC()->cart)
			return;
		if (\in_array($code, \WC()->cart->get_applied_coupons())) {
			// still in real cart, so let it be
			return;
		}

		$pool = \apply_filters('lws_woorewards_get_pools_by_args', false, array('system' => $refs[1]), $userId)->last();
		if ($pool)
		{
			$stackId = $pool->getStackId();
			\update_user_meta($userId, 'lws_wr_points_on_cart_' . $stackId, 0);
		}
	}

	/** WC set code twice, the second with the filtered code value.
	 * So, we must to look deeper for original code since WooCommerce
	 * never use its own $context argument. */
	function fromOrderItem($item)
	{
		$data = $item->get_meta('wr_discount_data', true, 'edit');
		if ($data)
		{
			$data['item'] = $item;
			$data['pool'] = false;
		}
		return $data;
	}

	function createOrderItem($item, $code, $coupon, $order)
	{
		$data = self::getDiscountMeta($coupon);
		if ($data) {
			$data['pool'] = false; // no need to save such Object, we have pool_name to reload it.
			$data['item'] = false;
			$item->add_meta_data('wr_discount_data', $data);
		}
	}

	/** Get a label (instead of raw code) from Order Item */
	function asCode($code, $item)
	{
		if ($item && \is_a($item, '\WC_Order_Item_Coupon'))
		{
			$data = $item->get_meta('wr_discount_data', true, 'edit');
			if ($data)
			{
				return $this->getTitle($data);
			}
		}
		return $code;
	}

	public static function getPool($discountData)
	{
		static $pools = array();
		$name = $discountData['pool_name'];
		if (isset($pools[$name]))
			return $pools[$name];
		else
			return ($pools[$name] = \apply_filters('lws_woorewards_get_pools_by_args', false, array('system' => $name, 'force' => true), false)->last());
	}

	private function poedit()
	{
		__('Reward from %1$s on Order #%2$s', 'woorewards-lite');
	}

	function emptied()
	{
		$userId = \intval(\get_current_user_id());
		if ($userId) {
			global $wpdb;
			$wpdb->query($wpdb->prepare("UPDATE {$wpdb->usermeta} SET meta_value='0' WHERE user_id=%d AND meta_key LIKE 'lws_wr_points_on_cart_%'", $userId));
		}
	}

	/** Ajax
	 */
	function reserveAmount()
	{
		if (!(isset($_REQUEST['nonce']) && \wp_verify_nonce($_REQUEST['nonce'], 'lws_woorewards_reserve_pointsoncart')))
			\wp_send_json(array('error' => __("Action control failed. Try to refresh the page.", 'woorewards-lite')));

		$userId = \get_current_user_id();
		if (!$userId)
			\wp_send_json(array('error' => __("A connected user is required.", 'woorewards-lite')));

		if (!(isset($_REQUEST['system']) && ($pool = \sanitize_key($_REQUEST['system']))))
			\wp_send_json(array('error' => __('Missing destination or bad format.', 'woorewards-lite')));

		if (!\WC()->cart)
			\wp_send_json(array('error' => __('Cannot load the Cart. Operation aborted.', 'woorewards-lite')));

		$pool = \apply_filters('lws_woorewards_get_pools_by_args', false, array('system' => $pool), $userId)->last();
		if (!$pool)
			\wp_send_json(array('error' => __('Points and Rewards System missing or access not granted.', 'woorewards-lite')));
		if (!$pool->getOption('direct_reward_mode'))
			\wp_send_json(array('error' => __('Points and Rewards System does not accept this kind of reward.', 'woorewards-lite')));

		$stackId = $pool->getStackId();
		$max = $pool->getPoints($userId);

		$points = \sanitize_text_field(isset($_REQUEST['amount']) ? $_REQUEST['amount'] : 0);
		$points = (int)$pool->reversePointsFormat($points);
		$points = \max(0, \min($points, $max));
		\update_user_meta($userId, 'lws_wr_points_on_cart_' . $stackId, $points);

		$code = self::CODE_PREFIX . '-' . $pool->getName();
		if ($points)
		{
			// add coupon if not exists
			if (!\WC()->cart->has_discount($code))
				\WC()->cart->apply_coupon($code);
		}
		else
		{
			// silently remove coupon if exists
			if (\WC()->cart->has_discount($code))
				\WC()->cart->remove_coupon($code);
		}

		$formated = \LWS_WooRewards::formatPoints($points, $pool->getName());
		\wp_send_json(array(
			'contribution' => $points,
			'max' => $max,
			'formated' => $formated,
			'dispMax' => \LWS_WooRewards::formatPoints($max, $pool->getName()),
			'success' => sprintf(__('Use %1$s from %2$s', 'woorewards-lite'), $formated, $pool->getOption('display_title')),
		));
	}
}
