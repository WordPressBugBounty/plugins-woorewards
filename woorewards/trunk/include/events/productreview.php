<?php
namespace LWS\WOOREWARDS\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points for product review.
 * That must be the first review of that customer on that product.
 * The customer must have ordered the product before. */
class ProductReview extends \LWS\WOOREWARDS\Abstracts\Event
{
	protected $purchaseRequired = true;

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-star-full',
			'short' => __("The customer will earn points for writing a review on a product.", 'woorewards-lite'),
			'help'  => __("You can restrict this method to products already purchased by the customer", 'woorewards-lite'),
		));
	}

	function isRuleSupportedCooldown() { return true; }

	public function isMaxTriggersAllowed()
	{
		return \defined('LWS_WOOREWARDS_ACTIVATED') && LWS_WOOREWARDS_ACTIVATED;
	}

	/** If additionnal info should be displayed in settings form. */
	protected function getCooldownTooltips($text)
	{
		$text .= '<br/>';
		$text .= __("Points are still given once per product. The cooldown simply limits the number of times a user can earn points for his reviews during a set period.", 'woorewards-lite');
		return $text;
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'purchase_required'] = $this->isPurchaseRequired() ? 'on' : '';
		return $data;
	}

	/** add help about how it works */
	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);

		$form .= $this->getFieldsetBegin(2, __("Options", 'woorewards-lite'));

		// hide from search and robots on/off
		$label = __("Purchase Required", 'woorewards-lite');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'purchase_required', array(
			'id'      => $prefix . 'purchase_required',
			'layout'  => 'toggle',
		));
		$tooltip = __("If checked, points are earned only if the customer already purchased the product and order status is 'Complete'.", 'woorewards-lite');
		$form .= "<div class='field-help'>{$tooltip}</div>";
		$form .= "<div class='lws-{$context}-opt-title label'>{$label}<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-{$context}-opt-input value'>{$toggle}</div>";

		$form .= $this->getFieldsetEnd(2);
		return $form;
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'purchase_required' => 's',
			),
			'defaults' => array(
				$prefix.'purchase_required' => '',
			),
			'labels'   => array(
				$prefix.'purchase_required' => __("Purchase Required", 'woorewards-lite'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setPurchaseRequired($values['values'][$prefix.'purchase_required']);
		}
		return $valid;
	}

	public function setPurchaseRequired($yes=false)
	{
		$this->purchaseRequired = boolval($yes);
		return $this;
	}

	function isPurchaseRequired()
	{
		return $this->purchaseRequired;
	}

	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	protected function _fromPost(\WP_Post $post)
	{
		$this->setPurchaseRequired(\get_post_meta($post->ID, 'wre_event_purchase_required', true));
		return $this;
	}

	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_purchase_required', $this->isPurchaseRequired() ? 'on' : '');
		return $this;
	}

	/** @return string a human readable type for UI */
	public function getDisplayType()
	{
		return _x("Product review", "getDisplayType", 'woorewards-lite');
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
//		\add_action('comment_post', array($this, 'trigger'), 10, 2);
		\add_action('wp_insert_comment', array($this, 'review'), 10, 2);
		\add_action('comment_unapproved_to_approved', array($this, 'delayedApproval'), 10, 1);
	}

	function delayedApproval($comment)
	{
		if( $this->isValid($comment, true) )
			$this->process($comment);
	}

	function review($comment_id, $comment)
	{
		if (1 != $comment->comment_approved)
			return;
		if( $this->isValid($comment) )
			$this->process($comment);
	}

	/** When a registered customer comment a product for the very first time. */
	function trigger($comment_id, $comment_approved)
	{
		if (!($comment = $this->getValidComment($comment_id, $comment_approved)))
			return;
		$this->process($comment);
	}

	protected function oncekey()
	{
		return 'lws_wre_event_review_'.$this->getId();
	}

	protected function process($comment, $force=false)
	{
		if (!$force && !$this->isCool($comment->user_id))
			return;

		if( $points = \apply_filters('trigger_'.$this->getType(), 1, $this, $comment) )
		{
			\add_user_meta($comment->user_id, $this->oncekey(), $comment->comment_post_ID, false);
			$reason = \LWS\WOOREWARDS\Core\Trace::byReason(array("Review about a product (%s)", \get_the_title($comment->comment_post_ID)), 'woorewards-lite');
			$this->addPoint(array(
					'user'    => $comment->user_id,
					'product' => $comment->comment_post_ID,
				), $reason, $points);
		}
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("Review about a product (%s)", 'woorewards-lite');
	}

	protected function isValid($comment, $delayed=false)
	{
		if( empty($comment->user_id) ) // not anonymous
			return false;
		if( in_array($comment->comment_post_ID, \get_user_meta($comment->user_id, $this->oncekey(), false)) ) // already commented by him
			return false;
		if( 'review' !== $comment->comment_type )
			return false;
		if( 'product' !== \get_post_type($comment->comment_post_ID) ) // it is a type we looking for
			return false;
		if( $this->isPurchaseRequired() && !$this->isProductOrdered($comment) )
			return false;
		if( $delayed ) // test rating exists
		{
			if( empty(\get_comment_meta($comment->comment_ID, 'rating', false)) )
				return false;
		}
		return true;
	}

	/** @return true if customer already purchase product.
	 * Order should ends to a term.
	 * So tested post_status IN ('wc-completed', 'wc-processing', 'wc-refunded')
	 * Other status (that are omited) are:
	 * * wc-pending, wc-on-hold : still running.
	 * * wc-cancelled, wc-failed : never finalised. */
	protected function isProductOrdered($comment)
	{
		global $wpdb;
		if (\LWS\Adminpanel\Tools\Conveniences::isHPOS()) {
			$sql = <<<EOT
SELECT count(*) FROM {$wpdb->prefix}wc_orders as p
INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta as m ON m.meta_key='_product_id' AND m.meta_value=%d
INNER JOIN {$wpdb->prefix}woocommerce_order_items as i ON m.order_item_id=i.order_item_id AND p.id=i.order_id
WHERE p.type='shop_order' AND p.status IN ('wc-completed', 'wc-processing', 'wc-refunded')
AND customer_id=%d
EOT;
		} else {
			$sql = <<<EOT
SELECT count(*) FROM {$wpdb->posts} as p
INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta as m ON m.meta_key='_product_id' AND m.meta_value=%d
INNER JOIN {$wpdb->prefix}woocommerce_order_items as i ON m.order_item_id=i.order_item_id AND p.ID=i.order_id
INNER JOIN {$wpdb->postmeta} as c ON c.post_id=p.ID AND c.meta_key='_customer_user' AND c.meta_value=%d
WHERE p.post_type='shop_order' AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-refunded')
EOT;
		}
		return !empty(intval($wpdb->get_var($wpdb->prepare($sql, $comment->comment_post_ID, $comment->user_id))));
	}

	/** @return (false|object{userId, productId} */
	protected function getValidComment($comment_id, $comment_approved)
	{
		if( !$comment_approved )
			return false;
		if( !(isset($_POST['rating']) && isset($_POST['comment_post_ID'])) ) // it is a review
			return false;
		if( !is_numeric($_POST['rating']) ) // it is a valid rating
			return false;
		if( empty($comment = \get_comment($comment_id, OBJECT)) ) // it is a valid comment
			return false;
		if( !$this->isValid($comment) )
			return false;

		return $comment;
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'woocommerce' => __("WooCommerce", 'woorewards-lite'),
			'site'  => __("Website", 'woorewards-lite')
		));
	}
}
