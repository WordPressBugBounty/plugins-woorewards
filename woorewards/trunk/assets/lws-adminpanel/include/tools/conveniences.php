<?php
namespace LWS\Adminpanel\Tools;

if( !defined( 'ABSPATH' ) ) exit();

class Conveniences
{
	/** list of order status formatted for LAC */
	static function getOrderStatusList($reset=false)
	{
		static $orderStatusList = false;
		if (false === $orderStatusList || $reset) {
			if (\function_exists('\wc_get_order_statuses'))
			{
				$orderStatusList = array();
				foreach (\wc_get_order_statuses() as $value => $label)
				{
					if (substr($value, 0, 3) == 'wc-')
						$value = substr($value, 3);
					$orderStatusList[] = array('value' => $value, 'label' => $label);
				}
			}
			else
			{
				$orderStatusList = array(
					array('value' => 'pending', 'label' => __("Pending payment", 'lws-adminpanel')),
					array('value' => 'processing', 'label' => __("Processing", 'lws-adminpanel')),
					array('value' => 'on-hold', 'label' => __("On hold", 'lws-adminpanel')),
					array('value' => 'completed', 'label' => __("Completed", 'lws-adminpanel')),
					array('value' => 'cancelled', 'label' => __("Cancelled", 'lws-adminpanel')),
					array('value' => 'refunded', 'label' => __("Refunded", 'lws-adminpanel')),
					array('value' => 'failed', 'label' => __("Failed", 'lws-adminpanel')),
				);
			}
			$orderStatusList = \apply_filters('lws_adminpanel_order_status_list', $orderStatusList);
		}
		return $orderStatusList;
	}

	static function getWooCommerceCurrencies()
	{
		static $currenciesList = false;
		if (false === $currenciesList){
			$currenciesList = array();
			if (\function_exists('\get_woocommerce_currencies')){
				foreach (\get_woocommerce_currencies() as $value => $label)
				{
					$currenciesList[] = array('value' => $value, 'label' => $label);
				}
			}
		}
		return $currenciesList;
	}

	static function getCurrentAdminPage()
	{
		static $currentPage = false;
		if (false !== $currentPage)
			return $currentPage;
		if (isset($_REQUEST['page']) && ($currentPage = \sanitize_text_field($_REQUEST['page'])))
			return $currentPage;
		if (isset($_REQUEST['option_page']) && ($currentPage = \sanitize_text_field($_REQUEST['option_page'])))
			return $currentPage;
		return false;
	}

	/** Simulates a WooCommerce Product to return a price for multi currency plugins
	 * $price        → The price to format
	 * $calcdecimals → False : Uses WooCommerce decimals | True : Determines the number of decimals from $price
	 * $formatted    → False : Raw Price | True : Formats the price using WooCommerce
	 */
	static function getCurrencyPrice($price, $calcdecimals=false, $formatted = true)
	{
		if (\class_exists('\WC_Product')) {
			$product = new \WC_Product();
			$product->set_regular_price($price);
			$amount = $product->get_regular_price();
		} else {
			$amount = $price;
		}
		$amount = \apply_filters('wcml_raw_price_amount', $amount); // use its own filter since it cannot do anything easy or like the easer

		if ($formatted) {
			if ($calcdecimals) {
				if ((int)$amount == $amount) {
					$dec = 0;
				} else {
					$dec = strlen($amount) - strrpos($amount, '.') - 1;
					if (\function_exists('\wc_get_price_decimals')) {
						$dec = \max($dec, \wc_get_price_decimals());
					}
				}
				$dec = \apply_filters('lws_adminpanel_currency_price_decimals', $dec, $amount, $price, true);
				if (\function_exists('\wc_price'))
					$amount = \wc_price($amount, array('decimals' => $dec));
				else
					$amount = \number_format_i18n($amount, $dec);
			} elseif (\function_exists('\wc_price')) {
				$amount = \wc_price($amount);
			} else {
				$dec = \apply_filters('lws_adminpanel_currency_price_decimals', 2, $amount, $price, false);
				$amount = \number_format_i18n($amount, $dec);
			}
		}
		return $amount;
	}

	/** Provided for convenience.
	 * @return (string) the current page url.
	 * @param $args (array of key(string) => value(string)) arguments that will be append to url before it is returned. */
	public static function getCurrentPageUrl($args=array())
	{
		$protocol = 'http://';
		if( (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1)) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') )
			$protocol = 'https://';

		$url = ($protocol . $_SERVER['HTTP_HOST'] . \add_query_arg($args, false));
		return $url;
	}

