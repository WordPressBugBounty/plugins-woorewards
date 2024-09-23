<?php
namespace LWS\WOOREWARDS\PointsFlow\Methods;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Get the total points for users.
 * @warning Sumo support float values,
 * that will be rounded (floor) at import in MyRewards (points are integer). */
class Sumo extends \LWS\WOOREWARDS\PointsFlow\ExportMethod
{
	/** @return (array) the json that will be send,
	 * An array with each entries as {email, points} */
	public function export($value, $arg)
	{
		global $wpdb;
		$table = $wpdb->prefix . 'rspointexpiry';
		$sql = <<<EOT
SELECT u.user_email as `email`, SUM((sumo.earnedpoints-sumo.usedpoints)) as `points`
FROM {$table} as sumo
INNER JOIN {$wpdb->users} as u ON u.ID=sumo.userid
WHERE sumo.expiredpoints IN(0)
GROUP BY sumo.userid
EOT;
		$results = $wpdb->get_results($sql);
		if ($wpdb->last_error) {
			\wp_die("SUMO must be installed and active", 410);
		}
		return $results;
	}

	/** @return (string) human readable name */
	public function getTitle()
	{
		return __("Sumo", 'woorewards-lite');
	}
}
