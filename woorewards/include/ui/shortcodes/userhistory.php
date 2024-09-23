<?php

namespace LWS\WOOREWARDS\Ui\Shortcodes;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Display default pool user point history */
class UserHistory
{
	private $stygen = false;

	public static function install()
	{
		$me = new self();

		/** Shortcode */
		\add_shortcode('wr_show_history', array($me, 'shortcode'));

		/** Admin */
		\add_filter('lws_woorewards_shortcodes', array($me, 'admin'));
		\add_filter('lws_woorewards_users_shortcodes', array($me, 'adminPro'));

		\add_filter('lws_adminpanel_stygen_content_get_' . 'history_template', array($me, 'template'));
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
	}

	function registerScripts()
	{
		\wp_register_style('woorewards-history', LWS_WOOREWARDS_CSS . '/templates/history.css?stygen=lws_woorewards_history_template', array(), LWS_WOOREWARDS_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_style('woorewards-history');
	}

	public function admin($fields)
	{
		$fields['history'] = array(
			'id' => 'lws_woorewards_sc_history',
			'title' => __("Points History", 'woorewards-lite'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode'   => "[wr_show_history]",
				'description' =>  __("This shortcode displays a user's points history.", 'woorewards-lite'),
				'options'     => array(
					array(
						'option' => 'count',
						'desc' => __("(Optional) The number of rows displayed. Default is 15.", 'woorewards-lite'),
						'example' => '[wr_show_history count="15"]'
					),
					array(
						'option' => 'columns',
						'desc' => array(
							array(
								'tag' => 'p', 'join' => '<br/>',
								__("(Optional) The Columns to display (comma separated). <b>The order in which you specify the columns will be the grid columns order</b>.", 'woorewards-lite'),
								__("If not specified, the history will display the points and rewards system name, date, reason and points movement columns", 'woorewards-lite'),
								__(" Here are the different options available :", 'woorewards-lite'),
							),
							array(
								'tag' => 'ul',
								array(
									"system",
									__("The points and rewards system's name.", 'woorewards-lite'),
								), array(
									"date",
									__("The date at which the points movement happened.", 'woorewards-lite'),
								), array(
									"descr",
									__("The operation's description.", 'woorewards-lite'),
								), array(
									"points",
									__("The amount of points earned or lost during the operation.", 'woorewards-lite'),
								), array(
									"total",
									__("The new total of points in the user's reserve at the end of the operation.", 'woorewards-lite'),
								)
							)
						),
					),
					array(
						'option' => 'headers',
						'desc' => __("(Optional) The column headers (comma separated). <b>Must be specified if you specified the columns option</b>. The headers must respect the same order than the ones of the previous option.", 'woorewards-lite'),
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
		$fields['history']['extra']['options'] = array_merge(
			array(
				'system' => array(
					'option' => 'system',
					'desc' => __("(Optional) The points and rewards systems you want to display (comma separated). You can find this value in <strong>MyRewards → points and rewards systems</strong>, in the <b>Shortcode Attribute</b> column.", 'woorewards-lite'),
				),
			),
			$fields['history']['extra']['options']
		);
		$fields['historystyle'] = array(
			'id' => 'lws_woorewards_history_template',
			'type' => 'stygen',
			'extra' => array(
				'purpose' => 'filter',
				'template' => 'history_template',
				'html' => false,
				'css' => LWS_WOOREWARDS_CSS . '/templates/history.css',
			)
		);
		return $fields;
	}

	/** Handle RetroCompatibility */
	protected function parseArgs($atts)
	{
		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\wp_parse_args($atts, array(
			'count' => 15,
			'offset' => 0,
			'columns' => 'system,date,descr,points',
			'headers' => ''
		)));
		if (!isset($atts['system'])) {
			if (isset($atts['pool_name']))
				$atts['system'] = $atts['pool_name'];
			else if (isset($atts['pool']))
				$atts['system'] = $atts['pool'];
			else
				$atts['showall'] = true;
		}
		return $atts;
	}

	/** Displays the user's points history in one or several loyalty systems
	 * [wr_show_history system='poolname1,poolname2' count='15']
	 * @param $system the loyalty systems for which to show the history
	 * @param $count the max number of history lines displayed
	 */
	public function shortcode($atts = array(), $content = '')
	{
		$userId = \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'wr_show_history');
		if (!$userId) return $content;

		$atts = $this->parseArgs($atts);
		$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);

		$history = array();
		$doneStacks = array();
		foreach ($pools->asArray() as $pool) {
			$stack = $pool->getStack($userId);
			if (!\in_array($stack, $doneStacks)) {
				$doneStacks[] = $stack;
				if ($hist = $stack->getHistory(false, true, 0, $atts['count'])) {
					$poolName = $pool->getOption('display_title');
					foreach ($hist as $item) {
						$sort = \date_create($item['op_date']);
						$history[] = array(
							'system' => $poolName,
							'sort'   => $sort ? $sort->getTimestamp() : 0,
							'date'   => sprintf(
								'<span title="%s">%s</span>',
								\esc_attr(\LWS\WOOREWARDS\Core\PointStack::dateTimeI18n($item['op_date'])),
								\LWS\WOOREWARDS\Core\PointStack::dateI18n($item['op_date'])
							),
							'descr'  => $item['op_reason'],
							'points' => $item['op_value'],
							'total'  => $item['op_result'],
						);
					}
				}
			}
		}
		\usort($history, function ($a, $b) {
			return $b['sort'] - $a['sort'];
		});

		$history = array_slice($history, 0, \intval($atts['count']));
		if ($history) {
			$content = $this->getContent($atts, $history);
		}
		return $content;
	}

	public function template()
	{
		$this->stygen = true;
		$history = array(
			array('system' => 'Default', 'date' => "2020-10-15", 'descr' => 'A test reason', 'points' => '50'),
			array('system' => 'Default', 'date' => "2020-09-15", 'descr' => 'Another test reason', 'points' => '-50'),
			array('system' => 'Default', 'date' => "2020-08-15", 'descr' => 'A third test reason', 'points' => '20'),
			array('system' => 'Default', 'date' => "2020-07-15", 'descr' => 'A fourth test reason', 'points' => '350'),
			array('system' => 'Default', 'date' => "2020-06-15", 'descr' => 'A fifth test reason', 'points' => '18'),
		);
		$atts = $this->parseArgs(array());
		$html = $this->getContent($atts, $history);
		$this->stygen = false;
		return $html;
	}

	/**	@param $history (array of {op_date, op_value, op_result, op_reason}) */
	protected function getContent($atts, $history)
	{
		if (!$this->stygen) {
			$this->enqueueScripts();
		}
		$atts['columns'] = array_map('strtolower', array_map('trim', explode(',', $atts['columns'])));
		$atts['headers'] = $this->getColumnHeaders($atts);

		$content = '';
		foreach ($history as $item) {
			foreach ($atts['columns'] as $i => $column) {
				$value = isset($item[$column]) ? $item[$column] : '';
				$content .= sprintf("\n\t\t<div class='lwss_selectable cell %s history-grid-%s' data-type='%s'>%s</div>", \esc_attr($column), \esc_attr($column), $atts['headers'][$i], $value);
			}
		}

		$gridTemplateColumns = implode(' ', array_fill_keys($atts['columns'], 'auto'));
		$head = '';
		for ($i = 0; $i < count($atts['columns']); ++$i)
			$head .= sprintf("\n\t\t<div class='lwss_selectable history-grid-title %s' data-type='Title'>%s</div>", \esc_attr($atts['columns'][$i]), isset($atts['headers'][$i]) ? $atts['headers'][$i] : '');

		return <<<EOT
<div class='lwss_selectable wr-history-grid' data-type='Grid' style='grid-template-columns:{$gridTemplateColumns}'>
	{$head}
	{$content}
</div>
EOT;
	}

	protected function getColumnHeaders($atts)
	{
		if (\trim($atts['headers'])) {
			$headers = array_map('trim', explode(',', $atts['headers']));
		} else {
			$headers = array();
		}
		for ($i = count($headers); $i < count($atts['columns']); ++$i) {
			switch ($atts['columns'][$i]) {
				case 'system':
					$headers[$i] = __("Loyalty System", 'woorewards-lite');
					break;
				case 'date':
					$headers[$i] = __("Date", 'woorewards-lite');
					break;
				case 'descr':
					$headers[$i] = __("Description", 'woorewards-lite');
					break;
				case 'points':
					$headers[$i] = __("Points", 'woorewards-lite');
					break;
				case 'total':
					$headers[$i] = __("Points Balance", 'woorewards-lite');
					break;
				default:
					$headers[$i] = $atts['columns'][$i];
			}
		}
		return $headers;
	}
}