	/** $return the link that leads to current page without any unnecessary arguments. */
	public static function getCurrentPermalink($fallbackOnCurrentQuery=false)
	{
		if (\is_home()) {
			return \home_url();
		}
		if (\is_singular()) {
			return \get_permalink();
		}
		if (\is_search()) {
			if ($fallbackOnCurrentQuery)
				return \add_query_arg('s', \get_query_var('s'), \home_url());
			else
				return \home_url();
		}
		if (\is_date()) {
			if ($fallbackOnCurrentQuery)
				return \add_query_arg(array(
					'second'   => \get_query_var('second'),
					'minute'   => \get_query_var('minute'),
					'hour'     => \get_query_var('hour'),
					'day'      => \get_query_var('day'),
					'monthnum' => \get_query_var('monthnum'),
					'year'     => \get_query_var('year'),
					'm'        => \get_query_var('m'),
					'w'        => \get_query_var('w'),
				), \home_url());
			else
				return \home_url();
		}
		if (\is_feed()) {
			if ($fallbackOnCurrentQuery)
				return \add_query_arg('feed', \get_query_var('feed'), \home_url());
			else
				return \home_url();
		}

		$objId = \get_queried_object_id();
		if ($objId) {
			if (\is_author()) {
				// author archive page
				$url = \get_author_posts_url($objId);
				if ($url && !\is_wp_error($url))
					return $url;
			}
			if (\is_archive()) {
				// categories, tags and other taxonmies list
				$url = \get_term_link($objId);
				if ($url && !\is_wp_error($url))
					return $url;
			}
		}

		if (function_exists('\is_woocommerce') && \is_woocommerce()) {
			// wc bypass standard page flow for some of them
			if (\is_shop())
				$url = \wc_get_page_permalink('shop');
			if ($url && !\is_wp_error($url))
				return $url;
		}

		if ($fallbackOnCurrentQuery)
			return self::getCurrentPageUrl();
		else
			return \home_url();
	}

	/** Convert between bases.
	* @param   string      $number     The number to convert
	* @param   int         $frombase   Numeric base of the number to convert
	* @param   int         $tobase     destination base or 0 if a map is used (default is biggest base possible with $map)
	* @param   string      $map        The alphabet to use (default is [0-9a-zA-Z_-]; means base 64)
	* @return  string|false            Converted number or FALSE on error
	* @author  Geoffray Warnants */
	static function rebaseNumber($number, $frombase, $tobase=false, $map=false)
	{
		if (!$map)
			$map = implode('',array_merge(range(0,9),range('a','z'),range('A','Z'), array('-', '_')));
		if (false === $tobase)
			$tobase = strlen($map);
		if ($frombase<2 || ($tobase==0 && ($tobase=strlen($map))<2) || $tobase<2)
			return false;

		// conversion en base 10 si nécessaire
		if ($frombase != 10) {
			$number = ($frombase <= 16) ? strtolower($number) : (string)$number;
			$map_base = substr($map,0,$frombase);
			$decimal = 0;
			for ($i=0, $n=strlen($number); $i<$n; $i++) {
				$decimal += strpos($map_base,$number[$i]) * pow($frombase,($n-$i-1));
			}
		} else {
			$decimal = $number;
		}
		// conversion en $tobase si nécessaire
		if ($tobase != 10) {
			$map_base = substr($map,0,$tobase);
			$tobase = strlen($map_base);
			$result = '';
			while ($decimal >= $tobase) {
				$result = $map_base[intval($decimal%$tobase)].$result;
				$decimal /= $tobase;
			}
			return $map_base[intval($decimal)].$result;
		}
		return $decimal;
	}

	/** generate a random gift card code */
	public static function randString($length = 8)
	{
		$characters       = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString     = '';
		for( $i = 0; $i < $length; $i++ ) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}

	/** To ease boolean argument reading from user.
	 * Understand yes, no, on, off, true, false, numeric value and empty string.
	 * Empty string is false.
	 * @param $arg (string) a human meaning of true (case insensitive)
	 * @return bool */
	public static function argIsTrue($arg)
	{
		if( !$arg )
			return false;
		if( true === $arg )
			return true;
		if( \is_numeric($arg) )
			return (0 != \intval($arg));
		$low = \strtolower($arg);
		if( 'of' == \substr($low, 0, 2) )
			return false;
		return \in_array(\substr($low, 0, 1), array('y', 't', 'o'));
	}

