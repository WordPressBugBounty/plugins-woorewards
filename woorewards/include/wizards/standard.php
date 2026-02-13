<?php

namespace LWS\WOOREWARDS\Wizards;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** satic class to manage activation and version updates. */
class Standard extends \LWS\WOOREWARDS\Wizards\Subwizard
{
	function getHierarchy()
	{
		// first step must be named 'ini' as the faky one from wizard.php
		return array(
			'ini',
			'met',
			'rew',
			'sum',
		);
	}

	function getStepTitle($slug)
	{
		switch ($slug)
		{
			case 'ini':
				return __("Standard System", 'woorewards');
			case 'met':
				return __("Methods to earn points", 'woorewards');
			case 'rew':
				return __("Reward", 'woorewards');
			case 'sum':
				return __("Summary", 'woorewards');
		}
		return $slug;
	}

	function getPage($slug, $mode = '')
	{
		switch ($slug)
		{
			case 'ini':
				return array(
					'title' => $this->getStepTitle($slug),
					'help'  => __("Welcome to this Wizard. This tool will help you configure your first loyalty system in less than 5 minutes.", 'woorewards') . "<br/>" .
						__("This wizard is limited for MyRewards Standard.", 'woorewards') . "<br/>" .
						__("If you want to have access to all methods and rewards, please consider trying MyRewards Pro.", 'woorewards'),
					'groups' => array(
						array(
							'fields'  => array(
								array(
									'id'    => 'system_title',
									'title' => __('Loyalty system name', 'woorewards'),
									'type'  => 'text',
									'extra' => array(
										'placeholder' => __('Standard System', 'woorewards'),
										'help' => __("Name your loyalty system. If you leave it empty, it will be named automatically", 'woorewards'),
									),
								),
							)
						)
					)
				);
			case 'met':
				return array(
					'title' => $this->getStepTitle($slug),
					'help'  => __("Your customers will earn points every time they perform the actions defined here.", 'woorewards') . "<br/>" .
						__("Set points for all the methods you want and ignore the ones you don't want. You can change all these settings later.", 'woorewards') . "</br>" .
						__("Some categories are not accessible in the free version of MyRewards.", 'woorewards'),
					'groups' => array(
						array(
							'fields'  => array(
								array(
									'id'    => 'spent_earn',
									/* translators: %s: currency symbol */
									'title' => sprintf(__("Points for each %s spent", 'woorewards'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									'type'  => 'text',
									'extra' => array(
										//'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards'),
									),
								),
								array(
									'id'    => 'order_earn',
									'title' => sprintf(__("Points on order placed", 'woorewards'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									'type'  => 'text',
									'extra' => array(
										//'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards'),
									),
								),
								array(
									'id'    => 'first_order_earn',
									'title' => sprintf(__("Extra points on first order", 'woorewards'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									'type'  => 'text',
									'extra' => array(
										//'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards'),
									),
								),
								array(
									'id'    => 'product_review',
									'title' => __("Points on product review", 'woorewards'),
									'type'  => 'text',
									'extra' => array(
										'placeholder' => __('Number | Empty to ignore', 'woorewards'),
									),
								),
								array(
									'id'    => 'sponsored_spent',
									/* translators: %s: currency symbol */
									'title' => sprintf(__("Points for each %s spent by Referee", 'woorewards'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									'type'  => 'text',
									'extra' => array(
										//'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards'),
									),
								),
								array(
									'id'    => 'sponsored_order',
									'title' => sprintf(__("Points on Referee orders", 'woorewards'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									'type'  => 'text',
									'extra' => array(
										//'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards'),
									),
								),
							),
						),
					),
				);
			case 'rew':
				return array(
					'title' => $this->getStepTitle($slug),
					'help'  => __("Select the reward for your customers. The rewards types are limited in the standard version. You can change these settings later.", 'woorewards'),
					'groups' => array(
						array(
							'fields' => array(
								array(
									'id'    => 'reward',
									'title' => __("Select a reward", 'woorewards'),
									'type'  => 'radiogrid', // radiogrid is specific to the wizard
									'extra' => array(
										'type' => 'auto-cols',
										'columns' => 'repeat(auto-fit, minmax(120px, 1fr))',
										'source' => array(
											array('value' => 'pointsoncart', 'icon' => 'lws-icon lws-icon-cart-2', 'label' => __("Points on Cart", 'woorewards')),
											/* translators: %s: currency symbol */
											array('value' => 'coupon', 'icon' => 'lws-icon lws-icon-coins', 'label' => sprintf(_x("Coupon (%s)", "Coupon Unlockable", 'woorewards'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?')),
											array('value' => 'discount', 'icon' => 'lws-icon lws-icon-discount', 'label' => __("Discount (%)", 'woorewards')),
											array('value' => 'product', 'class' => 'inactive', 'icon' => 'lws-icon lws-icon-gift', 'label' => __("Free Product", 'woorewards')),
											array('value' => 'shipping', 'class' => 'inactive', 'icon' => 'lws-icon lws-icon-supply', 'label' => __("Free Shipping", 'woorewards')),
											array('value' => 'variable', 'class' => 'inactive', 'icon' => 'lws-icon lws-icon-discount', 'label' => __("Variable Discount", 'woorewards')),
											array('value' => 'badgge', 'class' => 'inactive', 'icon' => 'lws-icon lws-icon-reward', 'label' => __("Badge", 'woorewards')),
											array('value' => 'role', 'class' => 'inactive', 'icon' => 'lws-icon lws-icon-users', 'label' => __("User Role", 'woorewards')),
											array('value' => 'role', 'class' => 'inactive', 'icon' => 'lws-icon lws-icon-crown', 'label' => __("VIP Membership", 'woorewards')),
										),
										'default' => 'pointsoncart',
									),
								)
							),
						),
						array(
							'require' => array('selector' => 'input#reward', 'value' => 'pointsoncart'),
							'fields' => array(
								array(
									'id'    => 'point_value',
									/* translators: %s: currency symbol */
									'title' => sprintf(_x("Point value in %s", "Points on Cart Reward", 'woorewards'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									'type'  => 'text',
									'extra' => array(
										'placeholder' => '1',
										'help' => __("Set the monetary value of a point. Each point used on a cart will discount the total by that value.", 'woorewards'),
									),
								),
							),
						),
						array(
							'require' => array('selector' => 'input#reward', 'cmp' => '!=', 'value' => 'pointsoncart'),
							'fields' => array(
								array(
									'id'    => 'needed',
									'title' => __("Points Needed", 'woorewards'),
									'type'  => 'text',
								),
							),
						),
						array(
							'require' => array('selector' => 'input#reward', 'value' => 'coupon'),
							'fields' => array(
								array(
									'id'    => 'coupon_amount',
									/* translators: %s: currency symbol */
									'title' => sprintf(_x("Coupon Amount (%s)", "Coupon Unlockable", 'woorewards'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									'type'  => 'text',
									'extra' => array(
										'placeholder' => '1',
										'help' => __("Every time customers have enough points, those points will be used to generate a coupon of that amount.", 'woorewards'),
									),
								),
							),
						),
						array(
							'require' => array('selector' => 'input#reward', 'value' => 'discount'),
							'fields' => array(
								array(
									'id'    => 'discount_amount',
									'title' => __("Discount (%)", 'woorewards'),
									'type'  => 'text',
									'extra' => array(
										'placeholder' => '1',
										'help' => __("Every time customers have enough points, those points will be used to generate a discount coupon with that percentage.", 'woorewards'),
									),
								),
							),
						),
					),
				);
			case 'sum':
				return array(
					'title' => $this->getStepTitle($slug),
					'help'  => __("You're almost done. Check your settings below and submit if you're satisfied with the settings.", 'woorewards'),
					'groups' => array(
						array(
							'fields' => array(
								array(
									'id'    => 'summary',
									'title' => __("Settings Summary", 'woorewards'),
									'type'  => 'custom', // radiogrid is specific to the wizard
									'extra' => array(
										'content' => $this->getSummary(),
										'help' => __("Do you want to start your loyalty system at the end of this wizard ? If you select No, you'll have to start it manually later.", 'woorewards'),
									),
								),
								array(
									'id'    => 'emails',
									'title' => __("Enable rewards emails ?", 'woorewards'),
									'type'  => 'radiogrid', // radiogrid is specific to the wizard
									'extra' => array(
										'type' => 'auto-cols',
										'columns' => 'repeat(auto-fit, minmax(120px, 1fr))',
										'source' => array(
											array('value' => 'yes', 'label' => __("Yes", 'woorewards')),
											array('value' => 'no', 'label' => __("No", 'woorewards')),
										),
										'default' => 'no',
										'help' => __("By setting this option to Yes, customers will receive an email every time they receive a new coupon reward.", 'woorewards'),
									),
								),
								array(
									'id'    => 'start',
									'title' => __("Start the program ?", 'woorewards'),
									'type'  => 'radiogrid', // radiogrid is specific to the wizard
									'extra' => array(
										'type' => 'auto-cols',
										'columns' => 'repeat(auto-fit, minmax(120px, 1fr))',
										'source' => array(
											array('value' => 'yes', 'label' => __("Yes", 'woorewards')),
											array('value' => 'no', 'label' => __("No", 'woorewards')),
										),
										'default' => 'yes',
										'help' => __("Do you want to start your loyalty system at the end of this wizard ? If you select No, you'll have to start it manually later.", 'woorewards'),
									),
								)
							),
						),
					)
				);
			default:
				return array();
		}
	}

	function getActiveStatus($tested = '')
	{
		$data = $this->getData();
		$exists = false;
		$methods = $this->getDataValue($data, 'met', false, $exists);
		foreach ($methods as $method)
		{
			if ($method['order_methods'] == $tested)
				return 'inactive';
		}
		return ('');
	}

	function getSummary()
	{
		$data = $this->getData();
		$exists = false;
		$currency = \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?';
		$summary = "<div class='lws-wizard-summary-container'>";
		/* Loyalty system name */
		$usedData = $this->getDataValue($data, 'ini', false, $exists);
		$system = reset($usedData);
		$summary .= "<div class='summary-title'>" . __("Loyalty System", 'woorewards') . "</div>";
		$value = ($system['system_title']) ? $system['system_title'] : __("Standard System", 'woorewards');
		$summary .= "<div class='lws-wizard-summary-label'>" . __("Loyalty System Name", 'woorewards') . "</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";

		/* Earning methods */
		$usedData = $this->getDataValue($data, 'met', false, $exists);
		$methods = reset($usedData);
		$summary .= "<div class='summary-title'>" . __("Methods to earn points", 'woorewards') . "</div>";
		if ($methods['spent_earn'] && $methods['spent_earn'] > 0)
		{
			/* translators: %1$s: number of points, %2$s: currency symbol */
			$value = sprintf(__(' %1$s points earned for each %2$s spent', 'woorewards'), $methods['spent_earn'], $currency);
			$summary .= "<div class='lws-wizard-summary-label'>" . __("Spend Money", 'woorewards') . "</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
		}
		if ($methods['order_earn'] && $methods['order_earn'] > 0)
		{
			/* translators: %s: number of points */
			$value = sprintf(__(' %s points for each placed order', 'woorewards'), $methods['order_earn']);
			$summary .= "<div class='lws-wizard-summary-label'>" . __("Place an order", 'woorewards') . "</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
		}
		if ($methods['first_order_earn'] && $methods['first_order_earn'] > 0)
		{
			/* translators: %s: number of points */
			$value = sprintf(__(' %s extra points for the first order', 'woorewards'), $methods['first_order_earn']);
			$summary .= "<div class='lws-wizard-summary-label'>" . __("Place a first order", 'woorewards') . "</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
		}
		if ($methods['product_review'] && $methods['product_review'] > 0)
		{
			/* translators: %s: number of points */
			$value = sprintf(__(' %s points for a product review', 'woorewards'), $methods['product_review']);
			$summary .= "<div class='lws-wizard-summary-label'>" . __("Product Review", 'woorewards') . "</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
		}
		if ($methods['sponsored_spent'] && $methods['sponsored_spent'] > 0)
		{
			/* translators: %1$s: number of points, %2$s: currency symbol */
			$value = sprintf(__(' %1$s points earned for each %2$s spent by a Referee', 'woorewards'), $methods['sponsored_spent'], $currency);
			$summary .= "<div class='lws-wizard-summary-label'>" . __("Referee Spends Money", 'woorewards') . "</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
		}
		if ($methods['sponsored_order'] && $methods['sponsored_order'] > 0)
		{
			/* translators: %s: number of points */
			$value = sprintf(__(' %s points for each time a Referee places order', 'woorewards'), $methods['sponsored_order']);
			$summary .= "<div class='lws-wizard-summary-label'>" . __("Referee places an order", 'woorewards') . "</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
		}
		/* Rewards */
		$usedData = $this->getDataValue($data, 'rew', false, $exists);
		$rewards = reset($usedData);
		$summary .= "<div class='summary-title'>" . __("Reward", 'woorewards') . "</div>";
		if ($rewards['reward'] == "discount")
		{
			/* translators: %1$s: discount percentage, %2$s: points required */
			$value = sprintf(__(' %1$s percent discount for %2$s points', 'woorewards'), $rewards['discount_amount'], $rewards['needed']);
			$summary .= "<div class='lws-wizard-summary-label'>" . __("Percentage Discount", 'woorewards') . "</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
		}
		if ($rewards['reward'] == "coupon")
		{
			/* translators: %1$s: discount amount, %2$s: currency symbol, %3$s: points required */
			$value = sprintf(__(' %1$s%2$s discount for %3$s points', 'woorewards'), $rewards['coupon_amount'], $currency, $rewards['needed']);
			$summary .= "<div class='lws-wizard-summary-label'>" . __("Fixed Discount", 'woorewards') . "</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
		}
		if ($rewards['reward'] == "pointsoncart")
		{
			/* translators: %1$s: discount value, %2$s: currency symbol */
			$value = sprintf(__(' %1$s%2$s discount for each point', 'woorewards'), $rewards['point_value'], $currency);
			$summary .= "<div class='lws-wizard-summary-label'>" . __("Points on Cart", 'woorewards') . "</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
		}

		$summary .= "</div>";
		return ($summary);
	}

	function isValid($step, &$submit)
	{
		$err = array();
		if ($step == 'met')
		{
			if (!$this->isIntGE0($submit, 'spent_earn'))
				/* translators: %s: currency symbol */
				$err[] = sprintf(__("Points for each %s spent expects numeric value greater than zero or leave blank.", 'woorewards'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?');

			if (!$this->isIntGE0($submit, 'order_earn'))
				$err[] = __("Points on order placed expects numeric value greater than zero or leave blank.", 'woorewards');

			if (!$this->isIntGE0($submit, 'first_order_earn'))
				$err[] = __("Extra points on first order expects numeric value greater than zero or leave blank.", 'woorewards');
			if (!$this->isIntGE0($submit, 'product_review'))
				$err[] = __("Points for product review expects numeric value greater than zero or leave blank.", 'woorewards');

			if (!$this->isIntGE0($submit, 'sponsored_spent'))
				/* translators: %s: currency symbol */
				$err[] = sprintf(__("Points for each %s spent by Referee expects numeric value greater than zero or leave blank.", 'woorewards'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?');
			if (!$this->isIntGE0($submit, 'sponsored_order'))
				$err[] = __("Points on order placed by Referee expects numeric value greater than zero or leave blank.", 'woorewards');
		}
		else if ($step == 'rew')
		{
			$rew = isset($submit['reward']) ? trim($submit['reward']) : '';
			$pts = isset($submit['needed']) ? trim($submit['needed']) : '';
			if (intval($pts) <= 0 && $rew != 'pointsoncart')
			{
				$err[] = __("Points Needed expects numeric value greater than zero.", 'woorewards');
			}
			if ($rew == 'discount')
			{
				if (!$this->isFloatInRangeEI($submit, 'discount_amount', 0.0, 100.0, false))
					$err[] = __("Please, set a reward percentage greater than 0% up to 100%.", 'woorewards');
			}
			else if ($rew == 'coupon')
			{
				if (!$this->isFloatGT0($submit, 'coupon_amount', false))
					$err[] = __("Please, set a positive reward amount.", 'woorewards');
			}
			else if ($rew == 'pointsoncart')
			{
				if (!$this->isFloatGT0($submit, 'point_value', false))
					$err[] = __("Please, set a positive point value.", 'woorewards');
			}
			else
				$err[] = __("Please, select a reward type.", 'woorewards');
		}
		return $err ? $err : true;
	}

	/** Instanciate pools, events, unlockables, etc. */
	function submit(&$data)
	{
		if (!isset($data['data']))
			return false;
		$pool = $this->getDefaultPool();
		$pool->setOptions(array(
			'type'      => \LWS\WOOREWARDS\Core\Pool::T_STANDARD,
			'public'    => 'yes' === $this->getValue($data['data'], 'start', 'sum/*'),
			'title'     => $this->getValue($data['data'], 'system_title', 'ini/*', __("Standard System", 'woorewards')),
			'whitelist' => array(\LWS\WOOREWARDS\Core\Pool::T_STANDARD),
		));

		if ($this->getValue($data['data'], 'emails', 'sum/*') === 'yes') {
			\update_option('lws_woorewards_enabled_mail_wr_new_reward', 'on');
		} else {
			\update_option('lws_woorewards_enabled_mail_wr_new_reward', '');
		}

		$this->deleteEvents($pool, 'lws_woorewards_events_orderamount');
		$this->deleteEvents($pool, 'lws_woorewards_events_ordercompleted');
		$this->deleteEvents($pool, 'lws_woorewards_events_firstorder');
		$this->deleteEvents($pool, 'lws_woorewards_events_productreview');
		$this->deleteEvents($pool, 'lws_woorewards_events_sponsoredorderamount');
		$this->deleteEvents($pool, 'lws_woorewards_events_sponsoredorder');

		$pool->addEvent(new \LWS\WOOREWARDS\Events\OrderAmount(),          \absint($this->getValue($data['data'], 'spent_earn', 'met/*', 0)));
		$pool->addEvent(new \LWS\WOOREWARDS\Events\OrderCompleted(),       \absint($this->getValue($data['data'], 'order_earn', 'met/*', 0)));
		$pool->addEvent(new \LWS\WOOREWARDS\Events\FirstOrder(),           \absint($this->getValue($data['data'], 'first_order_earn', 'met/*', 0)));
		$pool->addEvent(new \LWS\WOOREWARDS\Events\ProductReview(),        \absint($this->getValue($data['data'], 'product_review', 'met/*', 0)));
		$pool->addEvent(new \LWS\WOOREWARDS\Events\SponsoredOrderAmount(), \absint($this->getValue($data['data'], 'sponsored_spent', 'met/*', 0)));
		$event = new \LWS\WOOREWARDS\Events\SponsoredOrder();
		$pool->addEvent($event->setFirstOrderOnly(false),                  \absint($this->getValue($data['data'], 'sponsored_order', 'met/*', 0)));

		$this->deleteUnlockables($pool, 'lws_woorewards_unlockables_coupon');

		$reward = $this->getValue($data['data'], 'reward', 'rew/*');
		if ('coupon' === $reward)
		{
			$pool->setOption('direct_reward_mode', false);
			$coupon = new \LWS\WOOREWARDS\Unlockables\Coupon();
			$coupon->setInPercent(false);
			$coupon->setValue($this->getValue($data['data'], 'coupon_amount', 'rew/*', 0));
			$pool->addUnlockable($coupon, \absint($this->getValue($data['data'], 'needed', 'rew/*', 0)));
		}
		else if ('discount' === $reward)
		{
			$pool->setOption('direct_reward_mode', false);
			$coupon = new \LWS\WOOREWARDS\Unlockables\Coupon();
			$coupon->setInPercent(true);
			$coupon->setValue($this->getValue($data['data'], 'discount_amount', 'rew/*', 0));
			$pool->addUnlockable($coupon, \absint($this->getValue($data['data'], 'needed', 'rew/*', 0)));
		}
		else if ('pointsoncart' === $reward)
		{
			$pool->setOptions(array(
				'direct_reward_mode' => true,
				'direct_reward_point_rate' => $this->getValue($data['data'], 'point_value', 'rew/*', 0)
			));
		}

		$pool->save();
		if (!$pool->getId())
			return false;
		else
		{
			// set default pool marks
			\clean_post_cache($pool->getId());
			\update_post_meta($pool->getId(), 'wre_pool_prefab', 'yes');
			\update_option('lws_wr_default_pool_name', $pool->getName());
			return \add_query_arg('page', LWS_WOOREWARDS_PAGE . '.loyalty', \admin_url('admin.php'));
		}
	}

	function deleteEvents(&$pool, $type)
	{
		$e = $pool->getEvents()->filter(function ($item) use ($type)
		{
			return $item->getType() == $type;
		});
		while ($e->count())
		{
			$item = $e->last();
			$e->remove($item);
			$pool->removeEvent($item);
			$item->delete();
		}
	}

	function deleteUnlockables(&$pool, $type)
	{
		$u = $pool->getUnlockables()->filter(function ($item) use ($type)
		{
			return $item->getType() == $type;
		});
		while ($u->count())
		{
			$item = $u->last();
			$u->remove($item);
			$pool->removeUnlockable($item);
			$item->delete();
		}
	}

	function getDefaultPool($deep = true)
	{
		/// In free version, it can be only one.
		$pools = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array(
			'numberposts' => 1,
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- required for pool identification
				array(
					'key'     => 'wre_pool_prefab',
					'value'   => 'yes', // This cannot be empty because of a bug in WordPress
					'compare' => 'LIKE'
				),
				array(
					'key'     => 'wre_pool_type',
					'value'   => \LWS\WOOREWARDS\Core\Pool::T_STANDARD,
					'compare' => 'LIKE'
				)
			),
			'deep' => $deep
		));

		if ($pools->count() <= 0)
		{
			$pools = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load();
			$name = 'default';
			if (\is_multisite())
				$name .= \get_current_blog_id();
			return $pools->create($name)->last();
		}
		else
		{
			return $pools->last();
		}
	}
}
