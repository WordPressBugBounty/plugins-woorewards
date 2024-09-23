<?php

namespace LWS\WOOREWARDS\Ui\Editlists;

// don't call the file directly
if (!defined('ABSPATH')) {
	exit();
}

/** Manage WooRewards Sponsorships. */
class Sponsorships extends \LWS\Adminpanel\EditList\Source
{
	const ROW_ID = 'sponsorship_id';

	public static function instanciate()
	{
		return \lws_editlist(
			'wrspons',
			self::ROW_ID,
			new self(),
			\LWS\Adminpanel\EditList::FIX,
			array(
				'user' => new \LWS\Adminpanel\EditList\FilterSimpleField('u', __('Search Users...', 'woorewards-lite')),
			)
		);
	}

	public function labels()
	{
		$labels = array(
			'sponsor_name' => array(__("Referrer", 'woorewards-lite'), 'auto'),
			'sponsee_name' => array(__("Referee", 'woorewards-lite'), 'auto'),
		);
		return $labels;
	}

	public function read($limit)
	{
		$request = $this->getSponsorships();
		$request->select("meta.umeta_id as sponsorship_id, meta.user_id as sponsee_id, meta.meta_value as sponsor_id");
		$request->order("sponsor.user_login");
		$request->rowLimit($limit);

		$sponsorships = $request->getResults(ARRAY_A);
		if (!$sponsorships)
			return array();

		foreach ($sponsorships as &$sponsorship) {
			$this->shapeSponsor($sponsorship);
		}
		return $sponsorships;
	}

	public function getSponsorships()
	{
		global $wpdb;
		$request = \LWS\Adminpanel\Tools\Request::from($wpdb->usermeta, 'meta');
		$request->innerJoin($wpdb->users, 'sponsor', 'meta.meta_value=sponsor.ID');
		$request->innerJoin($wpdb->users, 'sponsee', 'meta.user_id=sponsee.ID');
		$request->where('meta.meta_key = "lws_woorewards_sponsored_by"');

		if (isset($_REQUEST['u']) && trim($_REQUEST['u'])) {
			$val = \esc_sql(\trim(\stripslashes($_REQUEST['u'])));
			$filter = array(
				'condition' => 'OR',
				"sponsor.user_login LIKE '%{$val}%'",
				"sponsor.user_email LIKE '%{$val}%'",
				"sponsor.user_nicename LIKE '%{$val}%'",
				"sponsor.display_name LIKE '%{$val}%'",
				"sponsee.user_login LIKE '%{$val}%'",
				"sponsee.user_email LIKE '%{$val}%'",
				"sponsee.user_nicename LIKE '%{$val}%'",
				"sponsee.display_name LIKE '%{$val}%'"
			);
			if ($id = intval($val)) {
				$filter[] = sprintf("u.ID = %d", $id);
			}
			$request->where($filter);
		}
		return $request;
	}

	public function shapeSponsor(&$sponsorship)
	{
		$sponsor = \get_user_by('ID', $sponsorship['sponsor_id']);
		$sponsorship['sponsor_id'] = $sponsor->ID;
		$sponsorship['sponsor_name'] = <<<EOT
		<div class="lws-wre-sponsor-line">
			<div class="user-login">{$sponsor->user_login}</div>
			<div class="sep">-</div>
			<div class="user-name">{$sponsor->display_name}</div>
			<div class="sep">-</div>
			<div class="user-email">{$sponsor->user_email}</div>
		</div>
EOT;

		$sponsee = \get_user_by('ID', $sponsorship['sponsee_id']);
		$sponsorship['sponsee_id'] = $sponsee->ID;
		$sponsorship['sponsee_name'] = <<<EOT
		<div class="lws-wre-sponsor-line">
			<div class="user-login">{$sponsee->user_login}</div>
			<div class="sep">-</div>
			<div class="user-name">{$sponsee->display_name}</div>
			<div class="sep">-</div>
			<div class="user-email">{$sponsee->user_email}</div>
		</div>
EOT;
	}

	public function total()
	{
		$request = $this->getSponsorships();
		$request->select('COUNT(meta.umeta_id)');
		return (int)$request->getVar();
	}

	protected function getDisclamer()
	{
		return sprintf(
			__('Not available here. Please take a look at our PRO version and its free Referral addon %s!', 'woorewards-lite'),
			sprintf('<a target="_blank" href="%s">%s</a>',
				\esc_attr('https://plugins.longwatchstudio.com/product/woorewards/'),
				__("here", 'woorewards-lite')
			)
		);
	}

	public function write($row)
	{
		return \LWS\Adminpanel\EditList\UpdateResult::err($this->getDisclamer());
	}

	public function input()
	{
		return '<p>' . $this->getDisclamer() . '</p>';
	}

	public function erase($row)
	{
		return \LWS\Adminpanel\EditList\UpdateResult::err($this->getDisclamer());
	}
}