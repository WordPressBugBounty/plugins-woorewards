<?php
namespace LWS\WOOREWARDS\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage set of Event/Unlockable with PointStack. */
class Pool
{
	const POST_TYPE = 'lws-wre-pool';

	const T_STANDARD = 'standard';
	const T_LEVELLING = 'levelling';

	protected $events = null;
	protected $unlockables = null;
	protected $points = null;
	protected $id = false;
	protected $display_title = false;
	protected $order = false; // menu_order, pool loading sorting

	protected $name                = '';               /// to get it by code (should be unique).
	protected $status              = 'draft';
	protected $title               = '';               /// unfiltered display name
	protected $displayName         = '';               /// to display to user
	protected $pointStackId        = false;            /// usually a pool has its own stack but some can share
	private   $deletable           = true;             /// the user can delete it (prefabs are not deletable)
	protected $type                = self::T_STANDARD; /// @enum {'standard', 'levelling'}
	protected $categoriesWhitelist = false;            /// to authorise only a set of Event and Unlockable in this pool
	protected $categoriesBlacklist = false;            /// to forbid few of Event and Unlockable in this pool
	protected $options             = null;             /// a basic class to store custom options
	protected $directRewardMode    = false;            /// Points are diectly converted to discount on cart
	protected $drmPointRate        = 1.0;              /// directRewardMode: value of a point in current WC currency
	protected $drmCats             = array();          /// directRewardMode restriction: assign category to virtual coupon

	/** A pool is active if set as activated */
	public function isActive()
	{
		$active = in_array($this->status, array('publish', 'private'));
		return \apply_filters('lws_woorewards_core_pool_is_active', $active, $this);
	}

	/** Prepare for pro version pool with dates */
	public function isBuyable()
	{
		return $this->isActive();
	}

	/** Add points to the pool point stack of a user.
	 *	@param $user (int) the user earning points.
	 *	@param $value (int) final number of point earned.
	 *	@param $reason (string) optional, the cause of the earning.
	 *	@param $origin (\LWS\WOOREWARDS\Abstracts\Event) optional, the source Event. */
	public function addPoints($userId, $value, $reason='', \LWS\WOOREWARDS\Abstracts\Event $origin=null, $origin2=false)
	{
		$value = \apply_filters('lws_woorewards_core_pool_point_add', $value, $userId, $reason, $this, $origin);
		$this->getStack($userId)->add($value, $reason, false, $origin ? $origin : '', $origin2);
		return $this;
	}

	/** Define the point amount of the pool point stack of a user.
	 *	@param $user (int) the user earning points.
	 *	@param $value (int) the point amount of a user to set.
	 *	@param $reason (string|\LWS\WOOREWARDS\Core\Trace) optional, the cause of the earning.
	 *	@param $origin (\LWS\WOOREWARDS\Abstracts\Event|\LWS\WOOREWARDS\Abstracts\Unlockable) optional, the source Event. */
	public function setPoints($userId, $value, $reason='', $origin=null, $origin2=false)
	{
		$value = \apply_filters('lws_woorewards_core_pool_point_set', $value, $userId, $reason, $this);
		$this->getStack($userId)->set($value, $reason, $origin ? $origin : '', $origin2);
		return $this;
	}

	/** Remove points from the pool point stack of a user.
	 *	@param $userId (int) the user earning points.
	 *	@param $value (int) the point amount of a user to substract.
	 *	@param $reason (string|\LWS\WOOREWARDS\Core\Trace) optional, the cause of the earning.
	 *	@param $origin (\LWS\WOOREWARDS\Abstracts\Unlockable) optional, the source Event. */
	public function usePoints($userId, $value, $reason='', \LWS\WOOREWARDS\Abstracts\Unlockable $origin=null, $origin2=false)
	{
		$value = \apply_filters('lws_woorewards_core_pool_point_sub', $value, $userId, $reason, $this, $origin);
		$this->getStack($userId)->sub($value, $reason, false, $origin ? $origin : '', $origin2);
		return $this;
	}

	/** Read the point amount of the pool point stack of a user.
	 *	@param $user (int) the user earning points.
	 * @return int */
	public function getPoints($userId)
	{
		return \apply_filters('lws_woorewards_core_pool_point_get', $this->getStack($userId)->get(), $this);
	}

