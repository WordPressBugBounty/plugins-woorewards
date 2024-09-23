<?php
namespace LWS\WOOREWARDS\Collections;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOREWARDS_INCLUDES . '/core/pool.php';

/** A collection of Pool. */
class Pools extends \LWS\WOOREWARDS\Abstracts\Collection
{
	/** read pool from database.
	 * @param $args @see WP_Query::parse_query() with addionnal options:
	 * * deep (bool) if element have children, load them too.
	 * @param $deepLoad load events and unlockables too. */
	public function load($args=array())
	{
		$deepLoad = true;
		if( isset($args['deep']) )
		{
			$deepLoad = boolval($args['deep']);
			unset($args['deep']);
		}
		$cache = false;
		if( isset($args['cache']) )
		{
			$cache = $args['cache'];
			unset($args['cache']);
		}
		$this->items = array();

		$args = \wp_parse_args($args, $this->loadArgs());
		if( $cache === true || $cache === false )
		{
			$args = array_merge($args, array(
				'update_post_meta_cache' => $cache,
				'update_post_term_cache' => $cache,
				'cache_results'  => $cache
			));
		}
		$posts = \get_posts($args);

		if( !is_array($posts) )
		{
			error_log("Fail to load pools, check posts with post_type=".\LWS\WOOREWARDS\Core\Pool::POST_TYPE);
		}
		else if( count($posts) > 0 )
		{
			$classname = static::register();
			foreach( $posts as $post )
			{
				if( !\wp_is_post_revision($post) && !empty($pool = $classname::fromPost($post, $deepLoad)) )
					$this->items[] = $pool;
			}
		}

		return $this;
	}

	protected function loadArgs()
	{
		return array(
			'numberposts' => -1,
			'post_type' => \LWS\WOOREWARDS\Core\Pool::POST_TYPE,
			'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
			'orderby' => array('menu_order' => 'DESC', 'ID' => 'ASC'),
		);
	}

	/** Add a new Pool instance.
	 * @warning name can be change to be unique in this collection.
	 * @see last()
	 * @return $this */
	function create($ref=false)
	{
		$name = $this->uniqueName(empty($ref)?'':$ref);
		$classname = static::register();
		$this->items[$name] = \apply_filters('lws_woorewards_collections_pools_create', new $classname($name));
		return $this;
	}

	/** install each loaded pool. */
	public function install()
	{
		foreach( $this->items as &$pool )
			$pool->install();
		return $this;
	}

	public function save()
	{
		foreach( $this->items as &$pool )
			$pool->save();
		return $this;
	}

	static function register($fullclassname=false)
	{
		static $s_fullclassname = '\LWS\WOOREWARDS\Core\Pool';
		if( $fullclassname !== false )
			$s_fullclassname = $fullclassname;
		return $s_fullclassname;
	}

	public function sort()
	{
		if( !empty($this->items) )
		{
			$pool = $this->last();
			if( method_exists($pool, 'cmp') )
				return $this->usort(array($pool, 'cmp'));
		}
		return $this;
	}

	/** Return a Collection filtered by $refs.
	 *	Elements order of $refs is kept.
	 *	@param $refs (array) array of name, id or Pool instance. */
	public function filterByReferences($refs = array())
	{
		$classname = '\\' . get_class($this);
		$filtered = new $classname();
		if( !($refs && $this->items) )
			return $filtered;

		foreach ($refs as $ref)
		{
			$key = $this->findKey($ref);
			if( false !== $key )
				$filtered->items[$key] = $this->items[$key];
		}
		return $filtered;
	}

	public function filterByStackId($stackId)
	{
		return $this->filter(function($item)use($stackId){return $item->getStackId() == $stackId;});
	}

	public function filterByUserCan($userId)
	{
		return $this->filter(function($item)use($userId){return $item->userCan($userId);});
	}

	public function filterByType($poolType)
	{
		return $this->filter(function($item)use($poolType){return $item->getOption('type') == $poolType;});
	}
}
