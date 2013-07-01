<?php namespace Phpr;

use Phpr;

/**
 * PHPR DateTimeFormat Class
 *
 * Phpr\DateTime_Format provides methods for converting date/time values to strings vice versa.
 */
class DateTime_Format
{
	const sp_type = 'spt';
	const sp_type_string = 'string';
	const sp_type_int = 'integer';
	const sp_type_complex = 'complex';
	const sp_type_loc_link = 'llink';
	const sp_complex_value = 'value';
	const sp_loc_link_key = 'llkey';
	const sp_int_min = 'min';
	const sp_int_max = 'max';
	const sp_domain = 'domain';
	const sp_value_num = 'valuenum';
	const sp_value_list = 'valuelist';
	const sp_method = 'method';
	const sp_custom_method = 'custom';
	const sp_int_padding = 'padding';

	const sp_parser_meaning = 'pm';
	const sp_pr_mn_year = 'year';
	const sp_pr_mn_month = 'month';
	const sp_pr_mn_day = 'day';
	const sp_pr_mn_hour = 'hour';
	const sp_pr_mn_minute = 'minute';
	const sp_pr_mn_second = 'second';
	const sp_pr_mn_custom = 'custom';

	const format_pattern = "/(?P<specifier>%.)/";
	const parser_pattern = "/(?P<datepart>[\w]+)/";

	const localization_prefix = 'dates';

	private static $language = null;
	private static $parsed_formats = array();
	private static $unwrapped_formats = array();

	private static $format_specifiers = array(
		'a' => array(self::sp_type=>self::sp_type_string, self::sp_domain=>'a_weekday_', self::sp_method=>'get_day_of_week', self::sp_value_num=>7),
		'A' => array(self::sp_type=>self::sp_type_string, self::sp_domain=>'A_weekday_', self::sp_method=>'get_day_of_week', self::sp_value_num=>7),
		'b' => array(self::sp_type=>self::sp_type_string, self::sp_domain=>'b_month_', self::sp_method=>'get_month'),
		'B' => array(self::sp_type=>self::sp_type_string, self::sp_domain=>'B_month_', self::sp_method=>'get_month'),
		'c' => array(self::sp_type=>self::sp_type_complex, self::sp_complex_value=>'%x %X'),
		'C' => array(self::sp_type=>self::sp_type_int, self::sp_int_min=>0, self::sp_int_max=>99, self::sp_method=>self::sp_custom_method, self::sp_int_padding=>2),
		'd' => array(self::sp_type=>self::sp_type_int, self::sp_int_min=>1, self::sp_int_max=>31, self::sp_method=>'get_day', self::sp_int_padding=>2, self::sp_parser_meaning=>self::sp_pr_mn_day),
		'D' => array(self::sp_type=>self::sp_type_complex, self::sp_complex_value=>'%m/%d/%y'),
		'e' => array(self::sp_type=>self::sp_type_int, self::sp_int_min=>1, self::sp_int_max=>31, self::sp_method=>'get_day', self::sp_parser_meaning=>self::sp_pr_mn_day),
		'F' => array(self::sp_type=>self::sp_type_loc_link, self::sp_loc_link_key=>'full_date_format'),
		'H' => array(self::sp_type=>self::sp_type_int, self::sp_int_min=>0, self::sp_int_max=>23, self::sp_method=>'get_hour', self::sp_int_padding=>2, self::sp_parser_meaning=>self::sp_pr_mn_hour),
		'I' => array(self::sp_type=>self::sp_type_int, self::sp_int_min=>1, self::sp_int_max=>12, self::sp_method=>self::sp_custom_method, self::sp_int_padding=>2, self::sp_parser_meaning=>self::sp_pr_mn_hour),
		'i' => array(self::sp_type=>self::sp_type_int, self::sp_int_min=>1, self::sp_int_max=>12, self::sp_method=>self::sp_custom_method, self::sp_parser_meaning=>self::sp_pr_mn_hour),
		'j' => array(self::sp_type=>self::sp_type_int, self::sp_int_min=>1, self::sp_int_max=>366, self::sp_method=>'get_day_of_year', self::sp_int_padding=>3),
		'l' => array(self::sp_type=>self::sp_type_int, self::sp_int_min=>1, self::sp_int_max=>12, self::sp_method=>self::sp_custom_method, self::sp_parser_meaning=>self::sp_pr_mn_hour),
		'm' => array(self::sp_type=>self::sp_type_int, self::sp_int_min=>1, self::sp_int_max=>12, self::sp_method=>'get_month', self::sp_int_padding=>2, self::sp_parser_meaning=>self::sp_pr_mn_month),
		'M' => array(self::sp_type=>self::sp_type_int, self::sp_int_min=>0, self::sp_int_max=>59, self::sp_method=>'get_minute', self::sp_int_padding=>2, self::sp_parser_meaning=>self::sp_pr_mn_minute),
		'n' => array(self::sp_type=>self::sp_type_string, self::sp_domain=>'n_month_', self::sp_method=>'get_month'),
		'p' => array(self::sp_type=>self::sp_type_string, self::sp_domain=>'ampm_', self::sp_method=>self::sp_custom_method, self::sp_parser_meaning=>self::sp_pr_mn_custom, self::sp_value_list=>array('ampm_am', 'ampm_pm')),
		'S' => array(self::sp_type=>self::sp_type_int, self::sp_int_min=>0, self::sp_int_max=>59, self::sp_method=>'get_second', self::sp_int_padding=>2, self::sp_parser_meaning=>self::sp_pr_mn_second),
		'T' => array(self::sp_type=>self::sp_type_complex, self::sp_complex_value=>'%H:%M:%S'),
		'u' => array(self::sp_type=>self::sp_type_int, self::sp_int_min=>1, self::sp_int_max=>7, self::sp_method=>'get_day_of_week'),
		'w' => array(self::sp_type=>self::sp_type_int, self::sp_int_min=>0, self::sp_int_max=>6, self::sp_method=>self::sp_custom_method),
		'x' => array(self::sp_type=>self::sp_type_loc_link, self::sp_loc_link_key=>'short_date_format'),
		'X' => array(self::sp_type=>self::sp_type_loc_link, self::sp_loc_link_key=>'time_format'),
		'y' => array(self::sp_type=>self::sp_type_int, self::sp_int_min=>0, self::sp_int_max=>99, self::sp_method=>self::sp_custom_method, self::sp_int_padding=>2, self::sp_parser_meaning=>self::sp_pr_mn_custom),
		'Y' => array(self::sp_type=>self::sp_type_int, self::sp_int_min=>1, self::sp_int_max=>9999, self::sp_method=>'get_year', self::sp_parser_meaning=>self::sp_pr_mn_year)
	);

