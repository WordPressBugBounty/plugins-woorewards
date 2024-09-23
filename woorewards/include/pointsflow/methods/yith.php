<?php
namespace LWS\WOOREWARDS\PointsFlow\Methods;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Get the total points for users.
 * that will be rounded (floor) at import in MyRewards (points are integer). */
class Yith extends \LWS\WOOREWARDS\PointsFlow\ExportMethod
{
	/** @return (array) the json that will be send,
	 * An array with each entries as {email, points} */
	public function export($value, $arg)
	{
		global $wpdb;
		$table = $wpdb->prefix . 'yith_ywpar_points_log';
		$sql = <<<EOT
SELECT u.user_email as `email`, SUM((yith.amount)) as `points`
FROM {$table} as yith
INNER JOIN {$wpdb->users} as u ON u.ID=yith.user_id
GROUP BY yith.user_id
EOT;
		$results = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			\wp_die("Yith Points and Rewards must be installed and active", 410);
		}
		return $results;
	}

	/** @return (string) human readable name */
	public function getTitle()
	{
		return __("Yith Points and rewards", 'woorewards-lite');
	}
}