	/** Based on user point, check possible unlockable.
	 *	Based on pool setting, apply it or mail user about a choice.
	 *	@param $user (int) the user who consume its points.
	 *	@return (int) the count of unlock. */
	public function tryUnlock($userId, $force=false)
	{
		$uCount = 0;
		if( empty($user = \get_user_by('ID', $userId)) )
		{
			error_log("Unlock reward attempt for unknown user ($userId). Pool ".$this->getId());
			return $uCount;
		}

		if( $this->directRewardMode )
			return $uCount;

		$tryUnlock = true;
		while( $tryUnlock )
		{
			$tryUnlock = false;
			$points = $this->getPoints($userId);
			$availables = $this->_getGrantedUnlockables($points, $user);

			if( $availables->count() >= 1 )
			{
				// immediate unlock
				$unlockable = $availables->last();
				if( $this->_applyUnlock($user, $unlockable) )
				{
					$tryUnlock = $this->_payAndContinue($userId, $unlockable);
					$uCount++;
				}
			}
			else
				return $uCount;
		}
		return $uCount;
	}

	/** @param $points (false|int) if false, this method will read it for given user. */
	public function isPurchasable($unlockable, $points=false, $userId=NULL)
	{
		if (!$this->isBuyable())
			return false;

		if (false === $points) {
			$points = (NULL === $userId ? PHP_INT_MAX : $this->getPoints($userId));
		}
		return $unlockable->isPurchasable($points, $userId);
	}

	/** @return \LWS\WOOREWARDS\Collections\Unlockables sorted by cost ASC. */
	public function getGrantedUnlockablesForUser($userId)
	{
		$points = $this->getPoints($userId);
		return $this->_getGrantedUnlockables($points, $userId);
	}

	/** @return \LWS\WOOREWARDS\Collections\Unlockables sorted by cost ASC. */
	public function getGrantedUnlockablesWithPoints($points)
	{
		return $this->_getGrantedUnlockables($points);
	}

	/** @return \LWS\WOOREWARDS\Collections\Unlockables sorted by cost ASC unlockable available with given point amount. */
	public function _getGrantedUnlockables($points, $user=null)
	{
		$availables = $this->unlockables->filter(function($item)use($points, $user){
			return $item->isPurchasable($points, \is_numeric($user) ? $user : $user->ID);
		});
		return $availables->sort();
	}

	/** apply the unlockable and keep a trace.
	 * @return (bool) if really unlocked */
	protected function _applyUnlock($user, &$unlockable)
	{
		if( $unlockable->apply($user) )
		{
			\do_action('lws_woorewards_core_pool_single_unlocking', $unlockable, $user, $this);
			$userId = is_numeric($user) ? $user : $user->ID;
			if( !empty($userId) && !empty($unlockable->getId()) && !in_array($unlockable->getId(), \get_user_meta($userId, 'lws_wre_unlocked_id', false)) )
			{
				\add_user_meta($userId, 'lws_wre_unlocked_id', $unlockable->getId(), false);
			}
			return true;
		}
		return false;
	}

	/**	Consume any required points.
	 *	@return bool if we can try to unlock more. */
	protected function _payAndContinue($userId, &$unlockable)
	{
		$cost = \method_exists($unlockable, 'getUserCost') ? $unlockable->getUserCost($userId) : $unlockable->getCost('pay');
		if( $cost > 0 )
			$this->usePoints($userId, $cost, $unlockable->getRawReason(), $unlockable);
		return ($cost > 0);
	}

	/** Some configuration sets are relevant as specific pool kind.
	 *	@return array of option */
	public function getDefaultConfiguration($type)
	{
		$config = array();
		if( $type == \LWS\WOOREWARDS\Core\Pool::T_STANDARD )
		{
			$config = array(
				'public'         => true
			);
		}
		return \apply_filters('lws_woorewards_core_pool_default_configuration', $config, $type);
	}