	/**	Default return (int) $userId or id from cart/order if provided.
	 *	@param $userId (int|false) default value
	 *	@param $orderOrCart (\WC_Order|\WC_Cart|false) */
	public static function getCustomerId($userId, $orderOrCart=false)
	{
		$original = $userId;
		if ($orderOrCart) {
			if (\is_a($orderOrCart, 'WC_Order')) {
				$userId = $orderOrCart->get_customer_id('edit');
				if (!$userId) {
					$user = \get_user_by('email', $orderOrCart->get_billing_email());
					$userId = ($user && $user->exists()) ? $user->ID : 0;
				}
			} elseif (\is_a($orderOrCart, 'WC_Cart')) {
				$customer = $orderOrCart->get_customer();
				if ($customer) {
					$email = $customer->get_billing_email();
					if ($email) {
						$user = \get_user_by('email', $email);
						$userId = ($user && $user->exists()) ? $user->ID : 0;
					}
				}
			}
		}
		return \apply_filters('lws_adminpanel_get_customer_id', $userId, $orderOrCart, $original);
	}

	/**	Default return (\WP_User|false) $user or WP_User instance from cart/order if provided.
	 *	@param $userId (\WP_User|false) default value
	 *	@param $orderOrCart (\WC_Order|\WC_Cart|false) */
	public static function getCustomer($user=false, $orderOrCart=false)
	{
		$original = $user;
		if ($orderOrCart) {
			if (\is_a($orderOrCart, 'WC_Order')) {
				$userId = $orderOrCart->get_customer_id();
				if ($userId) {
					if (!$user || ($user->ID != $userId))
						$user = \get_user_by('ID', $userId);
				} else {
					$email = $orderOrCart->get_billing_email();
					if (!$user || ($user->user_email != $email))
						$user = \get_user_by('email', $email);
				}
			} elseif (\is_a($orderOrCart, 'WC_Cart')) {
				$customer = $orderOrCart->get_customer();
				if ($customer) {
					$email = $customer->get_billing_email();
					if ($email && (!$user || ($user->user_email != $email))) {
						$user = \get_user_by('email', $email);
					}
				}
			}
		}
		return \apply_filters('lws_adminpanel_get_customer', ($user && $user->exists()) ? $user : false, $orderOrCart, $original);
	}

	public static function htmlToPlain($body)
	{
		static $toDelPattern = array(
			'@<head[^>]*?>.*?</head>@siu',
			'@<style[^>]*?>.*?</style>@siu',
			'@<script[^>]*?.*?</script>@siu',
			'@<object[^>]*?.*?</object>@siu',
			'@<embed[^>]*?.*?</embed>@siu',
			'@<noscript[^>]*?.*?</noscript>@siu',
			'@<noembed[^>]*?.*?</noembed>@siu'
		);
		$body = \preg_replace($toDelPattern, '', $body);

		static $replace = array(
			"<br" => "\n<br",
			"</p>" => "</p>\n\n",
			"</td>" => "</td>\t",
			"</tr>" => "</tr>\n",
			"<table" => "\n<table",
			"</thead>" => "</thead>\n",
			"</tbody>" => "</tbody>\n",
			"</table>" => "</table>\n",
		);
		$body = \str_replace(\array_keys($replace), \array_values($replace), $body);
		$body = \trim(\wp_kses($body, array()));

		static $redondant = array("/\t+/", '/ +/', "/(\n[ \t]*\n[ \t]*)+/", "/\n[ \t]*/");
		static $single = array("\t", ' ', "\n\n", "\n");
		$body = \html_entity_decode(\preg_replace($redondant, $single, $body));
		return $body ? $body : '';
	}

	/**	Is WooCommerce installed and activated.
	 *	Could be sure only after hook 'plugins_loaded'.
	 *	@return bool is WooCommerce installed and activated. */
	public static function isWC()
	{
		return \function_exists('wc');
	}

	/**	Is WooCommerce HPOS enabled. */
	public static function isHPOS()
	{
		if (\class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
			return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		} else {
			return false;
		}
	}

