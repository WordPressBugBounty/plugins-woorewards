<?php
namespace LWS\WOOREWARDS;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** satic class to manage activation and version updates. */
class Wizard extends \LWS\Adminpanel\Wizard
{
	protected $subWizard = null;
	protected $color = null;

	protected function getColor()
	{
		if (null === $this->color) {
			$this->color = '#f54f33';
		}
		return $this->color;
	}

	protected function getLogoURL()
	{
		return LWS_WOOREWARDS_IMG . '/icon-wr.png';
	}

	protected function getTitle()
	{
		return __("Loyalty System Setup", 'woorewards-lite');
	}

	/** @return object a i_wizard implementation instance or false if user selected none. */
	protected function getOrCreateWizard()
	{
		if (null === $this->subWizard)
		{
			$this->subWizard = false;
			$data = $this->getData();
			$exists = false;
			$choice = $this->getDataValue($data, 'wchoice', false, $exists);
			if (!$choice) {
				$choice = 'standard';
				$exists = true;
			}
			if( $exists )
			{
				$this->subWizard = $this->instanciateWizard($choice);
				if( !$this->subWizard )
				{
					error_log("Unknown wizard: ".$choice);
					$this->resetData();
				}
			}
		}
		return $this->subWizard;
	}

	/** wizard file must be lower case in wizards subdir.
	 * wizard class must implement i_wizard
	 * wizard classname must be first letter upper case (and only first letter, not camelCase) */
	protected function instanciateWizard($choice)
	{
		$choice = strtolower(\sanitize_key($choice)); // sanitize remove any ../ changedir
		$path = LWS_WOOREWARDS_INCLUDES . "/wizards/{$choice}.php";
		if( \file_exists($path) )
		{
			$classname = '\LWS\WOOREWARDS\Wizards\\'.\ucfirst($choice);
			include_once($path);
			return new $classname($this);
		}
		return false;
	}

	protected function getHierarchy()
	{
		$hierarchy = array(
			//'choice',
			'ini', /// that faky step is required to avoid 'choice' being the last, since last step display submit button instead of next button.
		);
		if( $wiz = $this->getOrCreateWizard() )
		{
			// first step must be named 'ini' too
			$hierarchy = $wiz->getHierarchy();
			//$hierarchy = array_merge(array('choice'), $wiz->getHierarchy());
		}
		return $hierarchy;
	}

	protected function getStepTitle($slug)
	{
		$title = $slug;
		if( $slug == 'choice' )
			$title = __("Choose a Wizard", 'woorewards-lite');
		else if( $wiz = $this->getOrCreateWizard() )
			$title = $wiz->getStepTitle($slug);
		else if( $slug == 'ini' )
			$title = __("Settings…", 'woorewards-lite');
		return $title;
	}

	protected function getPage($slug, $mode='')
	{
		if( $slug != 'choice' && ($wiz = $this->getOrCreateWizard()) )
		{
			return $wiz->getPage($slug, $mode);
		}
		else
		{
			\wp_enqueue_script('wizard-choice', LWS_WOOREWARDS_JS.'/wizard-choice.js', array('jquery'), LWS_WOOREWARDS_VERSION, true);
			return $this->getChoicePage($mode);
		}
	}

	function isValid($step, &$submit)
	{
		if( $step != 'choice' && ($wiz = $this->getOrCreateWizard()) )
			return $wiz->isValid($step, $submit);
		return true;
	}

	protected function getChoicePage($mode='')
	{
		return array(
			'groups' => array(
				'wchoice' => array(
					'class' => 'large',
					'fields' => array(
						'wchoice' => array(
							'id'    => 'wchoice',
							'title' => '',
							'type'  => 'radiogrid', // radiogrid is specific to the wizard
							'extra' => array(
								'type' => 'wizard-choice',
								'source' => array(
									array(
										'value'=>'standard',
										'image' => LWS_WOOREWARDS_IMG . '/standard_system.png',
										'color' => '#526981',
										'label' => __("Standard System", 'woorewards-lite'),
										'descr' => __("The standard system is the most common loyalty system available. Users earn points by performing various actions. When they have enough points, they can unlock rewards such as discount coupons.", 'woorewards-lite'),
									),
									array(
										'value'=>'leveling',
										'image' => LWS_WOOREWARDS_IMG . '/leveling_system.png',
										'color' => '#999999',
										'pro-color' => '#16b9ba',
										'label' => __("Leveling System", 'woorewards-lite'),
										'descr' => __("Leveling systems works differently than standard systems. Users earn points by performing various actions. But they don't spend their points. Instead, they reach different levels and unlock all rewards set at a level when they have enough points.", 'woorewards-lite'),
										'pro-only' => 'yes',
									),
									array(
										'value'=>'event',
										'image' => LWS_WOOREWARDS_IMG . '/events.png',
										'color' => '#999999',
										'pro-color' => '#ff9a4c',
										'label' => __("Special Events", 'woorewards-lite'),
										'descr' => __("Use the special events wizard to create temporary loyalty programs for various occasions. This wizard proposes scenarios for the following events :", 'woorewards-lite')."<br/>".
											"<div class='lws-wizard-desc-grid'>".
											"<ul><li>".__("Black Friday", 'woorewards-lite')."</li>".
											"<li>" . __("Christmas", 'woorewards-lite') . "</li>" .
											"<li>" . __("Easter", 'woorewards-lite') . "</li></ul></div>",
										'pro-only' => 'yes',
									),
									array(
										'value'=>'double',
										'image' => LWS_WOOREWARDS_IMG . '/double_points.png',
										'color' => '#999999',
										'pro-color' => '#6e96b5',
										'label' => __("Double Points", 'woorewards-lite'),
										'descr' => __("Create a special event and allow customers to earn twice the points for a limited period of time. You can also choose to allow users with a special role to earn twice the points. Or both.", 'woorewards-lite'),
										'pro-only' => 'yes',
									),
									array(
										'value'=>'sponsorship',
										'image' => LWS_WOOREWARDS_IMG.'/sponsorship.png',
										'color' => '#999999',
										'pro-color' => '#59515c',
										'label' => __("Referrals", 'woorewards-lite'),
										'descr' => __("Add a points and rewards system that rewards customers for referring new people on your website. Referees also receive a reward to encourage them to subscribe and buy on your website.", 'woorewards-lite'),
										'pro-only' => 'yes',
									),
									array(
										'value'=>'anniversary',
										'image' => LWS_WOOREWARDS_IMG.'/anniversary.png',
										'color' => '#999999',
										'pro-color' => '#a4255b',
										'label' => __("Customer Birthday or Registration Anniversary", 'woorewards-lite'),
										'descr' => __("Celebrate your customers birthday or registration anniversary by sending them a discount coupon on that occasion. Really easy to set up and very appreciated by customers.", 'woorewards-lite'),
										'pro-only' => 'yes',
									),
								),
							)
						)
					)
				)
			)
		);
	}

	/** Instanciate pools, events, unlockables, etc. */
	protected function submit(&$data)
	{
		if( $wiz = $this->getOrCreateWizard() )
			return $wiz->submit($data);

		error_log("Do some magic!");
		return false;
	}
}