	/** @param (string) option name.
	 * For option list @see getOption()
	 *
	 * Options are:
	 * * public        : (bool) enabled and visible to customer (public, private and disabled are exclusive).
	 * * private       : (bool) enable but not visible for customer (public, private and disabled are exclusive).
	 * * disabled      : (bool) not visible and not activated (public, private and disabled are exclusive).
	 * * title         : (string) unfiltered title.
	 * * stack         : (string) name of point stack, usually same as pool name. Generate a unique name if new value is empty.
	 * * type          : (string) [standard, levelling] Apply a set of default configuration (use it carefully).
	 * * whitelist     : (array of string) Event and Unlockable category that could be used with this pool.
	 * * whitelist+    : same as 'whitelist' but append to existant.
	 * * whitelist-    : same as 'whitelist' but remove from existant.
	 * * blacklist     : (array of string) Event and Unlockable category that could not be used with this pool.
	 * * blacklist+    : same as 'blacklist' but append to existant.
	 * * blacklist-    : same as 'blacklist' but remove from existant.
	 * * direct_reward_mode               : (bool) directRewardMode: value of a point in current WC currency
	 * * direct_reward_point_rate         : (float) directRewardMode: value of a point in current WC currency
	 * * direct_reward_discount_cats      : (int[]) categories applied on the virtual coupon
	 **/
	public function setOption($option, $value)
	{
		switch($option)
		{
			case 'public':
				$this->status = (boolval($value) ? 'publish' : 'draft');
				break;
			case 'private':
				$this->status = (boolval($value) ? 'private' : 'draft');
				break;
			case 'disabled':
				$this->status = (boolval($value) ? 'draft' : 'publish');
				break;
			case 'enabled':
				if( boolval($value) )
				{
					if( !in_array($this->status, array('publish', 'private')) )
						$this->status = 'publish';
				}
				else
					$this->status = 'draft';
				break;
			case 'title':
				$this->title = \esc_html(\trim($value));
				$this->display_title = false;
				break;
			case 'stack':
				if( !$value )
				{
					$this->pointStackId = '';
					$this->initUniqueStackId();
					$value = $this->pointStackId;
				}
				else
					$this->setStackId(\sanitize_key($value));
				break;
			case 'type':
				$value = \trim($value);
				$config = $this->getDefaultConfiguration($value);
				if( isset($config['type']) )
					unset($config['type']); // sure someone will have fun to make infinite loop som day
				$this->setOptions($config);
				$this->type = $value;
				break;
			case 'whitelist':
				$this->categoriesWhitelist = (empty($value) ? false : (is_array($value) ? $value : array(\trim($value))));
				break;
			case 'whitelist+':
				$this->categoriesWhitelist = array_unique(array_merge($this->categoriesWhitelist, is_array($value) ? $value : array(\trim($value))), SORT_REGULAR);
				break;
			case 'whitelist-':
				$this->categoriesWhitelist = array_diff($this->categoriesWhitelist, is_array($value) ? $value : array(\trim($value)));
				break;
			case 'blacklist':
				$this->categoriesBlacklist = (empty($value) ? false : (is_array($value) ? $value : array(\trim($value))));
				break;
			case 'blacklist+':
				$this->categoriesBlacklist = array_unique(array_merge($this->categoriesBlacklist, is_array($value) ? $value : array(\trim($value))), SORT_REGULAR);
				break;
			case 'blacklist-':
				$this->categoriesBlacklist = array_diff($this->categoriesBlacklist, is_array($value) ? $value : array(\trim($value)));
				break;
			case 'direct_reward_mode':
				$this->directRewardMode = \boolval($value);
				break;
			case 'direct_reward_point_rate':
				$value = \str_replace(',', '.', \trim($value));
				if (\is_numeric($value))
					$this->drmPointRate = \abs(\floatval($value));
				break;
			case 'direct_reward_discount_cats':
				if ($value)
					$this->drmCats = (\is_array($value) ? $value : array($value));
				else
					$this->drmCats = array();
				break;
			default:
				if( !$this->_setCustomOption($option, $value) )
					$this->options = \apply_filters('lws_woorewards_core_pool_custom_option_value_set', $this->options, $option, $value, $this);
		}
		\do_action('lws_woorewards_core_pool_option_set', $this, $option, $value);
		return $this;
	}

	/** @return bool the option is accepted. */
	protected function _setCustomOption($option, $value)
	{
		return false;
	}

	/** @param (string) option name
	 * @param $default return that value if option does not exists.
	 *
	 * Options are:
	 * * public        : (bool) enabled and visible to customer (public, private and disabled are exclusive).
	 * * private       : (bool) enable but not visible for customer (public, private and disabled are exclusive).
	 * * disabled      : (bool) not visible and not activated (public, private and disabled are exclusive).
	 * * title         : (string) unfiltered title.
	 * * display_title : (string) filtered title (see get_title hook from WP_Post) cannot be active after recording.
	 * * stack         : (string) name of point stack, usually same as pool name.
	 * * type          : (string) [standard, levelling] define a set of default option (read-only for not advanced user).
	 * * whitelist     : (array of string) Event and Unlockable category that could be used with this pool.
	 * * blacklist     : (array of string) Event and Unlockable category that could not be used with this pool.
	 * * direct_reward_mode               : (bool) directRewardMode: value of a point in current WC currency
	 * * direct_reward_point_rate         : (float) directRewardMode: value of a point in current WC currency
	 * * direct_reward_discount_cats      : (int[]) categories applied on the virtual coupon
	 **/
	public function getOption($option, $default=null)
	{
		$value = $default;
		switch($option)
		{
			case 'public':
				$value = ($this->status == 'publish');
				break;
			case 'private':
				$value = ($this->status == 'private');
				break;
			case 'disabled':
				$value = !in_array($this->status, array('publish', 'private'));
				break;
			case 'enabled':
				$value = in_array($this->status, array('publish', 'private'));
				break;
			case 'title':
				$value = $this->title;
				break;
			case 'display_title':
				if (false !== $this->display_title)
					$value = $this->display_title;
				else
				{
					$this->display_title = (empty($this->displayName) ? $this->title : $this->displayName);
 					$this->display_title = \apply_filters('wpml_translate_string', $this->display_title, 'title', $this->getPackageWPML());
					$this->display_title = \apply_filters('the_title', $this->display_title, $this->getId());
					$value = $this->display_title;
				}
				break;
			case 'stack':
				$value = $this->getStackId();
				break;
			case 'type':
				$value = $this->type;
				break;
			case 'whitelist':
				$value = empty($this->categoriesWhitelist) ? array() : $this->categoriesWhitelist;
				break;
			case 'blacklist':
				$value = empty($this->categoriesBlacklist) ? array() : $this->categoriesBlacklist;
				break;
			case 'direct_reward_mode':
				$value = $this->directRewardMode;
				break;
			case 'direct_reward_point_rate':
				$value = $this->drmPointRate;
				break;
			case 'direct_reward_discount_cats':
				if ($this->drmCats && \is_array($this->drmCats))
					$value = $this->drmCats;
				else
					$value = array();
				break;
			default:
				$value = $this->_getCustomOption($option, $default);
		}
		return \apply_filters('lws_woorewards_core_pool_option_value_get', $value, $option, $this);
	}