	/** WC HPOS. Only for conveniency and optimization.
	 *	Sometime, you do not need to load a whole WC_Order
	 *	only to read/write a single meta.
	 *	Be carefull about HPOS, since we bypass the cache here,
	 *	data can be not sync.
	 *	@param $orderId (int|\WC_Order) only for consistency with update, get the id if an object is provided. */
	static public function getOrderMeta($orderId, string $key='', bool $single=false)
	{
		if (\is_object($orderId)) {
			$orderId = $orderId->get_id();
		}
		if (self::isHPOS()) {
			global $wpdb;
			if ($single) {
				return \maybe_unserialize($wpdb->get_var($wpdb->prepare(
					"SELECT `meta_value` FROM `{$wpdb->prefix}wc_orders_meta` WHERE `order_id`=%d AND meta_key=%s",
					$orderId, $key
				)));
			} else {
				if ($key) {
					$col = $wpdb->get_col($wpdb->prepare(
						"SELECT `meta_value` FROM `{$wpdb->prefix}wc_orders_meta` WHERE `order_id`=%d AND meta_key=%s",
						$orderId, $key
					));
				} else {
					$col = $wpdb->get_col($wpdb->prepare(
						"SELECT `meta_value` FROM `{$wpdb->prefix}wc_orders_meta` WHERE `order_id`=%d",
						$orderId
					));
				}
				if ($col)
					$col = \array_map('\maybe_unserialize', $col);
				return $col;
			}
		} else {
			return \get_post_meta($orderId, $key, $single);
		}
	}

