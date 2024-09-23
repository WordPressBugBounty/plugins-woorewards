<?php

namespace LWS\WOOREWARDS\Unlockables;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/**
 * Create a WooCommerce Coupon. */
class Coupon extends \LWS\WOOREWARDS\Abstracts\Unlockable
{
	const COUPON_CODE_MIN_LENGTH = 5;

	protected $value             = 1;
	protected $inPercent         = false;
	protected $couponCategoryIds = array();
	protected $timeout           = false;
	protected $lastCode          = '';

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-coupon',
			'short' => __("The customer will receive a WooCommerce Coupon, either for a fix discount or a percentage one.", 'woorewards-lite'),
			'help'  => __("The generated coupon can be used like any other WooCommerce coupon.", 'woorewards-lite'),
		));
	}

	function getData($min = false)
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix . 'timeout'] = $this->getTimeout()->toString();
		$data[$prefix . 'value']   = $this->getValue();
		$data[$prefix . 'percent'] = $this->getInPercent() ? 'per' : 'fix';
		if ($this->grantExclCat())
			$data[$prefix . 'coupon_cat'] = \base64_encode(\json_encode($this->getCouponCategoryIds()));
		return $data;
	}

	function getForm($context = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Coupon options", 'woorewards-lite'));

		// percent or fixed
		$label = _x("Discount type", "Coupon Unlockable", 'woorewards-lite');
		$value = $this->getInPercent() ? 'per' : 'fix';
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'><select id='{$prefix}percent' name='{$prefix}percent' class='lac_select' data-mode='select'>";
		foreach (array('fix' => __("Fixed cart discount", 'woorewards-lite'), 'per' => __("Percentage discount", 'woorewards-lite')) as $v => $l) {
			$selected = ($v == $value ? ' selected' : '');
			$form .= "<option value='$v'$selected>$l</option>";
		}
		$form .= "</select>";
		$form .= "</div>";

		// value
		$label = _x("Coupon amount", "Coupon Unlockable", 'woorewards-lite');
		$currency = \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '$';
		$value = empty($this->getValue()) ? '' : \esc_attr($this->getValue());
		$form .= "<div class='lws-$context-opt-title label bold'>$label (<span class='{$prefix}currency_hide currency_fix'>$currency</span><span class='{$prefix}currency_hide currency_per'>%</span>)</div>";
		$form .= "<div class='lws-$context-opt-input value'><input type='text' id='{$prefix}value' name='{$prefix}value' value='$value' placeholder='5' pattern='\\d*(\\.|,)?\\d*' /></div>";

		// timeout
		$label = _x("Validity period", "Coupon Unlockable", 'woorewards-lite');
		$value = $this->getTimeout()->toString();
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\Duration::compose($prefix . 'timeout', array('value' => $value));
		$form .= "</div>";

		if ($this->grantExclCat() && \apply_filters('lwsdev_coupon_individual_use_solver_exists', false)) {
			$label   = _x("Exclusive categories", "Coupon category", 'woorewards-lite');
			$tooltip = __("Exclusive categories that the coupon will be applied to. Extends the <i>“Individual use only”</i> rule.", 'woorewards-lite');
			$input = \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix . 'coupon_cat', array(
				'comprehensive' => true,
				'ajax'          => 'lwsdev_coupon_individual_use_solver_categories',
			));
			$form .= <<<EOT
<div class='field-help'>{$tooltip}</div>
<div class='lws-{$context}-opt-title label'>{$label}<div class='bt-field-help'>?</div></div>
<div class='lws-{$context}-opt-input value'>{$input}</div>
EOT;
		}

		$form .= $this->getFieldsetPlaceholder(false, 2);
		return $form;
	}

	function submit($form = array(), $source = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$args = array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix . 'timeout' => '/(p?\d+[DYM])?/i',
				$prefix . 'value'   => 'F',
				$prefix . 'percent' => 's',
			),
			'defaults' => array(
				$prefix . 'timeout' => '',
				$prefix . 'value'   => '0',
				$prefix . 'percent' => 'fix',
			),
			'labels'   => array(
				$prefix . 'timeout' => __("Validity period", 'woorewards-lite'),
				$prefix . 'value'   => __("Coupon amount", 'woorewards-lite'),
				$prefix . 'percent' => __("Discount in percent or fixed price", 'woorewards-lite'),
			)
		);
		if ($this->grantExclCat()) {
			$args['format'][$prefix . 'coupon_cat']   = array('D');
			$args['defaults'][$prefix . 'coupon_cat'] = array();
			$args['labels'][$prefix . 'coupon_cat']   = __("Exclusive categories", 'woorewards-lite');
		}
		$values = \apply_filters('lws_adminpanel_arg_parse', $args);
		if (!(isset($values['valid']) && $values['valid']))
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if ($valid === true) {
			$this->setInPercent($values['values'][$prefix . 'percent'] == 'per');
			if ($this->getInPercent())
				$values['values'][$prefix . 'value'] = min(100.0, $values['values'][$prefix . 'value']);
			$this->setValue($values['values'][$prefix . 'value']);
			$this->setTimeout($values['values'][$prefix . 'timeout']);
			if ($this->grantExclCat())
				$this->setCouponCategoryIds($values['values'][$prefix . 'coupon_cat']);
		}
		return $valid;
	}

	public function getValue()
	{
		return $this->value;
	}

	public function setValue($value = 0.0)
	{
		$this->value = floatval(str_replace(',', '.', $value));
		return $this;
	}

	public function setTestValues()
	{
		$this->setValue(rand(15, 50) / 10.0);
		$this->setTimeout(rand(5, 78) . 'D');
		return $this;
	}

	/** @return (bool) if percent instead fix value */
	public function getInPercent()
	{
		return $this->inPercent;
	}

	public function setInPercent($yes = true)
	{
		$this->inPercent = $yes;
		return $this;
	}

	/** return a Duration instance */
	public function getTimeout()
	{
		if (!$this->timeout)
			$this->timeout = \LWS\Adminpanel\Duration::void();
		return $this->timeout;
	}

	/** @param $days (false|int|\LWS\Adminpanel\Duration) */
	public function setTimeout($days = false)
	{
		if (empty($days))
			$this->timeout = \LWS\Adminpanel\Duration::void();
		else if (is_a($days, '\LWS\Adminpanel\Duration'))
			$this->timeout = $days;
		else
			$this->timeout = \LWS\Adminpanel\Duration::fromString($days);
		return $this;
	}

	public function getDisplayType()
	{
		return _x("Fixed/Percentage discount", "getDisplayType", 'woorewards-lite');
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getDescription($context = 'backend')
	{
		return $this->getCouponDescription($context);
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getCouponDescription($context = 'backend', $date = false)
	{
		$value = '';
		if ($this->getInPercent())
			$value = trim(trim(\number_format_i18n($this->getValue(), 2), '0'), '.,') . '%';
		else {
			$value = (\LWS\Adminpanel\Tools\Conveniences::isWC() && $context != 'edit') ? \wc_price($this->getValue()) : \number_format_i18n($this->getValue(), 2);
			$value = self::getPriceTaxStatus($value);
		}

		$str = sprintf(
			__("%s discount on an order", 'woorewards-lite'),
			$value
		);

		if (!$this->getTimeout()->isNull()) {
			$str .= ' - ';
			if ($date) {
				$str .= sprintf(
					__('valid up to %s', 'woorewards-lite'),
					\date_i18n(\get_option('date_format'), $this->getTimeout()->getEndingDate($date)->getTimestamp())
				);
			} else {
				$str .= sprintf(
					__('valid for %1$d %2$s', 'woorewards-lite'),
					$this->getTimeout()->getCount(),
					$this->getTimeout()->getPeriodText()
				);
			}
		}
		return $str;
	}

	/** Append included/excluded tax indication on given $description.
	 *	Only if inconsistency in price tax setting between
	 *	display and edition. */
	static public function getPriceTaxStatus($price = '')
	{
		static $once = true;
		static $dispTax = true;
		static $priceWithoutTax = false;
		static $omitSuffix = false;
		if ($once) {
			$once = false;
			$dispTax = ('incl' == \get_option('woocommerce_tax_display_cart'));
			$priceWithoutTax = ('yes' != \get_option('woocommerce_prices_include_tax'));
			/// This option can be used if needed to hide the suffix anyway
			$omitSuffix = \get_option('lws_woorewards_omit_coupon_amount_tax_suffix');
		}
		if (!$omitSuffix) {
			if ($dispTax && $priceWithoutTax) {
				$price .= __(" (excl. tax)", 'woorewards-lite');
			} else if (!($dispTax || $priceWithoutTax)) {
				$price .= __(" (incl. tax)", 'woorewards-lite');
			}
		}
		return $price;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setTimeout(\LWS\Adminpanel\Duration::postMeta($post->ID, 'wre_unlockable_timeout'));
		$this->setValue(\get_post_meta($post->ID, 'wre_unlockable_value', true));
		$this->setInPercent(boolval(\get_post_meta($post->ID, 'wre_unlockable_percent', true)));
		if ($this->grantExclCat())
			$this->setCouponCategoryIds(\get_post_meta($post->ID, 'coupon_cat', true));
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'wre_unlockable_value', $this->getValue());
		\update_post_meta($id, 'wre_unlockable_percent', $this->getInPercent() ? 'on' : '');
		$this->getTimeout()->updatePostMeta($id, 'wre_unlockable_timeout');
		if ($this->grantExclCat())
			\update_post_meta($id, 'coupon_cat', $this->getCouponCategoryIds());
		return $this;
	}

	function getCouponCategoryIds()
	{
		return (array)$this->couponCategoryIds;
	}

	/** @param $categories (array|string) as string, it should be a json base64 encoded array. */
	function setCouponCategoryIds($cats=array())
	{
		if (!\is_array($cats))
			$cats = @json_decode(@base64_decode($cats));
		if (\is_array($cats))
			$this->couponCategoryIds = $cats;
		return $this;
	}

	public function getCost($context = 'edit')
	{
		if (empty($this->getValue()) && ($context == 'view' || $context == 'front'))
			return _x("No discount", "Cannot be bought cause no discount", 'woorewards-lite');
		else
			return parent::getCost($context);
	}

	public function isPurchasable($points = PHP_INT_MAX, $userId = null)
	{
		if (empty($this->getValue()))
			return false;
		else
			return parent::isPurchasable($points, $userId);
	}

	public function isAutoApplicable()
	{
		return true;
	}

	public function createReward(\WP_User $user, $demo = false)
	{
		if (!\LWS\Adminpanel\Tools\Conveniences::isWC())
			return false;

		if (!\is_email($user->user_email)) {
			error_log(__CLASS__ . "::apply - invalid email for user {$user->ID}");
			return false;
		}

		$this->lastCode = '';
		if ($demo) {
			$this->lastCode = strtoupper(__('TESTCODE', 'woorewards-lite'));
		} else if (empty($this->lastCode = apply_filters('lws_woorewards_new_coupon_label', '', $user, $this))) {
			$this->lastCode = self::uniqueCode($user);
		}

		if (false === ($coupon = $this->createShopCoupon($this->lastCode, $user, $demo))) {
			$this->lastCode = '';
			return false;
		}

		return $coupon;
	}

	/** For point movement historic purpose. Can be override to return a reason.
	 *	Last generated coupon code is consumed by this function. */
	public function getReason($context = 'backend')
	{
		if ($this->lastCode) {
			$reason = sprintf(__("Coupon code : %s", 'woorewards-lite'), $this->lastCode);
			if ($context == 'frontend')
				$reason .= '<br/>' . $this->getDescription($context);
			//			unset($this->lastCode);
			return $reason;
		}
		return $this->getDescription($context);
	}

	/** @return false|\WC_Coupon a saved WC_Coupon instance */
	protected function createShopCoupon($code, \WP_User $user, $demo = false)
	{
		if (!$demo)
			\do_action('wpml_switch_language_for_email', $user->user_email); // switch to customer language before fixing content

		$coupon = $this->buildCouponPostData($code, $user);

		if (!$demo) {
			$coupon->save();
			if (empty($coupon->get_id())) {
				\do_action('wpml_restore_language_from_email');
				error_log("Cannot generate a shop_coupon: WC error");
				error_log(print_r($coupon, true));
				return false;
			}

			\wp_update_post(array(
				'ID' => $coupon->get_id(),
				'post_author'  => $user->ID,
				'post_content' => $this->getTitle()
			));
			\update_post_meta($coupon->get_id(), 'reward_origin', $this->getType());
			\update_post_meta($coupon->get_id(), 'reward_origin_id', $this->getId());

			// dokan support, force default behavior for a working coupon
			\update_post_meta($coupon->get_id(), 'admin_coupons_enabled_for_vendor', 'yes');

			if ($this->grantExclCat())
				\do_action('lwsdev_coupon_individual_use_solver_apply', $coupon->get_id(), $this->getCouponCategoryIds());

			\do_action('wpml_restore_language_from_email');
			\do_action('woocommerce_coupon_options_save', $coupon->get_id(), $coupon);
		}
		return $coupon;
	}

	/** @return \WC_Coupon instance
	 * @see WC_Meta_Box_Coupon_Data::save */
	protected function buildCouponPostData($code, \WP_User $user)
	{
		$txt = $this->getCustomExcerpt($user);

		/** That filter is required to counter poorly coded plugins, that prevent data_store instanciation in fresh coupon. */
		\add_filter('woocommerce_get_shop_coupon_data', '__return_false', PHP_INT_MAX);
		$coupon = new \WC_Coupon();
		\remove_filter('woocommerce_get_shop_coupon_data', '__return_false', PHP_INT_MAX);

		$coupon->set_props(array(
			'code'                 => $code,
			'description'          => $txt,
			'discount_type'        => $this->getInPercent() ? 'percent' : 'fixed_cart',
			'amount'               => $this->getValue(),
			'date_expires'         => !$this->getTimeout()->isNull() ? $this->getTimeout()->getEndingDate()->format('Y-m-d') : '',
			'usage_limit'          => 1,
			'email_restrictions'   => array($user->user_email),
		));
		return \apply_filters('lws_woorewards_unlockable_coupon_creation', $coupon);
	}

	protected function getCustomExcerpt($user)
	{
		$txt = $this->getCustomDescription(false);
		if (empty($txt))
			$txt = $this->getCouponDescription('frontend', \date_create());
		return $txt;
	}

	/** Loop through random coupon code until find one unique for the user. */
	static function uniqueCode(\WP_User $user)
	{
		$length = \intval(\get_option('lws_woorewards_coupon_code_length', 10));
		$length = ($length ? \max(self::COUPON_CODE_MIN_LENGTH, $length) : 10);

		$prefix = \get_option('lws_woorewards_reward_coupon_code_prefix', '');
		global $wpdb;
		$code = $prefix . \LWS\Adminpanel\Tools\Conveniences::randString($length);
		$sql = "select count(*) from {$wpdb->posts} as p";
		$sql .= " LEFT JOIN {$wpdb->postmeta} as m ON m.post_id=p.ID AND m.meta_key='customer_email' AND m.meta_value=%s";
		$sql .= " where post_title=%s AND post_type='shop_coupon'";
		while (0 < $wpdb->get_var($wpdb->prepare($sql, serialize(array($user->user_email)), $code)))
			$code = $prefix . \LWS\Adminpanel\Tools\Conveniences::randString($length);
		return \wc_format_coupon_code($code);
	}

	/**	WooCommerce redirection.
	 *	Allow filtering by customer_email directly in shop_coupon admin list
	 *	and push a link to them inside userspoints editlist. */
	static function addUiFilters()
	{
		if (\is_admin()) {
			\add_filter('query_vars', array(__CLASS__, 'addQueryVars'));
			\add_action('restrict_manage_posts', array(__CLASS__, 'showQueryParsing'), 10, 1);
			\add_action('parse_query', array(__CLASS__, 'parseQuery'));
			\add_filter('lws_woorewards_ui_userspoints_rewards_cell', array(__CLASS__, 'seeUserCoupons'), 10, 2);
		}
	}

	/** @see parseQuery */
	static function addQueryVars($vars)
	{
		$vars[] = 'customer_email';
		return $vars;
	}

	/** Show coupon custom filter wher applied. */
	static function showQueryParsing($postType)
	{
		if ($postType == 'shop_coupon' && isset($_REQUEST['customer_email']) && !empty($email = \sanitize_email($_REQUEST['customer_email']))) {
			echo "<label for='customer_email' class='lws-wr-coupon-filter-customer_email'>" . __("Customer Email", 'woorewards-lite') . "</label>";
			echo "<input  id='customer_email' class='lws-wr-coupon-filter-customer_email' type='email' name='customer_email' value='" . \esc_attr($email) . "' aria-describedby='customer email'>";
		}
	}

	/** Allow filtering by customer_email directly in shop_coupon admin list */
	static function parseQuery(&$query)
	{
		if (\is_admin() && $query->query && isset($query->query['post_type']) && $query->query['post_type'] == 'shop_coupon') {
			$email = isset($query->query['customer_email']) ? trim($query->query['customer_email']) : '';
			if (!empty($email)) {
				$customer_email = serialize(array($email));
				$query->query_vars['meta_query'][] = array(
					'key' => 'customer_email',
					'value' => $customer_email
				);
			}
		}
	}

	/** Link to 'shop_coupon' posts list, filtered by 'Allowed email'.
	 * Works for generated coupon when 'Allowed email' array is a single email. */
	static function seeUserCoupons($content, $user)
	{
		if (!empty($user)) {
			global $wpdb;
			$sql = "SELECT COUNT(ID) FROM {$wpdb->posts} as p INNER JOIN {$wpdb->postmeta} as c ON p.ID=c.post_id AND c.meta_key='customer_email' AND c.meta_value=%s WHERE p.post_type='shop_coupon' AND post_status='publish'";
			$c = $wpdb->get_var($wpdb->prepare($sql, serialize(array($user['user_email']))));
			if (empty($c)) {
				static $disp = false;
				if ($disp === false)
					$disp = __("No coupon", 'woorewards-lite');
				$content[] = "<div class='lws-adm-btn disabled lws_wre_rewards_no_link'>$disp</div>";
			} else {
				$url = \esc_attr(\add_query_arg(array('post_type' => 'shop_coupon', 'customer_email' => $user['user_email']), \admin_url('edit.php')));
				if (!empty($url)) {
					static $link = false;
					if ($link === false)
						$link = __("See coupons", 'woorewards-lite');
					$content[] = sprintf("<a class='lws-adm-btn lws_wre_rewards_link' href='$url' target='_blank'>$link (%d)</a>", $c);
				}
			}
		}
		return $content;
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'woocommerce' => __("WooCommerce", 'woorewards-lite'),
			'shop_coupon' => __("Coupon", 'woorewards-lite'),
			'sponsorship' => _x("Referred", "unlockable category", 'woorewards-lite')
		));
	}

	protected function grantExclCat()
	{
		return true;
	}
}