	/**
	 * Initializes the object
	 */
	private static function init()
	{
		$lang = Phpr::$locale->get_language_code();
		if ($lang != self::$language)
		{
			self::$language = $lang;
			self::$parsed_formats = array();
			self::$unwrapped_formats = array();
		}
	}

	/**
	 * Unwraps the complex specifiers and returns the modified format string.
	 * @param string $format Specifies the format string to process
	 * @param integer $count Specifies the number of complex specifiers found
	 * @return string
	 */
	private static function unwrap_format($format, &$count)
	{
		$count = 0;

		if (array_key_exists($format, self::$unwrapped_formats))
			return self::$unwrapped_formats[$format];

		$format_wrapped = $format;

		self::init();

		// Parse format and unwrap complex specifiers
		//
		preg_match_all(self::format_pattern, $format, $matches);

		foreach ($matches['specifier'] as $match_data) 
		{
			$match_specifier = substr($match_data, 1);

			if (array_key_exists($match_specifier, self::$format_specifiers)) 
			{
				$specifier_desc = self::$format_specifiers[$match_specifier];

				$specifier_value = null;

				if ($specifier_desc[self::sp_type] == self::sp_type_loc_link) 
				{
					// Load specifier value from the localization resources
					//
					$specifier_value = Phpr::$locale->get_string('phpr.' . self::localization_prefix, $specifier_desc[self::sp_loc_link_key]);

					$count++;
				} 
				else if ($specifier_desc[self::sp_type] == self::sp_type_complex) 
				{
					$specifier_value = $specifier_desc[self::sp_complex_value];
					$count++;
				}

				if (!is_null($specifier_value))
					$format = str_replace('%'.$match_specifier, $specifier_value, $format);
			}
		}

		self::$unwrapped_formats[$format_wrapped] = $format;

		return $format;
	}

	/**
	 * Parses the date format string and returns the array of format specifiers.
	 * @param string $format Specifies the format string to parse.
	 * @return array
	 */
	private static function parse_format(&$format)
	{
		// Preprocess the format
		//
		$count = null;

		do
		{
			$format = self::unwrap_format($format, $count);
		}
		while ($count > 0);

		// Check if parsed format is not cached
		//
		if (array_key_exists($format, self::$parsed_formats))
			return self::$parsed_formats[$format];

		// Parse the format
		//
		preg_match_all(self::format_pattern, $format, $matches);

		self::$parsed_formats[$format] = $matches['specifier'];

		return $matches['specifier'];
	}