	/** @param $accepted (in/out bool) the option is recognized.
	 * @return $default if $option is unknown. */
	protected function _getCustomOption($option, $default)
	{
		return $default;
	}

	public function setOptions($options)
	{
		foreach($options as $option => $value)
			$this->setOption($option, $value);
		return $this;
	}

	/** @return array associative of option values.
	 * One entry for each item in $options, that item is used as key for returned array.
	 * @param $options array of option name.
	 * @param $default associative array [option_name => default_value] */
	public function getOptions($options, $default=array())
	{
		$values = array();
		foreach($options as $option)
		{
			$values[$option] = $this->getOption($option, isset($default[$option]) ? $default[$option] : null);
		}
		return $values;
	}

	/** @return \LWS\WOOREWARDS\Collections\Events the added item or false on failure.
	 * Take care name could be changed to avoid duplication. */
	public function addEvent(\LWS\WOOREWARDS\Abstracts\Event $event, $multiplier=false)
	{
		$event->setPool($this);
		if( $multiplier !== false )
			$event->setGain($multiplier);
		return $this->events->add($event);
	}

	/** @return \LWS\WOOREWARDS\Collections\Unlockables the added item or false on failure.
	 * Take care name could be changed to avoid duplication. */
	public function addUnlockable(\LWS\WOOREWARDS\Abstracts\Unlockable $unlockable, $cost=false)
	{
		$unlockable->setPool($this);
		if( $cost !== false )
			$unlockable->setCost($cost);
		return $this->unlockables->add($unlockable);
	}

	/** update, replace or add the event in this pool.
	 * Events are looked by id, then name. */
	public function updateEvent(\LWS\WOOREWARDS\Abstracts\Event $event)
	{
		$event->setPool($this);
		$this->events->update($event);
		return $this;
	}

	/** update, replace or add the unlockable in this pool.
	 * Unlockables are looked by id, then name. */
	public function updateUnlockable(\LWS\WOOREWARDS\Abstracts\Unlockable $unlockable)
	{
		$unlockable->setPool($this);
		$this->unlockables->update($unlockable);
		return $this;
	}

	/** @return \LWS\WOOREWARDS\Collections\Unlockables the registered rewards collection. */
	public function getUnlockables()
	{
		return $this->unlockables;
	}

	/** @return\LWS\WOOREWARDS\Collections\Events the registered event collection. */
	public function getEvents()
	{
		return $this->events;
	}

	/** @param $unlockable (\LWS\WOOREWARDS\Abstracts\Event|string) instance or name.
	 * @return mixed Event the instance or false if not found. */
	public function findEvent($event)
	{
		return $this->events->find($event);
	}

	/** @param $unlockable (\LWS\WOOREWARDS\Abstracts\Unlockable|string) instance or name.
	 * @return \LWS\WOOREWARDS\Abstracts\Unlockable the instance or false if not found. */
	public function findUnlockable($unlockable)
	{
		return $this->unlockables->find($unlockable);
	}

	/** @param $unlockable (\LWS\WOOREWARDS\Abstracts\Event|string) instance or name.
	 * @return \LWS\WOOREWARDS\Abstracts\Event the removed item or false if not found. */
	public function removeEvent($event)
	{
		return $this->events->remove($event);
	}

	/** @param $unlockable (\LWS\WOOREWARDS\Abstracts\Unlockable|string) instance or name.
	 * @return \LWS\WOOREWARDS\Abstracts\Unlockable the removed item or false if not found. */
	public function removeUnlockable($unlockable)
	{
		return $this->unlockables->remove($unlockable);
	}

