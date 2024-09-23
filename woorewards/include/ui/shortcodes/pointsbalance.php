<?php

namespace LWS\WOOREWARDS\Ui\Shortcodes;

// don't call the file directly
if (!defined('ABSPATH')) exit();

class PointsBalance
{
	public static function install()
	{
		$me = new self();

		/** Shortcode */
		\add_shortcode('wr_points_balance', array($me, 'shortcode'));

		/** Admin */
		\add_filter('lws_woorewards_shortcodes', array($me, 'admin'));
		\add_filter('lws_woorewards_points_shortcodes', array($me, 'adminPro'), 4);

		/** Scripts */
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));

	}

	function registerScripts()
	{
		\wp_register_style('wr-points-balance', LWS_WOOREWARDS_CSS . '/shortcodes/points-balance.min.css', array(), LWS_WOOREWARDS_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_style('wr-points-balance');
	}

	/** Get the shortcode admin */
	public function admin($fields)
	{
		$fields['pointsbalance'] = array(
			'id' => 'lws_woorewards_points_balance',
			'title' => __("Points Balance", 'woorewards-lite'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_points_balance]',
				'description' =>  __("Use this shortcode to display their points balance to your customers.", 'woorewards-lite') . "<br/>" .
				__("Use the following options to change how the balance is displayed.", 'woorewards-lite'),
				'options' => array(
					'element' => array(
						'option' => 'element',
						'desc' => __("(Optional) Select how the points balance element is displayed. 3 possible values :", 'woorewards-lite'),
						'options' => array(
							array(
								'option' => 'none',
								'desc'   => __("Default value. Simple text without stylable elements", 'woorewards-lite'),
							),
							array(
								'option' => 'tile',
								'desc'   => __("Stylable tile with a background color", 'woorewards-lite'),
							),
							array(
								'option' => 'line',
								'desc'   => __("Horizontal display in stylable elements", 'woorewards-lite'),
							),
						),
						'example' => '[wr_points_balance element="tile"]'
					),
					'display' => array(
						'option' => 'display',
						'desc' => __("(Optional) Select how the points are displayed. 2 possible values :", 'woorewards-lite'),
						'options' => array(
							array(
								'option' => 'formatted',
								'desc'   => __("Default. Points are formatted with the points currency/name", 'woorewards-lite'),
							),
							array(
								'option' => 'simple',
								'desc'   => __("Only the points balance numeric value is displayed", 'woorewards-lite'),
							),
						),
						'example' => '[wr_points_balance display="simple"]'
					),
					'showname' => array(
						'option' => 'showname',
						'desc' => __("(Optional) If set, will force the display of the points and rewards system name", 'woorewards-lite'),
						'example' => '[wr_points_balance showname="yes"]',
					),
					'showcurrency' => array(
						'option' => 'showcurrency',
						'desc' => __("(Optional) Set to 'off' to do not append points currency but show only the number", 'woorewards-lite'),
						'example' => '[wr_points_balance showcurrency="yes"]',
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
		$fields['pointsbalance']['extra']['options'] = array_merge(
			array(
				'system' => array(
					'option' => 'system',
					'desc'   => __("(Optional, comma separated) Select the points and rewards systems you want to show. If left empty, all active systems are displayed", 'woorewards-lite') .
						"<br/>" . __("You can find the points and rewards systems names in WooRewards → Points and Rewards", 'woorewards-lite'),
					'example' => '[wr_points_balance system="name_of_your_system"]'
				),
				'layout' => array(
					'option' => 'layout',
					'desc' => __("(Optional) Select how the points balance elements are organized . 4 possible values :", 'woorewards-lite'),
					'options' => array(
						array(
							'option' => 'none',
							'desc'   => __("Default value. Simple text without stylable elements", 'woorewards-lite'),
						),
						array(
							'option' => 'grid',
							'desc'   => __("Elements are displayed in a responsive grid", 'woorewards-lite'),
						),
						array(
							'option' => 'horizontal',
							'desc'   => __("Elements are displayed in row", 'woorewards-lite'),
						),
						array(
							'option' => 'vertical',
							'desc'   => __("Elements are displayed on top of another", 'woorewards-lite'),
						),
					),
					'example' => '[wr_points_balance layout="grid"]'
				),
			),
			$fields['pointsbalance']['extra']['options']
		);
		return $fields;
	}

	/** Shows one or several points balances
	 * [wr_points_balance systems='poolname1, poolname2']
	 * * system 	→ Default: ''
	 * 					  The points and rewards systems for which the balance is displayed. If empty, show all active systems
	 * 					  One value or several ones, comma separated
	 * * layout 	→ Default: 'none'
	 * 					  Defines the presentation of the wrapper.
	 * 					  4 possible values : grid, vertical, horizontal, none.
	 * * element 	→ Default: 'none'
	 * 					  Defines the presentation of the elements.
	 * 					  3 possible values : tile, line, none.
	 * * display	→ Default: 'formatted'
	 * 					  'simple'    → only the points balance numeric value is displayed.
	 * 					  'formatted' → points are formatted with the points currency/name.
	 * * showname	→ Default: false
	 * 					  Force the display of the system name even if there's only one system
	 */
	public function shortcode($atts = array(), $content = '')
	{
		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\wp_parse_args($atts, array(
			'system'       => '',
			'layout'       => 'none',
			'element'      => 'none',
			'display'      => 'formatted',
			'showname'     => '',
			'showcurrency' => 'true',
		)));
		$userId = \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'wr_points_balance');

		/** Basic verifications */
		if (!$atts['system']) {
			$atts['showall'] = true;
		}
		if (!$userId) {
			return \do_shortcode((string)$content);
		}
		/** Get the data */
		$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);
		if (!($pools && $pools->count())) {
			return \do_shortcode((string)$content);
		}
		// check optional arg
		if (!\strlen("{$atts['showname']}"))
			$atts['showname'] = ($pools->count() != 1);
		else
			$atts['showname'] = \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['showname']);

		$content = '';
		$this->enqueueScripts();

		foreach ($pools->asArray() as $pool) {
			// info
			$points = $pool->getPoints($userId);
			if ($atts['display'] == 'formatted') {
				$points = $pool->formatPoints($points, \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['showcurrency']));
			}
			$name = \esc_attr($pool->getName());

			if ($atts['element'] == 'tile') {
				$title = $atts['showname'] ? sprintf("<div class='system-name'>%s</div>", $pool->getOption('display_title')) : '';
				$content .= "<div class='item tile {$name}'>{$title}<div class='points-balance'>{$points}</div></div>";
			} else if ($atts['element'] == 'line') {
				$title = $atts['showname'] ? sprintf("<span class='system-name'>%s</span>", $pool->getOption('display_title')) : '';
				$content .= "<span class='item line {$name}'>{$title}<span class='points-balance'>{$points}</span></span>";
			} else {
				if ($atts['showname'])
					$content .= sprintf(_x("%s : %s", 'wr_points_balance element="none"', 'woorewards-lite'), $pool->getOption('display_title'), $points);
				else
					$content .= $points;
			}
		}

		// define a container
		switch (\strtolower(\substr($atts['layout'], 0, 3))) {
			case 'gri':
				return "<div class='wr-points-balance wr-shortcode-grid'>{$content}</div>";
			case 'hor':
				return "<div class='wr-points-balance wr-shortcode-hflex'>{$content}</div>";
			case 'ver':
				return "<div class='wr-points-balance wr-shortcode-vflex'>{$content}</div>";
			default:
				return $content;
		}
	}
}
