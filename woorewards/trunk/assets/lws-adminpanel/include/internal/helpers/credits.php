<?php
if( !defined( 'ABSPATH' ) ) exit();

/** @deprecated moved out of common framework */
function lws_require_activation($file, $depracated=false, $adminPageId='', $uuid='')
{
	$ret = \apply_filters('lws_manager_instance', false);
	return $ret ? $ret->instance->test($file, $adminPageId, $uuid) : false;
}

/** @deprecated moved out of common framework */
function lws_plugin($file, $adminPageId, $uuid, $def=false, $targetPage=false)
{
	$ret = \apply_filters('lws_manager_instance', false);
	if ($ret)
		$ret->instance->install($file, $adminPageId, $uuid, $def, $targetPage);
}

/** @deprecated moved out of common framework */
function lws_addon($file, $masterSlug, $addonUuid, $def=false)
{
	$ret = \apply_filters('lws_manager_instance', false);
	if ($ret)
		$ret->instance->add($file, $masterSlug, $addonUuid, $def);
}

/** Obsolete, just ignored but declaration kept for backward compatibility. */
function lws_register_update($arg1, $arg2='', $arg3='', $arg4=false)
{
}

/** Obsolete, just ignored but declaration kept for backward compatibility. */
function lws_extension_showcase($arg1, $arg2='', $arg3='', $arg4='')
{
}
