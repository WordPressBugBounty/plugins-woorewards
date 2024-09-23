<?php
namespace LWS\WOOREWARDS\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Add points to a set of user. */
class UsersPointsBulkAction extends \LWS\Adminpanel\EditList\Action
{
	private $key = 'points_add';

	function input()
	{
		$help = esc_attr(__("Enter '=42' to set a total of 42 points to selected users instead of adding it to current amounts.", 'woorewards-lite'));
		$str = "<label for='{$this->key}' title='$help'>".__("Add/Subtract points", 'woorewards-lite')."</label>";
		$str .= " <input type='text' pattern='[0-9]+' name='{$this->key}' id='{$this->key}' size='4' class='lws-input lws-ignore-confirm'/>";
		return $str;
	}

	function apply($user_ids)
	{
		if (!isset($_POST[$this->key]))
			return false;
		$points = \sanitize_text_field($_POST[$this->key]);
		$set = false;
		if( substr($points, 0, 1) == '=' )
		{
			$points = substr($points, 1);
			$set = true;
		}
		if( !is_numeric($points) )
			return false;
		else if( $set )
			$points = max(0, \intval($points));

		// load the default pool
		$pools = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array(
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
			)
		));

		if( empty($pool = $pools->get(0)) )
		{
			\lws_admin_add_notice_once('lws-wre-users-points-add', __("Cannot load default loyalty system.", 'woorewards-lite'), array('level'=>'error'));
			return false;
		}
		else
		{
			$count = 0;
			$reason = __("Commercial operation", 'woorewards-lite');
			foreach( $user_ids as $user_id )
			{
				if( $set )
					$count += $pool->setPoints($user_id, $points, $reason)->tryUnlock($user_id);
				else if( $points < 0 )
					$count += $pool->usePoints($user_id, \absint($points), $reason)->tryUnlock($user_id);
				else
					$count += $pool->addPoints($user_id, $points, $reason)->tryUnlock($user_id);
			}

			$msg = _n("Points added to user.", "Points added to users.", count($user_ids), 'woorewards-lite');
			if( $count > 0 )
				$msg .= '<br/>' . _n("A reward was generated.", "Few rewards generated.", $count, 'woorewards-lite');
			\lws_admin_add_notice_once('lws-wre-users-points-add', $msg, array('level'=>'success'));
		}
		return true;
	}

}

?>