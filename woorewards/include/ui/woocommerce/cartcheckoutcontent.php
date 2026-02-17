<?php

namespace LWS\WOOREWARDS\Ui\Woocommerce;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Displays specific contents on WooCommerce pages
 *  2 different locations in Cart allowed.
 *	3 different locations in Checkout allowed.
 */
class CartCheckoutContent
{
	static function install()
	{
		foreach (self::getSettings(false, true) as $hook => $settings) {
			\add_action($hook, function()use($settings){
				$content = \trim(\get_option($settings['option'], ''));
				if ($content) {
					echo \do_shortcode(\apply_filters('wpml_translate_single_string', $content, 'Widgets', $settings['wpml']));
				}
			}, isset($settings['priority']) ? $settings['priority'] : 10);
		}
	}

	/** hook => [option, wpml, page, title]
	 *	where page is 'cart' or 'checkout'
	 *	@param $withTexts (bool) if false, title is not set. */
	static function getSettings($withTexts=true, $withPriorities=false)
	{
		$settings = array(
			'woocommerce_after_cart_table' => array(
				'page'   => 'cart',
				'option' => 'lws_woorewards_cart_content_afterproducts',
				'wpml'   => 'WooRewards - Cart Page Content - Between Products And Totals',
				'title'  => '',
			),
			'woocommerce_cart_collaterals' => array(
				'page'   => 'cart',
				'option' => 'lws_woorewards_cart_content_side',
				'wpml'   => 'WooRewards - Cart Page Content - Aside From Totals',
				'title'  => '',
			),
			'woocommerce_before_checkout_form' => array(
				'page'   => 'checkout',
				'option' => 'lws_woorewards_checkout_content_top',
				'wpml'   => 'WooRewards - Checkout Page Content - Top of the Page',
				'title'  => '',
			),
			'woocommerce_before_checkout_billing_form' => array(
				'page'   => 'checkout',
				'option' => 'lws_woorewards_checkout_content_customer',
				'wpml'   => 'WooRewards - Checkout Page Content - Before Customer Details',
				'title'  => '',
			),
			'woocommerce_checkout_before_order_review' => array(
				'page'   => 'checkout',
				'option' => 'lws_woorewards_checkout_content_review',
				'wpml'   => 'WooRewards - Checkout Page Content - Before Order Review',
				'title'  => '',
			),
			'woocommerce_checkout_order_review' => array(
				'page'   => 'checkout',
				'option' => 'lws_woorewards_checkout_content_between_reviews',
				'wpml'   => 'WooRewards - Checkout Page Content - Before Order Review',
				'title'  => '',
			),
		);
		if ($withTexts) {
			$settings['woocommerce_after_cart_table']['title'] = __("Between Products and Totals", 'woorewards-lite');
			$settings['woocommerce_cart_collaterals']['title'] = __("Aside From Totals", 'woorewards-lite');
			$settings['woocommerce_before_checkout_form']['title'] = __("Top of the page", 'woorewards-lite');
			$settings['woocommerce_before_checkout_billing_form']['title'] = __("Before Customer Details", 'woorewards-lite');
			$settings['woocommerce_checkout_before_order_review']['title'] = __("Before Order Review", 'woorewards-lite');
			$settings['woocommerce_checkout_order_review']['title'] = __("Inside Order Review", 'woorewards-lite');
		}
		if ($withPriorities) {
			$settings['woocommerce_checkout_order_review']['priority'] = 15;
			$settings = \apply_filters('lws_woorewards_woocommerce_pages_inclusions', $settings);
		}
		return $settings;
	}
}
