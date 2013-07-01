<?php

class Db_Data_Collection implements ArrayAccess, IteratorAggregate, Countable 
{

	public $object_array = array();
	public $parent = null;
	public $relation = '';

	/**
	 * Constructor
	 * Create collection from array if passed
	 * @param mixed[] $array
	 */
	public function __construct($array = null) 
	{
		if (is_array($array))
			$this->object_array = $array;
	}

	/**
	 * These are the required iterator functions
	 */
 
	function offsetExists($offset) 
	{
		if (isset($this->object_array[$offset]))
			return true;
		else
			return false;
	}
 
	function offsetGet($offset) 
	{
		if ($this->offsetExists($offset))
			return $this->object_array[$offset];
		else
			return (false);
	}
 
	function offsetSet($offset, $value) 
	{
		if (!is_null($this->parent) && ($this->parent instanceof Db_ActiveRecord))
			$this->parent->bind($this->relation, $value);
		
		if ($offset)
			$this->object_array[$offset] = $value;
		else
			$this->object_array[] = $value;
	}
 
	function offsetUnset($offset) 
	{
		unset($this->object_array[$offset]);
	}
 
	function getIterator() 
	{
		return new ArrayIterator($this->object_array);
	}

	/**
	 * End required iterator functions
	 */

	/**
	 * Return first element from collection
	 * @return mixed|null
	 */
	function first() 
	{
		if (count($this->object_array) > 0)
			return $this->object_array[0];
		else
			return null;
	}

	/**
	 * Get collection size
	 * @return integer
	 */
	function count() 
	{
		return count($this->object_array);
	}

	function position(&$object) 
	{
		return array_search($object, $this->object_array);
	}

	function limit($count) 
	{
		$limit = 0;
		$limited = array();
		
		foreach ($this->object_array as $item) 
		{
			if ($limit++ >= $count) break;
			$limited[] = $item;
		}
		return new Db_Data_Collection($limited);
	}

	function skip($count)
	{
		$skipped = array();
		foreach ($this->object_array as $item) 
		{
			if ($count-- > 0) 
				continue;
				
			$skipped[] = $item;
		}
		
		return new Db_Data_Collection($skipped);
	}

	function except($value, $key = 'id') 
	{
		return $this->exclude(array($value), $key);
	}

	function find($value, $field = 'id')
	{
		foreach ($this->object_array as $object)
			if ($object->{$field} == $value) return $object;

		return null;
	}

	function find_by($field = 'id', $value) 
	{
		return $this->find($value, $field);
	}

	/**
	 * Convert collection to array or return array of values by field
	 * @param string $field optional
	 * @return mixed[]
	 */
	function as_array($field = null, $key_field = null)
	{
		if ($field === null && $key_field === null)
			return $this->object_array;

		$result = array();
		foreach ($this->object_array as $index=>$item)
		{
			$value = $field === null ? $item : $item->$field;
			$key = $key_field === null ? $index : $item->$key_field;
			
			$result[$key] = $value;
		}

		return $result;
	}
	
	/**
	 * Returns an array with keys matching records primary keys
	 * @return mixed[]
	 */
	function as_mapped_array()
	{
		if (!count($this->object_array))
			return $this->object_array;
		
		return $this->as_array(null, $this->object_array[0]->primary_key);
	}

	/**
	 * Convert collection to associative array
	 * @param string|mixed[] $field optional
	 * @param string $key optional
	 * @param string $subkey optional
	 * @return mixed[]
	 */
	function as_dict($field = '', $key = '', $subkey = '') 
	{
		if ($field == '')
			return $this->object_array;

		$result = array();
		foreach ($this->object_array as $item) 
		{
			$k = $key;
			if ($k == '') 
				$k = $item->primary_key;
				
			if (is_string($field)) 
			{
				if ($subkey != '') 
				{
					if (!isset($result[$item->$k]))
						$result[$item->$k] = array();

					$result[$item->$k][$item->$subkey] = $item->$field;
				} else
					$result[$item->$k] = $item->$field;
			} 
			elseif (is_array($field)) 
			{
				$res = array();
				foreach ($field as $model_field)
					$res[$model_field] = $item->$model_field;

				if (!isset($result[$item->$k]))
					$result[$item->$k] = array();

				if ($subkey == '')
					$result[$item->$k][] = $res;
				else
					$result[$item->$k][$item->$subkey] = $res;
			} 
			else
				continue;
		}
		return $result;
	}

	function exclude($values, $key = 'id') 
	{
		$result = array();
		foreach ($this->object_array as $item) 
		{
			if (!in_array($item->{$key}, $values))
				$result[] = $item;
		}
		
		$this->object_array = $result;
		return $this;
	}

	function has($value, $field) 
	{
		$items = $this->as_array($field);
		return in_array($value, $items);
	}

	/**
	 * Magic method: get properties from first object in collection
	 * @param string $key
	 * @return mixed
	 */
	function __get($key) 
	{
		switch ($key) 
		{
			case "first":
				return $this->first();
			case "count":
				return $this->count();
		}
		
		if (count($this->object_array) > 0)
			return @$this->object_array[0]->$key;

		return null;
	}

	/**
	 * Magic method: call methods from first object in collection
	 * @param string $name
	 * @param mixed[] $arguments
	 * @return mixed
	 */
	function __call($name, $arguments) 
	{
		if (count($this->object_array) > 0)
			return call_user_func_array(array(&$this->object_array[0], $name), $arguments);
			
		return null;
	}

	/**
	 * Adds an object to the collection
	 * @param mixed|ActiveRecord $record
	 * @param string $deferred_session_key An edit session key for deferred bindings
	 */
	public function add($record, $deferred_session_key=null)
	{
		if (is_null($this->parent) || !($this->parent instanceof Db_ActiveRecord)) return;
		$this->parent->bind($this->relation, $record, $deferred_session_key);
	}

	/**
	 * Deletes an object from the collection
	 * @param mixed|ActiveRecord $record
	 * @param string $deferred_session_key An edit session key for deferred bindings
	 */
	public function delete($record, $deferred_session_key=null)
	{
		if (is_null($this->parent) || !($this->parent instanceof Db_ActiveRecord)) 
			return;
			
		$this->parent->unbind($this->relation, $record, $deferred_session_key);
	}

	/**
	 * Removes all objects from the collection
	 * @param string $deferred_session_key An edit session key for deferred bindings
	 */
	public function clear($deferred_session_key=null)
	{
		if (is_null($this->parent) || !($this->parent instanceof Db_ActiveRecord)) 
			return;
			
		$this->parent->unbind_all($this->relation, $deferred_session_key);
		$this->object_array = array();
	}

	public function item($key) 
	{
		if (isset($this->object_array[$key]))
			return $this->object_array[$key];
			
		return null;
	}

	public function total() 
	{
		if ($this->parent == null) 
			return count($this);
			
		if (!isset($this->_total))
			$this->_total = $this->parent->count();

		return $this->_total;
	}

	function sql_count() 
	{
		return (!is_null($this->parent) ? $this->parent->count() : 0);
	}
}