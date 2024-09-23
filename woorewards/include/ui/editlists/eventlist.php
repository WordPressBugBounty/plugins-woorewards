<?php
namespace LWS\WOOREWARDS\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Edit point earning amouts and the way to get them.
 * Tips: prevent page nav with EditList::setPageDisplay(false) */
class EventList extends \LWS\WOOREWARDS\Ui\Editlists\MultiFormList
{
	protected $stepInfo = false;
	protected $choices  = false;

	function labels()
	{
		$labels = array(
			'earning'     => array(__("Earned points", 'woorewards-lite'), 'max-content'),
			'title'       => __("Public title", 'woorewards-lite'),
			'description' => __("Action to perform", 'woorewards-lite')
		);
		return \apply_filters('lws_woorewards_eventlist_labels', $labels);
	}

	function read($limit=null)
	{
		$events = array();
		foreach( $this->pool->getEvents()->asArray() as $event )
		{
			$events[] = $this->objectToArray($event);
		}
		return $events;
	}

	private function objectToArray($item)
	{
		$class = 'number';
		$label = $item->getGainRaw(true);
		$earning = \esc_attr(\wp_kses($label, array()));
		if ('=' == substr($item->getGainRaw(false), 0, 1)) {
			$class = 'formula';
			$label = __("Formula", 'woorewards-lite');
		}

		return array_merge(
			array(
				self::ROW_ID  => $item->getId(), // it is important that id is first for javascript purpose
				'wre_type'    => $item->getType(),
				'earning'     => "<div class='lws-wr-event-multiplier {$class}' title='{$earning}'>{$label}</div>",
				'title'       => "<div class='lws-wr-event-title'>" . $item->getTitle() . "</div><div class='lws-wr-event-id'>ID : " . $item->getId() . "</div>",
				'description' => $item->getDescription()
			),
			$item->getData()
		);
	}

	function getPopupTitle()
	{
		return __("Action to perform to earn points", 'woorewards-lite');
	}

	protected function getStepInfo()
	{
		if (!$this->stepInfo) {
			$this->stepInfo = __("Points value and options", 'woorewards-lite');
		}
		return $this->stepInfo;
	}

	protected function getGroupTitles()
	{
		return array(
			array(
				'idle' 		=> __("Select a category", 'woorewards-lite'),
				'selected' 	=> __("Category : ", 'woorewards-lite')
			),
			array(
				'idle' 		=> __("Select an action", 'woorewards-lite'),
				'selected' 	=> __("Action : ", 'woorewards-lite')
			)
		);
	}

	protected function loadChoices()
	{
		if (false === $this->choices)
		{
			$blacklist = $this->pool->getOption('blacklist');
			if( !\LWS\Adminpanel\Tools\Conveniences::isWC() )
				$blacklist = array_merge(array('woocommerce'=>'woocommerce'), is_array($blacklist)?$blacklist:array());

			$this->choices = \LWS\WOOREWARDS\Collections\Events::instanciate()->create()->byCategory(
				$blacklist,
				$this->pool->getOption('whitelist'),
				$this->pool->getEvents()->getTypes()
			)->usort(function($a, $b){return strcmp($a->getDisplayType(), $b->getDisplayType());});
		}
		return $this->choices;
	}

	static function getChoiceCategories()
	{
		$dftIcon = 'lws-icon-c-pulse';
		return \apply_filters('lws_woorewards_system_item_type_groups', array(
			'order'            => array('label' => _x("Orders", "Option Group", 'woorewards-lite'), 'descr' => __("Customers earn points when they place an order", 'woorewards-lite'), 'color' => '#cc1d25', 'icon' => 'lws-icon-cart-2'),
			'site'             => array('label' => _x("Website", "Option Group", 'woorewards-lite'), 'descr' => __("Users earn points by registering or posting content", 'woorewards-lite'), 'color' => '#0e97af', 'icon' => 'lws-icon-home-3'),
			'social'           => array('label' => _x("Social Media", "Option Group", 'woorewards-lite'), 'descr' => __("Users earn points for sharing on social media", 'woorewards-lite'), 'color' => '#0136a7', 'icon' => 'lws-icon-network-communication'),
			'sponsorship'      => array('label' => _x("Referral", "Option Group", 'woorewards-lite'), 'descr' => __("Referrers earn points when they refer someone", 'woorewards-lite'), 'color' => '#7801a7', 'icon' => 'lws-icon-handshake'),
			'miscellaneous'    => array('label' => _x("Miscellaneous", "Option Group", 'woorewards-lite'), 'descr' => __("Users earn points for various reasons", 'woorewards-lite'), 'color' => '#a70190', 'icon' => 'lws-icon-c-pulse'),
			'woovip'           => array('label' => _x("WooVIP", "Option Group", 'woorewards-lite'), 'descr' => __("Earn points related to the WooVIP Plugin", 'woorewards-lite'), 'color' => '#c79648', 'icon' => 'lws-icon-crown'),
			'woovirtualwallet' => array('label' => _x("WooVirtualWallet", "Option Group", 'woorewards-lite'), 'descr' => __("Earn points related to the WooVirtualWallet Plugin", 'woorewards-lite'), 'color' => '#cd7627', 'icon' => $dftIcon),
		), 'event');
	}

	protected function getGroups()
	{
		return self::getChoiceCategories();
	}

	function write($row)
	{
		$item = null;
		$type = (is_array($row) && isset($row['wre_type'])) ? trim($row['wre_type']) : '';
		if( is_array($row) && isset($row[self::ROW_ID]) && !empty($id = intval($row[self::ROW_ID])) )
		{
			$item = $this->pool->getEvents()->find($id);
			if( empty($item) )
				return new \WP_Error('404', __("The selected Earning Points System cannot be found.", 'woorewards-lite'));
			if( $type != $item->getType() )
				return new \WP_Error('403', __("Earning Points System Type cannot be changed. Delete this and create a new one instead.", 'woorewards-lite'));
		}
		else if( !empty($type) )
		{
			$item = \LWS\WOOREWARDS\Collections\Events::instanciate()->create($type)->last();
			if( empty($item) )
				return new \WP_Error('404', __("The selected Earning Points System type cannot be found.", 'woorewards-lite'));
		}

		if( !empty($item) )
		{
			if( true === ($err = $item->submit($row)) )
			{
				$item->save($this->pool);
				return $this->objectToArray($item);
			}
			else
				return new \WP_Error('update', $err);
		}
		return false;
	}

	function erase($row)
	{
		if( is_array($row) && isset($row[self::ROW_ID]) && !empty($id = intval($row[self::ROW_ID])) )
		{
			$item = $this->pool->getEvents()->find($id);
			if( empty($item) )
			{
				return new \WP_Error('404', __("The selected Earning Point System cannot be found.", 'woorewards-lite'));
			}
			else
			{
				$this->pool->removeEvent($item);
				$item->delete();
				return true;
			}
		}
		return false;
	}
}