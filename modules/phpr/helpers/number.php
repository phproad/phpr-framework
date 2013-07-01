<?php namespace Phpr;

class Number 
{
	/**
	 * Returns true if the passed value is a floating point number
	 * @param number $value number
	 * @return boolean Returns boolean
	 */
	public static function is_valid_float($value) 
	{
		return preg_match('/^[0-9]*?\.?[0-9]*$/', $value);
	}
	
	/**
	 * Returns true if the passed value is an integer value
	 * @param number $value number
	 * @return boolean Returns boolean
	 */
	public static function is_valid_int($value) 
	{
		return preg_match('/^[0-9]*$/', $value);
	}

	// Decode Identifiers YouTube style
	public static function decode_id($int)
	{
		return intval(self::base36_decode(str_rot13($int)))-100;
	}

	// Encode Identifiers YouTube style
	public static function encode_id($int)
	{
		return str_rot13(self::base36_encode($int+100));
	}

	public static function base36_encode($base10)
	{
		return base_convert($base10,10,36);
	}

	public static function base36_decode($base36)
	{
		return base_convert($base36,36,10);
	}

}