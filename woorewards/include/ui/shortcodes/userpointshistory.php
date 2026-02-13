<?php

namespace LWS\WOOREWARDS\Ui\Shortcodes;

if (!defined('ABSPATH')) exit();

class UserPointsHistory
{
	private $stygen = false;
	const AJAX_ACTION = 'lws_woorewards_load_page_history';

	public static function install()
	{
		$me = new self();

		\add_shortcode('wr_show_history', array($me, 'shortcode'));

		\add_action('wp_ajax_' . self::AJAX_ACTION, array($me, 'ajaxLoadPage'));

		\add_filter('lws_woorewards_shortcodes', array($me, 'admin'));
		\add_filter('lws_woorewards_users_shortcodes', array($me, 'adminPro'));

		\add_filter('lws_adminpanel_stygen_content_get_' . 'history_template', array($me, 'template'));
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
	}

	function registerScripts()
	{
		\wp_register_style('woorewards-history', LWS_WOOREWARDS_CSS . '/templates/userpointshistory.css?stygen=lws_woorewards_history_template', array(), LWS_WOOREWARDS_VERSION);
		\wp_register_script('woorewards-history', LWS_WOOREWARDS_JS . '/shortcodes/userpointshistory.js', array('jquery'), LWS_WOOREWARDS_VERSION, true);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_style('woorewards-history');
		\wp_enqueue_script('woorewards-history');

		static $localized = false;
		if (!$localized) {
			\wp_localize_script('woorewards-history', 'lwsWooRewardsHistory', array(
				'ajaxUrl' => \admin_url('admin-ajax.php'),
				'action' => self::AJAX_ACTION,
				'loadingText' => __('Loading...', 'woorewards'),
				'errorText' => __('Error loading history. Please try again.', 'woorewards'),
			));
			$localized = true;
		}
	}

	public function admin($fields)
	{
		$fields['history'] = array(
			'id' => 'lws_woorewards_sc_history',
			'title' => __("Points History", 'woorewards'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode'   => "[wr_show_history]",
				'description' =>  __("This shortcode displays a user's points history with pagination.", 'woorewards'),
				'options'     => array(
					array(
						'option' => 'per_page',
						'desc' => __("(Optional) The number of rows displayed per page. Default is 15.", 'woorewards'),
						'example' => '[wr_show_history per_page="15"]'
					),
					array(
						'option' => 'columns',
						'desc' => array(
							array(
								'tag' => 'p', 'join' => '<br/>',
								__("(Optional) The Columns to display (comma separated). <b>The order in which you specify the columns will be the grid columns order</b>.", 'woorewards'),
								__("If not specified, the history will display the points and rewards system name, date, reason and points movement columns", 'woorewards'),
								__(" Here are the different options available :", 'woorewards'),
							),
							array(
								'tag' => 'ul',
								array(
									"system",
									__("The points and rewards system's name.", 'woorewards'),
								), array(
								"date",
								__("The date at which the points movement happened.", 'woorewards'),
							), array(
								"descr",
								__("The operation's description.", 'woorewards'),
							), array(
								"points",
								__("The amount of points earned or lost during the operation.", 'woorewards'),
							), array(
								"total",
								__("The new total of points in the user's reserve at the end of the operation.", 'woorewards'),
							)
							)
						),
					),
					array(
						'option' => 'headers',
						'desc' => __("(Optional) The column headers (comma separated). <b>Must be specified if you specified the columns option</b>. The headers must respect the same order than the ones of the previous option.", 'woorewards'),
					),
				),
				'flags' => array('current_user_id'),
			)
		);
		return $fields;
	}

