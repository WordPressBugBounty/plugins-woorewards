<?php
namespace LWS\WOOREWARDS\PointsFlow\Methods;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Get the total points for users.
 * @warning Sumo support float values,
 * that will be rounded (floor) at import in MyRewards (points are integer). */
class MyCred extends \LWS\WOOREWARDS\PointsFlow\Methods\MetaKey
{
	/** @return (array) the json that will be send,
	 * An array with each entries as {email, points} */
	public function export($value, $arg)
	{
		return parent::export($arg, false);
	}

	public function getTitle()
	{
		return __("myCRED", 'woorewards-lite');
	}

	/** allow free user input */
	public function supportFreeArgs()
	{
		return false;
	}

	public function getArgs()
	{
		$optionId = \apply_filters('mycred_get_option_id', 'mycred_types');

		$pointTypes = \get_option($optionId, array());
		if( $pointTypes && \is_array($pointTypes) )
			return $pointTypes;
		else
			return array();
	}
}
