<?php
if( !defined( 'WP_UNINSTALL_PLUGIN' ) ) exit();
@include_once dirname(__FILE__) . '/modules/woorewards-pro/uninstall.php';

\wp_clear_scheduled_hook('lws_woorewards_daily_event');
\delete_site_option('lws-license-key-woorewards');

$cap = 'manage_rewards';
foreach( array('administrator', 'shop_manager') as $slug )
{
	$role = \get_role($slug);
	if( !empty($role) && $role->has_cap($cap) )
		$role->remove_cap($cap);
}
