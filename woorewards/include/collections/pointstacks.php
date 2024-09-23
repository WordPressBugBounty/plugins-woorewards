<?php
namespace LWS\WOOREWARDS\Collections;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOREWARDS_INCLUDES . '/core/pointstack.php';

/** A collection of PointStack implementation. */
class PointStacks
{
	/** @return \LWS\WOOREWARDS\Core\PointStack implementation instance. */
	function create($name, $userId, $source=null)
	{
		$ps = \apply_filters('lws_woorewards_collections_pointstacks_create', false, $name, $userId, $source);
		if( empty($ps) )
			$ps = new \LWS\WOOREWARDS\Core\PointStack($name, $userId);
		return $ps;
	}

	static function instanciate()
	{
		return new self();
	}
}
