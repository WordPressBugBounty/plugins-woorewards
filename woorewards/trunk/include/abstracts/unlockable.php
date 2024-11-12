<?php

namespace LWS\WOOREWARDS\Abstracts;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Base class for each way to spend points.
 *	To be used, an Event must be declare by calling register @see register
 *
 *	Each pool is in charge to apply its selected unlockables after a purchase @see apply.
 *	An unlockable could be purchased if cost is greater than zero.
 *
 *	Anyway, an unlockable is available for information or selection and so can be instanciated from anywhere.
 *  */
abstract class Unlockable implements \LWS\WOOREWARDS\Abstracts\ICategorisable, \LWS\WOOREWARDS\Abstracts\IRegistrable
{
	const POST_TYPE = 'lws-wre-unlockable';
	private static $s_unlockables = array();

	protected $pool          = null;
	protected $poolId        = false;
	protected $dataKeyPrefix = false;
	protected $groupedTitle  = '';
	protected $emailEnabled  = true;
	protected $dateStart     = null;
	protected $dateEnd       = null;
	public $id               = false;
	public $name             = false;
	public $title            = '';
	public $description      = '';
	public $cost             = 1;
	public $thumbnail        = 0;

	/** Inhereted Unlockable already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	abstract protected function _fromPost(\WP_Post $post);
	/** Unlockable already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	abstract protected function _save($id);
	/** @return string a human readable type for UI */
	abstract public function getDisplayType();
	/** Produce a reward.
	 * @param $user the customer the reward is for.
	 * @param $demo (bool, default is false) the reward is not really generated, but the data are returned as if (especially used for stygen).
	 * @return (mixed) the array represent the generated reward. or false on error. */
	abstract public function createReward(\WP_User $user, $demo = false);

	function apply(\WP_User $user, $mailTemplate = 'wr_new_reward')
	{
		$reward = $this->createReward($user);
		if ($reward !== false) {
			$this->incrRedeemCount($user->ID);
			if ($this->isEmailEnabled())
				$this->sendMail($user, $reward, $mailTemplate);
			return true;
		}
		return false;
	}

	/**	@return array of data to feed the form @see getForm.
	 *	Each key should be the name of an input balise.
	 * @param $min (default false) if true, omit cosmetic data. */
	function getData($min = false)
	{
		$prefix = $this->getDataKeyPrefix();
		$data = array(
			$prefix . 'cost'          => $this->getCost(),
			$prefix . 'date_start'    => $this->getDateStartFormatted(),
			$prefix . 'date_end'      => $this->getDateEndFormatted(),
			$prefix . 'email_enabled' => $this->isEmailEnabled() ? 'on' : '',
			$prefix . 'max_redeem'    => ($red = $this->getMaxRedeem()) ? $red : '',
		);
		if (!$min) {
			$data[$prefix . 'title']         = $this->title;
			$data[$prefix . 'description']   = $this->description;
			$data[$prefix . 'thumbnail']     = $this->getThumbnail();
			$data[$prefix . 'thumbnail_url'] = $this->getThumbnailUrl();
			$data['grouped_title']         = $this->getGroupedTitle();
		}
		return $data;
	}

