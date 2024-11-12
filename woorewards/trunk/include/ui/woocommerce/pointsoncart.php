<?php
namespace LWS\WOOREWARDS\Ui\Woocommerce;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Provide widget in Cart and Checkout page to
 * convert points to some kind of immediate disconut.
 * Show point amount used resume in Cart and Checkout Subtotal.
 * The widget lets user choose the point amount to use on that Cart. */
class PointsOnCart
{
	const CART_ASIDE  = 'cart_collaterals';
	const CART_AFTER  = 'after_products';
	const CART_NONE   = 'not_displayed';

	const CHECKOUT_TOP      = 'top_page';
	const CHECKOUT_CUSTOMER = 'before_customer';
	const CHECKOUT_REVIEW   = 'before_review';
	const CHECKOUT_NONE     = 'not_displayed';

	private $positionInCart     = 'not_displayed';
	private $positionInCheckout = 'not_displayed';
	private $pool               = false;
	private $stygen             = false;

	static function install()
	{
		// global hooks, works on any instance of pool or defined at last minute
		$me = new self();
		\add_filter('lws_adminpanel_stygen_content_get_' . 'lws_woorewards_points_to_cart', array($me, 'template'));
		\add_shortcode('wr_points_on_cart', array($me, 'shortcode'));
		\add_shortcode('wr_max_points_on_cart', array($me, 'shortcodeMaxUsable'));

		/** Admin */
		\add_filter('lws_woorewards_shortcodes', array($me, 'admin'));
		\add_filter('lws_woorewards_woocommerce_shortcodes', array($me, 'adminPro'));

		// refresh
		\add_action('wp_ajax_lws_woorewards_pointsoncart_bloc_refresh', array($me, 'ajaxRefresh'));
		\add_action('wp_ajax_nopriv_lws_woorewards_pointsoncart_bloc_refresh', array($me, 'ajaxRefresh'));
		\add_action('wp', array($me, 'formValidation')); // be sure cart is loaded AND computed

		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($me, 'registerScripts'));

