<?php
namespace LWS\WOOREWARDS\PointsFlow\Methods;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** @see https://woocommerce.com/products/woocommerce-points-and-rewards/
 *	WC official */
class WooCommercePAndR extends \LWS\WOOREWARDS\PointsFlow\ExportMethod
{
	/** @return (array) the json that will be send,
	 * An array with each entries as {email, points} */
	public function export($value, $arg)
	{
		// get content
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- third-party table name
		return $wpdb->get_results("SELECT u.user_email as `email`, SUM(wc.points_balance) as `points` FROM {$wpdb->prefix}wc_points_rewards_user_points as wc INNER JOIN {$wpdb->users} as u ON u.ID=wc.user_id GROUP BY wc.user_id");
	}

	/** @return (string) human readable name */
	public function getTitle()
	{
		return __("WooCommerce Points And Rewards (by WooCommerce)", 'woorewards');
	}
}