	/**
	 * Converts the specified Phpr\DateTime object value to string according the specified format.
	 * @param DateTime $datetime Specifies the value to format
	 * @param string $format Specifies the format string
	 * @return string
	 */
	public static function format_datetime(DateTime $datetime, $format)
	{
		self::init();

		$format_specifiers = self::parse_format($format);

		// Replace specifiers with date values
		//
		$processed_specifiers = array();

		$method_values = array();

		foreach ($format_specifiers as $match_data) 
		{
			$match_specifier = substr($match_data, 1);

			// Skip unknown specifiers
			//
			if (!array_key_exists($match_specifier, self::$format_specifiers))
				continue;

			// Obtain the specifier description
			//
			$specifier_desc = self::$format_specifiers[$match_specifier];

			// Evaluate the specifier value in case if it was not evaluated so far
			//
			if (!array_key_exists($match_specifier, $processed_specifiers)) 
			{
				$specifier_value = null;
				$method = $specifier_desc[self::sp_method];

				if ($method != self::sp_custom_method) 
				{
					// Evaluate auto method values
					//
					if (array_key_exists($method, $method_values))
						$method_value = $method_values[$method];
					else 
					{
						$method_value = $datetime->$method();
						$method_values[$method] = $method_value;
					}
				} 
				else 
				{
					// Evaluate custom method values
					//
					switch ($match_specifier) 
					{
						case 'p' :
								$hours = $datetime->get_hour();
								$method_value = ($hours < 12) ? 'am' : 'pm';
								break;
						case 'C' :
								$method_value = floor($datetime->get_year() / 100);
								break;
						case 'I' :
								$hours = $datetime->get_hour();
								if ($hours == 0) $hours = 12;
								$method_value = ($hours <= 12) ? $hours : $hours % 12;
								break;
						case 'l' :
								$hours = $datetime->get_hour();
								if ($hours == 0) $hours = 12;
								$method_value = ($hours <= 12) ? $hours : $hours % 12;
								break;
						case 'i' :
								$hours = $datetime->get_hour();
								if ($hours == 0) $hours = 12;
								$method_value = ($hours <= 12) ? $hours : $hours % 12;
								break;
						case 'w' :
								$weekDay = $datetime->get_day_of_week();
								if ($weekDay == 7)
									$weekDay = 0;
								$method_value = $weekDay;
								break;
						case 'y' :
								$method_value = $datetime->get_year() % 100;
								break;
					}
				}

				if ($specifier_desc[self::sp_type] == self::sp_type_string) 
				{
					// Load the localization string for the string specifiers
					//
					$specifier_value = Phpr::$locale->get_string('phpr.' . self::localization_prefix, $specifier_desc[self::sp_domain].$method_value);
				} 
				else if ($specifier_desc[self::sp_type] == self::sp_type_int) 
				{
					if (array_key_exists(self::sp_int_padding, $specifier_desc)) 
					{
						$padding = $specifier_desc[self::sp_int_padding];
						$method_value = sprintf("%0{$padding}d", $method_value);
					}

					$specifier_value = $method_value;
				}

				$processed_specifiers[$match_specifier] = $specifier_value ;
			} 
			else
				$specifier_value = $processed_specifiers[$match_specifier];

			if (!is_null($specifier_value))
				$format = str_replace('%'.$match_specifier, $specifier_value, $format);
		}

		// Replace the %% sequence with the % character
		//
		$format = str_replace('%%', '%', $format);

		return $format;
	}

	/**
	 * Returns the array of the specified domain values.
	 * @param string $domain Specifies the domain name
	 * @param integer $number Specifies the number of values to load
	 * @return array
	 */
	private static function preload_domain_values($domain, $number)
	{
		$result = array();

		for ($index = 1; $index <= $number; $index++)
			$result[$index] = Phpr::$locale->get_string('phpr.' . self::localization_prefix, $domain.$index);

		return $result;
	}

	/**
	 * Returns the array of the specified domain values.
	 * @param array $value_list Specifies the list of the domain values
	 * @return array
	 */
	private static function preload_domain_value_list($value_list)
	{
		$result = array();

		foreach ($value_list as $value_name)
		{
			$result[] = Phpr::$locale->get_string('phpr.' . self::localization_prefix, $value_name);
		}

		return $result;
	}

