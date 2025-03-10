<?php

namespace LWS\WOOREWARDS\Ui\Shortcodes;

// don't call the file directly
if (!defined('ABSPATH')) exit();

class AvailableCoupons
{
	CONST SLUG = 'wr_available_coupons';

	static function install()
	{
		$me = new self();
		/** Shortcode */
		\add_shortcode(self::SLUG, array($me, 'shortcode'));
		/** Admin */
		\add_filter('lws_woorewards_shortcodes', array($me, 'admin'));
		\add_filter('lws_woorewards_rewards_shortcodes', array($me, 'admin'));
		/** Scripts */
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
	}

	function registerScripts()
	{
		\wp_register_script('wr-available-coupons', LWS_WOOREWARDS_JS . '/shortcodes/available-coupons.js', array('jquery'), LWS_WOOREWARDS_VERSION, true);
		\wp_register_style('wr-available-coupons', LWS_WOOREWARDS_CSS . '/shortcodes/available-coupons.min.css', array(), LWS_WOOREWARDS_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_script('wr-available-coupons');
		\wp_enqueue_style('wr-available-coupons');
	}

	public function admin($fields)
	{
		$fields['availablecoupons'] = array(
			'id' => 'lws_woorewards_available_coupons',
			'title' => __("Available Coupons", 'woorewards-lite'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_available_coupons]',
				'description' =>  __("Use this shortcode to display a list of their available coupons to your customers.", 'woorewards-lite') . "<br/>" .
				__("This shortcode is better used on the cart or checkout page.", 'woorewards-lite'),
				'options' => array(
					array(
						'option' => 'layout',
						'desc' => __("(Optional) Select how the coupons list is displayed. 4 possible values :", 'woorewards-lite'),
						'options' => array(
							array(
								'option' => 'vertical',
								'desc'   => __("Default value. Elements are displayed on top of each other", 'woorewards-lite'),
							),
							array(
								'option' => 'horizontal',
								'desc'   => __("Elements are displayed in row", 'woorewards-lite'),
							),
							array(
								'option' => 'grid',
								'desc'   => __("Elements are displayed in a responsive grid", 'woorewards-lite'),
							),
							array(
								'option' => 'none',
								'desc'   => __("Elements are not wrapped in a container", 'woorewards-lite'),
							),
						),
						'example' => '[wr_available_coupons layout="vertical"]'
					),
					array(
						'option' => 'element',
						'desc' => __("(Optional) Select how a coupon element is displayed. 3 possible values :", 'woorewards-lite'),
						'options' => array(
							array(
								'option' => 'line',
								'desc'   => __("Default Value. Horizontal display in stylable elements", 'woorewards-lite'),
							),
							array(
								'option' => 'tile',
								'desc'   => __("Stylable tile with a background color", 'woorewards-lite'),
							),
							array(
								'option' => 'none',
								'desc'   => __("Simple text without stylable elements", 'woorewards-lite'),
							),
						),
						'example' => '[wr_available_coupons element="tile"]'
					),
					array(
						'option' => 'buttons',
						'desc' => __("(Optional) If set to true, apply buttons are added on each element to apply the coupon on the cart. Default is false", 'woorewards-lite'),
						'example' => '[wr_available_coupons buttons="true"]'
					),
					array(
						'option' => 'expire-html',
						'desc' => __("(Optional) Override the expiration label if any. Use the <b>%s</b> placeholder for the date. You can set an empty string to display nothing.", 'woorewards-lite'),
						'example' => '[wr_available_coupons expire-html=" (Expires on %s)"]'
					),
					array(
						'option' => 'in-the-last',
						'desc' => [
							__("(Optional) Show only coupons created in the last given period.", 'woorewards-lite'),
							__("A period is defined by a number and a duration unit.", 'woorewards-lite'),
							__("Accepted units are:", 'woorewards-lite'), ['tag' => 'ul',
								['D', __("for Days", 'woorewards-lite')],
								['W', __("for Weeks", 'woorewards-lite')],
								['M', __("for Months", 'woorewards-lite')],
								['Y', __("for Years", 'woorewards-lite')],
							],
						],
						'example' => '[wr_available_coupons in-the-last="1M"]'
					),
					array(
						'option' => 'reset-day',
						'desc' => [
							sprintf(__("(Optional) Works with %s attribute to build an incremental period instead the default shift date.", 'woorewards-lite'), '`<i>in-the-last</i>`'),
							__("Expect the day of the month the period should reset within the original rolling period.", 'woorewards-lite'),
							__("The value is automatically clamped to the last day of the month if necessary.", 'woorewards-lite'),
						],
						'example' => '[wr_available_coupons in-the-last="1Y" reset-day="1"]'
					),
				),
				'flags' => array('current_user_id'),
			)
		);
		if (defined('LWS_WOOREWARDS_ACTIVATED') && LWS_WOOREWARDS_ACTIVATED) {
			$fields['availablecoupons']['extra']['options'][] = array(
				'option' => 'reload',
				'desc' => __("(Optional) Only applies if buttons is set to true. If set to true, clicking an apply button will refresh the page.", 'woorewards-lite'),
				'example' => '[wr_available_coupons buttons="true" reload="true"]'
			);
		}
		return $fields;
	}