	/** @return \LWS\WOOREWARDS\Core\PointStack instance. */
	public function getStack($userId)
	{
		return $this->points->create($this->getStackId(), $userId, $this);
	}

	public function getStackId()
	{
		static $stackNameLenMax = 230;
		return empty($this->pointStackId) ? substr($this->getName(), 0, $stackNameLenMax) : $this->pointStackId;
	}

	public function getRawStackId()
	{
		return $this->pointStackId;
	}

	public function setStackId($name)
	{
		$this->pointStackId = $name;
		return $this;
	}

	/** generate a stack id through all the pool.
	 * Do nothing if pointStackId already has a value. */
	protected function initUniqueStackId()
	{
		if( empty($this->pointStackId) )
		{
			// get all existant pointStackId
			global $wpdb;
			$stackIds = $wpdb->get_col("SELECT DISTINCT(meta_value) FROM {$wpdb->postmeta} WHERE meta_key='wre_pool_point_stack'");
			$stackIds = \array_filter($stackIds);
			// default name is pool name, but we ensure a not empty name
			if( empty($base = trim($this->getStackId())) )
				$base = 'noname';
			// ensure name unicity
			$this->pointStackId = \LWS\WOOREWARDS\Abstracts\Collection::getNewName($base, $stackIds);
		}
		return $this;
	}

	/** @param $force (bool) direct set without clean or tests, default is false. */
	public function setName($name, $force=false)
	{
		if( $force )
		{
			$this->name = $name;
		}
		else
		{
			$name = \remove_accents(strtolower(\trim($name)));
			$name = preg_replace(array('/\s*-\s*/', '/[\s_]+/'), array('-', '_'), $name);
			$this->name = \sanitize_key($name);
			if( empty($this->name) )
				$this->name = substr(md5($name), 0, 8);
			if( empty($this->name) )
				$this->name = 'sys';
		}
		return $this;
	}

	public function getPostType()
	{
		return self::POST_TYPE;
	}

	protected function getSimilarPostTypes()
	{
		return array(self::POST_TYPE);
	}

	/** Ensure current name does not exists in database.
	 * Else increment a counter a end of current name. */
	public function ensureNameUnicity()
	{
		global $wpdb;
		$post_id = intval($this->getId());
		$typeCond = ("post_type IN ('" . implode("','", $this->getSimilarPostTypes()) . "')");
		$names = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT(post_name) FROM {$wpdb->posts} WHERE {$typeCond} AND ID<>%d", $post_id)); // phpcs:ignore WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->setName(\LWS\WOOREWARDS\Abstracts\Collection::getNewName($this->getName(), $names));
	}

	/** Initialise the pool.
	 *	@param $load (bool) try to load events and unlockables too. */
	static public function fromPost(\WP_Post $post, $load=true)
	{
		$pool = new static();
		$pool->name   = $post->post_name;
		$pool->status = $post->post_status;
		$pool->title  = $post->post_title;
		$pool->order  = $post->menu_order;

		if( !empty($post->ID) )
		{
			$pool->id = intval($post->ID);
			$pool->pointStackId        = \get_post_meta($post->ID, 'wre_pool_point_stack', true);
			$pool->deletable           = !boolval(\get_post_meta($post->ID, 'wre_pool_prefab', true));
			$pool->type                = \get_post_meta($post->ID, 'wre_pool_type', true);
			$pool->categoriesWhitelist = \get_post_meta($post->ID, 'wre_pool_whitelist', true);
			$pool->categoriesBlacklist = \get_post_meta($post->ID, 'wre_pool_blacklist', true);
			$options                   = \get_post_meta($post->ID, 'wre_pool_options', true);
			if( !empty($options) )
				$pool->options = $options;

			$pool->directRewardMode = \boolval($pool->getSinglePostMetaIfExists($post->ID, 'wre_pool_direct_reward_mode', false));
			if ($pool->directRewardMode) {
				$pool->drmPointRate = \floatval($pool->getSinglePostMetaIfExists($post->ID, 'wre_pool_direct_reward_point_rate', 1.0));
				$pool->drmCats      = $pool->getSinglePostMetaIfExists($post->ID, 'wre_pool_direct_reward_discount_cats', array());
			}

			if( $load )
				$pool->subLoad();
			$pool->_customLoad($post, $load);
		}
		return \apply_filters('lws_woorewards_core_pool_from_post', $pool, $post, $load);
	}

	protected function getSinglePostMetaIfExists($postId, $meta, $default=false)
	{
		$meta = \get_post_meta($postId, $meta, false);
		return $meta ? reset($meta) : $default;
	}

