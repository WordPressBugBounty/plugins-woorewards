<?php
namespace LWS\WOOREWARDS\Ui\Blocks;
if (!defined('ABSPATH')) exit();

/**	Exchange data with client-side cart.
 *	@see https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce-blocks/docs/third-party-developers/extensibility/rest-api/extend-rest-api-update-cart.md
 *	@see https://github.com/woocommerce/woocommerce/tree/trunk/plugins/woocommerce-blocks/packages/checkout/utils
 */
class Extension {
	const API = 'lws_woorewards';

	public static function install()
	{
		$me = new self();
		\woocommerce_store_api_register_endpoint_data([
			'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
			'namespace'       => self::API,
			'data_callback'   => [$me, 'apiData'],
			'schema_callback' => [$me, 'apiSchema'],
			'schema_type'     => ARRAY_A,
		]);
		\woocommerce_store_api_register_endpoint_data([
			'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
			'namespace'       => self::API,
			'data_callback'   => [$me, 'apiData'],
			'schema_callback' => [$me, 'apiSchema'],
			'schema_type'     => ARRAY_A,
		]);
		\woocommerce_store_api_register_update_callback([
		'namespace' => self::API,
		'callback'  => [$me, 'apiUpdate'],
		]);
		return $me;
	}

	/** @see https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce-blocks/docs/third-party-developers/extensibility/rest-api/available-endpoints-to-extend.md */
	public function apiData()
	{
		$systems = [];
		foreach ($this->getSystems() as $slug => $pool) {
			/** @var \LWS\WOOREWARDS\PRO\Core\Pool $pool */
			$info = $this->getInfo($pool);
			if (!$info) continue;
			$info['used_formated'] = $pool->formatPoints($info['used']);
			$info['max_formated'] = $pool->formatPoints($info['max']);
			$info['amount_formated'] = $pool->formatPoints($info['amount']);
			$systems[$slug] = $info;
		}
		$data = ['systems' => $systems];
		return \apply_filters('lws_woorewards_bloc_cart_data', $data, $this);
	}

