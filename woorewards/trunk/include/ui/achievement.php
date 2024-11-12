<?php
namespace LWS\WOOREWARDS\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage achievenent display. */
class Achievement
{
	function __construct()
	{
		\add_action('lws_woorewards_achievement_push', array(__CLASS__, 'push'), 10, 1);

		\add_action('admin_footer', array($this, 'footer'));
		\add_action('wp_footer', array($this, 'footer'));
		\add_action('admin_enqueue_scripts', array($this , 'scripts'), 99999); // late to let anybody going first
		\add_action('wp_enqueue_scripts', array($this , 'scripts'), 99999); // late to let anybody going first
	}

	/**	Register an achievement for display on the page (backend or frontend).
	 *	@param $options (array) with:
	 *	* 'title' (string) At least a title is required for custom achievement.
	 *	* 'message' (string) optional, display a custom achievement with that message.
	 *	* 'image' (url) Achievement icon, if no url given, a default image is picked.
	 *	*	'user' (int) recipient user id.
	 *	* 'origin' (mixed, optional) source of the achievement.
	 *	* 'badge_id' (int) source of the achievement but as an ID.
	 *  * 'visible' (bool) optional, default true. If false, the achievement is logged only but not shown to the user.
	 *	@param $user (int) recipient user id, if false, use $options, if still undefined, use \get_current_user_id().
	 *
	 *	If recipient user cannot be defined (not set, and no one logged in),
	 *	the message will be displayed to the first logged out guy that load a page, whoever he is. */
	static function push($options=array(), $user=false)
	{
		if( $options && is_array($options) && isset($options['title']) )
		{
			$data = array(
				'user_id'    => self::getUserId($user, $options),
				'title'      => $options['title'],
			);
			if( isset($options['message']) )
				$data['message'] = $options['message'];
			if( isset($options['image']) )
				$data['image'] = $options['image'];
			if( isset($options['background']) )
				$data['background'] = $options['background'];
			if( isset($options['origin']) )
				$data['origin'] = is_array($options['origin']) ? serialize($options['origin']) : $options['origin'];
			if( isset($options['badge_id']) )
				$data['badge_id'] = intval($options['badge_id']);
			if( isset($options['visible']) )
				$data['popup'] = boolval($options['visible']) ? 1 : 0;

			global $wpdb;
			$wpdb->insert(
				self::tableName(),
				$data
			);
		}
	}

	static function tableName()
	{
		global $wpdb;
		return $wpdb->prefix.'lws_wr_achieved_log';
	}

	static protected function getUserId($user=false, $options=array())
	{
		if( !empty($user = intval($user)) )
			return $user;
		if( is_array($options) && isset($options['user']) )
		{
			if( !empty($user = intval($options['user'])) )
				return $user;
		}
		return intval(\get_current_user_id());
	}

	protected function get()
	{
		global $wpdb;
		$table = self::tableName();
		return $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$table} WHERE `display` IS NULL AND `popup`=1 AND user_id=%d ORDER BY id ASC",
			self::getUserId()
		));
	}

	protected function count()
	{
		global $wpdb;
		$table = self::tableName();
		return $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(id) FROM {$table} WHERE `display` IS NULL AND `popup`=1 AND user_id=%d",
			self::getUserId()
		));
	}

	protected function clear()
	{
		global $wpdb;
		$table = self::tableName();
		if( \get_option('lws_woorewards_achievement_log_delete_on_clear', false) )
			$wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE `display` IS NULL AND user_id=%d", self::getUserId()));
		else
			$wpdb->query($wpdb->prepare("UPDATE {$table} SET display=CURRENT_TIMESTAMP WHERE `display` IS NULL AND user_id=%d", self::getUserId()));
	}

	protected function getScriptDependencies()
	{
		return array('jquery', 'jquery-ui-core','jquery-ui-widget');
	}

	public function scripts()
	{
		\wp_register_script('lws-wre-badge', LWS_WOOREWARDS_JS.'/badge.js', $this->getScriptDependencies(), LWS_WOOREWARDS_VERSION, true);
		$key = 'lws_wre_badge_css';
		$url = \apply_filters($key, LWS_WOOREWARDS_CSS.'/badge.css');
		\wp_register_style($key, $url, array(), LWS_WOOREWARDS_VERSION);
	}

	/** Display achievement DOM. */
	function footer()
	{
		static $first = true;
		$achievements = $this->get();
		while( !empty($achievements) )
		{
			$this->clear();

			foreach( $achievements as $options )
			{
				$options = \apply_filters('lws_woorewards_achievement_options', $options);
				if( $options )
				{
					$title = esc_attr($options->title);
					$image = esc_attr($options->image ? $options->image : LWS_WOOREWARDS_IMG.'/badge-reward.png');
					$background = esc_attr($options->background ? $options->background : LWS_WOOREWARDS_IMG.'/badge-star.png');
					echo "<div class='lws_wre_badge' data-title='$title' data-imageurl='$image' data-bgurl='$background'>$options->message</div>";
					if( $first )
					{
						$first = false;
						foreach( $this->getScriptDependencies() as $inc )
							\wp_enqueue_script($inc);
						\wp_enqueue_script('lws-wre-badge');
						\wp_enqueue_style('lws_wre_badge_css');
					}
				}
			}

			// cause unlock an achievement can produce achievement
			$achievements = $this->get();
		}
	}

}