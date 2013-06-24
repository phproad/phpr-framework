<?php

/**
 * PHPR general-purpose utility
 *
 * This class contains functions used by other PHPR classes.
 */
class Phpr_Util
{
	/**
	 * Converts the argument passed in to an array if it is defined and not already an array.
	 *
	 * @param mixed $value
	 * @return mixed[]
	 */
	public static function splat($value, $explode = false) 
	{
		if (is_string($value) && $explode)
			$value = explode(',', $value);

		if (!is_array($value))
			$value = array($value);

		return $value;
	}

	/**
	 * Converts the argument passed in to an array (argument as key) if it is defined and not already an array.
	 *
	 * @param mixed $value
	 * @return mixed[]
	 */
	public static function splat_keys($value, $strict = true) 
	{
		if (!is_array($value)) 
		{
			if ($strict && (is_null($value) || (is_string($value) && (trim($value) == ''))))
				return $value;

			$value = array($value => array());
		}
		
		return $value;
	}

	/**
	 * Set value for each key in array
	 *
	 * @param mixed[] $array
	 * @param mixed $value
	 * @return mixed[]
	 */
	public static function indexing($array, $value) 
	{
		$keys = array_keys($array);
		$result = array();

		foreach ($keys as $key) 
		{
			if (!is_string($key)) 
				continue;
				
			$result[$key] = $value;
		}
		return $result;
	}

	/*
	 * Returns first non empty argument value
	 */
	public static function any()
	{
		$args = func_get_args();
		foreach ($args as $arg)
		{
			if (!empty($arg))
				return $arg;
		}

		return null;
	}
	
	/**
	* Creates an associative array from two values
	* 
	* (1, [4, 8, 12, 22]) => ([1, 4], [1, 8], [1, 12], [1, 22])
	*
	* @param mixed $first
	* @param array $second
	*/
	public static function pairs($first, $second)
	{
		$result = array();
		foreach ($second as $value) 
			$result[] = array($first, $value);
			
		return $result;
	}


	/**
	 * Build an array from an object and defined keys and values
	 * 
	 * @param  array  $key    Key reference eg: id
	 * @param  array  $value  Value reference eg: name
	 * @param  object $object Object to source data
	 * @return array
	 */
	public static function build_array_from_object($key, $value, $object)
	{  
		$result = array();	        
		foreach ($object as $o)
			$result[$o->{$key}] = $o->{$value};

		return $result;
	}

	/**
	 * Unset an array key based on it's value
	 * 
	 * @param  array  $array Array collection
	 * @param  string $item  Value to remove
	 * @return array
	 */
	public static function unset_value($array, $item) 
	{
		return array_diff($array, (array)$item);
	}

	/**
	 * Replaces keys in array with standard numeric
	 * @param  array $array Array to rebuild
	 * @return array
	 */
	public static function rebuild_keys($array)
	{
		$result = array();
		foreach ($array as $val)
			$result[] = $val;

		return $result;
	}

	/**
	 * Converts amultidimensional array to an object
	 * @param  array  $array Given array
	 * @return object
	 */
	public static function array_to_object($array = array())
	{
		if (is_array($array)) 
			return (object) array_map(array('Phpr_Util', __FUNCTION__), $array);    
		else 
			return $array;
	}

	/**
	 * Converts an object to a multidimensional array
	 * @param  object  $object Given object
	 * @return array
	 */
	public static function object_to_array($object = null)
	{
		if (is_object($object)) 
			$object = get_object_vars($object);
 
		if (is_array($object)) 
			return array_map(array('Phpr_Util', __FUNCTION__), $object);
		else 
			return $object;
	}

}