	static function getSystems()
	{
		static $systems = null;
		if (null === $systems) {
			$systems = [];
			$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, ['showall' => true], \get_current_user_id());
			if ($pools) {
				$pools = $pools->filter([__CLASS__, 'filterPools']);
				$systems = \array_combine($pools->map(function($pool){
					return $pool->getName();
				}), $pools->asArray());
			}
		}
		return $systems;
	}

	function getInfo($pool)
	{
		$userId = \get_current_user_id();
		if( !$userId )
			return false;
		if( !\WC()->cart )
			return false;
		if( !$pool )
			return false;

		$stackId = $pool->getStackId();
		$max = $this->getMaxPoints($pool, \WC()->cart, $userId);
		$min = \intval($pool->getOption('direct_reward_min_points_on_cart'));
		$used = \intval(\get_user_meta($userId, 'lws_wr_points_on_cart_'.$stackId, true));

		if ($min > 0 && $min > $max) {
			\update_user_meta($userId, 'lws_wr_points_on_cart_'.$stackId, 0);
			$used = 0;
		} elseif ($used > $max) {
			\update_user_meta($userId, 'lws_wr_points_on_cart_'.$stackId, $max);
			$used = $max;
		} elseif ($used > 0 && $min > 0 && $min > $used) {
			\update_user_meta($userId, 'lws_wr_points_on_cart_'.$stackId, $min);
			$used = $min;
		}

		return array(
			'amount' => $pool->getPoints($userId),
			'min'    => $min,
			'max'    => $max,
			'used'   => $used,
			'stack'  => $stackId,
			'name'   => $pool->getName(),
		);
	}

	static function filterPools($pool)
	{
		if( !$pool->getOption('direct_reward_mode') )
			return false;
		if( !$pool->userCan() )
			return false;
		if (!$pool->isBuyable())
			return false;
		return true;
	}

	protected function getMaxPoints(&$pool, &$cart, $userId)
	{
		$rate = $pool->getOption('direct_reward_point_rate');
		if( $rate == 0.0 )
			return 0;
		$points = $pool->getPoints($userId);

		$total = $cart->get_subtotal();
		if( 'yes' === get_option('woocommerce_prices_include_tax') )
			$total += $cart->get_subtotal_tax();

		foreach($cart->get_applied_coupons() as $otherCode)
		{
			if (strpos($otherCode, 'wr_points_on_cart') === false) {
				$total -= $cart->get_coupon_discount_amount($otherCode);
			}
		}

		$currencyRate = \LWS\Adminpanel\Tools\Conveniences::getCurrencyPrice(1, false, false);
		if (0 != $currencyRate)
			$total =  $total / $currencyRate;

		$max = (int)\ceil($total / $rate);
		$points = \min($max, $points);
		$points = \apply_filters('lws_woorewards_pointdiscount_max_points', $points, $rate, $pool, $userId, $cart);
		return $points;
	}

	/** @see https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce-blocks/docs/third-party-developers/extensibility/rest-api/available-endpoints-to-extend.md */
	public function apiSchema()
	{
		$schema = [
			'systems' => [
				'description' => __('List of Loyalty systems.', 'woorewards-lite'),
				'context'     => ['view', 'edit'],
				'readonly'    => false,
				'type'        => 'array',
				'items'       => \array_fill_keys(\array_keys($this->getSystems()), [
					'description' => __('System description.', 'woorewards-lite'),
					'context'     => ['view', 'edit'],
					'readonly'    => false,
					'type'        => 'array',
					'items'       => [
						'used' => [
							'description' => __('Points used in Cart.', 'woorewards-lite'),
							'type'        => ['integer', 'null'],
							'context'     => ['view', 'edit'],
							'readonly'    => false,
						],
						'used_formated' => [
							'description' => __('Points used in Cart for display.', 'woorewards-lite'),
							'type'        => ['string', 'null'],
							'context'     => ['view', 'edit'],
							'readonly'    => false,
						],
						'amount' => [
							'description' => __('Available Points.', 'woorewards-lite'),
							'type'        => ['integer', 'null'],
							'context'     => ['view', 'edit'],
							'readonly'    => false,
						],
						'amount_formated' => [
							'description' => __('Available Points for display.', 'woorewards-lite'),
							'type'        => ['string', 'null'],
							'context'     => ['view', 'edit'],
							'readonly'    => false,
						],
						'min'    => [
							'description' => __('Minimum Points to use in Cart.', 'woorewards-lite'),
							'type'        => ['integer', 'null'],
							'context'     => ['view', 'edit'],
							'readonly'    => false,
						],
						'max'    => [
							'description' => __('Maximum Points to use in Cart.', 'woorewards-lite'),
							'type'        => ['integer', 'null'],
							'context'     => ['view', 'edit'],
							'readonly'    => false,
						],
						'max_formated' => [
							'description' => __('Maximum Points to use in Cart for display.', 'woorewards-lite'),
							'type'        => ['string', 'null'],
							'context'     => ['view', 'edit'],
							'readonly'    => false,
						],
						'stack'  => [
							'description' => __('Points reserve.', 'woorewards-lite'),
							'type'        => ['string', 'null'],
							'context'     => ['view', 'edit'],
							'readonly'    => false,
						],
						'name'   => [
							'description' => __('Loyalty system.', 'woorewards-lite'),
							'type'        => ['string', 'null'],
							'context'     => ['view', 'edit'],
							'readonly'    => false,
						],
					]
				]),
			]
		];
		return \apply_filters('lws_woorewards_bloc_cart_schema', $schema);
	}

	/** @see https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce-blocks/docs/third-party-developers/extensibility/rest-api/extend-rest-api-update-cart.md */
	public function apiUpdate($data)
	{
		if (\apply_filters('lws_woorewards_bloc_cart_update_is', true, $data)) {
			if ('use_points' === $data['action']) {
				$userId = \get_current_user_id();
				if ($userId) {
					$points = \sanitize_text_field($data['value']);
					$system = \apply_filters('lws_woorewards_get_pools_by_args', false, array(
						'system' => \sanitize_key($data['system'])
					), $userId)->last();
					if ($system) {
						$points = (int)$system->reversePointsFormat($points);
						$this->applyPointsOnCart($system, $points, $userId);
					}
				}
			}
		}
		\do_action('lws_woorewards_bloc_cart_updated', $data);
	}

	protected function applyPointsOnCart($system, $points, $userId)
	{
		$stackId = $system->getStackId();
		if (\WC()->cart) {
			$points = \min($points, $this->getMaxPoints($system, \WC()->cart, $userId));
		} else {
			$max = $system->getPoints($userId);
			$points = \max(0, \min($points, $max));
		}
		\update_user_meta($userId, 'lws_wr_points_on_cart_' . $stackId, $points);

		if (\WC()->cart) {
			$code = 'wr_points_on_cart-' . $system->getName();
			if ($points) {
				// add coupon if not exists
				if( !\WC()->cart->has_discount($code) )
					\WC()->cart->apply_coupon($code);
			} else {
				// silently remove coupon if exists
				if( \WC()->cart->has_discount($code) )
					\WC()->cart->remove_coupon($code);
			}
		}
	}
}