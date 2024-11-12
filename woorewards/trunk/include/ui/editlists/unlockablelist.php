<?php
namespace LWS\WOOREWARDS\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Edit what can be bought with points.
 * Tips: prevent page nav with EditList::setPageDisplay(false) */
class UnlockableList extends \LWS\WOOREWARDS\Ui\Editlists\MultiFormList
{
	protected $stepInfo = false;
	protected $choices  = false;

	function labels()
	{
		$labels = array();
		if( $this->pool->getOption('type') != \LWS\WOOREWARDS\Core\Pool::T_LEVELLING )
			$labels['purchasing']  = array(__("Points cost", 'woorewards-lite'), 'max-content');
		$labels['title']       = __("Public title", 'woorewards-lite');
		$labels['description'] = __("Reward description", 'woorewards-lite');
		return \apply_filters('lws_woorewards_unlockablelist_labels', $labels);
	}

	function read($limit=null)
	{
		$unlockables = array();
		if( $this->pool->getOption('type') != \LWS\WOOREWARDS\Core\Pool::T_STANDARD )
			$this->pool->getUnlockables()->sort();
		foreach( $this->pool->getUnlockables()->asArray() as $unlockable )
		{
			$unlockables[] = $this->objectToArray($unlockable);
		}
		return $unlockables;
	}

	protected function getGroupTitles()
	{
		return array(
			array(
				'idle' 		=> __("Select a reward category", 'woorewards-lite'),
				'selected' 	=> __("Reward Category : ", 'woorewards-lite')
			),
			array(
				'idle' 		=> __("Select a Reward", 'woorewards-lite'),
				'selected' 	=> __("Reward : ", 'woorewards-lite')
			)
		);
	}

	protected function getStepInfo()
	{
		if (!$this->stepInfo) {
			$this->stepInfo = __("Reward Settings", 'woorewards-lite');
		}
		return $this->stepInfo;
	}

	private function objectToArray($item)
	{
		$descr = trim($item->getCustomDescription(false));
		return array_merge(
			array(
				self::ROW_ID  => $item->getId(), // it is important that id is first for javascript purpose
				'wre_type'    => $item->getType(),
				'purchasing'  => "<div class='lws-wr-unlockable-cost'>".$item->getCost('view')."</div>",
				'title'       => $item->getThumbnailImage('lws_wr_thumbnail_small') . "<div class='lws-wr-unlockable-title'>" . $item->getTitle() . "</div><div class='lws-wr-unlockable-id'>ID : " . $item->getId() . "</div>",
				'description' => $descr ? $descr : $item->getDescription(),
				'cost'        => $item->getCost(),
			),
			$item->getData()
		);
	}

	protected function loadChoices()
	{
		if (false === $this->choices)
		{
			$blacklist = $this->pool->getOption('blacklist');
			if( !\LWS\Adminpanel\Tools\Conveniences::isWC() )
				$blacklist = array_merge(array('woocommerce'=>'woocommerce'), is_array($blacklist)?$blacklist:array());

			$this->choices = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->create()->byCategory(
				$blacklist,
				$this->pool->getOption('whitelist'),
				$this->pool->getUnlockables()->getTypes()
			)->usort(function($a, $b){return strcmp($a->getDisplayType(), $b->getDisplayType());});
		}
		return $this->choices;
	}

	static function getChoiceCategories()
	{
		$dftIcon = 'lws-icon-present';
		return \apply_filters('lws_woorewards_system_item_type_groups', array(
			'shop_coupon'      => array('label' => _x("Coupons", "Option  Group", 'woorewards-lite'),         'descr' => __("Rewards that will generate WooCommerce Coupons",'woorewards-lite'),'color' => '#cc1d25', 'icon' => 'lws-icon-coupon'),
			'wp_user'          => array('label' => _x("User's status", "Option Group", 'woorewards-lite'),    'descr' => __("Rewards that will change the user's status",'woorewards-lite'),    'color' => '#0136a7', 'icon' => 'lws-icon-circle-09-2'),
			'woovirtualwallet' => array('label' => _x("WooVirtualWallet", "Option  Group", 'woorewards-lite'), 'descr' => __("Wallet Credit rewards",'woorewards-lite'),                         'color' => '#cd7627', 'icon' => 'lws-icon-wallet-44'),
			'woovip'           => array('label' => _x("WooVIP", "Option  Group", 'woorewards-lite'),          'descr' => __("Memberships rewards",'woorewards-lite'),                           'color' => '#c79648', 'icon' => 'lws-icon-crown'),
			'miscellaneous'    => array('label' => _x("Miscellaneous", "Option Group", 'woorewards-lite'),    'descr' => __("Other types of rewards",'woorewards-lite'),                        'color' => '#7801a7', 'icon' => 'lws-icon-gift'),
		), 'unlockable');
	}

	protected function getGroups()
	{
		return self::getChoiceCategories();
	}

	protected function getHiddenInputs()
	{
		$hiddens = parent::getHiddenInputs();
		$hiddens .= "<input type='hidden' name='grouped_title' />";
		$hiddens .= "<input type='hidden' name='cost' class='lws_wr_unlockable_master_cost' />";
		return $hiddens;
	}

	public function defaultValues()
	{
		$values = parent::defaultValues();
		$values['grouped_title'] = '';
		return $values;
	}

	function write($row)
	{
		$item = null;
		$type = (is_array($row) && isset($row['wre_type'])) ? trim($row['wre_type']) : '';
		if( is_array($row) && isset($row[self::ROW_ID]) && !empty($id = intval($row[self::ROW_ID])) )
		{
			$item = $this->pool->getUnlockables()->find($id);
			if( empty($item) )
				return new \WP_Error('404', __("The selected reward cannot be found.", 'woorewards-lite'));
			if( $type != $item->getType() )
				return new \WP_Error('403', __("The reward type cannot be changed. Delete this and create a new one instead.", 'woorewards-lite'));
		}
		else if( !empty($type) )
		{
			$item = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->create($type)->last();
			if( empty($item) )
				return new \WP_Error('404', __("The selected reward type cannot be found.", 'woorewards-lite'));
		}

		if( !empty($item) )
		{
			if( isset($_REQUEST['groupedBy']) && boolval($_REQUEST['groupedBy']) && isset($row['cost']) )
			{
				$row[$item->getDataKeyPrefix().'cost'] = $row['cost'];
			}

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
			$item = $this->pool->getUnlockables()->find($id);
			if( empty($item) )
			{
				return new \WP_Error('404', __("The selected reward cannot be found.", 'woorewards-lite'));
			}
			else
			{
				$this->pool->removeUnlockable($item);
				$item->delete();
				return true;
			}
		}
		return false;
	}
}