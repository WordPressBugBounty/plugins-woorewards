<?php
namespace LWS\WOOREWARDS\Collections;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOREWARDS_INCLUDES . '/abstracts/unlockable.php';

/** A collection of Unlockable. */
class Unlockables extends \LWS\WOOREWARDS\Abstracts\Collection
{
	/** read unlockable from database.
	 * @param array args @see WP_Query::parse_query() */
	public function load($args=array())
	{
		$cache = false;
		if( isset($args['cache']) )
		{
			$cache = $args['cache'];
			unset($args['cache']);
		}
		$this->items = array();
		$args = \wp_parse_args($args, array(
			'numberposts' => -1,
			'post_type' => \LWS\WOOREWARDS\Abstracts\Unlockable::POST_TYPE,
			'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
			'orderby' => array('menu_order' => 'DESC', 'ID' => 'ASC'),
		));
		if( $cache === true || $cache === false )
		{
			$args = array_merge($args, array(
				'update_post_meta_cache' => $cache,
				'update_post_term_cache' => $cache,
				'cache_results'  => $cache
			));
		}
		$posts = isset($args['post_parent']) ? \get_children($args) : \get_posts($args);
		if( !is_array($posts) )
		{
			error_log("Fail to load unlockables, check posts for ".print_r($args, true));
		}
		else
		{
			foreach( $posts as $post )
			{
				if( !\wp_is_post_revision($post) && !empty($unlockable = \LWS\WOOREWARDS\Abstracts\Unlockable::fromPost($post)) )
					$this->items[] = $unlockable;
			}
		}
		return $this;
	}

	/** Instanciate all registered types. */
	public function create($ref=false)
	{
		if( $ref === false )
		{
			$this->items = array();
			foreach( \LWS\WOOREWARDS\Abstracts\Unlockable::getRegistered() as $reg )
			{
				if( !empty($unlockable = \LWS\WOOREWARDS\Abstracts\Unlockable::instanciate($reg)) )
					$this->items[$unlockable->getName()] = $unlockable;
			}
		}
		else
		{
			$reg = \LWS\WOOREWARDS\Abstracts\Unlockable::getRegisteredByName($ref);
			if( !empty($reg) && !empty($unlockable = \LWS\WOOREWARDS\Abstracts\Unlockable::instanciate($reg)) )
				$this->add($unlockable);
			else
				error_log("Cannot found MyRewards Unlockable type: ".print_r($ref, true));
		}
		return $this;
	}

	public function save(\LWS\WOOREWARDS\Core\Pool &$pool)
	{
		foreach( $this->items as &$unlockable )
		{
			$unlockable->save($pool);
		}
		return $this;
	}

	/**  Sort by cost ASC */
	public function sort()
	{
		$this->usort(function($a, $b){
			$ca = $a->getCost();
			$cb = $b->getCost();
			if( $ca == $cb )
				return intval($a->getId()) - intval($b->getId());
			else
				return ($ca < $cb) ? -1 : 1;
		});
		return $this;
	}

	public function getTypes()
	{
		$types = array();
		foreach( $this->items as &$item )
			$types[$item->getType()] = true;
		return array_keys($types);
	}

	public function filterByType($type)
	{
		return $this->filter(function($item)use($type){return $item->getType() == $type;});
	}

	public function filterByCategories($cats)
	{
		if (!\is_array($cats))
			$cats = array($cats);
		return $this->filter(function($item)use($cats){
			return \array_intersect(\array_keys($item->getCategories()), $cats);
		});
	}
}
