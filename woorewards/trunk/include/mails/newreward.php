<?php
namespace LWS\WOOREWARDS\Mails;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/*
Class that will be used for new reward email in MyRewards
*/
class NewReward
{
	protected $template = 'wr_new_reward';

	public function __construct()
	{
		add_filter( 'lws_woorewards_mails', array($this, 'addTemplate'), 20 );
		add_filter( 'lws_mail_settings_' . $this->template, array( $this , 'settings' ) );
		add_filter( 'lws_mail_body_' . $this->template, array( $this , 'body' ), 10, 3 );
	}

	public function addTemplate($arr)
	{
		$arr[] = $this->template;
		return $arr;
	}

	public function settings( $settings )
	{
		$settings['domain']        = 'woorewards';
		$settings['settings']      = 'New Reward';
		$settings['icon']          = 'lws-icon-present';
		$settings['settings_name'] = __("New Reward", 'woorewards-lite');
		$settings['about']         = __("Sent to customers when they receive a new reward", 'woorewards-lite');
		$settings['subject']       = __("You've received a new reward", 'woorewards-lite');
		$settings['title']         = __("New Reward", 'woorewards-lite');
		$settings['header']        = __("You just got a new reward !", 'woorewards-lite');
		$settings['footer']        = __("Powered by MyRewards", 'woorewards-lite');
		$settings['css_file_url']  = LWS_WOOREWARDS_CSS . '/mails/newreward.css';
		$settings['fields']['enabled'] = array(
			'id' => 'lws_woorewards_enabled_mail_' . $this->template,
			'title' => __("Enabled", 'woorewards-lite'),
			'type' => 'box',
			'extra' => array(
				'layout' => 'toggle',
				'default' => '',
			)
		);

		return $settings;
	}

	public function body( $html, $data, $settings )
	{
		if( !empty($html) )
			return $html;
		if( $demo = \is_wp_error($data) )
			$data = $this->placeholders();

		if( false === $data['reward'] )
		{
			return __("That feature requires WooCommerce.", 'woorewards-lite');
		}

		$code = $data['reward']->get_code();

		$expiry = false;
		if( $data['reward']->get_date_expires('edit') )
			$expiry = \mysql2date(\get_option('date_format'), $data['reward']->get_date_expires('edit')->date('Y-m-d'));

		$value = $data['reward']->get_amount('edit');
		if( $data['reward']->get_discount_type('edit') == 'percent' )
			$value = round($value, 2) . '%';
		else if( $data['reward']->get_discount_type('edit') == 'fixed_cart' )
			$value = \wc_price($value, array('currency' => \get_option('woocommerce_currency')));

		$labels = array(
			'date'   => __("Expiration Date", 'woorewards-lite'),
			'detail' => __("Coupon Details", 'woorewards-lite'),
			'code'   => __("Coupon Code", 'woorewards-lite'),
			'value'  => __("Coupon Value", 'woorewards-lite')
		);

		if (empty($data['reward']->get_usage_limit('edit')))
			$value .= (' ' . _x("(reusable)", "permanent coupon", 'woorewards-lite'));

		$expDivs = array('', '');
		if( !empty($expiry) )
		{
			$expDivs = array(
				"<div class='lwss_selectable lws-reward-desc' data-type='Reward Description'>{$labels['date']}</div>",
				"<div class='lwss_selectable lws-reward-expiry' data-type='Expiration Date'>{$expiry}</div>"
			);
		}

		$img = $labels['detail'];
		if( !empty($data['unlockable']) && empty($img = $data['unlockable']->getThumbnailImage()) )
		{
			if( $demo )
				$img = "<div class='lws-sponsor-thumbnail lws-icon lws-icon-image'></div>";
			else
				$img = $labels['detail'];
		}

		return <<<EOT
<tr><td class='lws-middle-cell'>
	<table class='lwss_selectable lws-rewards-table' data-type='Rewards Table'>
		<tr>
			<td><div class='lwss_selectable lws-reward-desc' data-type='Reward Description'>{$img}</div></td>
			<td>
				<div class='lwss_selectable lws-reward-desc' data-type='Reward Description'>{$labels['code']}</div>
				<div class='lwss_selectable lws-reward-desc' data-type='Reward Description'>{$labels['value']}</div>
				{$expDivs[0]}
			</td><td>
				<div class='lwss_selectable lws-reward-code' data-type='Reward Code'>{$code}</div>
				<div class='lwss_selectable lws-reward-value' data-type='Reward Value'>{$value}</div>
				{$expDivs[1]}
			</td>
		</tr>
	</table>
</td></tr>
EOT;
	}

	protected function placeholders()
	{
		if( !\LWS\Adminpanel\Tools\Conveniences::isWC() )
			return array('user' => \wp_get_current_user(), 'type' => '', 'unlockable' => null, 'reward' => false);

		$unlockable = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->create('lws_woorewards_unlockables_coupon')->last();
		$unlockable->setTestValues();
		$user = \wp_get_current_user();

		return array(
			'user' => $user,
			'type' => $unlockable->getType(),
			'unlockable' => $unlockable,
			'reward' => $unlockable->createReward($user, true)
		);
	}

}