	/**	Provided to be overriden.
	 *	@param $context usage of returned inputs, default is an edition in editlist. Used to form css class name too.
	 *	@return (string) the inside of a form (without any form balise).
	 *	@notice in override, dedicated option name must be type specific @see getDataKeyPrefix()
	 *	dedicated DOM must declare css attribute for hidden/show editlist behavior
	 * 	@code
	 *		class='lws_woorewards_system_choice {$this->getType()}'
	 *	@endcode
	 *	You can use several placeholder balises to insert DOM in middle of previous form (take care to conserve for anyone following).
	 *	For each fieldset (numbered from 0, 1...) @see str_replace @see getFieldsetPlaceholder()
	 *	@code
	 *	<!-- [fieldset-1-head:{$this->getType()}] -->
	 *	<!-- [fieldset-1-foot:{$this->getType()}] -->
	 *	@endcode */
	function getForm($context = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$str = $this->getFieldsetBegin(0, __("Reward Information", 'woorewards-lite'), '', false);

		$str .= "<div class='lws-$context-opt-title label'>" . __("Reward type", 'woorewards-lite') . "</div>";
		$str .= "<div class='value lws_woorewards_system_type_info'>" . $this->getDisplayType() . "</div>";
		$str .= $this->getFieldsetPlaceholder(true, 0); // type will always be first, so exceptionnaly put at second place

		// custom title
		$label = _x("Title", "Unlockable title", 'woorewards-lite');
		$placeholder = \esc_attr(\apply_filters('the_title', $this->getDisplayType(), $this->getId()));
		$value = \esc_attr($this->title);
		$str .= "<div class='lws-$context-opt-title label lws_wru_field_title'>$label</div>";
		$str .= "<div class='value lws-$context-opt-input value lws_wru_field_title'><input type='text' id='{$prefix}title' name='{$prefix}title' value='$value' placeholder='$placeholder' /></div>";

		// custom description
		$label = _x("Description", "Unlockable title", 'woorewards-lite');
		$placeholder = \esc_attr(strip_tags(\apply_filters('the_wre_unlockable_description', $this->getDescription('edit'), $this->getId())));
		$value = \htmlspecialchars($this->description, ENT_QUOTES);
		$tooltip = $this->getDescriptionTooltip();
		if ($tooltip) {
			$str .= "<div class='field-help'>{$tooltip}</div>";
			$str .= "<div class='lws-$context-opt-title label lws_wru_field_descr'>$label<div class='bt-field-help'>?</div></div>";
		} else {
			$str .= "<div class='lws-$context-opt-title label lws_wru_field_descr'>$label</div>";
		}
		$str .= "<div class='value lws-$context-opt-input value lws_wru_field_descr'>";
		$str .= "<textarea id='{$prefix}description' name='{$prefix}description' placeholder='$placeholder'>$value</textarea>";
		$str .= "</div>";

		// add thumbnail here
		$label = _x("Featured Image", "Unlockable Thumbnail", 'woorewards-lite');
		$str .= "<div class='lws-$context-opt-title label lws_wru_field_thumbnail'>$label</div>";
		$str .= "<div class='value lws-$context-opt-input value lws_wru_field_thumbnail'>";
		$str .= \LWS\Adminpanel\Pages\Field\Media::compose($prefix . 'thumbnail', array(
			'value'        => $this->getThumbnail(),
			'type'         => 'image',
			'size'         => 'thumbnail',
			'classSize'    => 'lws_wr_thumbnail',
			'urlInputName' => $prefix . 'thumbnail_url'
		));
		$str .= "</div>";

		$str .= $this->getFieldsetEnd(0);
		$str .= $this->getFieldsetBegin(1, __("Reward Settings", 'woorewards-lite'));

		// cost
		$label = _x("Points needed", "Unlockable cost", 'woorewards-lite');
		$value = empty($this->getCost()) ? '' : \esc_attr($this->getCost());
		$str .= "<div class='lws-$context-opt-title label bold lws_wru_field_cost'>$label</div>";
		$str .= "<div class='value lws-$context-opt-input value lws_wru_field_cost'><input type='text' id='{$prefix}cost' name='{$prefix}cost' value='$value' placeholder='0' class='lws_wr_unlockable_cost' /></div>";

		// email
		$label = _x("Send Reward email", "Unlockable cost", 'woorewards-lite');
		$str .= "<div class='lws-$context-opt-title label lws_wru_field_email'>$label</div>";
		$str .= "<div class='value lws-$context-opt-input value lws_wru_field_email'>";
		$str .= \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'email_enabled', array(
			'id'      => $prefix . 'email_enabled',
			'layout'  => 'toggle',
			'class'   => 'lws_wr_unlockable_email'
		));
		$str .= "</div>";