	protected function _customLoad(\WP_Post $post, $load=true)
	{
		return $this;
	}

	/** load events and unlockable. Should be done by fromPost in most cases. */
	public function subLoad()
	{
		$pool = &$this;
		$this->events->load(array('post_parent' => $this->getId()));
		$this->events->apply(function(&$item) use($pool) {$item->setPool($pool);});
		$this->unlockables->load(array('post_parent' => $this->getId()));
		$this->unlockables->apply(function(&$item) use($pool) {$item->setPool($pool);});
	}

	/** @see https://wpml.org/documentation/support/string-package-translation
	 * Known wpml bug: kind first letter must be uppercase */
	function getPackageWPML($full=false)
	{
		$pack = array(
			'kind' => 'WooRewards Loyalty System',//strtoupper(self::POST_TYPE),
			'name' => $this->getId(),
		);
		if( $full )
		{
			$pack['title'] = $this->getOption('title');
			$pack['edit_link'] = \add_query_arg(array('page'=>LWS_WOOREWARDS_PAGE.'.loyalty', 'tab'=>'wr_loyalty.'.$this->getTabId()), admin_url('admin.php'));
		}
		return $pack;
	}

	/** Save this pool in database. */
	public function save($withEvents=true, $withUnlockables=true)
	{
		/// Creation with a name, no need to specify the stack. Mainly for auto-creation (updater, wizards, etc.)
		if( !$this->id && $this->name && !$this->pointStackId )
			$this->initUniqueStackId();

		$data = array(
			'ID'          => intval($this->id),
			'post_name'   => $this->name,
			'post_title'  => $this->title,
			'post_status' => $this->status,
			'post_type'   => $this->getPostType(),
			'menu_order'  => (false !== $this->order) ? $this->order : ($this->isDeletable() ? 1024 : 0),
			'meta_input'  => array(
				'wre_pool_point_stack' => $this->getStackId(),
				'wre_pool_type' => $this->type,
				'wre_pool_whitelist' => $this->categoriesWhitelist,
				'wre_pool_blacklist' => $this->categoriesBlacklist,
				'wre_pool_options'  => $this->options
			)
		);

		if( !$this->directRewardMode )
			$this->drmPointRate = 1.0; // reset
		$data['meta_input']['wre_pool_direct_reward_mode']          = ($this->directRewardMode ? 'on' : '');
		$data['meta_input']['wre_pool_direct_reward_point_rate']    = $this->drmPointRate;
		$data['meta_input']['wre_pool_direct_reward_discount_cats'] = $this->drmCats;

		$postId = $data['ID'] ? \wp_update_post($data, true) : \wp_insert_post($data, true);
		if( \is_wp_error($postId) )
		{
			error_log("Error occured during pool saving: " . $postId->get_error_message());
			\lws_admin_add_notice_once('lws-wre-pool-save', __("Error occured during reward system saving.", 'woorewards-lite'), array('level'=>'error'));
			return $this;
		}
		$this->id = intval($postId);
		if ($this->id) {
			\lws_admin_delete_notice('lws-wre-pool-nothing-loaded');
		}

		\do_action('wpml_register_string', $this->title, 'title', $this->getPackageWPML(true), __("Title", 'woorewards-lite'), 'LINE');

		if( $withEvents )
			$this->events->save($this);
		if( $withUnlockables )
			$this->unlockables->save($this);

		$this->_customSave($withEvents, $withUnlockables);
		\do_action('lws_woorewards_core_pool_saved', $this, $withEvents, $withUnlockables);

		return $this;
	}

	protected function _customSave($withEvents=true, $withUnlockables=true)
	{
		return $this;
	}

	/** @param $user (false|int|\WP_User)
	 * @return true if the user can interact with pool. */
	public function userCan($user=false)
	{
		return true;
	}

	public function isDeletable()
	{
		return $this->deletable;
	}

	public function delete($delEvents=true, $delUnlockables=true, $force=false)
	{
		\do_action('lws_woorewards_core_pool_delete_before', $this);
		if( !($this->isDeletable() || $force) )
		{
			error_log("Try to delete a prefab pool: " . $this->getName());
		}
		else if ($this->id)
		{
			$stack = $this->getStack(0);

			if( $delEvents )
				$this->events->apply(function(&$item){$item->delete();});

			if( $delUnlockables )
				$this->unlockables->apply(function(&$item){$item->delete();});

			if( empty(\wp_delete_post($this->id, true)) )
			{
				error_log("Failed to delete the pool {$this->id}");
				\lws_admin_add_notice_once(
					'lws-wre-pool-delete-error',
					sprintf(
						__("Failed to delete the reward system <b>%s</b>/%s.", 'woorewards-lite'),
						$this->title,
						$this->name
					),
					array('level'=>'error')
				);
			}
			else
			{
				// if stack not used anymore, delete it
				if( !$stack->isUsed() )
					$stack->delete();

				$pack = $this->getPackageWPML();
				\do_action('wpml_delete_package_action', $pack['name'], $pack['kind']);

				$this->id = false;
				\lws_admin_add_notice_once(
					'lws-wre-pool-delete',
					sprintf(
						__("The reward system <b>%s</b>/%s successfully deleted.", 'woorewards-lite'),
						$this->title,
						$this->name
					),
					array('level'=>'success')
				);
			}
		}
		return $this;
	}