	public function adminPro($fields): array {
		$fields = $this->admin($fields);
		$fields['history']['extra']['options'] = array_merge(
			array(
				'system' => array(
					'option' => 'system',
					'desc' => __("(Optional) The points and rewards systems you want to display (comma separated). You can find this value in <strong>MyRewards → points and rewards systems</strong>, in the <b>Shortcode Attribute</b> column.", 'woorewards'),
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
				'css' => LWS_WOOREWARDS_CSS . '/templates/userpointshistory.css',
			)
		);
		return $fields;
	}

	public function ajaxLoadPage()
	{
		try {
			if (!isset($_POST['nonce']) || !\wp_verify_nonce(\sanitize_text_field(\wp_unslash($_POST['nonce'])), self::AJAX_ACTION)) {
				\wp_send_json_error(array('message' => __('Security check failed.', 'woorewards')));
				return;
			}

			$page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
			$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
			$atts = (isset($_POST['atts']) && is_array($_POST['atts'])) ? \array_map('\sanitize_text_field', \wp_unslash($_POST['atts'])) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			if (!$userId) {
				\wp_send_json_error(array('message' => __('Invalid user.', 'woorewards')));
				return;
			}

			$currentUserId = \get_current_user_id();
			if ($userId != $currentUserId) {
				\wp_send_json_error(array('message' => __('Invalid user.', 'woorewards')));
				return;
			}

			$atts = $this->parseArgs($atts);
			$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);
			$offset = ($page - 1) * $atts['per_page'];
			$result = $this->fetchHistory($userId, $pools, $offset, $atts['per_page']);

			if (empty($result['items'])) {
				\wp_send_json_success(array(
					'html' => '<div class="wr-history-no-results">' . __('No history found.', 'woorewards') . '</div>',
					'pagination' => '',
				));
				return;
			}

			$html = '';
			foreach ($result['items'] as $item) {
				$html .= $this->renderHistoryRow($item, $atts['columns'], $atts['headers']);
			}

			$totalPages = ceil($result['total'] / $atts['per_page']);
			$pagination = $this->renderPagination($page, $totalPages);

			\wp_send_json_success(array(
				'html' => $html,
				'pagination' => $pagination,
			));
		} catch (\Exception $e) {
			\wp_send_json_error(array(
				'message' => __('An error occurred while loading history.', 'woorewards'),
				'debug' => WP_DEBUG ? $e->getMessage() : null
			));
		}
	}

	protected function parseArgs($atts)
	{
		if (!is_array($atts)) {
			$atts = array();
		}

		$defaults = array(
			'per_page' => 15,
			'columns' => 'system,date,descr,points',
			'headers' => '',
			'system' => '',
			'pool_name' => '',
			'pool' => '',
			'count' => '',
		);
		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\shortcode_atts($defaults, $atts, 'wr_show_history'));

		foreach (['system', 'pool_name', 'pool'] as $key) {
			if (isset($atts[$key]) && $atts[$key] === '') {
				unset($atts[$key]);
			}
		}

		//Handle RetroCompatibility
		if (isset($atts['count']) && !isset($atts['per_page'])) {
			$atts['per_page'] = $atts['count'];
		}

		$atts['per_page'] = max(1, (int) $atts['per_page'] );

		if (!isset($atts['system'])) {
			if (isset($atts['pool_name']))
				$atts['system'] = $atts['pool_name'];
			else if (isset($atts['pool']))
				$atts['system'] = $atts['pool'];
			else
				$atts['showall'] = true;
		}

		$atts['columns'] = array_map('strtolower', array_map('trim', explode(',', $atts['columns'])));
		$atts['headers'] = $this->getColumnHeaders($atts);