	/**
	 * Parses the string and returns the Phpr\DateTime value.
	 * If a specified string can not be converted to a date/time value, returns boolean false.
	 * @param string $string Specifies the string to parse
	 * @param string $format Specieis the date format expected
	 * @param DateTimeZone $timezone Optional. Specifies a time zone to assign to a new object.
	 * @return Phpr\DateTime
	 */
	public static function parse_datetime($string, $format, $timezone = null)
	{
		self::init();

		$format_specifiers = self::parse_format($format);

		if (!count($format_specifiers))
			return false;

		// Split string
		//
		$matches = array();
		preg_match_all(self::parser_pattern, $string, $matches);
		$string_matches = $matches['datepart'];

		if (!count($string_matches))
			return false;

		// Process format specifiers
		//
		$date_elements = array(
			self::sp_pr_mn_year => null,
			self::sp_pr_mn_month => null,
			self::sp_pr_mn_day => null,
			self::sp_pr_mn_hour => null,
			self::sp_pr_mn_minute => null,
			self::sp_pr_mn_second => null
		);

		$Now = DateTime::now();

		$ampm = null;

		foreach ($format_specifiers as $index=>$specifier) 
		{
			$specifier = substr($specifier, 1);

			// Skip unknown specifiers
			//
			if (!array_key_exists($specifier, self::$format_specifiers))
				continue;

			// Obtain the specifier description
			//
			$specifier_desc = self::$format_specifiers[$specifier];

			// Skip non-parserable specifiers
			//
			if (!array_key_exists(self::sp_parser_meaning, $specifier_desc))
				continue;

			// Return false if no value was provided for the specifier
			//
			if (!array_key_exists($index, $string_matches))
				return false;

			$value = $string_matches[$index];

			// Preprocess the specifier value
			//
			$type = $specifier_desc[self::sp_type];

			$domain_values_cache = array();

			if ($type == self::sp_type_int) 
			{
				if (!preg_match("/^[0-9]+$/", $value))
					return false;

				$value = (int)$value;

				if (array_key_exists(self::sp_int_min, $specifier_desc))
					if ($value < $specifier_desc[self::sp_int_min] || $value > $specifier_desc[self::sp_int_max])
						return false;
			} 
			else if ($type == self::sp_type_string) 
			{
				$domain = $specifier_desc[self::sp_domain];

				if (!array_key_exists($domain, $domain_values_cache)) 
				{
					if (array_key_exists(self::sp_value_num, $specifier_desc))
						$domain_values = self::preload_domain_values($domain, $specifier_desc[self::sp_value_num]);
					else
						$domain_values = self::preload_domain_value_list($specifier_desc[self::sp_value_list]);

					$domain_values_cache[$domain] = $domain_values;
				} 
				else 
					$domain_values = $domain_values_cache[$domain];

				$str_index = null;
				foreach ($domain_values as $index=>$domainValue)
				{
					if (strcasecmp($domainValue, $value) == 0) 
					{
						$str_index = $index;
						break;
					}
				}

				if (is_null($str_index))
					return false;

				$value = $index;
			}

			// Assign value to a corresponding date element
			//
			$meaning = $specifier_desc[self::sp_parser_meaning];

			if ($meaning != self::sp_pr_mn_custom)
				$date_elements[$meaning] = $value;
			else 
			{
				switch ($specifier) 
				{
					case 'p' :
						$ampm = $value;
						break;
					case 'y' :
						$century = floor($Now->get_year()/100);
						$date_elements[self::sp_pr_mn_year] = $century*100 + $value;
						break;
				}
			}
		}

		// Assemble result value
		//
		$year = is_null($date_elements[self::sp_pr_mn_year]) ? $Now->get_year() : $date_elements[self::sp_pr_mn_year];
		$month = is_null($date_elements[self::sp_pr_mn_month]) ? $Now->get_month() : $date_elements[self::sp_pr_mn_month];
		$day = is_null($date_elements[self::sp_pr_mn_day]) ? $Now->get_day() : $date_elements[self::sp_pr_mn_day];
		$hour = is_null($date_elements[self::sp_pr_mn_hour]) ? 0 : $date_elements[self::sp_pr_mn_hour];
		$minute = is_null($date_elements[self::sp_pr_mn_minute]) ? 0 : $date_elements[self::sp_pr_mn_minute];
		$second = is_null($date_elements[self::sp_pr_mn_second]) ? 0 : $date_elements[self::sp_pr_mn_second];

		if (!is_null($ampm))
		{
			if ($ampm == 1) 
			{
				if ($hour < 12)
					$hour += 12;
			} 
			elseif ($ampm == 0)
			{
				if ($hour >= 12)
					$hour -= 12;
			}
		}

		$result = new DateTime(null, $timezone);
		$result->set_datetime($year, $month, $day, $hour, $minute, $second);

		return $result;
	}

	/**
	 * Returns the short week day name (corresponds the %A specifier).
	 * @param integer $day_number Specifies the day number, one of the DayOfWeek enumeration field values
	 * @return string
	 */
	public static function get_short_week_day_name($day_number)
	{
		self::init();
		return Phpr::$locale->get_string('phpr.' . self::localization_prefix, 'a_weekday_'.$day_number);
	}

	/**
	 * Returns the full week day name (corresponds the %A specifier).
	 * @param integer $day_number Specifies the day number, one of the DayOfWeek enumeration field values
	 * @return string
	 */
	public static function get_full_week_day_name($day_number)
	{
		self::init();
		return Phpr::$locale->get_string('phpr.' . self::localization_prefix, 'A_weekday_'.$day_number);
	}
}
