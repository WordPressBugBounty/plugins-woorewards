<?php
namespace LWS\WOOREWARDS\PointsFlow;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage Import/Export final process.
 *	Read submitted json for import
 *	and output json from ajax export requests.  */
class Action
{
	private $importError = false;

	static function register()
	{
		$me = new self();
		\add_action('wp_ajax_'.'woorewards-lite'.'-export-wr', array($me, 'exportWR'));
		\add_action('wp_ajax_'.'woorewards-lite'.'-export-points', array($me, 'exportPoints'));

		\add_filter('pre_update_option_'.'woorewards-lite'.'_import_file', array($me, 'import'), 9, 3);
		\add_filter('lws_adminpanel_form_attributes'.LWS_WOOREWARDS_PAGE.'.system', array($me, 'importFormAttributes'));
		\add_filter('pre_set_transient_settings_errors', array($me, 'importResult'), 999);
	}

	function importResult($value)
	{
		if (false !== $this->importError)
		{
			\lws_admin_delete_notice('lws_ap_page');
			\lws_admin_add_notice_once('woorewards-lite'.'-error', __("Import Error", 'woorewards-lite') . '<br/>' . $this->importError, array('level'=>'error'));
		}
		return $value;
	}

	function importFormAttributes($attrs)
	{
		$attrs['enctype']='multipart/form-data';
		return $attrs;
	}

	/**	@return $oldValue cause WP dont go further with that option. */
	function import($value, $oldValue, $option)
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by WP settings API
		if( !(isset($_POST['lws_wre_points_action']) && 'import' == \sanitize_text_field(\wp_unslash($_POST['lws_wre_points_action']))) )
			return $oldValue; // we only want a import button click, not a page save

		if( !\current_user_can('manage_options') )
		{
			$this->importError = __("You are not allowed to do that", 'woorewards-lite');
			return $oldValue;
		}
		$stack = isset($_REQUEST['woorewards-lite' . '_default_pool']) ? \sanitize_key(\wp_unslash($_REQUEST['woorewards-lite' . '_default_pool'])) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified by WP settings API
		if( $stack )
		{
			if (\class_exists('\LWS\WOOREWARDS\PRO\Core\Pool')) {
				$pool = \LWS\WOOREWARDS\PRO\Core\Pool::getOrLoad($stack, false);
			} else {
				$pool = \apply_filters('lws_woorewards_get_pools_by_args', false, array(
					'system' => $stack,
					'force'  => true,
				));
				if ($pool)
					$pool = $pool->last();
			}
			if ($pool)
				$stack = $pool->getStackId();
		}
		else
		{
			$this->importError = __("Missing destination loyalty system.", 'woorewards-lite');
			return $oldValue;
		}

		$replace = true;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by WP settings API
		if (isset($_POST['woorewards-lite' . '_behavior']) && 'add' == \sanitize_text_field(\wp_unslash($_POST['woorewards-lite' . '_behavior'])))
			$replace = false;

