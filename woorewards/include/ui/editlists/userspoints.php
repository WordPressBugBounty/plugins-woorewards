<?php
namespace LWS\WOOREWARDS\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Display users and their point at backend. */
class UsersPoints extends \LWS\Adminpanel\EditList\Source
{
	const L_PREFIX = 'lws_wre_pool_';
	const S_PREFIX = 'lws_wre_points_';

	protected $sortSource = false;
	protected $stackIds   = false;

	function labels()
	{
		$default = \LWS_WooRewards::getInstalledPool();
		if (!$default) {
			$prefabs = \LWS_WooRewards::getPrefabPools(false);
			if ($prefabs)
				$default = $prefabs->first();
		}
		$column = self::L_PREFIX . ($default ? $default->getId() : 'default');
		$labels = array(
			'user'    => array(__("Users", 'woorewards-lite'), '1fr'),
			$column   => array(\LWS_WooRewards::getPointSymbol(2, $default), 'max-content'), // usermeta 'lws_wre_points_default'
			'rewards' => array(__("Rewards", 'woorewards-lite'), 'auto'), // filled by filter
		);
		return \apply_filters('lws_woorewards_ui_userspoints_labels', $labels);
	}

	function read($limit)
	{
		global $wpdb;
		$request = \LWS\Adminpanel\Tools\Request::from($wpdb->users, 'u');
		$request->select('u.ID as user_id, u.*');
		$request->group('u.ID');
		$request->rowLimit($limit);
		$this->addSorting($request);

		$request = \apply_filters('lws_woorewards_admin_userspoints_request', $this->search($request), true);
		$users = $request->getResults(ARRAY_A);
		if (!$users)
			return array();
		else
			return \array_map(array($this, 'shapeRow'), $users);
	}

	function shapeRow($user)
	{
		global $wpdb;
		// get all points for that user
		$points = $wpdb->get_results($wpdb->prepare(
			"SELECT meta_key, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s",
			(int)$user['user_id'],
			self::S_PREFIX . '%'
		), OBJECT_K);
		// format them
		foreach ($this->getStackIds() as $info) {
			$column = (self::L_PREFIX . $info->post_id);
			$key = (self::S_PREFIX . $info->stack_id);
			$amount = (isset($points[$key]) ? $points[$key]->meta_value : 0);
			$user[$key] = \LWS_WooRewards::formatPoints($amount, $info->post_id);
			$user[$column] = sprintf(
				"<a class='lws_wre_point_history maxwidth right lws-icon-time-machine' data-stack='%s' data-user='%d'>%s</a>",
				\esc_attr($info->stack_id),
				(int)$user['user_id'],
				\apply_filters('lws_wre_editlist_point_amount_display', $user[$key], $user, $info->post_id, $info)
			);
		}
		// user name
		$edit = esc_attr(\get_edit_user_link($user['user_id']));
		$mailto = esc_attr('mailto:' . $user['user_email']);
		$display = \apply_filters('lws_woorewards_customer_display_name', $user['display_name'], $user);
		$user['user'] = implode(' - ', array(
			"<a href='{$edit}' target='_blank'>{$user['user_login']}</a>",
			"<a href='{$mailto}'>{$user['user_email']}</a>",
			"<span class='lws_wre_history_dispname'>{$display}</span>"
		));
		// rewards
		$user['rewards'] = "<div class='lws-editlist-btns-line'>";
		$user['rewards'] .= implode('', \apply_filters('lws_woorewards_ui_userspoints_rewards_cell', array(), $user));
		$user['rewards'] .= "</div>";
		// return modified value
		return $user;
	}

	/** @return array as [string:pool_name] => object{post_name, stack_id} */
	protected function getStackIds()
	{
		if (false === $this->stackIds)
		{
			global $wpdb;
			$this->stackIds = $wpdb->get_results("SELECT post_id, post_name, meta_value as stack_id FROM {$wpdb->postmeta} INNER JOIN {$wpdb->posts} ON ID=post_id WHERE meta_key='wre_pool_point_stack'", OBJECT_K);
		}
		return $this->stackIds;
	}