		// max redeems
		if (self::$maxRedeemAllowed) {
			$label = _x("Max Redeems", "Unlockable max redeems", 'woorewards-lite');
			$tooltip = __("Define how many times this reward can be redeemed by customers. Leave empty or set to 0 for no limits", 'woorewards-lite');
			$str .= "<div class='field-help'>$tooltip</div>";
			$str .= "<div class='lws-$context-opt-title label lws_wru_field_max_redeem'>$label<div class='bt-field-help'>?</div></div>";
			$str .= "<div class='value lws-$context-opt-input value lws_wru_field_max_redeem'><input type='text' id='{$prefix}max_redeem' name='{$prefix}max_redeem' class='lws_wr_unlockable_max_redeem' /></div>";
		}

		$str .= $this->getFieldsetEnd(1);

		return $str;
	}

	/** Provided to be overriden.
	 *	Back from the form, set and save data from @see getForm
	 *	@param $source origin of form values. Expect 'editlist' or 'post'. If 'post' we will apply the stripSlashes().
	 * 	@return true if ok, (false|string|WP_Error) false or an error description on failure. */
	function submit($form = array(), $source = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix . 'cost'          => '0',
				$prefix . 'title'         => 't',
				$prefix . 'description'   => 't',
				$prefix . 'date_start'    => 's',
				$prefix . 'date_end'      => 's',
				$prefix . 'thumbnail'     => 'd',
				$prefix . 'email_enabled' => 't',
				$prefix . 'max_redeem'    => '0',
				'grouped_title'           => 't'
			),
			'defaults' => array(
				$prefix . 'cost'          => '0',
				$prefix . 'title'         => '',
				$prefix . 'description'   => '',
				$prefix . 'date_start'    => '',
				$prefix . 'date_end'      => '',
				$prefix . 'thumbnail'     => '0',
				$prefix . 'email_enabled' => '',
				$prefix . 'max_redeem'    => '0',
				'grouped_title'           => ''
			),
			'labels'   => array(
				$prefix . 'cost'          => __("Cost", 'woorewards-lite'),
				$prefix . 'title'         => __("Title", 'woorewards-lite'),
				$prefix . 'description'   => __("Description", 'woorewards-lite'),
				$prefix . 'date_start'    => __("Validity Starting Date", 'woorewards-lite'),
				$prefix . 'date_end'      => __("Validity Ending Date", 'woorewards-lite'),
				$prefix . 'thumbnail'     => __("Featured Image", 'woorewards-lite'),
				$prefix . 'email_enabled' => __("Enable Reward email", 'woorewards-lite'),
				$prefix . 'max_redeem'    => __("Max Redeems", 'woorewards-lite'),
				'grouped_title'           => __("Level Title", 'woorewards-lite')
			)
		));
		if (!(isset($values['valid']) && $values['valid']))
			return isset($values['error']) ? $values['error'] : false;

		$this->setTitle($values['values'][$prefix . 'title']);
		$this->setDescription($values['values'][$prefix . 'description']);
		$this->setCost($values['values'][$prefix . 'cost']);
		$this->setDateStart($values['values'][$prefix . 'date_start']);
		$this->setDateEnd($values['values'][$prefix . 'date_end']);
		$this->setThumbnail($values['values'][$prefix . 'thumbnail']);
		$this->setEmailEnabled($values['values'][$prefix . 'email_enabled']);
		$this->setMaxRedeem($values['values'][$prefix . 'max_redeem']);
		$this->setGroupedTitle($values['values']['grouped_title']);
		return true;
	}

	protected function getFieldsetBegin($index, $title = '', $css = '', $withPlaceholder = true)
	{
		if (!empty($css))
			$css .= ' ';
		$css .= "fieldset fieldset-$index";
		$str = "<div class='$css'>";
		if (!empty($title))
			$str .= "<div class='title'>$title</div>";
		$str .= "<div class='fieldset-grid'>";
		if ($withPlaceholder)
			$str .= $this->getFieldsetPlaceholder(true, $index);
		return $str;
	}

	protected function getFieldsetEnd($index, $withPlaceholder = true)
	{
		$str = $withPlaceholder ? $this->getFieldsetPlaceholder(false, $index) : '';
		return $str . "</div></div>";
	}

	/** @see getForm insert that balise at top and bottom of each fieldset.
	 * @return (string) html */
	protected function getFieldsetPlaceholder($top, $index)
	{
		return "<!-- [fieldset-" . intval($index) . "-" . ($top ? 'head' : 'foot') . ":" . $this->getType() . "] -->";
	}

	public function getDataKeyPrefix()
	{
		if (false === $this->dataKeyPrefix)
			$this->dataKeyPrefix = \esc_attr($this->getType()) . '_';
		return $this->dataKeyPrefix;
	}

	public function setDataKeyPrefix($prefix = false)
	{
		$this->dataKeyPrefix = $prefix;
		return $this;
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getDescription($context = 'backend')
	{
		return $context == 'backend' ? $this->getDisplayType() : '';
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getDescriptionTooltip()
	{
		return '';
	}

	/** @alias for getReason('raw').
	 *	Used to set history traces.
	 *	@return string or \LWS\WOOREWARDS\Core\Trace instance. */
	public function getRawReason()
	{
		return $this->getReason('raw');
	}

	/** For point movement historic purpose. Can be override to return a reason. */
	public function getReason($context = 'backend')
	{
		return $this->getDescription($context);
	}

	static public function fromPost(\WP_Post $post)
	{
		$type = \get_post_meta($post->ID, 'wre_unlockable_type', true);
		$unlockable = static::instanciate($type);

		if (empty($unlockable)) {
			//			\lws_admin_add_notice_once('lws-wre-event-instanciate', __("Error occured during unlockable reward instanciation.", 'woorewards-lite'), array('level'=>'error'));
		} else {
			$unlockable->id    = intval($post->ID);
			$unlockable->name  = $post->post_name;
			$unlockable->title = $post->post_title;
			$unlockable->description = $post->post_content;
			$unlockable->setCost(\get_post_meta($post->ID, 'wre_unlockable_cost', true));
			$unlockable->setDateStart(\get_post_meta($post->ID, 'wre_unlockable_date_start', true));
			$unlockable->setDateEnd(\get_post_meta($post->ID, 'wre_unlockable_date_end', true));
			$unlockable->setThumbnail(\get_post_meta($post->ID, 'wre_unlockable_thumbnail', true));
			$unlockable->setGroupedTitle(\get_post_meta($post->ID, 'wre_unlockable_grouped_title', true));
			$unlockable->setEmailEnabled(\get_post_meta($post->ID, 'wre_unlockable_email_enabled', true) != 'off');
			$unlockable->setMaxRedeem(\get_post_meta($post->ID, 'wre_unlockable_max_redeem', true));
			$unlockable->poolId = intval($post->post_parent);

			$unlockable->_fromPost($post);
		}
		return \apply_filters('lws_woorewards_abstracts_unlockable_loaded', $unlockable, $post);
	}

	/** @param $type (string|array) a registered type or an item of getRegistered(). */
	static function instanciate($type)
	{
		$instance = null;
		$registered = (is_string($type) ? static::getRegisteredByName($type) : $type);

		if (is_array($registered) && !empty($registered)) {
			try {
				require_once $registered[1];
				$instance = new $registered[0];
			} catch (\Exception $e) {
				error_log("Cannot instanciate an woorewards Unlockable: " . $e->getMessage());
			}
		}
		//		else
		//			error_log("Unknown wooreward unlockable registered type from : ".print_r($type, true));

		return $instance;
	}

	public function save(\LWS\WOOREWARDS\Core\Pool &$pool)
	{
		$this->setPool($pool);
		$data = array(
			'ID'          => $this->id ? intval($this->id) : 0,
			'post_parent' => $pool->getId(),
			'post_type'   => self::POST_TYPE,
			'post_status' => $this->isPurchasable(PHP_INT_MAX) ? $this->getPoolStatus() : 'draft',
			'post_name'   => $this->getName($pool),
			'post_title'  => $this->title,
			'post_content' => $this->description,
			'meta_input'  => array(
				'wre_unlockable_cost'          => $this->getCost(),
				'wre_unlockable_type'          => $this->getType(),
				'wre_unlockable_date_start'    => $this->getDateStartFormatted(),
				'wre_unlockable_date_end'      => $this->getDateEndFormatted(),
				'wre_unlockable_thumbnail'     => $this->getThumbnail(),
				'wre_unlockable_grouped_title' => $this->getGroupedTitle(),
				'wre_unlockable_email_enabled' => $this->isEmailEnabled() ? 'on' : 'off',
				'wre_unlockable_max_redeem'    => $this->getMaxRedeem(),
			)
		);

		$postId = $data['ID'] ? \wp_update_post($data, true) : \wp_insert_post($data, true);
		if (\is_wp_error($postId)) {
			error_log("Error occured during event saving: " . $postId->get_error_message());
			\lws_admin_add_notice_once('lws-wre-unlockable-save', __("Error occured during unlockable reward saving.", 'woorewards-lite'), array('level' => 'error'));
			return $this;
		}
		$this->id = intval($postId);
		if ($this->title)
			\do_action('wpml_register_string', $this->title, 'title', $this->getPackageWPML(true), __("Title", 'woorewards-lite'), 'LINE');
		if ($this->description)
			\do_action('wpml_register_string', $this->description, 'description', $this->getPackageWPML(true), __("Description", 'woorewards-lite'), 'AREA');
		if ($this->groupedTitle)
			\do_action('wpml_register_string', $this->groupedTitle, 'level', $this->getPackageWPML(true), __("Level", 'woorewards-lite'), 'LINE');

		$this->_save($this->id);
		\do_action('lws_woorewards_abstracts_unlockable_save_after', $this);
		return $this;
	}

	public function setDescription($descr = '')
	{
		$this->description = $descr;
		return $this;
	}

	/** @see https://wpml.org/documentation/support/string-package-translation
	 * Known wpml bug: kind first letter must be uppercase */
	function getPackageWPML($full = false)
	{
		$pack = array(
			'kind' => 'WooRewards Reward', //strtoupper(self::POST_TYPE),
			'name' => $this->getId(),
		);
		if ($full) {
			$title = $this->title ? $this->title : ($this->getDisplayType() . '/' . $this->getId());
			if ($pool = $this->getPool())
				$title = ($pool->getOption('title') . ' - ' . $title);
			$pack['title'] = $title;
			$pack['edit_link'] = \add_query_arg(array('page' => LWS_WOOREWARDS_PAGE . '.loyalty', 'tab' => 'wr_loyalty.wr_upool_' . $this->getPoolId()), admin_url('admin.php'));
		}
		return $pack;
	}

	/** @alias for getTitle(false, true) */
	public function getTitleAsReason()
	{
		return $this->getTitle(false, true);
	}

	public function getTitle($fallback = true, $forceTranslate=false)
	{
		if ($this->title) {
			$title = $this->title;
		} else {
			$title = $fallback ? $this->getDisplayType() : '';
		}
		if ($forceTranslate || !(is_admin() || (defined('DOING_AJAX') && DOING_AJAX)))
			$title = \apply_filters('wpml_translate_string', $title, 'title', $this->getPackageWPML());
		return \apply_filters('the_title', $title, $this->getId());
	}

	/** if a custom descr is set, return it, else return the generated description.
	 * @param $fallback (bool) true: always return a value. */
	public function getCustomDescription($fallback = true)
	{
		$descr = false;
		if ($this->description) {
			$descr = $this->description;
			if ($fallback)
				$descr = \apply_filters('wpml_translate_string', $descr, 'description', $this->getPackageWPML());
		} else if ($fallback) {
			$descr = $this->getDescription('frontend');
			if (empty($descr)) // really cannot let it empty
				$descr = $this->getDisplayType();
		}
		return \apply_filters('the_wre_unlockable_description', $descr, $this->getId(), $fallback);
	}

	public function setTitle($title = '')
	{
		$this->title = $title;
		return $this;
	}

	/** provided to be used with levelling pools */
	public function getGroupedTitle($context = 'edit')
	{
		$title = $this->groupedTitle;
		if ($context == 'view') {
			$title = \apply_filters('wpml_translate_string', $title, 'level', $this->getPackageWPML());
			$title = \apply_filters('the_title', $title, $this->getId());
		}
		return $title;
	}

	public function setGroupedTitle($title)
	{
		$this->groupedTitle = $title;
	}

	public function delete()
	{
		if ($this->id) {
			\do_action('lws_woorewards_abstracts_unlockable_delete_before', $this);
			if (empty(\wp_delete_post($this->id, true)))
				error_log("Failed to delete the unlockable reward {$this->id}");
			else {
				$pack = $this->getPackageWPML();
				\do_action('wpml_delete_package_action', $pack['name'], $pack['kind']);

				$this->id = false;
			}
		}
		return $this;
	}

	/** Declare a new kind of event. */
	static public function register($classname, $filepath, $unregister = false, $typeOverride = false)
	{
		$id = empty($typeOverride) ? self::formatType($classname) : $typeOverride;
		if ($unregister) {
			if (isset(self::$s_unlockables[$id]))
				unset(self::$s_unlockables[$id]);
		} else
			self::$s_unlockables[$id] = array($classname, $filepath);
	}

	static public function getRegistered()
	{
		return self::$s_unlockables;
	}

	static public function getRegisteredByName($name)
	{
		return isset(self::$s_unlockables[$name]) ? self::$s_unlockables[$name] : false;
	}

	/** @param $classname full class with namespace. */
	public static function formatType($classname = false)
	{
		if ($classname === false)
			$classname = \get_called_class();
		return strtolower(str_replace('\\', '_', trim($classname, '\\')));
	}

	public function getType()
	{
		return static::formatType($this->getClassname());
	}

	function getClassname()
	{
		return \get_class($this);
	}

	/** @return int id */
	public function getThumbnail()
	{
		$id = $this->thumbnail;
		if ($id && !(is_admin() || (defined('DOING_AJAX') && DOING_AJAX)))
			$id = \apply_filters('wpml_object_id', $id, 'attachment', true);
		return $id;
	}

	/** @return string url */
	public function getThumbnailUrl()
	{
		if (empty($this->getThumbnail()))
			return '';
		else
			return \wp_get_attachment_url($this->getThumbnail());
	}

	/** @return string html <img> */
	public function getThumbnailImage($size = 'lws_wr_thumbnail')
	{
		if (empty($this->getThumbnail()))
			return '';
		else
			return \wp_get_attachment_image($this->getThumbnail(), $size, false, array('class' => 'lws-wr-thumbnail lws-wr-unlockable-thumbnail'));
	}

	public function setThumbnail($id)
	{
		$this->thumbnail = $id;
		return $this;
	}

	public function isEmailEnabled()
	{
		return $this->emailEnabled; // default: true
	}

	public function setEmailEnabled($yes)
	{
		$this->emailEnabled = boolval($yes);
	}

	/** @param $date (false|string|\DateTime) if false, today is used.
	 * If given date format is not valid, return false.
	 * If no date limit defined for this, return true. */
	public function inDateRange($date = false)
	{
		if (empty($date))
			$date = \date_create();
		else if (\is_string($date)) {
			$date = \date_create($date);
			if (empty($date))
				return false;
		}

		$date->setTime(0, 0, 0);
		if (!empty($this->getDateStart()) && $this->getDateStart() > $date)
			return false;
		if (!empty($this->getDateEnd()) &&  $date > $this->getDateEnd())
			return false;

		return true;
	}

	/** @return \DateTime|null
	 * If inside a levelling pool, then we cannot have a date. */
	public function getDateStart()
	{
		if ($this->getPoolType() == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING)
			return null;
		else if (defined('LWS_WOOREWARDS_ACTIVATED') && LWS_WOOREWARDS_ACTIVATED)
			return $this->dateStart;
		else
			return null;
	}

	/** @return string (can be empty if no date set) */
	public function getDateStartFormatted()
	{
		if (empty($this->getDateStart()))
			return '';
		else
			return $this->getDateStart()->format('Y-m-d');
	}

	/** @param $date (null|string|\DateTime) */
	public function setDateStart($date = null)
	{
		if (empty($date))
			$this->dateStart = null;
		else if (\is_a($date, 'DateTime'))
			$this->dateStart = $date->setTime(0, 0, 0);
		else if (\is_string($date)) {
			$date = \date_create($date);
			if (empty($date))
				error_log("Invalid Date format for " . \get_class($this) . '::' . __FUNCTION__);
			else
				$this->dateStart = $date->setTime(0, 0, 0);
		} else
			error_log("Invalid Date type for " . \get_class($this) . '::' . __FUNCTION__);
		return $this;
	}

	/** @return null|\DateTime insance
	 * If inside a levelling pool, then we cannot have a date. */
	public function getDateEnd()
	{
		if ($this->getPoolType() == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING)
			return null;
		else if (defined('LWS_WOOREWARDS_ACTIVATED') && LWS_WOOREWARDS_ACTIVATED)
			return $this->dateEnd;
		else
			return null;
	}

	/** @return string (can be empty if no date set) */
	public function getDateEndFormatted()
	{
		if (empty($this->getDateEnd()))
			return '';
		else
			return $this->getDateEnd()->format('Y-m-d');
	}

	/** @param $date (null|string|\DateTime) */
	public function setDateEnd($date = null)
	{
		if (empty($date))
			$this->dateEnd = null;
		else if (\is_a($date, 'DateTime'))
			$this->dateEnd = $date->setTime(0, 0, 0);
		else if (\is_string($date)) {
			$date = \date_create($date);
			if (empty($date))
				error_log("Invalid Date format for " . \get_class($this) . '::' . __FUNCTION__);
			else
				$this->dateEnd = $date->setTime(0, 0, 0);
		} else
			error_log("Invalid Date type for " . \get_class($this) . '::' . __FUNCTION__);
		return $this;
	}

	public function unsetPool()
	{
		$this->pool = null;
		return $this;
	}

	public function setPool(&$pool)
	{
		$this->pool = &$pool;
		return $this;
	}

	/** @return false|\LWS\WOOREWARDS\Core\Pool */
	public function getPool()
	{
		return $this->pool ? $this->pool : false;
	}

	/** @return false|\LWS\WOOREWARDS\Core\Pool */
	public function getOrLoadPool()
	{
		if (null !== $this->pool) {
			return $this->pool;
		} else if ($this->poolId) {
			if (\class_exists('\LWS\WOOREWARDS\PRO\Core\Pool')) {
				$this->pool = \LWS\WOOREWARDS\PRO\Core\Pool::getOrLoad($this->poolId, false);
			} else {
				$pool = \apply_filters('lws_woorewards_get_pools_by_args', false, array(
					'system' => $this->poolId,
					'force'  => true,
				));
				if ($pool)
					$this->pool = $pool->last();
			}
			if (!$this->pool)
				$this->pool = false;
			return $this->pool;
		} else {
			return false;
		}
	}

	public function getPoolId()
	{
		if ($this->pool)
			return $this->pool->getId();
		else if ($this->poolId)
			return $this->poolId;
		else
			return false;
	}

	public function getPoolName()
	{
		return $this->pool ? $this->pool->getName() : '';
	}

	public function getPoolType()
	{
		return $this->pool ? $this->pool->getOption('type') : '';
	}

	public function getPoolStatus()
	{
		if ($this->pool) {
			if ($this->pool->getOption('public'))
				return 'publish';
			else if ($this->pool->getOption('private'))
				return 'private';
			else
				return 'draft';
		}
		return '';
	}

	public function getStackName()
	{
		return $this->pool ? $this->pool->getStackId() : '';
	}

	public function setName($name)
	{
		$this->name = $name;
	}

	public function getName($pool = null)
	{
		if (false !== $this->name)
			return $this->name;
		else if ($this->getPool())
			return $this->getPool()->getName() . '-' . $this->getType();
		else if ($pool)
			return $pool->getName() . '-' . $this->getType();
		else
			return $this->getType();
	}

	public function getId()
	{
		return $this->id ? intval($this->id) : false;
	}

	public function detach()
	{
		$this->id = false;
	}

	/** The user already purchased/unlocked it and cannot do it a second time.
	 * @return bool (always false here). */
	public function noMorePurchase($userId)
	{
		return false;
	}

	/** The unlockable is officially purchasable and required points are enough. */
	public function isPurchasable($points = PHP_INT_MAX, $userId = null)
	{
		$cost = $this->getCost();
		return (0 < $cost) && ($cost <= $points) && $this->inDateRange() && $this->isRedeemable($userId);
	}

	static public $maxRedeemAllowed = false;
	protected $maxRedeem = false;

	/** if feature allowed and set to greater than zero,
	 *	unlockable cannot be purchased more than that. */
	protected function isRedeemable($userId)
	{
		if (!$userId)
			return true;
		$max = $this->getMaxRedeem();
		if (!$max)
			return true;
		$count = \intval(\get_user_meta($userId, 'lws_wr_redeemed_' . $this->getId(), true));
		return ($count < $max);
	}

	public function getMaxRedeem()
	{
		return (self::$maxRedeemAllowed && (null !== $this->maxRedeem) && $this->maxRedeem) ? \absint($this->maxRedeem) : false;
	}

	public function setMaxRedeem($maxRedeem)
	{
		$this->maxRedeem = ($maxRedeem ? \absint($maxRedeem) : false);
		return $this;
	}

	public function incrRedeemCount($userId, $step=1)
	{
		if ($userId && ($max = $this->getMaxRedeem()) && ($id = $this->getId())) {
			$key = 'lws_wr_redeemed_' . $id;
			$count = \intval(\get_user_meta($userId, $key, true));
			\update_user_meta($userId, $key, $step + $count);
		}
	}

	/** The unlockable can be automatically applied to the cart */
	public function isAutoApplicable()
	{
		return false;
	}

	/** Default ignore $userId @see getCost() */
	public function getUserCost($userId, $context = 'pay')
	{
		return $this->getCost($context);
	}

	/** Multiplier is registered by Pool, it is applied to the points generated by the event. */
	public function getCost($context = 'edit')
	{
		$cost = $this->cost;
		if ($context == 'view' || $context == 'front') {
			if ($cost <= 0)
				return _x("Not redeemable", "Cannot be redeemed", 'woorewards-lite');
			else if (!$this->inDateRange())
				return sprintf(_x("%d [Delayed]", "Out of valid date range", 'woorewards-lite'), $cost);
		}
		return $cost;
	}

	public function setCost($cost)
	{
		$this->cost = $cost;
		return $this;
	}

	/** to fill the unlockable when used as demo in stygen */
	public function setTestValues()
	{
	}

	/** To be overriden to provide choice to administrator. */
	function getInformation()
	{
		return array(
			'label' => $this->getDisplayType(),
			'icon'  => false, /// (string) html
			'color' => false, /// (string) css color format
			'short' => $this->getDescription(),
			'help'  => '',
		);
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array(
			\LWS\WOOREWARDS\Core\Pool::T_STANDARD  => __("Standard", 'woorewards-lite'),
			\LWS\WOOREWARDS\Core\Pool::T_LEVELLING => __("Leveling", 'woorewards-lite'),
			'custom'    => __("Events", 'woorewards-lite')
		);
	}

	/** send a mail about the newly generated reward, used by apply. @see createReward */
	public function sendMail(\WP_User $user, $reward = array(), $mailTemplate = 'wr_new_reward')
	{
		if (!empty(\get_option('lws_woorewards_enabled_mail_' . $mailTemplate, ''))) {
			\do_action(
				'lws_mail_send',
				$user->user_email,
				$mailTemplate,
				array(
					'user'       => $user,
					'type'       => $this->getType(),
					'unlockable' => $this,
					'reward'     => $reward
				)
			);
		}
	}
}