		$key = 'woorewards-lite' . '_import_file';
		if( isset($_FILES[$key]) && !empty($_FILES[$key]) && !empty($_FILES[$key]['tmp_name']) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by WP settings API
		{
			$filename = \sanitize_file_name(\wp_unslash($_FILES[$key]['name'])); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.NonceVerification.Missing
			if( !empty($_FILES[$key]['error']) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.NonceVerification.Missing
			{
				/* translators: %s: filename */
				$this->importError = sprintf(__("Error during upload of file %s, perhaps the file is too big. You can try to split it up or increase max allowed file size on your server.", 'woorewards-lite'), $filename);
			}
			else
			{
				if( \sanitize_mime_type(\wp_unslash($_FILES[$key]['type'])) != 'application/json' ) // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
				{
					$this->importError = __("Expects a JSON file.", 'woorewards-lite');
				}
				else try
				{
					$reason = isset($_POST['woorewards-lite' . '_import_reason']) ? \sanitize_text_field(\wp_unslash($_POST['woorewards-lite' . '_import_reason'])) : false; // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$filename_esc = \wp_unslash($_FILES[$key]['tmp_name']);
					$json = (array)@json_decode(@file_get_contents($filename_esc), true); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- reading uploaded tmp file
					$this->importJSON($json, $stack, $replace, $reason);
				}
				catch(\Exception $e)
				{
					$this->importError = __("The file cannot be read or format is invalid. Expects JSON content.", 'woorewards-lite');
				}
				\wp_delete_file(\sanitize_file_name(\wp_unslash($_FILES[$key]['tmp_name']))); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}
		else
			$this->importError = __("Please, select a file to import.", 'woorewards-lite');
		return $oldValue;
	}

	/** @param $replace (bool) if false, points are added. */
	protected function importJSON($json, $stack, $replace=true, $reason=false)
	{
		$affected = 0;
		$unknown = array();
		$ignored = array();
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dynamic table name from wpdb property
		$table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->lwsWooRewardsHistoric}'");
		\set_time_limit(0); // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found, Squiz.PHP.DiscouragedFunctions.Discouraged -- long import needs extended time
		if (!$reason)
			$reason = _x("Import", "History line", 'woorewards-lite');
		$metakey = 'lws_wre_points_'.$stack;
		$blogId = \get_current_blog_id();

		$multiply = floatval(str_replace(',', '.', \get_option('woorewards-lite'.'_multiply', 1)));
		if( !$multiply )
			$multiply = 1;
		$round = \get_option('woorewards-lite'.'_rounding', 'floor');

		foreach( $json as $row )
		{
			if( isset($row['email']) && isset($row['points']) )
			{
				$email = \trim($row['email']);
				if (!$email)
					continue;

				$points = floatval($row['points']) * $multiply;
				if( 'floor' == $round )
					$points = floor($points);
				else if( 'ceil' == $round )
					$points = ceil($points);
				else if( 'half_up' == $round )
					$points = round($points, 0, PHP_ROUND_HALF_UP);
				else if( 'half_down' == $round )
					$points = round($points, 0, PHP_ROUND_HALF_DOWN);

				if( $user = \get_user_by('email', $email) )
				{
					$oldPts = 0;
					if ($replace) {
						\update_user_meta($user->ID, $metakey, $points);
					} else {
						$oldPts = \intval(\get_user_meta($user->ID, $metakey, true));
						\update_user_meta($user->ID, $metakey, $points + $oldPts);
					}
					++$affected;
					if( $table )
					{
						$values = array(
							'user_id'   => $user->ID,
							'stack'     => $stack,
							'new_total' => $points,
							'commentar' => $reason,
							'origin'    => 'migration-tool',
							'blog_id'   => $blogId,
						);
						$formats = array('%d', '%s', '%d', '%s', '%s');
						if (!$replace) {
							$values['new_total'] += $oldPts;
							$values['points_moved'] = $points;
							$formats[] = '%d';
						}
						$wpdb->insert($table, $values, $formats); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					}
				}
				else
					$unknown[$email] = $email;
			}
			else
			{
				$this->importError = __("Invalid data: ", 'woorewards-lite') . htmlentities(json_encode($row));
				return false;
			}
		}

		/* translators: %d: number of items */
		\lws_admin_add_notice_once('woorewards-lite'.'-notice', sprintf(__("Import done. %d items affected.", 'woorewards-lite'), $affected), array('level'=>'success'));
		if( !empty($unknown) )
		{
			$warning = __("The following users cannot be found. Points ignored.", 'woorewards-lite');
			$unknown = htmlentities(implode("\n", $unknown));
			$warning .= "<textarea>$unknown</textarea>";
			\lws_admin_add_notice_once('woorewards-lite'.'-warning', $warning, array('level'=>'warning'));
		}
		if( !empty($ignored) )
		{
			$warning = __("The following point pools cannot be found. Points ignored.", 'woorewards-lite');
			$ignored = htmlentities(implode("\n", $ignored));
			$warning .= "<textarea>$ignored</textarea>";
			\lws_admin_add_notice_once('woorewards-lite'.'-warning2', $warning, array('level'=>'warning'));
		}
		return true;
	}

	function exportWR()
	{
		if (!\wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['lws_btn_nonce'] ?? '')), 'woorewards-lite' . '-export-wr')) {
			\wp_die('forbidden', 403);
		}
		if( !\current_user_can('manage_options') )
			\wp_die('forbidden', 403);

		$poolKey = 'woorewards-lite' . '_from_pool';
		$poolName = isset($_REQUEST[$poolKey]) ? \sanitize_text_field(\wp_unslash($_REQUEST[$poolKey])) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if( $poolName )
		{
			require_once LWS_WOOREWARDS_INCLUDES . '/pointsflow/exportmethod.php';
			require_once LWS_WOOREWARDS_INCLUDES . '/pointsflow/methods/woorewards.php';
			$method = new \LWS\WOOREWARDS\PointsFlow\Methods\WooRewards();
			$this->sendJSONPoints($method, $poolName, $poolName);
		}
		\wp_die('Bad request', 400);
	}

	function exportPoints()
	{
		if (!\wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['lws_btn_nonce'] ?? '')), 'woorewards-lite' . '-export-points')) {
			\wp_die('forbidden', 403);
		}
		if( !\current_user_can('manage_options') )
			\wp_die('forbidden', 403);

		$metaPost = 'woorewards-lite' . '_from_meta';
		$metaKey = isset($_REQUEST[$metaPost]) ? \sanitize_text_field(\wp_unslash($_REQUEST[$metaPost])) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$argPost = 'woorewards-lite' . '_with_arg';
		$argValue = isset($_REQUEST[$argPost]) ? \sanitize_text_field(\wp_unslash($_REQUEST[$argPost])) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if( !$metaKey || trim($metaKey) == '—' )
			\wp_die('Bad request', 400);
		if( !$argValue || trim($argValue) == '—' )
			$argValue = false;

		require_once LWS_WOOREWARDS_INCLUDES . '/pointsflow/exportmethods.php';
		$method = \LWS\WOOREWARDS\PointsFlow\ExportMethods::get($metaKey);
		if( !$method )
			\wp_die('Not Implemented', 501);

		if( !$method->instance->supportFreeArgs() && ($args = $method->instance->getArgs()) )
		{
			if( !in_array($argValue, array_keys($args)) )
				\wp_die('Invalid Export argument for ' . esc_html($method->instance->getTitle()), 418);
		}

		$this->sendJSONPoints($method->instance, $metaKey, $argValue);
	}

	private function sendJSONPoints($method, $value, $arg=false)
	{
		$json = $method->export($value, $arg);
		\array_walk($json, function(&$row){
			if (null === $row->points || !\strlen($row->points))
				$row->points = 0;
		});

		$base = str_replace(' ', '-', \get_bloginfo('name'));
		$origin = \remove_accents(strtolower($method->getTitle()));
		$origin = preg_replace(array('/\s*-+\s*/', '/[\s_]+/'), array('-', '_'), $origin);
		$origin = \sanitize_key($origin);
		$date = \gmdate('Ymd');
		$arg = \sanitize_key($arg);
		$filename = \esc_attr("{$base}-{$origin}-{$arg}-{$date}.json");
		header("Content-disposition: attachment; filename=\"{$filename}\""); // force download
		\wp_send_json($json);
	}

}