	/** WC HPOS. Only for conveniency and optimization.
	 *	Sometime, you do not need to load a whole WC_Order
	 *	only to read/write a single meta.
	 *	Be carefull about HPOS, since we bypass the cache here,
	 *	data can be not sync.
	 *	@param $orderId (int|\WC_Order) if an order instance, local meta will be updated too, but order is never saved here. */
	static public function updateOrderMeta($orderId, string $metaKey, $metaValue, $prevValue='')
	{
		if (\is_object($orderId)) {
			if ($prevValue)
				$orderId->delete_meta_data_value($metaKey, $prevValue);
			$orderId->update_meta_data($metaKey, $metaValue);
			$orderId = $orderId->get_id();
		}

		if (self::isHPOS()) {
			global $wpdb;
			$where = array(
				array('order_id', $orderId, '%d'),
				array('meta_key', $metaKey, '%s'),
			);
			if ($prevValue) {
				$where[] = array('meta_value', \maybe_serialize($prevValue), '%s');
			}

			$clause = \implode(' AND ', \array_map(function($c) {
				return "`{$c[0]}`={$c[2]}";
			}, $where));
			// phpcs:ignore WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$exists = (int)$wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders_meta WHERE {$clause}",
				\array_column($where, 1)
			));
			if ($exists) {
				return $wpdb->update(
					$wpdb->prefix . 'wc_orders_meta', array(
						'meta_value' => \maybe_serialize($metaValue),
					), \array_column($where, 1, 0), '%s', \array_column($where, 2, 0)
				);
			} else {
				return $wpdb->insert(
					$wpdb->prefix . 'wc_orders_meta', array(
						'order_id'   => $orderId,
						'meta_key'   => $metaKey,
						'meta_value' => \maybe_serialize($metaValue),
					), array(
						'%d', '%s', '%s',
					)
				);
			}
		} else {
			return update_post_meta($orderId, $metaKey, $metaValue, $prevValue);
		}
	}

	/** Some content editors enjoy adding not visible characters inside shortcode attributes.
	 *	Like word breaker html tag <wbr> */
	static public function sanitizeAttr($text, $toArray=false, $sep=',')
	{
		if (\is_string($text)) {
			$text = \preg_replace('@<wbr\s*/?>@i', '', $text);
			if ($toArray) {
				$text = \array_filter(\array_map('\trim', \explode($sep, $text)));
			}
		} elseif (\is_array($text)) {
			foreach ($text as $i => $t) {
				$text[$i] = self::sanitizeAttr($t, $toArray, $sep);
			}
		}
		return $text;
	}


	/** implode array, decorated with html.
	 *	* use a 'tag' entry to specify the a dom element (could include dom arguments) like 'p class="test"'
	 *		to surround the whole array.
	 *	* use a 'join' to specify a separator as '</br>'
	 *	* use a 'cast' entry to specify a default dom element for first level children.
	 *	* use a 'wp' to add a gutenberg surrounding tag over the whole bloc (do not put the wp: prefix in the value).
	 *	Default dom element is a <p> if 'tag' is not specified
	 *	Special cases:
	 *	* UL children are deployed as LI.
	 *	* By default, LI children are embeded in a <span>, if several children, the first is in a <strong>. */
	static function array2html(array $descr, $default='')
	{
		$gutenberg = false;
		if( isset($descr['wp']) ){
			$gutenberg = $descr['wp'];
			unset($descr['wp']);
		}

		$bal = $default;
		if( isset($descr['tag']) ){
			$bal = $descr['tag'];
			unset($descr['tag']);
		}
		$bal = explode(' ', $bal, 2);
		$tag = strtolower($bal[0]);
		$args = count($bal) > 1 ? (' '.$bal[1]) : '';

		$join = "\n";
		if( isset($descr['join']) ){
			$join = $descr['join'];
			unset($descr['join']);
		}

		if( 'li' == $tag )
		{
			$span = count($descr) > 1 ? 'strong' : 'span';
			foreach( $descr as $index => $item ){
				if( !\is_array($item) )
					$descr[$index] = array('tag' => $span, $item);
				$span = 'span';
			}
		}
		else if( 'ul' == $tag && !isset($descr['cast']) )
		{
			$descr['cast'] = 'li';
		}

		$cast = false;
		if( isset($descr['cast']) ){
			$cast = $descr['cast'];
			unset($descr['cast']);
		}

		foreach( $descr as $index => $item ){
			if( false !== $cast ){
				if( !\is_array($item) )
					$item = array('tag'=>$cast, $item);
				else if( !isset($item['tag']) )
					$item['tag'] = $cast;
			}
			if( \is_array($item) ){
				$descr[$index] = self::array2html($item, $default);
			}
		}

		$html = implode($join, $descr);
		if( $tag )
			$html = sprintf("<{$tag}{$args}>%s</{$tag}>", $html);
		if ($gutenberg)
			$html = sprintf("<!-- wp:%s -->\n%s<!-- /wp:%s -->\n\n", $gutenberg, $html, $gutenberg);
		return $html;
	}

	static public function getCurrentURL()
	{
		if (\is_multisite()) {
			$parts = \parse_url(\home_url());
			$uri   = ($parts['scheme'] . '://' . $parts['host']);
			if (isset($parts['port']) && $parts['port']) $uri .= (':' . $parts['port']);
			return $uri . \add_query_arg([], false);
		} else {
			return \home_url(\add_query_arg([], false));
		}
	}

	/** Provided to manage multisite with subdirs.
	 *	Most of the time, this method do the same as home_url, but
	 *	subdir is included in home_url AND add_query_arg([], false)
	 *	then we cannot use them together in multisite with subdirs as usual.
	 *	@return string same as home_url() but without the subdir in multisite cases.
	 */
	static public function getRootURL($path='', $scheme = null)
	{
		$orig_scheme = $scheme;
		$url = \get_option('home');
		if (!\in_array($scheme, array('http', 'https', 'relative'), true)) {
			$scheme = \is_ssl() ? 'https' : \parse_url($url, PHP_URL_SCHEME);
		}
		$url = \set_url_scheme($url, $scheme);

		if (\is_multisite()) {
			$parts = \parse_url($url);
			$url   = ($parts['scheme'] . '://' . $parts['host']);
			if (isset($parts['port']) && $parts['port']) $url .= (':' . $parts['port']);
		}

		if ($path && \is_string($path)) {
			$url .= ('/' . \ltrim($path, '/'));
		}
		return \apply_filters('home_url', $url, $path, $orig_scheme, null);
	}

	static public function isCartUseBlocs()
	{
		if (!\function_exists('wc_get_page_id')) return false;
		$pageId = \wc_get_page_id('cart');
		if (!$pageId) return false;
		if (!\class_exists('\WC_Blocks_Utils')) return false;
		return \WC_Blocks_Utils::has_block_in_page($pageId, 'woocommerce/cart');
	}

	static public function isCheckoutUseBlocs()
	{
		if (!\function_exists('wc_get_page_id')) return false;
		$pageId = \wc_get_page_id('checkout');
		if (!$pageId) return false;
		if (!\class_exists('\WC_Blocks_Utils')) return false;
		return \WC_Blocks_Utils::has_block_in_page($pageId, 'woocommerce/checkout');
	}

	static public function addCartNotice($content, $level='info')
	{
		$first = \strtolower(\substr($level, 0, 1));
		if ('s' == $first) {
			\LWS\Adminpanel\Internal\WC\CartNotice::addSuccess($content);
		} else if ('w' == $first || 'e' == $first) {
			\LWS\Adminpanel\Internal\WC\CartNotice::addWarning($content);
		} else {
			\LWS\Adminpanel\Internal\WC\CartNotice::addInfo($content);
		}
	}

	/** If $hook already passed, call $callable immediatly,
	 *	else register it to trigger with the given $priority. */
	static public function addGreadyHook(string $hook, callable $callable, int $priority = 10)
	{
		if (\did_action($hook))
			\call_user_func($callable);
		else
			\add_action($hook, $callable, $priority);
	}
}