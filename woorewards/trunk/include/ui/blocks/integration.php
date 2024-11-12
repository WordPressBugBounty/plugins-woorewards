<?php
namespace LWS\WOOREWARDS\Ui\Blocks;
if (!defined('ABSPATH')) exit();

/**
 * Class for integrating with WooCommerce Blocks
 * @see https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce-blocks/docs/third-party-developers/extensibility/checkout-block/available-slot-fills.md#experimentaldiscountsmeta
 */
class Integration implements \Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface {
	const API = 'lws_woorewards';
	protected $styleHandlers = [];
	protected $scriptHandlers = [];

	/** An array of key, value pairs of data made available to the block on the client side.
	 * @return array */
	public function get_script_data() {
		$systems = [];
		foreach (\LWS\WOOREWARDS\Ui\Blocks\Extension::getSystems() as $pool) {
			/** @var \LWS\WOOREWARDS\PRO\Core\Pool $pool */
			$symbol = $pool->getSymbol(2);
			$systems[$pool->getName()] = [
				'show'   => true,
				'name'   => $pool->getName(),
				//'label'  => \wp_kses($pool->getOption('display_title'), array()),
				'field'  => sprintf(__("Use your %s", 'woorewards-lite'), $symbol),
				'title'  => __("You own", 'woorewards-lite'),
				'input'  => __("Enter a value", 'woorewards-lite'),
				'apply'  => __("Apply", 'woorewards-lite'),
				'details'=> $this->getDetails($pool),
			];
		}
		$data = [
			'enable'      => true,
			'logged-user' => (bool)\get_current_user_id(),
			'systems'     => $systems,
		];
		return \apply_filters('lws-woorewards_blocks_script_data', $data);
	}

	/** @return array of string */
	protected function getDetails($system)
	{
		$details = [];
		$info = array_merge([
			'name' 			=> $system->getName(),
			'symbols'		=> $system->getSymbol('2'),
		], $system->getOptions([
			'direct_reward_point_rate',
			'direct_reward_max_percent_of_cart',
			'direct_reward_min_points_on_cart',
			'direct_reward_max_points_on_cart',
			'direct_reward_total_floor',
			'direct_reward_min_subtotal'
		]));

		if (\trim($info['direct_reward_point_rate']) != '') {
			$details['rate'] = sprintf(
				__('%s → %s', 'woorewards-lite'),
				$system->formatPoints(1, true),
				\LWS\Adminpanel\Tools\Conveniences::getCurrencyPrice(
					$info['direct_reward_point_rate'],
					\apply_filters('lws_woorewards_point_rate_displays_real_decimals', false),
					true
				)
			);
		}

		if (\intval($info['direct_reward_min_points_on_cart']) > 0) {
			$details['min_points'] = sprintf(
				__('Minimum: %s', 'woorewards-lite'),
				$system->formatPoints($info['direct_reward_min_points_on_cart'], true)
			);
		}

		if (\intval($info['direct_reward_max_points_on_cart']) > 0) {
			$details['max_points'] = sprintf(
				__('Maximum: %s', 'woorewards-lite'),
				$system->formatPoints($info['direct_reward_max_points_on_cart'], true)
			);
		}

		if ($info['direct_reward_max_percent_of_cart'] != '' && $info['direct_reward_max_percent_of_cart'] < 100.0) {
			$details['max_perc'] = sprintf(
				__('Max. discount: %s%%', 'woorewards-lite'),
				$info['direct_reward_max_percent_of_cart']
			);
		}

		if ($info['direct_reward_total_floor'] != '' && $info['direct_reward_total_floor'] > 0.0) {
			$details['floor'] = sprintf(
				__('Min. total: %s', 'woorewards-lite'),
				\LWS\Adminpanel\Tools\Conveniences::getCurrencyPrice($info['direct_reward_total_floor'])
			);
		}

		if ($info['direct_reward_min_subtotal'] != '' && $info['direct_reward_min_subtotal'] > 0.0) {
			$details['min'] = sprintf(
				__('Requires at least a %s subtotal', 'woorewards-lite'),
				\LWS\Adminpanel\Tools\Conveniences::getCurrencyPrice($info['direct_reward_min_subtotal'])
			);
		}

		return \apply_filters('lws_woorewards_blocks_pointsoncart_details_text', $details, $system, $info);
	}

	/** The name of the integration.
	 * @return string */
	public function get_name() {
		return 'lws-wr-blocks';
	}

	/** When called invokes any initialization/setup for the integration. */
	public function initialize() {
		$this->registerBloc(array());
	}

	/** Registers the main JS file required to add filters and Slot/Fills. */
	public function registerBloc(array $options) {
		$options = \array_merge(array(
			'script' => 'index.js',
			'style'  => 'style-index.css',
			'asset'  => 'index.asset.php',
			'handle' => LWS_WOOREWARDS_PAGE . '-blocks',
			'style-handle' => true,
		), $options);

		if (true === $options['style-handle']) $options['style-handle'] = $options['handle'];

		$options['script'] = '/build/' . $options['script'];
		$options['style']  = '/build/' . $options['style'];
		$options['asset']  = LWS_WOOREWARDS_PATH . '/build/' . $options['asset'];

		$script_asset = array(
			'dependencies' => [],
			'version'      => self::version($options['script']),
		);
		if (\file_exists($options['asset'])) {
			$script_asset = \array_merge($script_asset, require($options['asset']));
		}

		if ($options['style-handle']) {
			\wp_enqueue_style(
				'lws-wr-blocks-blocks-integration',
				\plugins_url($options['style'], LWS_WOOREWARDS_FILE),
				[],
				self::version($options['style'])
			);
			$this->styleHandlers[$options['style-handle']] = $options['style-handle'];
		}

		if ($options['handle']) {
			\wp_register_script(
				$options['handle'],
				\plugins_url($options['script'], LWS_WOOREWARDS_FILE),
				$script_asset['dependencies'],
				$script_asset['version'],
				true
			);
			\wp_set_script_translations(
				$options['handle'],
				'woorewards-lite',
				LWS_WOOREWARDS_PATH . '/languages'
			);
			$this->scriptHandlers[$options['handle']] = $options['handle'];
		}
	}

	/** Returns an array of script handles to enqueue in the frontend context.
	 * @return string[] */
	public function get_script_handles() {
		return $this->scriptHandlers;
	}

	/** Returns an array of script handles to enqueue in the editor context.
	 * @return string[] */
	public function get_editor_script_handles() {
		return $this->styleHandlers;
	}

	/** Get the file modified time as a cache buster if we're in dev mode.
	 * @param string $file Local path to the file.
	 * @return string The cache buster value to use for the given file. */
	protected static function version($file) {
		if (\defined('SCRIPT_DEBUG') && SCRIPT_DEBUG && \file_exists(LWS_WOOREWARDS_PATH . $file)) {
			return \filemtime(LWS_WOOREWARDS_PATH . $file);
		} else {
			return LWS_WOOREWARDS_VERSION;
		}
	}
}