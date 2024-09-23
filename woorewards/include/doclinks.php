<?php

namespace LWS\WOOREWARDS;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** All Documentation Links*/
class DocLinks
{
	const FALLBACK = 'home';

	public static $doclinks = array(
		'adv-features'   => "https://plugins.longwatchstudio.com/kb/combining-systems/",
		'customers'      => "https://plugins.longwatchstudio.com/kb/customers-management/",
		'disp-points'    => "https://plugins.longwatchstudio.com/kb/wr-sc-points-information/",
		'home'           => "https://plugins.longwatchstudio.com/kbtopic/wr/",
		'points'         => "https://plugins.longwatchstudio.com/kb/spend-money/",
		'pools'          => "https://plugins.longwatchstudio.com/kb/how-it-works/",
		'referral'       => "https://plugins.longwatchstudio.com/kb/referral-sponsorship/",
		'rewards'        => "https://plugins.longwatchstudio.com/kb/points-on-cart/",
		'past-orders'    => "https://plugins.longwatchstudio.com/kb/process-past-orders/",
	);

	static function get($index=false, $escape = true)
	{
		if (!($index && isset(self::$doclinks[$index])))
			$index = self::FALLBACK;
		if ($escape)
			return \esc_attr(self::$doclinks[$index]);
		else
			return self::$doclinks[$index];
	}

	static function toFields()
	{
		$fields = array();
		$prefix = (__CLASS__ . ':');
		foreach (self::$doclinks as $key => $url) {
			$fields[$key] = array(
				'id'    => $prefix . $key,
				'title' => $key,
				'type'  => 'custom',
				'extra' => array(
					'gizmo'   => true,
					'content' => sprintf('<a href="%s" target="_blank">%s</a>', \esc_attr($url), $url),
				),
			);
		}
		return $fields;
	}
}