		return $atts;
	}

	protected function getTotalHistoryCount($userId, $stackNames)
	{
		global $wpdb;

		if (empty($stackNames)) {
			return 0;
		}

		try {
			$placeholders = implode(',', array_fill(0, count($stackNames), '%s'));
			$sql = "
				SELECT COUNT(*)
				FROM {$wpdb->lwsWooRewardsHistoric}
				WHERE user_id = %d AND stack IN ($placeholders)
			";

			$args = array_merge(array($userId), $stackNames);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$count = $wpdb->get_var($wpdb->prepare($sql, $args));

			return (int) $count;
		} catch (\Exception $e) {
			return 0;
		}
	}

	protected function fetchHistory($userId, $pools, $offset = 0, $limit = 15)
	{
		try {
			$stackNames = array();
			$stackPools = array();

			if (!$pools) {
				return array('items' => array(), 'total' => 0);
			}

			$poolsArray = $pools->asArray();
			if (!is_array($poolsArray) || empty($poolsArray)) {
				return array('items' => array(), 'total' => 0);
			}

			foreach ($poolsArray as $pool) {
				if (!$pool || !is_object($pool)) {
					continue;
				}

				$stackName = $pool->getStackId();

				if (!isset($stackPools[$stackName])) {
					$stackNames[] = $stackName;
					$stackPools[$stackName] = $pool;
				}
			}

			if (empty($stackNames)) {
				return array('items' => array(), 'total' => 0);
			}

			$total = $this->getTotalHistoryCount($userId, $stackNames);

			$history = \LWS\WOOREWARDS\Core\PointStack::getHistoryBulk(
				$userId,
				$stackNames,
				$offset,
				$limit,
				true
			);

			if (!is_array($history)) {
				return array('items' => array(), 'total' => 0);
			}

			$allHistory = array();
			foreach ($history as $item) {
				if (!is_array($item) || !isset($item['stack'])) {
					continue;
				}

				$pool = $stackPools[$item['stack']] ?? null;
				if (!$pool) {
					continue;
				}

				$allHistory[] = array(
					'system' => $pool->getOption('display_title'),
					'date'   => sprintf(
						'<span class="wr-date-display" data-date="%s" title="%s">%s</span>',
						\esc_attr($item['op_date']),
						\esc_attr(\LWS\WOOREWARDS\Core\PointStack::dateTimeI18n($item['op_date'])),
						\LWS\WOOREWARDS\Core\PointStack::dateI18n($item['op_date'])
					),
					'descr'  => \wp_kses_post($item['op_reason']),
					'points' => $item['op_value'],
					'total'  => $item['op_result'],
				);
			}

			return array(
				'items' => $allHistory,
				'total' => $total
			);
		} catch (\Exception $e) {
			return array('items' => array(), 'total' => 0);
		}
	}

	protected function renderHistoryRow($item, $columns, $headers = array())
	{
		$html = '';
		foreach ($columns as $i => $column) {
			$value = isset($item[$column]) ? $item[$column] : '';
			$dataType = isset($headers[$i]) ? $headers[$i] : $column;
			$html .= sprintf(
				"\n\t\t<div class='lwss_selectable cell %s history-grid-%s' data-type='%s'>%s</div>",
				\esc_attr($column),
				\esc_attr($column),
				\esc_attr($dataType),
				$value
			);
		}
		return $html;
	}

	protected function renderPagination($currentPage, $totalPages)
	{
		if ($totalPages <= 1) {
			return '';
		}

		$html = '<div class="lwss_selectable wr-history-pagination" data-type="Pagination">';

		if ($currentPage > 1) {
			$html .= sprintf(
				'<button class="lwss_selectable wr-history-page-btn wr-history-prev" data-type="PrevButton" data-page="%d">%s</button>',
				$currentPage - 1,
				'&laquo; ' . __('Previous', 'woorewards')
			);
		}

		$html .= '<div class="lwss_selectable wr-history-page-numbers" data-type="PageNumbers">';

		$html .= $this->renderPageButton(1, $currentPage);

		$range = 2;
		$start = max(2, $currentPage - $range);
		$end = min($totalPages - 1, $currentPage + $range);

		if ($start > 2) {
			$html .= '<span class="lwss_selectable wr-history-ellipsis" data-type="Ellipsis">...</span>';
		}

		for ($i = $start; $i <= $end; $i++) {
			$html .= $this->renderPageButton($i, $currentPage);
		}

		if ($end < $totalPages - 1) {
			$html .= '<span class="lwss_selectable wr-history-ellipsis" data-type="Ellipsis">...</span>';
		}

		if ($totalPages > 1) {
			$html .= $this->renderPageButton($totalPages, $currentPage);
		}

		$html .= '</div>';

		if ($currentPage < $totalPages) {
			$html .= sprintf(
				'<button class="lwss_selectable wr-history-page-btn wr-history-next" data-type="NextButton" data-page="%d">%s</button>',
				$currentPage + 1,
				__('Next', 'woorewards') . ' &raquo;'
			);
		}

		$html .= '</div>';

		return $html;
	}

	protected function renderPageButton($page, $currentPage)
	{
		$isActive = ($page == $currentPage);

		return sprintf(
			'<button class="lwss_selectable wr-history-page-btn%s" data-type="PageButton" data-page="%d" %s>%d</button>',
			$isActive ? ' active' : '',
			$page,
			$isActive ? 'disabled' : '',
			$page
		);
	}

	public function shortcode($atts = array(), $content = '')
	{
		$userId = \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'wr_show_history');
		if (!$userId) return $content;

		$atts = $this->parseArgs($atts);
		$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);

		$result = $this->fetchHistory($userId, $pools, 0, $atts['per_page']);

		if (empty($result['items'])) {
			$totalPages = $result['total'] > 0 ? ceil($result['total'] / $atts['per_page']) : 1;
			return $this->renderGrid($atts, array(), 1, $totalPages);
		}

		$totalPages = ceil($result['total'] / $atts['per_page']);
		$content = $this->renderGrid($atts, $result['items'], 1, $totalPages);

		return $content;
	}

	protected function renderGrid($atts, $history, $currentPage = 1, $totalPages = 1)
	{
		if (!$this->stygen) {
			$this->enqueueScripts();
		}

		$gridTemplateColumns = implode(' ', array_fill_keys($atts['columns'], 'auto'));
		$head = '';
		$columnsCount = count($atts['columns']);
		for ($i = 0; $i < $columnsCount; ++$i) {
			$head .= sprintf(
				"\n\t\t<div class='lwss_selectable history-grid-title %s' data-type='Title'>%s</div>",
				\esc_attr($atts['columns'][$i]),
				$atts['headers'][$i] ?? ''
			);
		}

		$rows = '';
		foreach ($history as $item) {
			$rows .= $this->renderHistoryRow($item, $atts['columns'], $atts['headers']);
		}

		$pagination = $this->renderPagination($currentPage, $totalPages);

		$dataUserId = \esc_attr(\get_current_user_id());
		$dataNonce = \esc_attr(\wp_create_nonce(self::AJAX_ACTION));
		$dataPerPage = \esc_attr($atts['per_page']);
		$dataSystem = \esc_attr(isset($atts['system']) ? $atts['system'] : '');
		$dataColumns = \esc_attr(implode(',', $atts['columns']));
		$dataHeaders = \esc_attr(implode(',', $atts['headers']));

		return "<div class='lwss_selectable wr-history-wrapper'"
			. " data-type='HistoryWrapper'"
			. " data-user-id='{$dataUserId}'"
			. " data-nonce='{$dataNonce}'"
			. " data-per-page='{$dataPerPage}'"
			. " data-system='{$dataSystem}'"
			. " data-columns='{$dataColumns}'"
			. " data-headers='{$dataHeaders}'>"
			. "<div class='lwss_selectable wr-history-grid' data-type='Grid' style='grid-template-columns:{$gridTemplateColumns}'>"
			. $head
			. $rows
			. "</div>"
			. $pagination
			. "</div>";
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
		$html = $this->renderGrid($atts, $history, 1, 5);
		$this->stygen = false;
		return $html;
	}

	protected function getColumnHeaders($atts)
	{
		$headers = \trim($atts['headers']) ? array_map('trim', explode(',', $atts['headers'])) : array();
		$columns = $atts['columns'];
		$columnsCount = count($columns);

		for ($i = count($headers); $i < $columnsCount; ++$i) {
			switch ($columns[$i]) {
				case 'system':
					$headers[$i] = __("Loyalty System", 'woorewards');
					break;
				case 'date':
					$headers[$i] = __("Date", 'woorewards');
					break;
				case 'descr':
					$headers[$i] = __("Description", 'woorewards');
					break;
				case 'points':
					$headers[$i] = __("Points", 'woorewards');
					break;
				case 'total':
					$headers[$i] = __("Points Balance", 'woorewards');
					break;
				default:
					$headers[$i] = $columns[$i];
			}
		}
		return \array_map('\wp_kses_post', $headers);
	}
}