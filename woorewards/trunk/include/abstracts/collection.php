<?php
namespace LWS\WOOREWARDS\Abstracts;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** A collection of Element.
 * Element can be whatever we want. */
abstract class Collection
{
	protected $items = array();

	/** read elements from database.
	 * @param $args @see WP_Query::parse_query() with addionnal options:
	 * * deep (bool) if element have children, load them too.
	 * @return $this. */
	abstract public function load($args=array());

	/** Instanciate element of collection and add it.
	 * Behavior is different if element implement IRegistered:
	 * * IRegistered, try to use @param $ref as a type and create a single instance of that type. If ref is false, instanciate all registered types.
	 * * Else create a single instance of element and pass $ref to the constructor.
	 * @see last()
	 * @return $this. */
	abstract public function create($ref=false);

	/** Call the callable on each item in this collection.
	 * the item is given by reference to the callable. */
	public function apply($callable)
	{
		foreach( $this->items as &$item )
			call_user_func_array($callable, array(&$item));
		return $this;
	}

	/** Return a filtered collection.
	 *	If the resutl of a callable is true, the item is appended.
	 *	Note that item is NOT given by reference to the callable.
	 * @param $callable take an Unlockable as argument and return a boolean. */
	public function filter($callable)
	{
		$classname = '\\' . get_class($this);
		$filtered = new $classname();
		foreach( $this->items as $name => $item )
		{
			if( call_user_func($callable, $item) )
				$filtered->items[$name] = $item;
		}
		return $filtered;
	}

	public function map($callable)
	{
		return \array_map($callable, $this->items);
	}

	public function delete($object)
	{
		$this->remove($object);
		if( \method_exists($object, 'delete') )
			$object->delete();
		return $this;
	}

	/** Return a filtered collection. @see \LWS\WOOREWARDS\Abstracts\filterCategories */
	public function byCategory($blacklist=false, $whitelist=false, $excepted=array())
	{
		$classname = '\\' . get_class($this);
		$filtered = new $classname();
		if( !empty($this->items) && is_a($this->get(0), 'LWS\WOOREWARDS\Abstracts\ICategorisable') )
		{
			$filtered->items = \LWS\WOOREWARDS\Abstracts\filterCategories($this->items, $blacklist, $whitelist, $excepted);
		}
		return $filtered;
	}

	public function asArray()
	{
		return array_values($this->items);
	}

	/** Append $item to the collection.
	 * @param $name can be ignored if $item has a 'getName' method.
	 * @warning name will be change to be unique in this collection.
	 * @see last()
	 * @return $this */
	public function add($item, $name=false)
	{
		if( $name === false && is_object($item) && method_exists($item, 'getName') )
			$name = $item->getName();
		$unique = $this->uniqueName(empty($name)?'':$name);
		if( $unique != $name && is_object($item) && method_exists($item, 'setName') )
			$item->setName($unique);

		$this->items[$unique] = $item;
		return $this;
	}

	/** Replace $item to the collection (or add it if not exists).
	 * Items are compared by id if 'getId' method exists, then by name.
	 * @warning name can be change to be unique in this collection.
	 * @param $name can be ignored if $item has a 'getName' method.
	 * @return $this */
	public function update($item, $name=false)
	{
		$key = $this->findKey($name === false ? $item : $name);
		if( $key !== false )
			unset($this->items[$key]);
		$this->add($item, $name);
		return $this;
	}

	/** @param $ref (int|string|object) id, name or element instance.
	 * If object is given, look at getId, then getName if method exists.
	 * If int is given, elements of the collection must have a getId method.
	 * If string is given, look first at element key inside of collection, then at getName method if exists on elements.
	 * @return mixed false if not found. */
	public function find($ref)
	{
		return $this->findRef($ref);
	}

	/** @param $item (int|string|object) id, name or element instance.
	 * If element is given, look at getId, then getName if method exists.
	 * @return mixed the removed item or false if not found. */
	public function remove($item)
	{
		$key = $this->findKey($item);
		if( $key !== false )
		{
			$buffer =& $this->items[$key];
			unset($this->items[$key]);
			return $buffer;
		}
		return false;
	}

	/** Build a name that does not already exists in this collection based on a proposal.
	 * If $proposal does not exists, it is returned untouched, else an index is appended. */
	protected function uniqueName($proposal)
	{
		if( empty($this->items) )
			return $proposal;

		$names = array();
		foreach( $this->items as $id => $item )
		{
			$names[$id] = $id;
			if( method_exists($item, 'getName') )
			{
				$name = $item->getName();
				$names[$name] = $name;
			}
		}

		return self::getNewName($proposal, $names);
	}

