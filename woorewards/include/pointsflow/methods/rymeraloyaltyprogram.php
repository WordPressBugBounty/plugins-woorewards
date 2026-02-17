<?php

namespace LWS\WOOREWARDS\PointsFlow\Methods;

// don't call the file directly
if (!defined('ABSPATH')) exit();
require_once LWS_WOOREWARDS_INCLUDES . '/pointsflow/methods/metakey.php';

class RymeraLoyaltyProgram extends \LWS\WOOREWARDS\PointsFlow\Methods\MetaKey
{
	/** @return (array) the json that will be send,
	 * An array with each entries as {email, points} */
	public function export($value, $arg)
	{
		return parent::export('_acfw_loyalprog_user_total_points', $arg);
	}

	/** @return (string) human readable name */
	public function getTitle()
	{
		return __("Loyalty Program by Rymera", 'woorewards-lite');
	}
}
