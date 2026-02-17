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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public AJAX endpoint
		$args = array(
			'system' => isset($_GET['system']) ? \sanitize_key(\wp_unslash($_GET['system'])) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'value'  => isset($_GET['value'])  ? \intval($_GET['value']) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'symbol' => isset($_GET['symbol']) ? \boolval($_GET['symbol']) : true, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
			\wp_die(esc_html(__("Loyalty system not found.", 'woorewards-lite')), 404);
		}
	}

	function userPointsHistory()
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin AJAX, capability checked below
		$user = isset($_GET['user']) ? \intval($_GET['user']) : false;
		$stack = isset($_GET['stack']) ? \sanitize_key(\wp_unslash($_GET['stack'])) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if( !$user )
			$user = \get_current_user_id();
		if( empty($user) || empty($stack) )
			\wp_die(esc_html(__("Point system or user not found.", 'woorewards-lite')), 404);

		if( $user != \get_current_user_id() && !\current_user_can('manage_rewards') )
			\wp_die(esc_html(__("You do not have permission to see other history.", 'woorewards-lite')), 403);

		$page = isset($_GET['page']) ? \absint($_GET['page']) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$count = isset($_GET['count']) ? max(\intval($_GET['count']), 1) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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
