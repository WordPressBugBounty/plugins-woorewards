<?php
namespace LWS\WOOREWARDS\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();


/** Sponsor Earns points for the first time sponsored places an order. */
class SponsoredOrder extends \LWS\WOOREWARDS\Abstracts\Event
{
	use \LWS\WOOREWARDS\Events\T_SponsorshipOrigin;

	protected $firstOrderOnly = true;
	protected $eventPriority = 101;

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-shop',
			'short' => __("The customer will earn points when a person he referred placed an order. You can restrict this to the first referee order.", 'woorewards-lite'),
			'help'  => __("This method will only reward the Referrer, not the Referee", 'woorewards-lite'),
		));
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix . 'event_priority']   = $this->getEventPriority();
		$data[$prefix . 'first_order_only'] = $this->isFirstOrderOnly() ? 'on' : '';
		$data = $this->filterSponsorshipData($data, $prefix);
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);

		// just hidden since we do not want to reset the value on save
		$noPri = (\get_option('lws_woorewards_show_loading_order_and_priority') ? '' : ' style="display: none;"');
		$label = __("Priority", 'woorewards-lite');
		$tooltip = __("Customer orders will run by ascending priority value.", 'woorewards-lite');
		$str = <<<EOT
		<div class='field-help'{$noPri}>$tooltip</div>
		<div class='lws-$context-opt-title label'{$noPri}>$label<div class='bt-field-help'>?</div></div>
		<div class='lws-$context-opt-input value'{$noPri}>
			<input type='text' id='{$prefix}event_priority' name='{$prefix}event_priority' placeholder='10' size='5' />
		</div>
EOT;
		$phb0 = $this->getFieldsetPlaceholder(false, 0);
		$form = str_replace($phb0, $str.$phb0, $form);

		$form .= $this->getFieldsetBegin(2, __("Options", 'woorewards-lite'));

		// First Order Only
		$label   = _x("First order only", "Referral Order Event", 'woorewards-lite');
		$tooltip = __("If checked, only the first order placed by each referee will give points.", 'woorewards-lite');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'first_order_only', array(
			'id'      => $prefix . 'first_order_only',
			'layout'  => 'toggle',
		));
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>$toggle</div>";

		$form .= $this->getFieldsetEnd(2);
		return $this->filterSponsorshipForm($form, $prefix, $context, 10);
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix . 'first_order_only' => 's',
				$prefix . 'event_priority'   => 'd',
			),
			'defaults' => array(
				$prefix . 'first_order_only' => '',
				$prefix . 'event_priority'   => $this->getEventPriority(),
			),
			'labels'   => array(
				$prefix . 'first_order_only' => __("First order only", 'woorewards-lite'),
				$prefix . 'event_priority'   => __("Event priority", 'woorewards-lite'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if (true !== $valid)
			return $valid;
		$valid = $this->optSponsorshipSubmit($prefix, $form, $source);
		if (true !== $valid)
			return $valid;

		$this->setFirstOrderOnly($values['values'][$prefix.'first_order_only']);
		$this->setEventPriority  ($values['values'][$prefix.'event_priority']);
		return $valid;
	}

	public function setFirstOrderOnly($yes=false)
	{
		$this->firstOrderOnly = boolval($yes);
		return $this;
	}

	function isFirstOrderOnly()
	{
		return $this->firstOrderOnly;
	}

	function isGuestAllowed()
	{
		return false;
	}

	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	protected function _fromPost(\WP_Post $post)
	{
		$firstOnly = \get_post_meta($post->ID, 'wre_event_first_order_only', false); // backward compatibility, option introduced on 3.6.0
		$this->setFirstOrderOnly( empty($firstOnly) ? 'on' : reset($firstOnly) );
		$this->setEventPriority($this->getSinglePostMeta($post->ID, 'wre_event_priority', $this->getEventPriority()));
		$this->optSponsorshipFromPost($post);
		return $this;
	}

	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_first_order_only', $this->isFirstOrderOnly() ? 'on' : '');
		\update_post_meta($id, 'wre_event_priority', $this->getEventPriority());
		$this->optSponsorshipSave($id);
		return $this;
	}

	function getDescription($context='backend')
	{
		$descr = parent::getDescription($context);
		if( $this->isFirstOrderOnly() )
		{
			$descr .= __(" (first order only)", 'woorewards-lite');
		}
		return $descr;
	}

	/** @return string a human readable type for UI */
	public function getDisplayType()
	{
		return _x("Referee orders", "getDisplayType", 'woorewards-lite');
	}

	function getEventPriority()
	{
		return \intval($this->eventPriority);
	}

	public function setEventPriority($priority)
	{
		$this->eventPriority = \intval($priority);
		return $this;
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_filter('lws_woorewards_wc_order_done_'.$this->getPoolName(), array($this, 'orderDone'), $this->getEventPriority());
	}

	function orderDone($order, $sponsorshipInfo=false)
	{
		if ($sponsorshipInfo) {
			$this->sponsorship = $sponsorshipInfo;
		} else {
			$sponsorship = new \LWS\WOOREWARDS\Core\Sponsorship();
			$this->sponsorship = $sponsorship->getUsersFromOrder($order->order, $this->isGuestAllowed());
		}

		if( !$this->sponsorship->sponsor_id )
			return $order;
		if( !$this->isValidOrigin($this->sponsorship->origin) )
			return $order;

		if( $this->isFirstOrderOnly() )
		{
			$c = \LWS\WOOREWARDS\Conveniences::getOrderCount(
				$this->sponsorship->sponsored_id,
				$order->order->get_id(),
				'sponsorship',
				$this->sponsorship->sponsored_email
			);
			if ($c)
				return $order;
		}

		if( $points = \apply_filters('trigger_'.$this->getType(), 1, $this, $order->order) )
		{
			$reason = \LWS\WOOREWARDS\Core\Trace::byOrder($order->order)
				->setProvider($order->order->get_customer_id('edit'))
				->setReason(
					array(
					"Referred friend %s order #%s completed",
						$this->sponsorship->sponsored_email,
						$order->order->get_order_number()
					),
					'woorewards-lite'
				);

			$this->addPoint(array(
				'user'    => $this->sponsorship->sponsor_id,
				'sponsee' => $this->sponsorship->sponsored_id,
				'order'   => $order->order,
			), $reason, $points);
		}
		return $order;
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("Referred friend %s order #%s completed", 'woorewards-lite');
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'woocommerce' => __("WooCommerce", 'woorewards-lite'),
			'sponsorship' => __("Available for referees", 'woorewards-lite')
		));
	}
}