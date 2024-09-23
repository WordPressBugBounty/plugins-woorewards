<?php
namespace LWS\WOOREWARDS\Abstracts;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Provide an associative array of category [code => label]. */
interface ICategorisable
{
	public function getCategories();
}

if( !function_exists('LWS\WOOREWARDS\Abstracts\filterCategories') )
{
	/**	An empty category list is never filtered out.
	 *	An empty whitelist is ignored.
	 *	@param $excepted id in this array are never filtered out.
	 *	@param $categorisables array of ICategorisable instances.
	 *	@param $blacklist array of category key.
	 *	@param $whitelist array of category key. */
	function filterCategories($categorisables, $blacklist=false, $whitelist=false, $excepted=array())
	{
		if( empty($blacklist) && empty($whitelist) )
			return $categorisables;

		$filtered = array();
		foreach( $categorisables as $id => $item )
		{
			if( in_array($id, $excepted) )
				$filtered[$id] = $item;
			else
			{
				$cats = array_keys($item->getCategories());
				if( empty($cats)
				|| ( (empty($blacklist) ||  empty(array_intersect($cats, $blacklist)))
					&&  (empty($whitelist) || !empty(array_intersect($cats, $whitelist))) )
				)
					$filtered[$id] = $item;
			}
		}
		return $filtered;
	}
}

?>