		\add_action('lws_woorewards_pools_loaded', function () {
			/** Ask for a \LWS\WOOREWARDS\Collections\Pools instance */
			$pools = \apply_filters('lws_woorewards_pointsoncart_pools', false);
			if (false === $pools)
				$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, array('system' => 'default'), false);
			if (!$pools)
				return;

			$pools = $pools->filter(array(__CLASS__, 'filterPools'));
			if ($pools->count()) {
				$cartHook = self::getCartHook();
				$checkoutHook = self::getCheckoutHook();
				if ($cartHook || $checkoutHook) {
					foreach ($pools->sort()->asArray() as $pool) {
						$dedicated = new self($pool, $cartHook ? $cartHook[0] : false, $checkoutHook ? $checkoutHook[0] : false);
						if ($cartHook)
							\add_action($cartHook[1], array($dedicated, 'inCart'));
						if ($checkoutHook)
							\add_action($checkoutHook[1], array($dedicated, 'inCheckout'));
					}
				}
			}
		});
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

	protected function __construct($pool=false, $positionInCart=false, $positionInCheckout=false)
	{
		$this->pool = $pool;
		$this->positionInCart     = $positionInCart ? $positionInCart : 'not_displayed';
		$this->positionInCheckout = $positionInCheckout ? $positionInCheckout : 'not_displayed';
	}

	function registerScripts()
	{
		$alias = array();
		$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, array(
			'showall' => true,
		), \get_current_user_id());
		if ($pools) {
			foreach ($pools->asArray() as $pool) {
				if ($pool->getOption('direct_reward_mode')) {
					$code = (\LWS\WOOREWARDS\PointDiscount::CODE_PREFIX . '-' . $pool->getName());
					$alias[$code] = $pool->getOption('display_title');
				}
			}
		}

		\wp_register_script('lws_wr_pointsoncart', LWS_WOOREWARDS_JS . '/pointsoncart.js', array('jquery'), LWS_WOOREWARDS_VERSION, true);
		\wp_register_script('lws_wr_pointsoncart_labels', LWS_WOOREWARDS_JS . '/coupons-block.js', array('jquery', 'wc-blocks-checkout'), LWS_WOOREWARDS_VERSION, true);
		\wp_localize_script('lws_wr_pointsoncart_labels', 'lws_wr_pointsoncart', array(
			'coupons' => $alias,
		));
		\wp_enqueue_script('lws_wr_pointsoncart_labels');

		\wp_register_style('lws_wr_pointsoncart_hard', LWS_WOOREWARDS_CSS . '/pointsoncart.css', array(), LWS_WOOREWARDS_VERSION);
		\wp_register_style('lws_wr_pointsoncart_custom', LWS_WOOREWARDS_CSS . '/templates/pointsoncart.css?stygen=lws_woorewards_points_to_cart_style', array(), LWS_WOOREWARDS_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_script('lws_wr_pointsoncart');
		\wp_enqueue_style('lws_wr_pointsoncart_hard');
		\wp_enqueue_style('lws_wr_pointsoncart_custom');
	}

	public function admin($fields)
	{
		$fields['pointsoncart'] = array(
			'id' => 'wr_points_on_cart',
			'title' => __("Points on Cart Tool", 'woorewards-lite'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_points_on_cart]',
				'description' =>  __("This shortcode is used to display the Points on Cart tool.", 'woorewards-lite') . "<br/>" .
				__("You can customize its appearance in the Widgets Tab.", 'woorewards-lite'),
				'options' => array(
					array(
						'option' => 'reload',
						'desc' => __("(Optional) Set it to true to force a page reload when customers modify the points they want to apply on the order", 'woorewards-lite'),
						'example' => '[wr_points_on_cart reload="true"]'
					),
					'layout' => array(
						'option' => 'layout',
						'desc' => __("(Optional) Select how the tool is displayed. 3 possible values :", 'woorewards-lite'),
						'options' => array(
							array(
								'option' => 'horizontal',
								'desc'   => __("Default value. Elements are displayed in one line", 'woorewards-lite'),
							),
							array(
								'option' => 'vertical',
								'desc'   => __("Elements are displayed on top of another", 'woorewards-lite'),
							),
							array(
								'option' => 'half',
								'desc'   => __("If you present this tool next to the cart totals block, use this option to display the tool vertically with some spacing", 'woorewards-lite'),
							),
						),
						'example' => '[wr_points_on_cart layout="vertical"]'
					),
					'need_points' => array(
						'option' => 'need_points',
						'desc' => __("(Optional, default is false) Set it to true to show this widget only if the current customer has points to spend.", 'woorewards-lite'),
						'example' => '[wr_points_on_cart need_points="true"]'
					),
				),
			)
		);
		$fields['pointsoncartheader'] = array(
			'id' => 'lws_wooreward_points_cart_header',
			'title' => __("Tool Header", 'woorewards-lite'),
			'type' => 'text',
			'extra' => array(
				'placeholder' => __('Loyalty points discount', 'woorewards-lite'),
				'size' => '30',
				'wpml' => 'WooRewards - Points On Cart Action - Header',
			)
		);
		$fields['pocstyle'] = array(
			'id' => 'lws_woorewards_points_to_cart_style',
			'type' => 'stygen',
			'extra' => array(
				'purpose'  => 'filter',
				'template' => 'lws_woorewards_points_to_cart',
				'html'     => false,
				'css'      => LWS_WOOREWARDS_CSS . '/templates/pointsoncart.css',
				'help'     => __("Use the styling tool to change the tool's frontend appearance", 'woorewards-lite'),
				'subids'   => array(
					'lws_woorewards_points_to_cart_action_balance' => "WooRewards - Points On Cart Action - Balance",
					'lws_woorewards_points_to_cart_action_use'     => "WooRewards - Points On Cart Action - Use",
					'lws_woorewards_points_to_cart_action_update'  => "WooRewards - Points On Cart Action - Update",
					'lws_woorewards_points_to_cart_action_max'     => "WooRewards - Points On Cart Action - Max",
				),
			)
		);
		$fields['maxpointsoncart'] = array(
			'id' => 'wr_max_points_on_cart',
			'title' => __("Maximum Point Amount on Cart", 'woorewards-lite'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_max_points_on_cart raw=""]',
				'description' =>  __("This shortcode will show the maximum quantity of Point that could be used on this cart.", 'woorewards-lite'),
				'options'   => array(
					array(
						'option' => 'raw',
						'desc' => __("(Optional) If set, the amount will be a simple text. Otherwise, it will be presented inside a stylable element", 'woorewards-lite'),
					),
				),
			)
		);
		return $fields;
	}

	public function adminPro($fields)
	{
		$fields = $this->admin($fields);
		$fields['pointsoncart']['extra']['options'] = \array_merge(array(
			'system' => array(
				'option' => 'system',
				'desc'   => __("(Optional, comma separated) Select the points and rewards systems you want to show. If left empty, the first available system will be displayed", 'woorewards-lite') .
					"<br/>" . __("You can find the points and rewards systems names in WooRewards → Points and Rewards", 'woorewards-lite'),
			),
		), $fields['pointsoncart']['extra']['options']);
		return $fields;
	}

	protected static function getCartHook()
	{
		$pos = \get_option('lws_woorewards_points_to_cart_pos', self::CART_NONE);

		if( $pos == self::CART_ASIDE )
			return array($pos, 'woocommerce_cart_collaterals');
		else if( $pos == self::CART_AFTER )
			return array($pos, 'woocommerce_after_cart_table');
		else
			return false;
	}

	protected static function getCheckoutHook()
	{
		$pos = \get_option('lws_woorewards_points_to_checkout_pos', self::CHECKOUT_NONE);

		if( $pos == self::CHECKOUT_TOP )
			return array($pos, 'woocommerce_before_checkout_form');
		else if( $pos == self::CHECKOUT_CUSTOMER )
			return array($pos, 'woocommerce_before_checkout_billing_form');
		else if( $pos == self::CHECKOUT_REVIEW )
			return array($pos, 'woocommerce_checkout_before_order_review');
		else
			return false;
	}

	protected function getPositionClass($origin)
	{
		$class = '';
		if( 'shortcode' == $origin )
		{
			$class .= ' wr-pointsoncart-shortcode';
		}
		else if( 'demo' == $origin )
		{
			if( $this->positionInCart == self::CART_ASIDE )
				$class .= ' cross-sells';
		}
		else
		{
			if( $this->positionInCart == self::CART_ASIDE )
				$class .= ' cross-sells wr-action-collateral';
			else if( $this->positionInCart == self::CART_AFTER )
				$class .= ' wr-action-after-cart';

			if( $this->positionInCheckout == self::CHECKOUT_TOP )
				$class .= ' wr-action-before-checkout';
			else if( $this->positionInCheckout == self::CHECKOUT_CUSTOMER )
				$class .= ' wr-action-before-billing';
			else if( $this->positionInCheckout == self::CHECKOUT_REVIEW )
				$class .= ' wr-action-before-order-review';
		}
		return $class;
	}

	/** Update used points by POST instead of Ajax (option 'reload') */
	function formValidation()
	{
		if( isset($_POST['lws_wr_pointsoncart_amount_value']) )
		{
			if( isset($_POST['nonce']) && \wp_verify_nonce($_POST['nonce'], 'lws_woorewards_reserve_pointsoncart') && \is_numeric($_POST['lws_wr_pointsoncart_amount_value']) )
			{
				$points = \sanitize_text_field($_POST['lws_wr_pointsoncart_amount_value']);
				$userId = \get_current_user_id();
				if( $userId && isset($_POST['system']) && ($pool = \sanitize_key($_POST['system'])) )
				{
					$pool = \apply_filters('lws_woorewards_get_pools_by_args', false, array('system' => $pool), $userId)->last();
					if( !$pool )
						return;
					if( !$pool->getOption('direct_reward_mode') )
						return;

					if( !\WC()->cart )
					{
						error_log('Points on Cart value update too soon, WooCommerce cart not init yet.');
						return;
					}
					$coupons = \WC()->cart->get_applied_coupons();

					$points = (int)$pool->reversePointsFormat($points);
					$stackId = $pool->getStackId();
					$max = $pool->getPoints($userId);
					$points = \max(0, \min($points, $max));
					\update_user_meta($userId, 'lws_wr_points_on_cart_'.$stackId, $points);

					$code = 'wr_points_on_cart-' . $pool->getName();
					if( $points )
					{
						// add coupon if not exists
						if( !\WC()->cart->has_discount($code) )
							\WC()->cart->apply_coupon($code);
					}
					else
					{
						// silently remove coupon if exists
						if( \WC()->cart->has_discount($code) )
							\WC()->cart->remove_coupon($code);
					}

					if( \wp_redirect(\add_query_arg(array())) )
						exit;
				}
			}
		}
	}

	/** @return bool
	 *	@param $pool string|\LWS\WOOREWARDS\Core\Pool pool instance or pool name
	 */
	function isCouponAppliedOnCart($pool)
	{
		if (\WC()->cart) {
			$code = 'wr_points_on_cart-' . (\is_string($pool) ? $pool : $pool->getName());
			return \WC()->cart->has_discount($code);
		}
		return false;
	}

	function template()
	{
		$this->stygen = true;
		$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->create('dummy')->last();
		$pool->setOptions(array(
			'title'    => __("Demo. System", 'woorewards-lite'),
			'type'     => \LWS\WOOREWARDS\Core\Pool::T_STANDARD,
			'disabled' => false,
			'direct_reward_point_rate'    => 0.1,
			'direct_reward_discount_cats' => array(),
		));
		$info = \apply_filters('lws_woorewards_pointsoncart_template_info', array(
			'amount' => 150,
			'max'    => 100,
			'used'   => 20,
			'stack'  => 'dummy',
			'pool'   => $pool,
		));
		$content = $this->getContent('demo', $info);
		$this->stygen = false;
		return $content;
	}

	function inCart()
	{
		if( $this->pool )
		{
			if( $info = $this->getInfo($this->pool) )
				echo $this->getContent('cart', $info);
			else
				echo $this->getPlaceholder($this->pool, 'cart');
		}
	}

	function inCheckout()
	{
		if( $this->pool )
		{
			if( $info = $this->getInfo($this->pool) )
				echo $this->getContent('checkout', $info);
			else
				echo $this->getPlaceholder($this->pool, 'checkout');
		}
	}

	function shortcode($atts=array(), $content='')
	{
		$atts = \wp_parse_args($atts, array(
			'layout'      => 'horizontal',
			'system'      => '',
			'reload'      => false,
			'need_points' => false,
		));
		$userId = \get_current_user_id();
		if( !$userId )
			return \do_shortcode((string)$content);

		if (!$atts['system']) {
			$atts['showall'] = true;
		}
		$pool = $this->getPool($atts, $userId);
		if ($pool) {
			$info = $this->getInfo($pool);
			if ($info && \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['need_points'])) {
				if ($info['amount'] <= 0) $info = false;
			}

			if ($info) {
				return $this->getContent('shortcode', $info, \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['reload']), $atts['layout']);
			} else {
				return $this->getPlaceholder($pool, 'shortcode');
			}
		}
		return \do_shortcode((string)$content);
	}

	/** Echo the html bloc and die.
	 * @param $_GET['action'] (string) "lws_woorewards_pointsoncart_bloc_refresh"
	 * @param $_GET['origin'] (string) given by the DOM `.lws_wr_pointsoncart_bloc[data-origin]` */
	function ajaxRefresh()
	{
		// clean args
		$origin = (isset($_GET['origin']) ? \sanitize_key($_GET['origin']) : '');
		if( !\in_array($origin, array('cart', 'checkout', 'shortcode')) )
			$origin = '';

		$this->positionInCart = $this->positionInCheckout = '';
		if( 'cart' == $origin )
		{
			if( $pos = self::getCartHook() )
				$this->positionInCart = $pos[0];
		}
		else if( 'checkout' == $origin )
		{
			if( $pos = self::getCheckoutHook() )
				$this->positionInCheckout = $pos[0];
		}

		$atts = array();
		foreach (array('system', 'shared', 'force') as $a) {
			if (isset($_GET[$a])) $atts[$a] = \sanitize_text_field($_GET[$a]);
		}
		$pool = $this->getPool($atts);
		if( $pool )
		{
				if( $info = $this->getInfo($pool) )
					echo $this->getContent($origin, $info);
				else
					echo $this->getPlaceholder($pool, $origin);
		}
		// all is done, kill process
		exit;
	}

	protected function getPool($atts, $userId=false)
	{
		if( false === $userId )
			$userId = \get_current_user_id();

		$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts, $userId);
		if( !$pools )
			return false;

		$pools = $pools->filter(array(__CLASS__, 'filterPools'));
		return $pools->last();
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

	/** Format relevant information for partial payment display
	 * @return false|array */
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
			'max'    => $max,
			'used'   => $used,
			'stack'  => $stackId,
			'pool'   => $pool,
		);
	}

	function shortcodeMaxUsable($atts=array(), $content='')
	{
		$userId = \get_current_user_id();
		if( !$userId )
			return $content;

		$atts = \wp_parse_args($atts, array('raw' => ''));
		$pool = $this->getPool($atts, $userId);

		if( $pool && \WC()->cart )
		{
			$content = $this->getMaxPoints($pool, \WC()->cart, $userId);
			if( !$atts['raw'] )
				$content = \LWS_WooRewards::formatPointsWithSymbol($content, $pool->getName());
		}
		return $content;
	}

	function getPlaceholder($pool, $origin, $content='')
	{
		if (!$this->stygen)
			$this->enqueueScripts();

		$class = '';
		$url = '';
		if (!$this->stygen)
		{
			$class = 'lws_wr_pointsoncart_bloc';
			$url = \esc_attr(\admin_url('/admin-ajax.php'));
		}

		$content = \apply_filters('lws_woorewards_pointsoncart_placeholder', $content, $origin, $this);
		if( $content )
		{
			$class .= ' lwss_selectable lws-wr-pointsoncart ';
			$class .= ($origin=='cart') ? 'cart-pointsoncart' : 'order-pointsoncart' ;
			$class .= $this->getPositionClass($origin);
			$content = \do_shortcode((string)$content);
		}
		else
			$content = '&nbsp;';

		$pool = $pool ? \esc_attr($pool->getName()) : '';
		return "<div class='{$class}' data-origin='{$origin}' data-pool='{$pool}' data-url='{$url}'>{$content}</div>";
	}

	/** Display the partial payment tool on cart or checkout page,
	 *	@param $forceReload (null||bool) if null, read global option. */
	function getContent($origin, $info, $forceReload = null, $layout = 'horizontal')
	{
		if (!$this->stygen)
		{
			if( !\WC()->cart || \WC()->cart->is_empty() )
				return '';
			$this->enqueueScripts();
		}
		$poolInfo = array_merge(array(
			'name' 			=> $info['pool']->getName(),
			'symbols'		=> $info['pool']->getSymbol('2'),
		), $info['pool']->getOptions(array(
			'direct_reward_point_rate',
			'direct_reward_max_percent_of_cart',
			'direct_reward_min_points_on_cart',
			'direct_reward_max_points_on_cart',
			'direct_reward_total_floor',
			'direct_reward_min_subtotal'
		)));

		$layout = \strtolower(\substr(\trim($layout), 0, 2));
		if ('ho' == $layout) $layout = ' horizontal';
		elseif ('ve' == $layout) $layout = ' vertical';
		elseif ('ha' == $layout) $layout = ' half';
		else $layout = '';

		$balance = $info['pool']->formatPoints($info['amount']);
		$labels = array(
			'balance'     => sprintf(__("Your %s :", 'woorewards-lite'), $poolInfo['symbols']),
			'use'         => _x("Used Amount :", "Part of Points to use as reward", 'woorewards-lite'),
			'update'      => __("Apply", 'woorewards-lite'),
			'max'         => __("Use Max Amount", 'woorewards-lite'),
		);
		$labels['balance']     = \lws_get_option('lws_woorewards_points_to_cart_action_balance', $labels['balance']);
		$labels['use']         = \lws_get_option('lws_woorewards_points_to_cart_action_use'    , $labels['use']);
		$labels['update']      = \lws_get_option('lws_woorewards_points_to_cart_action_update' , $labels['update']);
		$labels['max']         = \lws_get_option('lws_woorewards_points_to_cart_action_max'    , $labels['max']);
		$labels['use']     = \apply_filters('wpml_translate_single_string', $labels['use']    , 'Widgets', "WooRewards - Points On Cart Action - Use");
		$labels['update']  = \apply_filters('wpml_translate_single_string', $labels['update'] , 'Widgets', "WooRewards - Points On Cart Action - Update");
		$labels['max']     = \apply_filters('wpml_translate_single_string', $labels['max']    , 'Widgets', "WooRewards - Points On Cart Action - Max");

		$esc = array(
			'amount' => \esc_attr($info['pool']->formatPointsNumber($info['amount'])),
			'max'    => \esc_attr($info['pool']->formatPointsNumber(\max(0, $info['max']))),
			'used'   => \esc_attr($info['pool']->formatPointsNumber(\max(0, $info['used']))),
			'update' => \esc_attr($labels['update']),
			'pool'  => \esc_attr($info['pool']->getName()),
			'url'    => \esc_attr(\admin_url('/admin-ajax.php')),
			'nonce'  => \esc_attr(\wp_create_nonce('lws_woorewards_reserve_pointsoncart')),
		);

		$applydisabled = ' disabled';
		$maxdisabled = ($info['used'] < $info['max']) ? '' : ' disabled';
		// check on cart for coupons, do not disabled if none or amount is different
		if ($this->stygen || ($info['used'] && !$this->isCouponAppliedOnCart($info['pool']))) {
			$applydisabled = '';
			$maxdisabled = '';
		}

		$class = ($origin == 'cart') ? ' cart-pointsoncart' : ' order-pointsoncart';
		$class .= $this->getPositionClass($origin);
		$reload = 'off';
		if (true === $forceReload)
			$reload = 'on';
		elseif (null === $forceReload && \get_option(('cart'==$origin) ? 'lws_woorewards_points_to_cart_reload' : 'lws_woorewards_points_to_checkout_reload'))
			$reload = 'on';
		$header = \lws_get_option('lws_wooreward_points_cart_header', __('Loyalty points discount', 'woorewards-lite'));
		$header = \apply_filters('wpml_translate_single_string', $header, 'Widgets', "WooRewards - Points On Cart Action - Header");

		$details = implode('', $this->getDetailsText($origin, $info, $poolInfo));
		$url = '';
		if (!$this->stygen)
		{
			$class .= ' lws_wr_pointsoncart_bloc';
			$url = \esc_attr(\admin_url('/admin-ajax.php'));
		}

		$content = <<<EOT
		<div class='lwss_selectable lws-wr-pointsoncart{$class}{$layout}' data-origin='{$origin}' data-pool='{$esc['pool']}' data-url='{$url}' data-type="Main Container">
			<h2>{$header}</h2>
			<div class='lwss_selectable lws-wr-cart lws_wr_pointsoncart_contribution' data-type='Inner Container' data-nonce='{$esc['nonce']}' data-url='{$esc['url']}' data-reload='{$reload}'>
				<div class='lwss_selectable wr-cart-balance' data-type='Balance Container'>
					<div class='lwss_selectable wr-cart-balance-label' data-editable='text' data-id='lws_woorewards_points_to_cart_action_balance' data-type='Balance Label'>
						<span class='lwss_modify_content'>{$labels['balance']}</span>
					</div>
					<div class='lwss_selectable wr-cart-balance-value' data-type='Balance Value'>{$balance}</div>
				</div>
				<div class='lwss_selectable wr-cart-input' data-type='Input Line'>
					<div class='lwss_selectable wr-cart-use-label' data-editable='text' data-id='lws_woorewards_points_to_cart_action_use' data-type='Input Label'>
						<span class='lwss_modify_content'>{$labels['use']}</span>
					</div>
					<div class='lwss_selectable wr-cart-line-input' data-type='Input Area'>
						<input name='lws_wr_pointsoncart_amount_value' autocomplete='off' value='{$esc['used']}' class='lwss_selectable wr-input-amount lws_wr_pointsoncart_amount_value' data-usemax='{$esc['max']}' data-max='{$esc['amount']}' data-type="Amount Input">
					</div>
				</div>
				<div class='lwss_selectable wr-cart-buttons' data-type='Buttons Line'>
					<button data-editable='text' data-id='lws_woorewards_points_to_cart_action_max' type='button' title='{$esc['max']}' class='button lwss_selectable wr-cart-max lws_wr_pointsoncart_use_max_amount'{$maxdisabled} data-type='Max Button'>
						<span class='lwss_modify_content'>{$labels['max']}</span>
					</button>
					<button data-editable='text' data-id='lws_woorewards_points_to_cart_action_update' type='button' class='button lwss_selectable wr-cart-apply lws_wr_pointsoncart_amount_apply' name='update_pointsoncart' value='{$esc['update']}'{$applydisabled} data-type='Update Button'>
						<span class='lwss_modify_content'>{$labels['update']}</span>
					</button>
				</div>
			</div>
			{$details}
		</div>
EOT;
		return $content;
	}

	protected function getDetailsText($origin, $info, $poolInfo)
	{
		$details = array();

		if (\trim($poolInfo['direct_reward_point_rate']) != '') {
			$details['rate'] = sprintf(
				"<div class='lwss_selectable wr-rateinfo' data-type='Point Rate'>%s</div>",
				sprintf(
					__('Every %s you use is worth %s', 'woorewards-lite'),
					$info['pool']->formatPoints(1, true),
					\LWS\Adminpanel\Tools\Conveniences::getCurrencyPrice($poolInfo['direct_reward_point_rate'], \apply_filters('lws_woorewards_point_rate_displays_real_decimals', false), true)
				)
			);
		}

		if (\intval($poolInfo['direct_reward_min_points_on_cart']) > 0) {
			$details['min_points'] = sprintf(
				"<div class='lwss_selectable wr-minpoints' data-type='Min Points'>%s</div>",
				sprintf(
					__('You can use a minimum of %s on a single cart.', 'woorewards-lite'),
					$info['pool']->formatPoints($poolInfo['direct_reward_min_points_on_cart'], true)
				)
			);
		}

		if (\intval($poolInfo['direct_reward_max_points_on_cart']) > 0) {
			$details['max_points'] = sprintf(
				"<div class='lwss_selectable wr-maxpoints' data-type='Max Points'>%s</div>",
				sprintf(
					__('You can use a maximum of %s on a single cart.', 'woorewards-lite'),
					$info['pool']->formatPoints($poolInfo['direct_reward_max_points_on_cart'], true)
				)
			);
		}

		if ($poolInfo['direct_reward_max_percent_of_cart'] != '' && $poolInfo['direct_reward_max_percent_of_cart'] < 100.0) {
			$details['max_perc'] = sprintf(
				"<div class='lwss_selectable wr-maxpercent' data-type='Max Cart Percentage'>%s</div>",
				sprintf(
					__('You can only use your %s to reduce the cart total by %s%%', 'woorewards-lite'),
					$poolInfo['symbols'],
					$poolInfo['direct_reward_max_percent_of_cart']
				)
			);
		}

		if ($poolInfo['direct_reward_total_floor'] != '' && $poolInfo['direct_reward_total_floor'] > 0.0) {
			$details['floor'] = sprintf(
				"<div class='lwss_selectable wr-lowerlimit' data-type='Lower Cart Limit'>%s</div>",
				sprintf(
					__('You can only use your %s to reduce the cart total to %s', 'woorewards-lite'),
					$poolInfo['symbols'],
					\LWS\Adminpanel\Tools\Conveniences::getCurrencyPrice($poolInfo['direct_reward_total_floor'])
				)
			);
		}

		if ($poolInfo['direct_reward_min_subtotal'] != '' && $poolInfo['direct_reward_min_subtotal'] > 0.0) {
			$details['min'] = sprintf(
				"<div class='lwss_selectable wr-mincartamount' data-type='Min Cart Amount'>%s</div>",
				sprintf(
					__('The cart subtotal needs to be over %s if you want to use %s', 'woorewards-lite'),
					\LWS\Adminpanel\Tools\Conveniences::getCurrencyPrice($poolInfo['direct_reward_min_subtotal']),
					$poolInfo['symbols']
				)
			);
		}
		return \apply_filters('lws_woorewards_pointsoncart_details_text', $details, $info, $poolInfo, $origin);
	}
}