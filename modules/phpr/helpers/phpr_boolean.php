<?php

/**
 * PHPR Boolean helper
 *
 * This class contains functions that may be useful for working with booleans.
 */
class Phpr_Boolean
{
	public static function from($obj)
	{
		if (is_string($obj)) {
			return self::from_string($obj);
		}
		else
			return (boolean) $obj;
	}

	public static function from_string($str)
	{
		$str = trim($str);
		
		if ($str == true)
			return true;
		else if ($str == 'y')
			return true;
		else if ($str == 'yes')
			return true;
		else if ($str == 'true')
			return true;

		return false;
	}
}