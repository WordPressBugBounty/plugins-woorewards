<?php
namespace LWS\WOOREWARDS\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage WooRewards relevant order notes. */
class OrderNote
{
	const TYPE = 'woorewards_note';

	/** read our comments */
	public static function get($orderId)
	{
		if ($orderId) {
			// let wp return our comments
			\remove_filter('comments_clauses', array(__CLASS__, 'exclude'), 10);
			// really get the comments
			$comments = \get_comments(array(
				'type'    => self::TYPE,
				'status'  => 'approve',
				'orderby' => 'comment_ID',
				'post_id' => $orderId,
			));
			// restore our filter
			\add_filter('comments_clauses', array(__CLASS__, 'exclude'), 10, 1);
			return $comments;
		} else {
			return array();
		}
	}

	/**	Avoid overstock WC_Order::add_order_note and pack them in our own metabox.
	 *	As WC, comment the order.
	 *	@param $order (\WC_Order|int)
	 *	@param $note (string) the message
	 *	@param $source (Pool|string|false) the pool, the stack id or any relevant origin
	 *	@return integer the new comment id or false on error. */
	public static function add($order, $note, $source=false)
	{
		$commentId = false;
		if ($order && !\is_object($order))
			$order = \wc_get_order($order);
		if (!$order)
			return $commentId;

		$author = __("MyRewards", 'woorewards-lite');
		if ($domain = \strtolower(\site_url()))
			$domain = \str_replace('www.', '', \parse_url($domain, PHP_URL_HOST));

		$commentId = \wp_insert_comment(apply_filters(
			'lws_woorewards_add_order_note',
			array(
				'comment_post_ID'      => $order->get_id(),
				'comment_author'       => $author,
				'comment_author_email' => \sanitize_email(sprintf('%s@%s', $author, $domain ? $domain : 'noreply.com')),
				'comment_author_url'   => '',
				'comment_content'      => $note,
				'comment_agent'        => 'WooRewards',
				'comment_type'         => self::TYPE,
				'comment_parent'       => 0,
				'comment_approved'     => 1,
			), $order, $note, $source
		));

		\do_action('lws_woorewards_new_order_note', $commentId, $order, $source);
		return $commentId;
	}

	public static function install()
	{
		\add_filter('comments_clauses', array(__CLASS__, 'exclude'), 10, 1);
		\add_filter('comment_feed_where', array(__CLASS__, 'exclude'), 10, 1);
		\add_filter('wp_count_comments', array(__CLASS__, 'otherCount'), 9, 2); // before WC and set its transient before him
	}

	/** filter out our comments from wp query */
	public static function exclude($clauses)
	{
		if (\is_array($clauses)) {
			if ($clauses['where'])
				$clauses['where'] .= ' AND';
			$clauses['where'] .= sprintf(" comment_type != '%s' ", self::TYPE);
		} else {
			if ($clauses)
				$clauses .= ' AND';
			$clauses .= sprintf(" comment_type != '%s' ", self::TYPE);
		}
		return $clauses;
	}

	/** WC has same problem and have to exclude its comments from the count,
	 *	but it does it such a it prevent us to do the same easily.
	 *	Set the WC transient to avoid its limited computing. */
	public static function otherCount($stats, $postId)
	{
		if (0 === $postId) {
			$stats = \get_transient('wc_count_comments');
			if (!$stats) {
				global $wpdb;

				// Unlike everyone else, lets have a type filter for exclusion
				$excluded = \implode("', '", \array_map('\esc_sql', \apply_filters('lws_excluded_comment_types_from_comment_count', array(
					self::TYPE,
					'action_log',
					'order_note',
					'webhook_delivery',
				))));
				// exclude post_type
				$excPost = \implode("', '", \array_map('\esc_sql', \apply_filters('lws_excluded_post_types_from_comment_count', array(
					'product',
				))));

				$request = \LWS\Adminpanel\Tools\Request::from($wpdb->comments, 'c');
				$request->select('c.comment_approved, COUNT(*) as `cc`');
				$request->where("c.comment_type NOT IN ('{$excluded}')");
				$request->group('c.comment_approved');
				if ($excPost) {
					$request->leftJoin($wpdb->posts, 'p', 'c.comment_post_ID = p.ID');
					$request->where("p.post_type NOT IN ('{$excPost}')");
				}

				$counts = $request->getResults(OBJECT_K);
				$stats = array(
					'total_comments' => 0,
					'all'            => 0,
					'moderated'      => 0,
					'approved'       => 0,
					'spam'           => 0,
					'trash'          => 0,
					'post-trashed'   => 0,
				);

				if ($counts) {
					$mapping = array(
						'hold' => 'moderated',
						'0'    => 'moderated',
						'1'    => 'approved',
					);

					foreach ($counts as $status => $c) {
						if (isset($mapping[$status]))
							$status = $mapping[$status];
						$stats[$status] = $c->cc;

						if (!\in_array($status, array('post-trashed', 'trash'))) {
							$stats['total_comments'] += $c->cc;
							if ('spam' != $status)
								$stats['all'] += $c->cc;
						}
					}
				}

				$stats = (object) $stats;
				// set already the WC transient since it does not let us exclude our comments
				\set_transient('wc_count_comments', $stats);
			}
		}
		return $stats; // let WP do its own, it is already good
	}
}