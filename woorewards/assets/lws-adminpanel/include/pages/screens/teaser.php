<?php
namespace LWS\Adminpanel\Pages\Screens;
if( !defined( 'ABSPATH' ) ) exit();


/** Ask LongWatchStudio website for a page content.
 *	Must declare an array index 'teaser' => true */
class Teaser extends \LWS\Adminpanel\Pages\Page
{
	public $uuid = false;
	
	public function __construct($uniquePluginId)
	{
		$this->uuid = $uniquePluginId;
	}

	public function content()
	{
		\wp_enqueue_style('lws-admin-page');
		echo '<div class="lws-admin-page">';
		echo '<div class="teaser-div">';

		$key = ('lws_teaser_' . $this->uuid);
		$force = isset($_GET['force-check']) ? \boolval($_GET['force-check']) : false;
		$lastCheck = \get_option('lws_last_teaser_' . $this->uuid, 0);
		if ($lastCheck && (\time() - $lastCheck) < MINUTE_IN_SECONDS)
			$force = false;

		$transiant = $force ? false : \get_site_transient($key);
		if (false === $transiant) {
			$lastCheck = \time();
			\update_option('lws_last_teaser_' . $this->uuid, $lastCheck);

			// call remote
			$args = array(
				'lws_teaser' => 'page',
				'key'        => $this->uuid,
				'domain'     => $this->getSiteUrl(),
			);
			$request = \add_query_arg($args, $this->getRemoteUrl());

			global $wp_version;
			$agent = ('WordPress/' . $wp_version . '; ' . get_bloginfo('url'));
			$data = \wp_remote_get($request, array('timeout' => 60, 'user-agent'  => $agent));

			if (!$this->isValidResponse($data)) {
				$transiant = sprintf('<h1>%s</h1>', __("No data loaded.", 'lws-adminpanel'));
				\set_site_transient($key, $transiant, MINUTE_IN_SECONDS * 5);
			} else {
				$transiant = \wp_remote_retrieve_body($data);
				\set_site_transient($key, $transiant, DAY_IN_SECONDS * 5);
			}
		}

		echo $transiant;

		echo '</div>';
		if ($lastCheck && \function_exists('wp_timezone')) {
			$lastCheck += \date_create('now', \wp_timezone())->getOffset();
		}
		echo sprintf(
			'<div id="lws-teaser-force-link"><small>%s <a href="%s">%s</a></small></div>',
			sprintf(
				__('Last checked on %1$s at %2$s.'),
				\date_i18n(\get_option('date_format', 'F j, Y'), $lastCheck),
				\date_i18n(\get_option('time_format', 'g:i a'), $lastCheck)
			),
			\add_query_arg(array('force-check' => 1)),
			__("Check again.", 'lws-adminpanel')
		);
		echo '</div>';
	}

	protected function isValidResponse($data)
	{
		if (!$data)
			return false;
		if (\is_wp_error($data))
			return false;
		if (!\in_array(\intval($data['response']['code']), array(200, 301, 302)))
			return false;
		$headers = \wp_remote_retrieve_headers($data);
		if (!$headers || !isset($headers['content-type']) || false === strpos(strtolower($headers['content-type']), 'text/html'))
			return false;
		$body = \wp_remote_retrieve_body($data);
		if (!$body)
			return false;
		//~ $validator = '<!-- lws_teaser -->';
		//~ if (substr($body, 0, strlen($validator)) != $validator)
			//~ return false;
		return true;
	}

	function getGlobalOption($name, $default=false)
	{
		return \get_network_option(\get_main_network_id(), $name ,$default);
	}

	function getSiteUrl()
	{
		if (defined('LWS_SITEURL') && LWS_SITEURL)
			$url = LWS_SITEURL;
		elseif (defined('WP_SITEURL') && WP_SITEURL)
			$url = WP_SITEURL;
		else
			$url = $this->getGlobalOption('siteurl');
		return \preg_replace('@^https?://@i', '', $url);
	}

	function getRemoteUrl($path='')
	{
		$url = 'https://plugins.longwatchstudio.com/';
		if( defined('LWS_DEV') && LWS_DEV )
			$url = \is_string(LWS_DEV) ? LWS_DEV : \site_url();

		if( $path && \is_string($path) )
			$url = (\rtrim($url, '/') . '/' . \ltrim($path, '/'));

		$url = \add_query_arg(array('lang'=>\get_locale()), $url);
		return $url;
	}

	protected function prepare()
	{}

	public function isTeaser()
	{
		return true;
	}

	public function getType()
	{
		return 'teaser';
	}

	public function allowSubmit()
	{
		return false;
	}

	/** @return bool */
	public function getGroups()
	{
		return false;
	}
}
