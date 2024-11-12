<?php
namespace LWS\WOOREWARDS\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage user points (and point history in pro version).
 *	Few functions have a force argument to reset the buffered amount by reading the database again. */
class PointStack
{
	const MetaPrefix = 'lws_wre_points_';
	public $lastLogId = 0;

	public $name   = '';
	public $userId = false;
	public $amount = false;

	function __construct($name, $userId)
	{
		$this->name = $name;
		$this->userId = $userId;
	}

	/** relevant when several pages opened at the same time.
	 *	WP load meta very soon, then all is read from cache if possible,
	 *	no way to know other thread changed something, db never called again. */
	static function cleanCache($userId)
	{
		if (\function_exists('\wp_cache_flush_group')) {
			// require at least WP 6.1.0
			if (\wp_cache_supports('flush_group')) {
				\wp_cache_flush_group('user_meta');
			} // else means another cache system exists that does not support flush_group
		}
	}

	/** $force bypass any cache and directly call DB. */
	function get($force = false)
	{
		if( false === $this->amount || $force )
		{
			if ($force) {
				global $wpdb;
				$val = $wpdb->get_var($wpdb->prepare(
					"SELECT `meta_value` FROM {$wpdb->usermeta} WHERE `user_id`=%d AND `meta_key`=%s",
					$this->userId, $this->metaKey()
				));
			} else {
				$val = \get_user_meta($this->userId, $this->metaKey(), true);
			}
			$this->amount = ($val && \is_numeric($val)) ? (int)$val : 0;
		}
		return $this->amount;
	}

	function &set($points, $reason='', $origin='', $origin2=false)
	{
		$this->amount = intval(round($points));
		\update_user_meta($this->userId, $this->metaKey(), $this->amount);
		$this->trace($points, null, $reason, $origin, $origin2);
		return $this;
	}

	function &add($points, $reason='', $force = false, $origin='', $origin2=false)
	{
		if( !empty($points = intval(round($points))) )
		{
			$amount = $this->get($force);
			$this->amount = $amount + $points;
			\update_user_meta($this->userId, $this->metaKey(), $this->amount);
			$this->trace($this->amount, $points, $reason, $origin, $origin2);
		}
		return $this;
	}

	function &sub($points, $reason='', $force = false, $origin='', $origin2=false)
	{
		if( !empty($points = intval(round($points))) )
		{
			$amount = $this->get($force);
			$this->amount = $amount - $points;
			\update_user_meta($this->userId, $this->metaKey(), $this->amount);
			$this->trace($this->amount, -$points, $reason, $origin, $origin2);
		}
		return $this;
	}