	protected function getTriggerStatus()
	{
		$status = \apply_filters('lws_woorewards_order_events', array('processing', 'completed'));
		return \array_unique((array)$status);
	}

	/** Register all the Hooks required to run points gain events and unlockables.
	 *	Must be called only once per active pool. */
	public function install()
	{
		if( $this->isActive() )
		{
			\add_action('lws_woorewards_pool_on_order_done', array($this, 'triggerOrderDone'), 10, 2);
			foreach ($this->getTriggerStatus() as $s) {
				\add_action('woocommerce_order_status_' . $s, array($this, 'triggerOrderDone'), 99, 2); // priority late to let someone change amount and wc to save order
			}
			\add_action('woocommerce_checkout_order_processed', array($this, 'legacyMaybeTriggerOrderDone'), 99, 3);
			\add_action('woocommerce_store_api_checkout_order_processed', array($this, 'maybeTriggerOrderDone'), 99, 1);

			\do_action('lws_woorewards_core_pool_install', $this);
			$this->events->filter(function($e){return $e->isValidGain(true);})->install();
		}
		return $this;
	}

	/**	@see maybePay */
	function legacyMaybeTriggerOrderDone($orderId, $postedData, $order)
	{
		$this->maybeTriggerOrderDone($order);
	}

	/**	To be hooked at order creation.
	 *	If the initial order status is already one of the selected one.
	 *	Since it will trigger no action on change. */
	public function maybeTriggerOrderDone($order)
	{
		if (\in_array($order->get_status(), $this->getTriggerStatus())) {
			$this->triggerOrderDone($order->get_id(), $order);
		}
	}

	/** try to remove points format if any.
	 *	Provided to interpret for user input, if he include
	 *	any formatting by hand or copy/past for example. */
	public function reversePointsFormat($input)
	{
		return $input;
	}

	/** apply format as much as possible but keep value as numeric */
	public function formatPointsNumber($points)
	{
		return $points;
	}

	public function formatPoints($points, $withSym=true, $plainText=false)
	{
		if ($withSym) {
			return sprintf("%s %s", $points, $this->getSymbol($points));
		} else {
			return $points;
		}
	}

	public function getSymbol($count = 1)
	{
		return \LWS_WooRewards::getPointSymbol($count, $this->getName());
	}

	public function triggerOrderDone($order_id, $order)
	{
		$onceKey = \LWS\WOOREWARDS\Abstracts\Event::formatType(__CLASS__) . '-' . $this->getId();
		// we cannot use $order->get_meta directly since some other poorly coded plugins
		// keep unsync order instances and then overwrite our meta (and status changes to completed several times from different origin status)
		$onceFlag = \LWS\Adminpanel\Tools\Conveniences::getOrderMeta($order_id, $onceKey, true);
		if (!\apply_filters('lws_woorewards_wc_order_get_processed_flag', $onceFlag, $order, $this)) {
			//$order->update_meta_data($onceKey, \date(DATE_W3C)); $order->save_meta_data();
			\LWS\Adminpanel\Tools\Conveniences::updateOrderMeta($order, $onceKey, \date(DATE_W3C));

			$action = \apply_filters('lws_woorewards_wc_order_parse_points', self::parseOrder($order_id, $order), $order, $this);
			\do_action('lws_woorewards_wc_order_trigger_order_done', $action, $order, $this);
			\apply_filters('lws_woorewards_wc_order_done_'.$this->getName(), $action);

			// add a note about this
			$points = $this->getPointsOnOrder($order);
			if ($points) {
				foreach ($points as $userId => $data) {
					$user = \get_user_by('ID', $userId);
					if ($user) {
						$name = $user->display_name ? $user->display_name : $user->user_login;
					} else {
						$name = sprintf('user[%d]', $userId);
					}
					\LWS\WOOREWARDS\Core\OrderNote::add($order, sprintf(
						__('<b>%1$s</b> earned <b>%2$s</b> in <b>%3$s</b> for this order', 'woorewards-lite'),
						$name, $this->formatPoints($data->points), $this->getOption('title')
					), $this);
				}
			} elseif ($this->getEvents()->filterByCategories('order')->count()) {
				// only show no points if an event is about order
				\LWS\WOOREWARDS\Core\OrderNote::add($order, sprintf(
					__('No %1$s earned in <b>%2$s</b> for this order', 'woorewards-lite'),
					$this->getSymbol(), $this->getOption('title')
				), $this);
			}

			\do_action('lws_woorewards_wc_order_after_trigger_order_done', $action, $order, $this);
		}
	}

