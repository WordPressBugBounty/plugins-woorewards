<?php
namespace LWS\WOOREWARDS\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage common feature for Events about order. */
trait T_SponsorshipOrigin
{
	protected $sponsorship = false;
	protected $origins = null;

	public function getAvailableOrigins()
	{
		return array(
			array('value' => 'referral',  'label' => __("Referral link", 'woorewards-lite')),
		);
	}

	public function getOrigins()
	{
		return (null !== $this->origins) ? $this->origins : array();
	}

	public function setOrigins($origins)
	{
		if( !is_array($origins) )
			$origins = @json_decode(@base64_decode($origins));
		if( is_array($origins) )
			$this->origins = $origins;
		return $this;
	}

	/** Guess against current visitor data */
	public function isValidCurrentSponsorship()
	{
		if (!$this->getOrigins())
			return true;

		$sponsorship = new \LWS\WOOREWARDS\Core\Sponsorship();
		$this->sponsorship = $sponsorship->getCurrentUsers();
		if (!$this->sponsorship->sponsor_id)
			return false;

		return $this->isValidOrigin($this->sponsorship->origin);
	}

	/** @param $order (\WC_Order) */
	public function isValidOriginByOrder($order, $guestAllowed=false)
	{
		if (!$this->getOrigins())
			return true;

		$sponsorship = new \LWS\WOOREWARDS\Core\Sponsorship();
		$this->sponsorship = $sponsorship->getUsersFromOrder($order, $guestAllowed);
		if (!$this->sponsorship->sponsor_id)
			return false;

		return $this->isValidOrigin($this->sponsorship->origin);
	}

	public function isValidOrigin($origin=false)
	{
		if( $origins = $this->getOrigins() ) // no origins set means no restriction
		{
			if( !$origin ) // default origin is sponsor
				$origin = 'sponsor';

			// complete generic restrictions
			if( \in_array('sponsor', $origins) )
				$origins[] = 'manual';

			if( !\in_array($origin, $origins) )
				return false;
		}
		return true;
	}

	protected function filterSponsorshipData($data=array(), $prefix='')
	{
		$data[$prefix . 'origins'] = base64_encode(json_encode($this->getOrigins()));
		return $data;
	}

	protected function filterSponsorshipForm($content='', $prefix='', $context='editlist', $column=10)
	{
		$form = $this->getFieldsetBegin($column, __("Constraints", 'woorewards-lite'));

		// Origin restriction
		$label   = __("Referral Origin", 'woorewards-lite');
		$form .= "<div class='lws-{$context}-opt-title label'>{$label}</div>";
		$form .= "<div class='lws-{$context}-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix . 'origins', array(
			'comprehensive' => true,
			'source'        => $this->getAvailableOrigins(),
			'class'         => 'above',
		));
		$form .= "</div>";

		$form .= $this->getFieldsetEnd($column);
		return $content . $form;
	}

	/** @return bool */
	protected function optSponsorshipSubmit($prefix='', $form=array(), $source='editlist')
	{
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix . 'origins' => array('S'),
			),
			'defaults' => array(
				$prefix . 'origins' => array(),
			),
			'labels'   => array(
				$prefix . 'origins' => __("Origin restrictions", 'woorewards-lite'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$this->setOrigins($values['values'][$prefix . 'origins']);
		return true;
	}

	protected function optSponsorshipFromPost(\WP_Post $post)
	{
		$this->setOrigins(\get_post_meta($post->ID, 'woorewards_sponsorship_origin', true));
		return $this;
	}

	protected function optSponsorshipSave($id)
	{
		\update_post_meta($id, 'woorewards_sponsorship_origin', $this->getOrigins());
		return $this;
	}
}