	function total()
	{
		global $wpdb;
		$request = \LWS\Adminpanel\Tools\Request::from($wpdb->users, 'u');
		$request->select('COUNT(u.ID)');
		$request = \apply_filters('lws_woorewards_admin_userspoints_request', $this->search($request), false);
		$c = $request->getVar();
		return (\is_null($c) ? -1 : $c);
	}

	/** @return object the given $sql array with WHERE clause if required. */
	protected function search($request)
	{
		$needle = isset($_REQUEST['usersearch']) ? \trim($_REQUEST['usersearch']) : '';
		if ($needle) {
			global $wpdb;
			$mask = ("'%" . \esc_sql($needle) . "%'");
			$where = array(
				'condition' => 'OR',
				"u.user_login LIKE {$mask}",
				"u.user_email LIKE {$mask}",
				"u.display_name LIKE {$mask}",
				"u.user_nicename LIKE {$mask}",
			);
			if (($id = \intval($needle)) > 0)
				$where[] = "u.ID = {$id}";
			if (\get_option('lws_woorewards_admin_userspoints_deep_search', 'on'))
				$where[] = "u.ID IN (SELECT m.user_id FROM {$wpdb->usermeta} as m WHERE m.meta_value LIKE {$mask} AND (m.meta_key LIKE '%name' OR m.meta_key LIKE 'billing_%'))";
			$request->where($where);
		}
		return $request;
	}

	protected function addSorting(&$request)
	{
		$asc = !$this->isSortDescsending('userspoints');
		$value = $this->getSortValue('userspoints');
		if ($value) {
			$value = explode('-', $value, 2);
			if (count($value) > 1) {
				global $wpdb;
				// need usermeta points
				$request->leftJoin($wpdb->usermeta, 'csort', $wpdb->prepare(
					'csort.user_id=u.ID AND csort.meta_key=%s',
					'lws_wre_points_' . $value[1]
				));
				// numerical order on casted or inexistant value
				return $request->order(
					'CASE WHEN csort.meta_value IS NOT NULL THEN CAST(csort.meta_value AS SIGNED) ELSE 0 END',
					$asc
				);
			} elseif ('email' == $value[0]) {
				return $request->order('u.user_email', $asc);
			} elseif ('name' == $value[0]) {
				return $request->order('u.display_name', $asc);
			}
		}
		return $request->order('u.user_login', $asc);
	}

	public function getSortColumns()
	{
		if (false === $this->sortSource) {
			global $wpdb;
			$sql = <<<EOT
SELECT pool.ID, stack.meta_value as stack_id, pool.post_title
FROM {$wpdb->posts} as `pool`
LEFT JOIN {$wpdb->postmeta} AS stack ON stack.post_id=pool.ID AND stack.meta_key="wre_pool_point_stack"
WHERE post_type=%s AND post_status NOT IN ("trash")
ORDER BY post_title ASC
EOT;
			$stacks = $wpdb->get_results($wpdb->prepare($sql, \LWS\WOOREWARDS\Core\Pool::POST_TYPE));

			$this->sortSource = array(
				array('value' => '', 'label' => __("User Login", 'woorewards-lite')), // user_login
				array('value' => 'email', 'label' => __("Email", 'woorewards-lite')), // user_email
				array('value' => 'name', 'label' => __("Display Name", 'woorewards-lite')), // display_name
			);
			$label = __("Points : %s", 'woorewards-lite');
			foreach($stacks as $stack) {
				$this->sortSource[] = array(
					'value' => ($stack->ID . '-' . $stack->stack_id),
					'label' => sprintf($label, $stack->post_title),
				);
			}
		}
		return $this->sortSource;
	}

	/** no edition, use bulk action */
	function input()
	{
		return '';
	}

	/** no edition, use bulk action */
	function write($row)
	{
		return false;
	}

	/** Cannot erase a user here. */
	function erase($row)
	{
		return false;
	}
}