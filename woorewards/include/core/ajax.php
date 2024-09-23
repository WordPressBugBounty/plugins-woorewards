<?php
namespace LWS\WOOREWARDS\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage set of Event/Unlockable with PointStack. */
class Ajax
{
	function __construct()
	{
		\add_action('wp_ajax_lws_woorewards_user_points_history', array($this, 'userPointsHistory'));

		\add_action('wp_ajax_lws_woorewards_point_format', array($this, 'formatPoints'));
		\add_action('wp_ajax_nopriv_lws_woorewards_point_format', array($this, 'formatPoints'));
	}

	/** echo json object [success (bool), original (int), formatted (string)]
	 * GET arguments:
	 * value (int) point amount
	 * system (string) pool name (also support pool id)
	 * symbol (bool) include pool currency symbol, default is true */
	function formatPoints()
	{
		$args = array(
			'system' => isset($_GET['system']) ? \sanitize_key($_GET['system']) : '',
			'value'  => isset($_GET['value'])  ? \intval($_GET['value']) : 0,
			'symbol' => isset($_GET['symbol']) ? \boolval($_GET['symbol']) : true,
		);

		$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $args);
		if( $pools && $pools->count() )
		{
			$pool = $pools->first();

			$response = array(
				'success'   => true,
				'original'  => $args['value'],
				'formatted' => $args['symbol'] ? \LWS_WooRewards::formatPointsWithSymbol($args['value'], $pool) : \LWS_WooRewards::formatPoints($args['value'], $pool),
			);
			\wp_send_json($response);
		}
		else
		{
			\wp_die(__("Loyalty system not found.", 'woorewards-lite'), 404);
		}
	}

	function userPointsHistory()
	{
		$user = isset($_GET['user']) ? intval($_GET['user']) : false;
		$stack = isset($_GET['stack']) ? \sanitize_key($_GET['stack']) : false;
		if( !$user )
			$user = \get_current_user_id();
		if( empty($user) || empty($stack) )
			\wp_die(__("Point system or user not found.", 'woorewards-lite'), 404);

		if( $user != \get_current_user_id() && !\current_user_can('manage_rewards') )
			\wp_die(__("You do not have permission to see other history.", 'woorewards-lite'), 403);

		$page = isset($_GET['page']) ? \absint($_GET['page']) : false;
		$count = isset($_GET['count']) ? max(\intval($_GET['count']), 1) : false;

		$stack = \LWS\WOOREWARDS\Collections\PointStacks::instanciate()->create($stack, $user, 'ajax');
		$points = $stack->getHistory(false, true, $page, $count);

		$date_format = \get_option('date_format');
		$tz = \wp_timezone();
		foreach($points as &$point) {
			$point['op_datetime'] = \date_create($point['op_date'])->setTimezone($tz)->format('Y-m-d H:i:s (P)');
			$point['op_date'] = \LWS\WOOREWARDS\Core\PointStack::dateI18n($point['op_date']);
		}
		\wp_send_json($points);
	}
}
