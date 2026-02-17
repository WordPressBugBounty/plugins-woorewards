<?php
namespace LWS\WOOREWARDS\Compatibility;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

class UltimateMember
{
	public static function install()
	{
		$me = new self();
		\add_filter('lws_adminpanel_field_shortcode_options', array($me, 'addDesc'), 10, 3);
		\add_filter('lws_woorewards_shortcode_current_user_id', array($me, 'getCurrentUserId'), 10, 3);
	}

	public function getCurrentUserId($userId=false, $atts=array(), $shortcode='')
	{
		if ($atts && \is_array($atts)) {
			if (isset($atts['um-public']) && \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['um-public'])) {
				if (\function_exists('um_get_requested_user'))
					return \um_get_requested_user();
				else
					return false;
			}
			if (isset($atts['user_id']))
				return $atts['user_id'];
		}
		return $userId;
	}

	// Add shortcode options description if Ultimate Member is active
	public function addDesc($options, $shortcode, $flags)
	{
		if (\in_array('current_user_id', $flags)) {
			$options['um-public'] = array(
				'option' => 'um-public',
				'desc' => __("Set the option to true (um-public='true') to make the information public on Ultimate Members profile pages", 'woorewards-lite'),
			);
		}
		return $options;
	}
}