	/** That action is performed for all users.
	 *
	 * Reset any point amount in this stack unchanged since $threshold.
	 * If option 'lws_woorewards_pointstack_timeout_delete' is 'on', delete all record before that date.
	 * @param $threshold (false|\DateTime) reset points if last change is before that date.
	 * @param $getAffectedUserIds (bool) if true, return an array with affected user IDs. default is false.
	 * @param $reason (string) the cleanup reason to set in user history.
	 * @param $resetTo (int) reset points to this value, default is zero.
	 * @return null|array depends on $getAffectedUserIds */
	public function reset($threshold, $getAffectedUserIds=false, $reason=false, $resetTo=0)
	{
		$affected = null;
		global $wpdb;
		$table = self::table();
		$resetTo = intval($resetTo);

		// reset point values for customers without recent activity but with points (note we set '' and not zero)
		$update = "UPDATE {$wpdb->usermeta} as raz SET raz.meta_value='' WHERE raz.meta_key=%s AND raz.meta_value>%d";
		$args = array(
			$this->metaKey(),
			$resetTo
		);
		if( \is_a($threshold, '\DateTime') )
		{
			$update .= " AND raz.user_id NOT IN (SELECT DISTINCT good.user_id FROM $table as good WHERE good.stack=%s AND date(good.mvt_date) >= date(%s))";
			$args[] = $this->name;
			$args[] = $threshold->format('Y-m-d');
		}
		$wpdb->query($wpdb->prepare($update, $args)); // phpcs:ignore WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared

		// insert reset line for customers with '' as point value
		$reason = $reason ? $this->formatReason($reason) : \LWS\WOOREWARDS\Core\Trace::byReason("Lost due to inactivity", 'woorewards-lite');
		$fields = array('new_total', 'stack', 'commentar', 'blog_id', 'origin', 'mvt_date');
		$values =  array('%d', '%s', '%s', '%d', '%s', \gmdate("'Y-m-d H:i:s'", \time()));
		$args = array(
			$resetTo,
			$this->name,
			$reason->reason,
			$reason->getBlog(),
			$reason->referral ? $reason->referral : 'stack_reset',
		);
		if( $reason->providerId ){ $fields[] = 'origin2'; $values[] = '%d'; $args[] = $reason->providerId; }
		if( $reason->orderId ){ $fields[] = 'order_id'; $values[] = '%d'; $args[] = $reason->orderId; }

		$fields = implode(', ', $fields);
		$values = implode(', ', $values);
		$insert = <<<EOT
INSERT INTO $table (user_id, {$fields})
SELECT DISTINCT pts.user_id, {$values} FROM {$wpdb->usermeta} as pts WHERE pts.meta_key=%s AND pts.meta_value=''
EOT;
		$args[] = $this->metaKey();
		$wpdb->query($wpdb->prepare($insert, $args)); // phpcs:ignore WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared

		if( $getAffectedUserIds )
		{
			$affected = $wpdb->get_col($wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} as raz WHERE raz.meta_key=%s AND raz.meta_value=''",
				$this->metaKey()
			));
		}

		// clean points amounts values (replace '' by zero)
		$wpdb->query($wpdb->prepare(
			"UPDATE {$wpdb->usermeta} as raz SET raz.meta_value=%d WHERE raz.meta_key=%s AND raz.meta_value=''",
			$resetTo,
			$this->metaKey()
		));

		if( \is_a($threshold, '\DateTime') && !empty(\get_option('lws_woorewards_pointstack_timeout_delete', '')) )
		{
			$this->cleanup($threshold);
		}

		$this->amount = false;

		\do_action('lws_woorewards_point_stack_reseted', $this, $threshold, $resetTo, $reason, $getAffectedUserIds, $affected);
		return $affected;
	}

	/** That action is performed for all users.
	 *
	 * Remove from db any trace of that stack (usermeta and history) */
	public function delete()
	{
		global $wpdb;
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE meta_key=%s", $this->metaKey()));
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->lwsWooRewardsHistoric} WHERE stack=%s", $this->name));

		$this->amount = false;
	}

	/** @return (bool) in usage by a pool */
	public function isUsed()
	{
		global $wpdb;
		$c = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='wre_pool_point_stack' AND meta_value=%s",
			$this->name
		));
		return is_null($c) ? false : !empty($c);
	}

	/** Merge points from another stack to this one.
	 * The other stack is NOT modified. */
	public function merge($otherStackName)
	{
		global $wpdb;

		// mark the merge in history, let stack empty for futur reference
		$insert = <<<EOT
INSERT INTO {$wpdb->lwsWooRewardsHistoric} (user_id, new_total, points_moved, stack, commentar, origin, blog_id, mvt_date)
SELECT m.user_id, SUM(m.meta_value), SUM(m.diff), '', %s, 'merge', %d, %s
FROM (
	SELECT s.user_id, s.meta_value, 0 as diff FROM {$wpdb->usermeta} as s
	WHERE s.meta_key=%s
	UNION
	SELECT d.user_id, d.meta_value, d.meta_value as diff FROM {$wpdb->usermeta} as d
	WHERE d.meta_key=%s
) as m GROUP BY m.user_id
EOT;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter
		$wpdb->query($wpdb->prepare(
			$insert,
			\LWS\WOOREWARDS\Core\Trace::serializeReason(array("Points merged from %s", $otherStackName), 'woorewards-lite'),
			\get_current_blog_id(),
			\gmdate('Y-m-d H:i:s', \time()),
			$this->metaKey(),
			$this->metaKey($otherStackName)
		));

		// copy points in history back to usermeta
		$update = <<<EOT
UPDATE {$wpdb->usermeta} as d
INNER JOIN {$wpdb->lwsWooRewardsHistoric} as s ON s.user_id=d.user_id AND s.stack=''
SET d.meta_value=s.new_total
WHERE d.meta_key=%s
EOT;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter
		$wpdb->query($wpdb->prepare(
			$update,
			$this->metaKey()
		));

		// clean history, restore stack name
		$wpdb->query($wpdb->prepare(
			"UPDATE {$wpdb->lwsWooRewardsHistoric} SET stack=%s WHERE stack=''",
			$this->name
		));

		$this->amount = false;
	}

	/** That action is performed for all users.
	 *
	 * delete history in database.
	 * @param $threshold (\DateTime) remove all entry before that date. */
	protected function cleanup(\DateTime $threshold)
	{
		global $wpdb;
		$table = self::table();
		$wpdb->query($wpdb->prepare(
			"DELETE FROM $table WHERE date(mvt_date)<date(%s)",
			$threshold->format('Y-m-d')
		));
	}

	public function metaKey($name=false)
	{
		return self::MetaPrefix . ($name===false ? $this->name : $name);
	}

	public function getName()
	{
		return $this->name;
	}

	static function getUTCTimezone()
	{
		static $tz = null;
		if (null === $tz)
			$tz = new \DateTimeZone('UTC');
		return $tz;
	}

	/** That usefull function exists since 5.3
	 * But we keep a 4.9 compatibility. */
	static function getSiteTimezone()
	{
		if( function_exists('wp_timezone') )
			return \wp_timezone();
		else
			return new \DateTimeZone(self::getSiteTimezoneString());
	}

	/** That usefull function exists since 5.3
	 * But we keep a 4.9 compatibility. */
	static function getSiteTimezoneString()
	{
		if( function_exists('wp_timezone_string') )
			return \wp_timezone_string();

		$timezone_string = get_option( 'timezone_string' );

		if ( $timezone_string ) {
				return $timezone_string;
		}

		$offset  = (float) get_option( 'gmt_offset' );
		$hours   = (int) $offset;
		$minutes = ( $offset - $hours );

		$sign      = ( $offset < 0 ) ? '-' : '+';
		$abs_hour  = abs( $hours );
		$abs_mins  = abs( $minutes * 60 );
		$tz_offset = sprintf( '%s%02d:%02d', $sign, $abs_hour, $abs_mins );

		return $tz_offset;
	}

	/**	Convert value and add timezone.
	 *	@param $op_date (string) the date as read in DB
	 *	@param $retDateTime (bool) choose to get a DateTime instance or the string representation.
	 *	@param $withTime (bool) set false if your string does not have time.
	 *	@return (string|\DateTime) depending on $retDateTime */
	static function dateI18n($op_date, $retDateTime=false)
	{
		$date = \date_create($op_date);
		if ($retDateTime)
			return $date;
		else
			return \date_i18n(\get_option('date_format'), $date->getTimestamp() + self::getSiteTimezone()->getOffset($date));
	}

	/**	Convert value and add timezone.
	 *	@param $op_date (string) the date as read in DB
	 *	@param $retDateTime (bool) choose to get a DateTime instance or the string representation.
	 *	@param $withTime (bool) set false if your string does not have time.
	 *	@return (string|\DateTime) depending on $retDateTime */
	static function dateTimeI18n($op_date, $retDateTime=false)
	{
		$date = \date_create($op_date);
		if ($retDateTime)
			return $date;
		else
			return \date_i18n(\get_option('date_format') . ' ' . \get_option('time_format'), $date->getTimestamp() + self::getSiteTimezone()->getOffset($date));
	}

	/** @return array [op_date, op_value, op_result, op_reason] */
	function getHistory($force = false, $translate=true, $offset=false, $limit=false)
	{
		global $wpdb;
		$sql = <<<EOT
SELECT id, mvt_date as op_date, points_moved as op_value, new_total as op_result, commentar as op_reason, `origin`
FROM $wpdb->lwsWooRewardsHistoric
WHERE user_id=%d AND stack=%s
ORDER BY mvt_date DESC, id DESC
EOT;
		$args = array(
			$this->userId,
			$this->name
		);
		if( $offset !== false && $limit )
		{
			$sql .= " LIMIT %d, %d";
			$args[] = \absint($offset);
			$args[] = max(\intval($limit), 1);
		}

		$history = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);

		if( $translate )
		{
			if( $history )
			{
				$pool = \apply_filters('lws_woorewards_get_pools_by_stack', false, $this->name);
				$pool = $pool ? $pool->sort()->first() : false;

				foreach($history as &$row)
				{
					if ($pool) {
						if (\is_numeric($row['op_value'])) {
							$row['op_value']  = $pool->formatPoints($row['op_value'], false);
						}
						if (\is_numeric($row['op_result'])) {
							$row['op_result'] = $pool->formatPoints($row['op_result'], false);
						}
					}

					if ($row['origin'] && \is_numeric($row['origin'])) {
						$title = $this->getOriginTitle($row['origin']);
						if ($title) {
							$row['op_reason'] = $title;
							continue; // skip translation of original reason
						}
					}
					if( $row['op_reason'] && \is_serialized($row['op_reason']) )
					{
						$reason = @unserialize($row['op_reason']);
						if( $reason && is_array($reason) )
							$row['op_reason'] = \LWS\WOOREWARDS\Core\Trace::reasonToString($reason, true);
					}
				}
			}
		}
		return $history;
	}

	/** overwrite \LWS\WOOREWARDS\Core\Trace reason with origin and origin2
	 * if given as arguments and not already in reason */
	protected function formatReason($reason='', $origin='', $origin2=false)
	{
		if( is_a($reason, '\LWS\WOOREWARDS\Core\Trace') )
			$trace = $reason;
		else if( is_array($reason) )
			$trace = new \LWS\WOOREWARDS\Core\Trace($reason);
		else
			$trace = \LWS\WOOREWARDS\Core\Trace::byReason($reason);

		if( $origin && !$trace->referral )
			$trace->setOrigin($origin);
		if( $origin2 !== false && $trace->providerId === false )
			$trace->setProvider($origin2);

		return $trace;
	}

	protected function &trace($points, $move=null, $reason='', $origin='', $origin2=false)
	{
		$reason = $this->formatReason($reason, $origin, $origin2);
		global $wpdb;
		$values = array(
			'user_id'      => $this->userId,
			'stack'        => $this->name,
			'points_moved' => $move,
			'new_total'    => $points,
			'commentar'    => $reason->reason,
			'origin'       => $reason->referral,
			'blog_id'      => $reason->getBlog(),
			'mvt_date'     => \gmdate('Y-m-d H:i:s', \time()),
		);
		$formats = array(
			'%d',
			'%s',
			'%d',
			'%d',
			'%s',
			'%s',
			'%d',
			'%s',
		);
		if( $reason->orderId )
		{
			$values['order_id'] = $reason->orderId;
			$formats[] = '%d';
		}
		if( $reason->providerId )
		{
			$values['origin2'] = $reason->providerId;
			$formats[] = '%d';
		}

		$wpdb->insert($wpdb->lwsWooRewardsHistoric, $values, $formats);
		$this->lastLogId = $wpdb->insert_id;
		return $this;
	}

	/** @return array history rows as array of object
	 * {trace_id, user_id, stack, date, move, total, origin, provider_id, order_id, blog_id, comments}
	 * @param $args (array) define values tested in where clause (equal).
	 * All returned field testable against a value or array of value, except comments.
	 * prefix field name by ! to neg the test.
	 * In addition, you can set:
	 * start (DateTime), end (DateTime) */
	static function queryTrace($args)
	{
		global $wpdb;
		$select = array(
			'id as trace_id',
			'user_id',
			'`stack`',
			'mvt_date as `date`',
			'points_moved as `move`',
			'new_total as `total`',
			'`origin`',
			'`origin2` as provider_id',
			'order_id',
			'blog_id',
			'commentar as `comments`',
		);
		$where = array();
		foreach( $args as $key => $value )
		{
			if( $neg = (substr($key, 0, 1) == '!') )
				$key = substr($key, 1);

			switch($key)
			{
				case 'trace_id'   : $where[] = self::clause('id'          , $value, $neg); break;
				case 'user_id'    : $where[] = self::clause('user_id'     , $value, $neg); break;
				case 'stack'      : $where[] = self::clause('stack'       , $value, $neg); break;
				case 'date'       : $where[] = self::clause('mvt_date'    , $value, $neg); break;
				case 'move'       : $where[] = self::clause('points_moved', $value, $neg); break;
				case 'total'      : $where[] = self::clause('new_total'   , $value, $neg); break;
				case 'origin'     : $where[] = self::clause('origin'      , $value, $neg); break;
				case 'provider_id': $where[] = self::clause('origin2'     , $value, $neg); break;
				case 'order_id'   : $where[] = self::clause('order_id'    , $value, $neg); break;
				case 'blog_id'    : $where[] = self::clause('blog_id'     , $value, $neg); break;
				case 'start':
					$where[] = sprintf("mvt_date >= DATE('%s')", $value->format('Y-m-d H:i:s'));
					break;
				case 'end':
					$where[] = sprintf("mvt_date <= DATE('%s')", $value->format('Y-m-d H:i:s'));
					break;
			}
		}

		$query = \LWS\Adminpanel\Tools\Request::from($wpdb->lwsWooRewardsHistoric);
		$query->select($select);
		$query->order(array('mvt_date DESC', 'id DESC'));
		if( $where )
			$query->where($where);
		return $query->getResults();
	}

	static protected function clause($key, $value, $neg=false)
	{
		$value = \esc_sql($value);
		if( is_array($value) )
			return sprintf("`%s` %s ('%s')", $key, $neg ? 'NOT IN' : 'IN', implode("','", $value));
		else
			return sprintf("`%s` %s '%s'", $key, $neg ? '!=' : '=', $value);
	}

	/** Get point move history.
	 * @return (array) each value is an object with:
	 * * trace_id
	 * * user_id
	 * * stack (string) : the id of point stack
	 * * date (string) : the move date, can be used with \date_create().
	 * * move (int) : points moved
	 * * total (int) : point total after the move
	 * * origin (false|int|string|array) : source of move, any text, a event id or an unlockable id.
	 * * origin2 (null|int)
	 * * comments (string)
	 * @param $dateStart (false|\DateTime) only after the date if not false
	 * @param $dateEnd (false|\DateTime) only before the date if not false
	 * @param $origin (false|string|array) any origin if false (strict compare, empty string is not false)
	 * @param $origin2 (false|int|array) same but for origin2 with integers
	 * @param $userId (false|int|array) the stack the use was init for (if any) if false or override with a user id or an array of (int) user id.
	 */
	function getTraces($dateStart, $dateEnd, $origin=false, $origin2=false, $userId=false, $withComments=false)
	{
		global $wpdb;
		$sql = "SELECT id as trace_id, user_id, `stack`, mvt_date as `date`, points_moved as `move`, new_total as `total`, `origin`, `origin2`";
		if( $withComments )
			$sql .= ", commentar as `comments`";
		$sql .= (' FROM ' . self::table());

		$where = array();
		$prepare = array();
		if( $dateStart )
		{
			$where[] = 'mvt_date>=FROM_UNIXTIME(%d)';
			$prepare[] = $dateStart->getTimestamp();
		}
		if( $dateEnd )
		{
			$where[] = 'mvt_date<=FROM_UNIXTIME(%d)';
			$prepare[] = $dateEnd->getTimestamp();
		}
		if( false !== $origin )
		{
			if( is_array($origin) )
			{
				if( $origin )
				{
					$in = implode("','", array_map('\esc_sql', $origin));
					$where[] = "origin IN ('{$in}')";
				}
			}
			else
			{
				$where[] = 'origin=%s';
				$prepare[] = $origin;
			}
		}
		if( false !== $origin2 )
		{
			if( is_array($origin2) )
			{
				if( $origin2 )
				{
					$in = implode(',', array_map('\intval', $origin2));
					$where[] = "origin2 IN ({$in})";
				}
			}
			else
			{
				$where[] = 'origin2=%d';
				$prepare[] = $origin2;
			}
		}
		if( is_array($userId) )
		{
			if( $userId )
			{
				$in = implode(',', array_map('\intval', $userId));
				$where[] = "user_id IN ({$in})";
			}
		}
		else
		{
			if( false === $userId )
				$userId = $this->userId;
			if( $userId )
			{
				$where[] = 'user_id=%d';
				$prepare[] = $userId;
			}
		}

		if( !$where )
			error_log("Read point history with any WHERE clause could lead to too many result.");
		else
			$sql .= (' WHERE ' . implode(' AND ', $where));

		$sql .= " ORDER BY mvt_date DESC, id DESC";
		$traces = $wpdb->get_results($prepare ? $wpdb->prepare($sql, $prepare) : $sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery
		if( false === $traces )
		{
			error_log("An error occured during point history table read.");
			return array();
		}
		return $traces;
	}

	/** @see getTraces but with just-in-time translated comments */
	function getFormatedTraces($dateStart, $dateEnd, $origin=false, $origin2=false, $userId=false)
	{
		$traces = $this->getTraces($dateStart, $dateEnd, $origin, $origin2, $userId, true);
		if( $traces )
		{
			foreach( $traces as &$row )
			{
				if ($row->origin && \is_numeric($row->origin)) {
					$title = $this->getOriginTitle($row->origin);
					if ($title) {
						$row->comments = $title;
						continue; // skip translation of original reason
					}
				}
				if ($row->comments && \is_serialized($row->comments)) {
					$reason = @unserialize($row->comments);
					if( $reason && is_array($reason) )
						$row->comments = \LWS\WOOREWARDS\Core\Trace::reasonToString($reason, true);
				}
			}
		}
		return $traces;
	}

	/** Read origin title from Event and Unlockable. */
	protected function getOriginTitle($origin)
	{
		static $replaceReason = null;
		static $origins = array();
		if (null === $replaceReason) {
			$replaceReason = \apply_filters('lws_woorewards_stack_history_prefers_origin_title', true);
			if ($replaceReason) {
				// load existant origins that implement `function getTitleAsReason()`
				foreach (\get_posts(array('post_type' => \LWS\WOOREWARDS\Abstracts\Event::POST_TYPE, 'numberposts' => -1)) as $post) {
					$origins[$post->ID] = \LWS\WOOREWARDS\Abstracts\Event::fromPost($post);
				}
				foreach (\get_posts(array('post_type' => \LWS\WOOREWARDS\Abstracts\Unlockable::POST_TYPE, 'numberposts' => -1)) as $post) {
					$origins[$post->ID] = \LWS\WOOREWARDS\Abstracts\Unlockable::fromPost($post);
				}
			}
		}
		if ($replaceReason) {
			if (isset($origins[$origin])) {
				$title = $origins[$origin]->getTitleAsReason();
				if ($title)
					return $title;
			}
		}
		return false;
	}

	static function table()
	{
		global $wpdb;
		return $wpdb->lwsWooRewardsHistoric;
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("Points merged from %s", 'woorewards-lite');
		__("Lost due to inactivity", 'woorewards-lite');
	}
}
