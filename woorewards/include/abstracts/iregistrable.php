<?php
namespace LWS\WOOREWARDS\Abstracts;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** The class must provide function to register it. */
interface IRegistrable
{
	/**	Declare a new kind of Registrable.
	 *	@param $classname (string) the class to instanciate.
	 *	@param $filepath (string) full path to class declaration file to include at need.
	 *	@param $unregister (bool) default: false, to remove a Registrable.
	 *	@param $typeOverride (false|string) default:false, to override an existant Registrable set the original classname @see getClass.
	 **/
	static public function register($classname, $filepath, $unregister=false, $typeOverride=false);
	static public function getRegistered();
	static public function getRegisteredByName($name);
	/** @return (string) class name.
	 *	Usually return \get_class($this).
	 *	But in case the Registrable override another one, it should return the original classname. */
	function getClassname();
}
