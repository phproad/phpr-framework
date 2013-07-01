<?php

/**
 * PHPR Parse class
 *
 * This helpful class allows text to have data parsed in.
 */
class Phpr_Parser
{
	const key_open = '{';
	const key_close = '}';

	private static $_options = array();

	// Services
	// 

	public static function parse_file($file_path, $data, $options=array())
	{
		self::$_options = $options;
		$string = file_get_contents($file_path);
		return self::process_string($string, $data);
	}

	public static function parse_text($string, $data, $options=array())
	{
		self::$_options = $options;
		return self::process_string($string, $data);
	}    

	// Internals
	// 

	// Internal string parse
	private static function process_string($string, $data)
	{
		if (!is_string($string) || !strlen(trim($string)))
			return false;

		foreach ($data as $key => $value)
		{
			if (is_array($value))
				$string = self::process_loop($key, $value, $string);
			else
				$string = self::process_key($key, $value, $string);
		}

		return $string;
	}

	// Process a single key
	private static function process_key($key, $value, $string)
	{
		if (isset(self::$_options['encode_html']) && self::$_options['encode_html'])
			$value = Phpr_Html::encode($value);

		$return_string = str_replace(self::key_open.$key.self::key_close, $value, $string);

		return $return_string;
	}

	// Search for open/close keys and process them in a nested fashion
	private static function process_loop($key, $data, $string)
	{
		$return_string = '';
		$match = self::process_loop_regex($string, $key);

		if (!$match)
			return $string;

		foreach ($data as $row)
		{
			$matched_text = $match[1];
			foreach ($row as $key => $value)
			{
				if (is_array($value))
					$matched_text = self::process_loop($key, $value, $matched_text);
				else
					$matched_text = self::process_key($key, $value, $matched_text);
			}

			$return_string .= $matched_text;
		}

		return str_replace($match[0], $return_string, $string);
	}

	private static function process_loop_regex($string, $key)
	{
		$open = preg_quote(self::key_open);
		$close = preg_quote(self::key_close);

		$regex = '|';
		$regex .= $open.$key.$close; // Open
		$regex .= '(.+?)'; // Content
		$regex .= $open.'/'.$key.$close; // Close
		$regex .='|s';

		preg_match($regex, $string, $match);
		return ($match) ? $match : false;
	}

}
