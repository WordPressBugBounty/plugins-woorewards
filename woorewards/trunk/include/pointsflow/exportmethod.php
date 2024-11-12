<?php
namespace LWS\WOOREWARDS\PointsFlow;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Point export method extends this abstract and are placed in methods/ subdir. */
abstract class ExportMethod
{
	/** @return (array) the json that will be send,
	 * An array with each entries as {email, points} */
	abstract public function export($value, $arg);

	/** @return (string) human readable name */
	abstract public function getTitle();

	/** @return (string) method identifier */
	public function getKey()
	{
		return $this->formatKey($this);
	}

	/** @return (array) additionnal argument to pass to export.
	 * array is associatvie, only the key is passed to export, value is for display only. */
	public function getArgs()
	{
		return array();
	}

	/** allow free user input.
	 * If no need of args, should return true */
	public function supportFreeArgs()
	{
		return true;
	}

	/** @return (bool) appear in method combobox */
	public function isVisible()
	{
		return true;
	}

	function formatKey($class)
	{
		if( is_object($class) )
			$class = get_class($class);
		return str_replace('\\', '_', strtolower($class));
	}

}
