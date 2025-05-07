<?php

namespace LWS\WOOREWARDS;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Conveniences Class for shared functions*/
class Conveniences
{
	public static function install()
	{
		$me = &self::instance();
		\add_filter('lws_woorewards_get_pools_by_args', array($me, 'getPrefabPool'), 20, 2); //Lowest priority than WooRewards Pro
	}

	/** @return object singleton instance */
	static function &instance()
	{
		static $_inst = false;
		if (false === $_inst)
			$_inst = new self();
		return $_inst;
	}

	/** prevent outside instanciation */
	protected function __construct()
	{
	}

	/** Test if the content should be displayed or not
	 *	@param $stepVersion → Version after which the legacy content is not displayed anymore
	 *	@return (bool) if we assume current install should use legacy feature. */
	function isLegacyShown($stepVersion)
	{
		static $installVers = false;
		if (false === $installVers)
			$installVers = \get_option('lws_woorewards_install_version', '0');
		return (!$installVers || \version_compare($installVers, $stepVersion, '<'));
	}

	/** Returns the Prefab Pool if it exists
	 *	Hooked to 'lws_woorewards_get_pools_by_args' */
	function getPrefabPool($pools, $atts)
	{
		if (false === $pools) {
			$pools = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array(
				'numberposts' => 1,
				'meta_query'  => array(
					array(
						'key'     => 'wre_pool_prefab',
						'value'   => 'yes',
						'compare' => 'LIKE'
					),
					array(
						'key'     => 'wre_pool_type',
						'value'   => \LWS\WOOREWARDS\Core\Pool::T_STANDARD,
						'compare' => 'LIKE'
					)
				),
				'deep' => true,
			));

			if (!(isset($atts['force']) && $atts['force']))
				$pools = $pools->filterByUserCan(\get_current_user_id());
		}
		return $pools;
	}

	/* Get Available WooCommerce coupons for the provided user */
	public function getCoupons($userId, $where=false)
	{
		$user = \get_user_by('ID', $userId);
		if (!$user->user_email) return [];

		global $wpdb;
		$query = \LWS\Adminpanel\Tools\Request::from($wpdb->posts, 'p');
		$query->select(['p.ID', 'p.post_content', 'p.post_title', 'p.post_excerpt', 'MAX(e.meta_value) AS expiry_date']);
		$query->group('p.ID');
		$query->innerJoin($wpdb->postmeta, 'm', ["p.ID = m.post_id AND m.meta_key='customer_email'"]);
		$query->leftJoin($wpdb->postmeta, 'l', ["l.post_id = m.post_id AND l.meta_key='usage_limit'"]);
		$query->leftJoin($wpdb->postmeta, 'u', ["u.post_id = m.post_id AND u.meta_key='usage_count'"]);
		$query->leftJoin($wpdb->postmeta, 'e', ["e.post_id = m.post_id AND e.meta_key='date_expires'"]);
		$query->where([
			"m.meta_value = %s",
			"post_type = 'shop_coupon'",
			"post_status = 'publish'",
			"(e.meta_value is NULL OR e.meta_value = '' OR e.meta_value >= %s)",
			"(u.meta_value < l.meta_value OR u.meta_value IS NULL OR l.meta_value IS NULL OR l.meta_value=0)",
		]);
		$query->arg(\serialize([$user->user_email]));
		$query->arg(\strtotime(\date('Y-m-d')));
		if ($where) {
			$query->where($where);
		}

		$result = $query->getResults(OBJECT_K);
		if (!$result) return [];

		$ids = implode(",", array_map('intval', array_keys($result)));
		$query = <<<EOT
			SELECT p.ID, v.meta_value AS coupon_amount, f.meta_value AS wr_prod_ids, o.meta_value AS product_ids, w.meta_value AS discount_type
			FROM {$wpdb->posts} as p
			LEFT JOIN {$wpdb->postmeta} as w ON p.ID = w.post_id AND w.meta_key='discount_type'
			LEFT JOIN {$wpdb->postmeta} as v ON p.ID = v.post_id AND v.meta_key='coupon_amount'
			LEFT JOIN {$wpdb->postmeta} as o ON p.ID = o.post_id AND o.meta_key='product_ids'
			LEFT JOIN {$wpdb->postmeta} as f ON p.ID = f.post_id AND f.meta_key='woorewards_product_list'
			WHERE p.ID IN ({$ids})
EOT;
		$sub = $wpdb->get_results($query, OBJECT_K);
		foreach ($sub as $id => $info) {
			foreach ($info as $k => $v)
				$result[$id]->$k = $v;
		}
		return $result;
	}

	/**	Avoid overstock WC_Order::add_order_note and pack them in our own metabox.
	 *	As WC, comment the order.
	 *	@param $order (\WC_Order|int)
	 *	@param $note (string) the message
	 *	@param $source mixed (\LWS\WOOREWARDS\Core\Pool|string|false) the pool, the stack id or any relevant origin
	 *	@return integer the new comment id or false on error. */
	public static function addOrderNote($order, $note, $source=false)
	{
		return \LWS\WOOREWARDS\Core\OrderNote::add($order, $note, $source);
	}

	/** @param $user (int|\WP_User|string) user Id or instance, a string is assumed as user email.
	 *	@param $exceptOrderId (false|int|[int]) ignore given order id.
	 *	@param $source (string) a caller reference (for information purpose only).
	 *	@param $extendedEmail (bool|string) look not only in billing email but associated user current email too. Can be a email string to test.
	 *	@return (int) */
	public static function getOrderCount($user, $exceptOrderId=false, $source=false, $extendedEmail=false)
	{
		if (!$user) {
			if ($extendedEmail && \is_string($extendedEmail))
				$user = $extendedEmail;
			else
				return 0;
		}

		$userId = 0;
		$email  = '';
		if (\is_object($user)) {
			$userId = (int)$user->ID;
			$email = $user->user_email;
		} elseif (\is_numeric($user)) {
			$userId = \intval($user);
			$user = \get_user_by('ID', $userId);
			if ($user && $user->exists())
				$email = $user->user_email;
		} else { // assume a string email
			$email = $user;
		}
		$transient = $userId . $email;

		if ($extendedEmail) {
			if (\is_string($extendedEmail) && \strtolower($email) != \strtolower($extendedEmail)) {
				$transient .= ('-' . $extendedEmail);
			} else {
				$transient .= '-=';
				$extendedEmail = $email;
			}
		}

		$isListOfExceptOrderId = false;
		if ($exceptOrderId) {
			if (\is_array($exceptOrderId)) {
				$isListOfExceptOrderId = true;
				$exceptOrderId = \array_map('\intval', $exceptOrderId);
				\sort($exceptOrderId, SORT_NUMERIC);
				$exceptOrderId = \implode(',', $exceptOrderId);
				$transient .= ('_' . $exceptOrderId);
			} else {
				$exceptOrderId = \intval($exceptOrderId);
				$transient .= ('_' . $exceptOrderId);
			}
		}

		static $cache = array();
		if (isset($cache[$transient])) {
			return $cache[$transient];
		}

		global $wpdb;
		if (\LWS\Adminpanel\Tools\Conveniences::isHPOS()) {
			// order has his owr table
			$query = \LWS\Adminpanel\Tools\Request::from($wpdb->prefix . 'wc_orders', 'p');
			$query->where("p.type='shop_order'");
			$query->select('COUNT(p.id)');

			$where = array();
			if ($userId || $extendedEmail) {
				if ($userId) {
					$where[] = 'p.customer_id = %d';
					$query->arg($userId);
				}
				if ($extendedEmail) {
					$query->leftJoin($wpdb->users, 'u', "p.customer_id=u.ID");
					$where[] = 'u.user_email = %s';
					$query->arg($extendedEmail);
					if (\is_string($extendedEmail) && $email != $extendedEmail) {
						$where[] = 'u.user_email = %s';
						$query->arg($email);
					}
				}
			}
			if ($email) {
				$where[] = 'p.billing_email = %s';
				$query->arg($email);
				if ($extendedEmail && \is_string($extendedEmail) && $email != $extendedEmail) {
					$where[] = 'p.billing_email = %s';
					$query->arg($extendedEmail);
				}
			}
			if (count($where) > 1) {
				$where['condition'] = 'OR';
				$query->where($where);
			} elseif ($where) {
				$query->where($where[0]);
			}

			$status = \apply_filters('lws_woorewards_ignored_order_status_for_count', array(), $source);
			if ($status) {
				if (count($status) > 1) {
					$status = \implode("','", \array_map(function($s) {
						return \esc_sql('wc-' . $s);
					}, $status));
					$query->where(sprintf("p.status NOT IN ('%s')", $status));
				} else {
					$status = \reset($status);
					$query->where('p.status <> %s')->arg('wc-' . $status);
				}
			}

			if ($exceptOrderId) {
				if ($isListOfExceptOrderId) {
					$query->where(sprintf('p.id NOT IN (%s)'), $exceptOrderId);
				} else {
					$query->where('p.id <> %d')->arg($exceptOrderId);
				}
			}

			return $cache[$transient] = (int)$query->getVar();
		} else {
			// order in WP_Post
			$union = array();

			if ($userId || $extendedEmail) {
				$query = \LWS\Adminpanel\Tools\Request::from($wpdb->postmeta, 'c');
				$query->select('c.post_id as order_id');
				$query->where("c.meta_key='_customer_user'");

				$where = array();
				if ($userId) {
					$where[] = 'c.meta_value = %d';
					$query->arg($userId);
				}
				if ($extendedEmail) {
					$query->leftJoin($wpdb->users, 'u', "c.meta_value=u.ID");
					$where[] = 'u.user_email = %s';
					$query->arg($extendedEmail);
					if (\is_string($extendedEmail) && $email != $extendedEmail) {
						$where[] = 'u.user_email = %s';
						$query->arg($email);
					}
				}
				if (count($where) > 1) {
					$where['condition'] = 'OR';
					$query->where($where);
				} elseif ($where) {
					$query->where($where[0]);
				}
				$union[] = $query->toString();
			}
			if ($email) {
				$query = \LWS\Adminpanel\Tools\Request::from($wpdb->postmeta, 'm');
				$query->select('m.post_id as order_id');
				$query->where("m.meta_key='_billing_email'");
				if ($extendedEmail && \is_string($extendedEmail) && $email != $extendedEmail) {
					$query->where('(m.meta_value = %s OR m.meta_value = %s)');
					$query->arg($email)->arg($extendedEmail);
				} else {
					$query->where('m.meta_value = %s')->arg($email);
				}
				$union[] = $query->toString();
			}

			$wOrder = array(
				"p.post_type='shop_order'",
			);
			$status = \apply_filters('lws_woorewards_ignored_order_status_for_count', array(), $source);
			if ($status) {
				if (count($status) > 1) {
					$status = \implode("','", \array_map(function($s) {
						return \esc_sql('wc-' . $s);
					}, $status));
					$wOrder[] = sprintf("p.post_status NOT IN ('%s')", $status);
				} else {
					$status = \reset($status);
					$wOrder[] = sprintf("p.post_status <> '%s'", \esc_sql('wc-' . $status));
				}
			}

			if ($exceptOrderId) {
				if ($isListOfExceptOrderId) {
					$wOrder[] = sprintf('p.ID NOT IN (%s)', $exceptOrderId);
				} else {
					$wOrder[] = sprintf('p.ID <> %d', (int)$exceptOrderId);
				}
			}

			$wOrder = \implode(' AND ', $wOrder);
			if ($union) {
				$union  = \implode("\nUNION\n", $union);
				$query = <<<EOT
SELECT COUNT(p.ID) FROM (
{$union}
) as a
INNER JOIN {$wpdb->posts} as p ON p.ID=a.order_id AND {$wOrder}
EOT;
			} else {
				$query = <<<EOT
SELECT COUNT(p.ID) FROM {$wpdb->posts} as p
WHERE {$wOrder}
EOT;
			}
			return $cache[$transient] = (int)$wpdb->get_var($query);
		}
	}


	/** @return array order status (as in database status field)
	 *	indicating a processed order. */
	static public function getOrderDoneStatus(): array
	{
		static $list = null;
		if (null === $list) {
			// WC_Order like status values
			$paid = \wc_get_is_paid_statuses();
			// Database like status values
			$all = \wc_get_order_statuses();
			// get first list but as database like format
			foreach ($paid as $index => $status) {
				$prefixed = 'wc-' . $status;
				if (isset($all[$prefixed])) $paid[$index] = $prefixed;
			}
			$paid[] = 'wc-refunded';
			$list = \apply_filters('lws_woorewards_product_ordered_status_list', $paid);
		}
		return $list;
	}
}
