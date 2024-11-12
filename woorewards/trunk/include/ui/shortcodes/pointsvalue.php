<?php

namespace LWS\WOOREWARDS\Ui\Shortcodes;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Displays the value of points in a points to cart system */
class PointsValue
{
	public static function install()
	{
		$me = new self();

		/** Shortcode */
		\add_shortcode('wr_points_value', array($me, 'shortcode'));

		/** Admin */
		\add_filter('lws_woorewards_shortcodes', array($me, 'admin'));
		\add_filter('lws_woorewards_points_shortcodes', array($me, 'adminPro'));
	}

	public function admin($fields)
	{
		$fields['pointsvalue'] = array(
			'id' => 'lws_woorewards_sc_points_value',
			'title' => __("Points Value", 'woorewards-lite'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_points_value]',
				'description' =>  __("This simple shortcode is used to display how much his points are worth.", 'woorewards-lite') . "<br/>" .
				__("This only works if your points and rewards system is set to points on cart.", 'woorewards-lite'),
				'options' => array(
					array(
						'option' => 'text',
						'desc' => __("The text displayed before the points value.", 'woorewards-lite'),
					),
					array(
						'option' => 'raw',
						'desc' => __("(Optional) If set to true, the result will be displayed as a simple text. Otherwise, it will be wrapped in stylable elements", 'woorewards-lite'),
					),
				),
				'flags' => array('current_user_id'),
			)
		);
		return $fields;
	}

	public function adminPro($fields)
	{
		$fields = $this->admin($fields);
		$fields['pointsvalue']['extra']['options'] = array_merge(
			array(
				'system' => array(
					'option' => 'system',
					'desc'   => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column. If you don’t set this value, nothing will be displayed.", 'woorewards-lite'),
				),
			),
			$fields['pointsvalue']['extra']['options']
		);
		return $fields;
	}

	/** Handle RetroCompatibility */
	protected function parseArgs($atts)
	{
		$atts = \wp_parse_args($atts, array(
			'text'      => '',
			'raw'       => true,
			'floating'  => false,
			'decorated' => true,
		));
		return $atts;
	}

	/** Displays the user's points value in currency for a specific pool
	 * [wr_points_value system='poolname1' text='Your points are worth' raw='true']
	 * @param $system the loyalty system for which to show the value
	 * @param $text text displayed before the points value
	 * @param $raw if true, the output is a simple text, otherwise, it's wrapped into dom elements
	 */
	public function shortcode($atts = array(), $content = '')
	{
		$userId = \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'wr_points_value');
		if (!$userId) return $content;

		$atts = $this->parseArgs($atts);
		$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);
		// we only display the value of a simple pool
		$pool = $pools->first();
		if ($pool && $pool->getOption('direct_reward_mode')) {
			// It's a points on cart pool
			$points = $pool->getPoints($userId);
			if ($points < 0) return '';
			$rate = $pool->getOption('direct_reward_point_rate');
			$value = $points * $rate;
			$formatted_value = \LWS\Adminpanel\Tools\Conveniences::getCurrencyPrice(
				$value,
				\LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['floating']),
				\LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['decorated']),
			);
			if ($atts['raw']) {
				if ($atts['text'] != '') {
					$content .= $atts['text'] . ' ';
				}
				$content .= $formatted_value;
			} else {
				$content .= "<span class='wr-points-value-wrapper'>";
				if ($atts['text'] != '') {
					$content .= "<span class='wr-points-value-text'>" . $atts['text'] . " </span>";
				}
				$content .= "<span class='wr-points-value-value'>" . $formatted_value . " </span>";
				$content .= "</span>";
			}
		}
		return $content;
	}
}