	/** @return string a name starting by $proposal that does not exist in $existant.
	 *	If similarity exists, a counter is incremented at end of the name. */
	public static function getNewName($proposal='untitled', $existants=array())
	{
		if( empty($existants) )
			return $proposal;

		$len = strlen($proposal);
		$endsWith = array();
		$duplicated = false;
		foreach( $existants as $name )
		{
			if( substr($name, 0, $len) == $proposal )
			{
				$endsWith[] = substr($name, $len);
				if( $len == strlen($name) )
					$duplicated = true;
			}
		}

		if( !$duplicated )
			return $proposal;

		// find max suffix value
		$suffix = 1;
		$pattern = '/(\d+)/';
		$match = array();
		foreach( $endsWith as $ending )
		{
			if( preg_match($pattern, $ending, $match) )
				$suffix = max($suffix, intval($match[1]));
		}
		++$suffix;
		return $proposal . '-' . $suffix;
	}

	/** @param $index (false|int|string) false return all as an array, int return the given index, string get it by name. */
	public function get($index=false)
	{
		return $this->getRef($index);
	}

	public function getByName($name)
	{
		return $this->getRefByName($name);
	}

	public function getKeys()
	{
		return array_keys($this->items);
	}

	/** @return mixed the last element of the collection. */
	public function last()
	{
		if( empty($this->items) )
			return false;
		$index = count($this->items) - 1;
		return $this->items[array_keys($this->items)[$index]];
	}

	/** @return mixed the first element of the collection. */
	public function first()
	{
		if( empty($this->items) )
			return false;
		return $this->items[array_keys($this->items)[0]];
	}

	public function count()
	{
		return count($this->items);
	}

	/** Get a new Collection instance. */
	static function instanciate()
	{
		$classname = '\\' . get_called_class();
		return new $classname();
	}

	/** @see last() same as find but return a reference to element, not a copy. */
	public function &lastRef()
	{
		if( empty($this->items) )
			return self::falseGuard();
		$index = count($this->items) - 1;
		return $this->items[array_keys($this->items)[$index]];
	}

	/** @see first() same as find but return a reference to element, not a copy. */
	public function &firstRef()
	{
		if( empty($this->items) )
			return self::falseGuard();
		return $this->items[array_keys($this->items)[0]];
	}

	/** @param $ref (int|string|object) id, name or element instance.
	 * If object is given, look at getId, then getName if method exists.
	 * If int is given, elements of the collection must have a getId method.
	 * If string is given, look first at element key inside of collection, then at getName method if exists on elements.
	 * @return mixed false if not found. */
	protected function findKey($ref)
	{
		if( is_object($ref) && method_exists($ref, 'getId') )
		{
			$refId = $ref->getId();
			foreach( $this->items as $key => &$item )
			{
				if( method_exists($item, 'getId') && $refId == $item->getId() )
					return $key;
			}
		}
		if( is_object($ref) && method_exists($ref, 'getName') )
			$ref = $ref->getName();

		if( is_string($ref) )
		{
			if( isset($this->items[$ref]) )
				return $ref;

			foreach( $this->items as $key => &$item )
			{
				if( method_exists($item, 'getName') && $ref == $item->getName() )
					return $key;
			}
		}
		if( is_numeric($ref) )
		{
			foreach( $this->items as $key => &$item )
			{
				if( method_exists($item, 'getId') && $ref == $item->getId() )
					return $key;
			}
		}
		return false;
	}

	/** @see find() same as find but return a reference to element, not a copy.
	 * If nothing found, false is returned. Do not change this false reference. */
	public function &findRef($ref)
	{
		if( false !== ($key = $this->findKey($ref)) )
			return $this->items[$key];
		return self::falseGuard();
	}

	/** @see getByName() same as find but return a reference to element, not a copy.
	 * If nothing found, false is returned. Do not change this false reference. */
	public function &getRefByName($name)
	{
		if( isset($this->items[$name]) )
			return $this->items[$name];
		else
		{
			foreach( $this->items as &$item )
			{
				if( !method_exists($item, 'getName') )
					return self::falseGuard();
				if( $name == $item->getName() )
					return $item;
			}
		}
		return self::falseGuard();
	}

	/** @see get() same as find but return a reference to element, not a copy.
	 * If nothing found, null is returned. Do not change this null reference. */
	public function &getRef($index=false)
	{
		if( $index === false )
			return $this->items;
		else if( is_string($index) )
			return $this->getRefByName($index);
		else if( is_numeric($index) && $index >= 0 && $index < count($this->items) )
			return $this->items[array_keys($this->items)[$index]];
		return self::nullGuard();
	}

	/** @param $value_compare_func (callable) @see http://php.net/manual/fr/function.uasort.php
	 * @return $this */
	public function usort($value_compare_func)
	{
		uasort($this->items, $value_compare_func);
		return $this;
	}

	private static function &nullGuard()
	{
		static $null = null;
		$null = null;
		return $null;
	}

	private static function &falseGuard()
	{
		static $false = false;
		$false = false;
		return $false;

	}
}