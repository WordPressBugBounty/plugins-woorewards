<?php
namespace LWS\Adminpanel\Internal\WC;

if( !defined( 'ABSPATH' ) ) exit();

/**	Since wc_add_notice() does not work anymore with new WC Blocks UI,
 *	We have to implement a complex way to feedback to user. */
class CartNotice implements \Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface
{
	const SCRIPT_HANDLER = 'lwswc-cartnotice';
	const API = 'lwswc_cartnotice';
	const SESSION_KEY = 'lwswc-cartnotice';
	private static $_instance = null;

	public static function addInfo($content)
	{
		self::get()->add($content, 'info');
	}

	public static function addWarning($content)
	{
		self::get()->add($content, 'warning');
	}

	public static function addSuccess($content)
	{
		self::get()->add($content, 'success');
	}

	public function add($notice, $level)
	{
		if (\WC()->session) {
			$session = \WC()->session->get(self::SESSION_KEY);
			if ($session && \is_array($session)) {
				$session[] = [
					'notice' => $notice,
					'level'  => $level,
				];
			} else {
				$session = [[
					'notice' => $notice,
					'level'  => $level,
				]];
			}
			\WC()->session->set(self::SESSION_KEY, $session);
		}
	}

	public function clear()
	{
		if (\WC()->session) {
			\WC()->session->set(self::SESSION_KEY, false);
		}
	}

	public function read($thenClear=true)
	{
		$notice = [];
		if (\WC()->session) {
			$session = \WC()->session->get(self::SESSION_KEY);
			if ($session && \is_array($session)) {
				foreach ($session as $note) {
					/// expect [['notice' => 'lorem ipsum', 'level' => 'success']]
					$notice[] = sprintf(
						'<div class="lwswc-cartnotice-content lwswc-%s">%s</div>',
						\esc_attr($note['level']),
						\wp_kses_post($note['notice'])
					);
				}
			}
			$this->clear();
		}
		return $notice ? \implode("\n", $notice) : false;
	}

	public static function get(): \LWS\Adminpanel\Internal\WC\CartNotice
	{
		if (null === self::$_instance) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function install()
	{
		// block wc slot
		\add_action('woocommerce_blocks_cart_block_registration', function($integration) {
			$integration->register(\LWS\Adminpanel\Internal\WC\CartNotice::get());
		}, 5);
		\add_action('woocommerce_blocks_checkout_block_registration', function($integration) {
			$integration->register(\LWS\Adminpanel\Internal\WC\CartNotice::get());
		}, 5);

		// api
		\woocommerce_store_api_register_endpoint_data([
			'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
			'namespace'       => self::API,
			'data_callback'   => [$this, 'apiData'],
			'schema_callback' => [$this, 'apiSchema'],
			'schema_type'     => ARRAY_A,
		]);
		\woocommerce_store_api_register_endpoint_data([
			'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
			'namespace'       => self::API,
			'data_callback'   => [$this, 'apiData'],
			'schema_callback' => [$this, 'apiSchema'],
			'schema_type'     => ARRAY_A,
		]);
	}

	public function get_script_data()
	{
		return []; // ['notice' => $this->read()];
	}

	public function apiData()
	{
		return ['notice' => $this->read()];
	}

	public function apiSchema()
	{
		return [
			'notice' => [
				'description' => __('Cart notice', 'lws-adminpanel'),
				'type'        => ['string', 'null'],
				'context'     => ['view', 'edit'],
				'readonly'    => false,
			],
		];
	}

	public function get_name()
	{
		return self::SCRIPT_HANDLER;
	}

	public function initialize()
	{
		$dependencies = ['react', 'wc-blocks-checkout', 'wc-blocks-components', 'wp-element', 'wp-plugins'];
		\wp_register_script(self::SCRIPT_HANDLER, LWS_ADMIN_PANEL_JS . '/wc/cartnotice.js', $dependencies, LWS_ADMIN_PANEL_VERSION, true);
	}

	public function get_script_handles()
	{
		return [self::SCRIPT_HANDLER];
	}

	public function get_editor_script_handles()
	{
		return [self::SCRIPT_HANDLER];
	}
}