	/** look back at history to get point amount given for that order. */
	public function getPointsOnOrder($order, $eventCollection=false)
	{
		if (!$eventCollection)
			$eventCollection = $this->getEvents();
		$origins = $eventCollection->map(function($e) {return (int)$e->getId();});
		if ($origins) {
			global $wpdb;
			$origins = \implode(',', $origins);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery
			return $wpdb->get_results($wpdb->prepare("SELECT `user_id`, SUM(`points_moved`) as `points` FROM {$wpdb->lwsWooRewardsHistoric}
WHERE `origin` IN ({$origins}) AND `order_id`=%d AND `points_moved` IS NOT NULL AND `blog_id`=%d
GROUP BY `user_id`", (int)$order->get_id(), (int)\get_current_blog_id()), OBJECT_K);
		}
		return array();
	}

	/** Build the historical structure casted to events for points.
	 * @param $order \WC_Order
	 * @return object */
	static public function parseOrder($order_id, $order)
	{
		$action = (object)array(
			'order_id' => $order_id,
			'order'    => $order,
			'items'    => array(),
			'inc_tax'  => !empty(\get_option('lws_woorewards_order_amount_includes_taxes', ''))
		);
		$action->amount = 0.0; // compute amount since wc is not able to return tax before discount
		foreach( $order->get_items() as $item )
		{
			$itemAmount = floatval($order->get_line_subtotal($item, $action->inc_tax, false)); // this is the cost before discount.
			$action->amount += $itemAmount;
			$action->items[] = (object)array(
				'item'   => $item,
				'amount' => $itemAmount,
			);
		}
		return $action;
	}

	public function getId()
	{
		return $this->id ? intval($this->id) : false;
	}

	/** @return string */
	public function getName()
	{
		return $this->name;
	}

	public function getTabId($prefix='wr_upool_')
	{
		$suffix = $this->getId();
		if (!$suffix && $this->name)
			$suffix = $this->name;
		return ($prefix . $suffix);
	}

	protected function get_meta_datetime($postId, $metaKey, $default=false, $midnight=true)
	{
		$value = \get_post_meta($postId, $metaKey, true);
		$value = $value ? \date_create($value, \function_exists('\wp_timezone') ? \wp_timezone() : NULL) : $default;
		if( $value && $midnight )
			$value->setTime(0, 0);
		return $value;
	}

	public function __construct($name='')
	{
		$this->options = new \stdClass();
		$this->name = $name;
		$this->points = new \LWS\WOOREWARDS\Collections\PointStacks();
		$this->events = new \LWS\WOOREWARDS\Collections\Events();
		$this->unlockables = new \LWS\WOOREWARDS\Collections\Unlockables();
	}

	public function detach($deep=true)
	{
		$this->id = false;
		if ($deep) {
			$this->getEvents()->apply(function($item){$item->detach();});
			$this->getUnlockables()->apply(function($item){$item->detach();});
		}
	}

	/** sort two pools. */
	function cmp($a, $b)
	{
		$aPrefab = !$a->isDeletable();
		$bPrefab = !$b->isDeletable();
		if ($aPrefab != $bPrefab) {
			return $aPrefab ? -1 : 1;
		} else if ($aPrefab && $a->type != $b->type) {
			return $a->type == self::T_STANDARD ? -1 : 1;
		}

		if( !isset($a->cmpData) ) {
			$a->cmpData = array(
//				'label' => $a->getOption('display_title'),
				'loading' => \intval($a->getOption('loading_order', 1024)),
				'enabled' => in_array($a->status, array('publish', 'private'))
			);
		}
		if( !isset($b->cmpData) ) {
			$b->cmpData = array(
//				'label' => $b->getOption('display_title'),
				'loading' => \intval($b->getOption('loading_order', 1024)),
				'enabled' => in_array($b->status, array('publish', 'private'))
			);
		}

		if( $a->cmpData['enabled'] != $b->cmpData['enabled'] )
			return $a->cmpData['enabled'] ? -1 : 1;
		if( $a->cmpData['loading'] != $b->cmpData['loading'] )
			return $b->cmpData['loading'] - $a->cmpData['loading'];
//		return strcasecmp($a->cmpData['label'], $b->cmpData['label']);
		return $a->getId() - $b->getId();
	}
}