	/** Shows available coupons
	 * [wr_available_coupons]
	 * @param $layout 	→ Default: 'vertical'
	 * 					  Defines the presentation of the wrapper.
	 * 					  4 possible values : grid, vertical, horizontal, none.
	 * @param $element 	→ Default: 'line'
	 * 					  Defines the presentation of the elements.
	 * 					  3 possible values : tile, line, none.
	 * @param $buttons 	→ Default: false
	 * 					  Defines if the tool displays an "Apply" button or not.
	 * @param $reload    → Default: false
	 * 					  Only applies if buttons is set to true
	 * 					  false leads to an ajax action, true leads to a page reload
	 */
	function shortcode($atts = array(), $content = '')
	{
		if (!(\LWS\Adminpanel\Tools\Conveniences::isWC() && \wc_coupons_enabled())) {
			return '';
		}
		$userId = \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, self::SLUG);
		if (!$userId) {
			return \do_shortcode((string)$content);
		}

		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\wp_parse_args($atts, array(
			'buttons'	=> false,
			'reload'	=> false,
			'layout'	=> 'vertical',
			'element'	=> 'line',
			'expire-html' => false,
			'in-the-last' => false,
			'min-date'    => false,
			'reset-day'   => false,
		)));
		$where = $this->checkDateFilters($atts);

		$data = \LWS\WOOREWARDS\Conveniences::instance()->getCoupons($userId, $where);
		if (!$data) {
			return \do_shortcode((string)$content);
		}

		$this->enqueueScripts();
		return $this->getContent($atts, $data);
	}

	/** merge $atts to set a 'reset-date' entry to filter coupon by min creation date.
	 * @param $atts array inout
	 * @return array sql where filter @see \LWS\WOOREWARDS\Conveniences\getCoupons */
	private function checkDateFilters(array &$atts): array
	{
		if ($atts['min-date']) {
			$atts['min-date'] = \date_create_immutable($atts['min-date'], \wp_timezone());
		}

		if ($atts['in-the-last']) {
			$atts['reset-day'] = (int)$atts['reset-day'];

			// test a valid format
			$pattern = '/(\d+)\s*([ymdw])/i';
			if (\preg_match_all($pattern, $atts['in-the-last'], $matches, PREG_SET_ORDER)) {
				$period = ['_' =>'P'];
				foreach ($matches as $match) {
					$u = \strtoupper($match[2]);
					$period[$u] = $match[1] . $u;
				}

				$interval = new \DateInterval(\implode('', $period));
				$date = \date_create_immutable('now', \wp_timezone())->sub($interval);

				if ($atts['reset-day']) {
					$coming = \LWS\Adminpanel\Tools\Dates::replace($date, ['day' => $atts['reset-day']]);
					if ($coming->setTime(0, 0) < $date->setTime(0, 0)) {
						$coming = \LWS\Adminpanel\Tools\Dates::addMonths($coming, 1, $atts['reset-day']);
					}
					$atts['min-date'] = $coming;
				} else {
					$atts['min-date'] = $date;
				}
			} else {
				$atts['in-the-last'] = false;
			}
		}

		if ($atts['min-date']) {
			return [
				sprintf(
					"p.post_date_gmt >= '%s'",
					$atts['min-date']->setTime(0, 0)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')
				),
			];
		} else {
			return [];
		}
	}

	protected function getContent($atts, $data)
	{
		// to hide already applied
		$done = (\LWS\Adminpanel\Tools\Conveniences::isWC() && \WC()->cart) ? array_map('strtolower', \WC()->cart->get_applied_coupons()) : array();
		$btemplate = '';
		$reloadNonce = false;
		$addButtons = \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['buttons']);
		if ($addButtons && !(\is_cart() || \is_checkout())) {
			if (defined('LWS_WOOREWARDS_ACTIVATED') && LWS_WOOREWARDS_ACTIVATED) {
				$atts['reload'] = true;
			} else {
				$addButtons = false;
			}
		}
		if ($addButtons) {
			// reloading behavior is coded in pro
			if (defined('LWS_WOOREWARDS_ACTIVATED') && LWS_WOOREWARDS_ACTIVATED
			&& \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['reload'])) {
				$nonce = \esc_attr(urlencode(\wp_create_nonce('wr_apply_coupon')));
				$reloadNonce = " data-reload='wrac_n={$nonce}&wrac_c=%s'";
			}
			// button template
			$text = \apply_filters('wpml_translate_single_string', __("Apply", 'woorewards-lite'), 'Shortcode', "MyRewards - Available Coupons - Button");
			$btemplate = "<div class='button coupon-button lws_woorewards_add_coupon' data-id='lws_woorewards_cart_coupons_button' data-coupon='%s'%s>{$text}</div>";
		}

		$elements = '';
		foreach ($data as $coupon) {
			// prepare
			$code = \esc_attr($coupon->post_title);
			$descr = \apply_filters('lws_woorewards_coupon_content', $coupon->post_excerpt, $coupon);
			if ($coupon->expiry_date) {
				if ($atts['expire-html'] === false) {
					$date = \wp_date(\get_option('date_format'), $coupon->expiry_date, \wp_timezone());
					$descr .= sprintf(__(' (Expires on %s)', 'woorewards-lite'), $date);
				} else if ($atts['expire-html']) {
					$date = \wp_date(\get_option('date_format'), $coupon->expiry_date, \wp_timezone());
					$descr .= \str_replace('%s', $date, $atts['expire-html']);
				}
			}
			$button = '';
			if ($btemplate) {
				if ($reloadNonce)
					$button = sprintf($btemplate, $code, sprintf($reloadNonce, \esc_attr(urlencode($coupon->post_title))));
				else
					$button = sprintf($btemplate, $code, '');
			}
			// item
			if ($atts['element'] == 'tile' || $atts['element'] == 'line') {
				$hidden = in_array(strtolower($coupon->post_title), $done) ? " style='display:none;'" : '';
				$elements .= <<<EOT
				<div class='item {$atts['element']} coupon-{$code}'{$hidden}>
					<div class='coupon-code'>{$coupon->post_title}</div>
					<div class='coupon-desc'>{$descr}</div>
					$button
				</div>
EOT;
			} else {
				$elements .= ($coupon->post_title . " " . $descr);
				if ($button)
					$elements .= (" " . $button);
			}
		}
		// container
		switch (\strtolower(\substr($atts['layout'], 0, 3))) {
			case 'gri':
				return "<div class='wr-available-coupons wr-shortcode-grid'>{$elements}</div>";
			case 'hor':
				return "<div class='wr-available-coupons wr-shortcode-hflex'>{$elements}</div>";
			case 'ver':
				return "<div class='wr-available-coupons wr-shortcode-vflex'>{$elements}</div>";
			default:
				return $elements;
		}
	}
}
