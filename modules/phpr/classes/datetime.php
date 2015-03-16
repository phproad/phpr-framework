<?php namespace Phpr;

use DateTimeZone;

use Phpr\String;
use Phpr\ApplicationException;

/**
 * PHPR DateTime Class
 *
 * Phpr\DateTime class represents a date and time value and provides a datetime arithmetic functions.
 */
class DateTime
{
	protected $int_value = 0;
	protected $timezone = null;

	protected $days_to_month_reg = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334, 365);
	protected $days_to_month_leap = array(0, 31, 60, 91, 121, 152, 182, 213, 244, 274, 305, 335, 366);

	const max_int_value = 3155378975999999999;
	const max_ml_seconds = 315537897600000;
	const min_ml_seconds = -315537897600000;
	const ml_seconds_in_day = 86400000;
	const ml_seconds_in_hour = 3600000;
	const ml_seconds_in_minute = 60000;
	const ml_seconds_in_second = 1000;
	const days_in400_years = 146097;
	const days_in100_years = 36524;
	const days_in4_years = 1461;
	const int_in_day = 864000000000;
	const int_in_hour = 36000000000;
	const int_in_minute = 600000000;
	const int_in_second = 10000000;
	const timestamp_offset = 621355968000000000;

	const element_year = 0;
	const element_day_of_year = 1;
	const element_month = 2;
	const element_day = 3;

	/**
	 * Represents the universal date format: 2006-02-20
	 * @var string
	 */
	const universal_date_format = '%Y-%m-%d';

	/**
	 * Represents the universal time format: 20:00:00
	 * @var string
	 */		
	const universal_time_format = '%H:%M:%S';
	
	/**
	 * Represents the universal date/time format: 2006-02-20 20:00:00
	 * @var string
	 */
	const universal_datetime_format = '%Y-%m-%d %H:%M:%S';
	
	/**
	 * Creates a new DateTime instance and sets its value to a local date and time.
	 * @param string $datetime Optional. Specifies the date and time in format '2006-01-01 10:00:00' to assign to the instance.
	 * If this parameter is omitted, the current time will be used.
	 * @param DateTimeZone $timezone Optional. Specifies the time zone to assign to the instance.
	 * If this parameter is omitted, the default time zone will be used.
	 */
	public function __construct($datetime = null, $timezone = null)
	{
        try {

            if (is_a($timezone,'DateTimeZone')) {
                $this->timezone = $timezone;
            }

            if (is_string($timezone) && !empty($timezone)) {
                $this->timezone = new DateTimeZone($timezone);
            }

            if (empty($this->timezone)) {
                $default_tz = \Phpr::$config->get('TIMEZONE', date_default_timezone_get());
                $this->timezone = new DateTimeZone($default_tz);
            }
        } catch (Exception $e){
            $this->timezone = null;
        }

		if ($datetime === null) {
            $this->int_value = self::get_current_datetime();
        } else {
            if (strlen($datetime) == 10) {
                $datetime .= ' 00:00:00';
            }

			$obj = DateTime_Format::parse_datetime($datetime, self::universal_datetime_format, $this->timezone);
			if ($obj === false) {
                throw new ApplicationException("Can not parse date/time string: " . $datetime);
            }

			$this->int_value = $obj->get_integer();
		}
	}

	public function __toString()
	{
		return $this->format(self::universal_datetime_format);
	}

	/**
	 * Returns a time zone associated with the date time object.
	 * @return DateTimeZone
	 */
	public function get_timezone()
	{
		return $this->timezone;
	}

	/**
	 * Sets the time zone for the date time object.
	 * @param DateTimeZone $timezone Specifies the time zone to assign to the instance.
	 */
	public function set_timezone(DateTimeZone $timezone)
	{
		$diff = DateTime::get_zones_offset($this->timezone, $timezone);

		$this->int_value -= $diff*DateTime::ml_seconds_in_second*10000;
		$this->timezone = $timezone;
		return $this;
	}
	
	/**
	 * Assign a time zone for the date time object, without changing the time value.
	 * @param DateTimeZone $timezone Specifies the time zone to assign to the instance.
	 */
	public function assign_timezone(DateTimeZone $timezone)
	{
		$this->timezone = $timezone;
		return $this;
	}

	/**
	 * Sets the object value to a date specified.
	 * @param integer $year Specifies the year
	 * @param integer $month Specifies the month
	 * @param integer $day Specifies the day
	 */
	public function set_date($year, $month, $day)
	{
		$this->int_value = $this->convert_date_val($year, $month, $day);
		return $this;
	}

	/**
	 * Sets the object value to a date and time specified.
	 * @param integer $year Specifies the year
	 * @param integer $month Specifies the month
	 * @param string $day Specifies the day
	 * @param integer $hour Specifies the hour
	 * @param integer $minute Specifies the minute
	 * @param string $second Specifies the second
	 */
	public function set_datetime($year, $month, $day, $hour, $minute, $second)
	{
		$this->int_value = $this->convert_date_val($year, $month, $day) + $this->convert_time_val($hour, $minute, $second);
		return $this;
	}
	
	/**
	 * Sets the object value to a date specified with a PHP timestamp
	 * @param int $timestamp PHP timestamp
	 */
	public function set_php_datetime($timestamp)
	{
		$this->set_datetime(
			(int)date('Y', $timestamp),
			(int)date('n', $timestamp),
			(int)date('j', $timestamp),
			(int)date('G', $timestamp),
			(int)date('i', $timestamp),
			(int)date('s', $timestamp)
		);
		return $this;
	}

	/**
	 * Gets the strtotime value of this Phpr\DateTime object
	 * @param bool $strip_time Set to true if you want to reset the time to 00:00:00
	 * @return int Unix timestamp
	 */
	public function get_php_datetime($strip_time = false)
	{
		if ($strip_time)
			$date_string = $this->format(DateTime::universal_date_format . ' 00:00:00');
		else
			$date_string = $this->format(DateTime::universal_datetime_format);
		
		return strtotime($date_string);
	}

	/**
	 * Returns the hour component of the time represented by the object.
	 * @return integer
	 */
	public function get_hour()
	{
		return floor(($this->int_value / DateTime::int_in_hour) % 24);
	}

	/**
	 * Returns the minute component of the time represented by the object.
	 * @return integer
	 */
	public function get_minute()
	{
		return floor(($this->int_value / DateTime::int_in_minute) % 60);
	}

	/**
	 * Returns the second element of the time represented by the object.
	 * @return integer
	 */
	public function get_second()
	{
		return floor($this->modulus($this->int_value / DateTime::int_in_second, 60));
	}

	/**
	 * Returns the year component of the date represented by the object.
	 * @return integer
	 */
	public function get_year()
	{
		return floor($this->convert_to_date_element(DateTime::element_year));
	}

	/**
	 * Returns the month component of the date, represented by the object, 1-based.
	 * @return integer
	 */
	public function get_month()
	{
		return floor($this->convert_to_date_element(DateTime::element_month));
	}

	/**
	 * Returns the day of the month represented by the object.
	 * @return integer
	 */
	public function get_day()
	{
		return $this->convert_to_date_element(DateTime::element_day);
	}

	/**
	 * Returns a new DateTime object corresponding the sum of this object and a number of years specified.
	 * @param integer $years Specifies the number of years to add.
	 * @return Phpr\DateTime
	 */
	public function add_years($years)
	{
		return $this->add_months($years * 12);
	}

	/**
	 * Returns a new DateTime object corresponding the sum of this object 
	 * and a number of months specified.
	 * @param integer $months Specifies a number of months to add.
	 * @return Phpr\DateTime
	 */
	public function add_months($months)
	{
		if ($months < -120000 || $months > 120000)
			throw new ApplicationException("Month is out of range");

		$year = $this->convert_to_date_element(DateTime::element_year);
		$month = $this->convert_to_date_element(DateTime::element_month);
		$day = $this->convert_to_date_element(DateTime::element_day);

		$month_sum = $month + $months - 1;

		if ($month_sum >= 0) 
		{
			$month = floor($month_sum % 12) + 1;
			$year += floor($month_sum/12);
		} 
		else 
		{
			$month = floor(12 + ($month_sum + 1) % 12);
			$year += floor(($month_sum - 11) / 12);
		}

		$days_in_month = DateTime::days_in_month($year, $month);

		if ($day > $days_in_month)
			$day = $days_in_month;

		$result = new DateTime();

		$inc_value = $this->modulus($this->int_value, DateTime::int_in_day);

		$result->set_integer($this->convert_date_val($year, $month, $day) + $inc_value);

		return $result;
	}

	/**
	 * Adds an interval to a current value and returns a new DateTime object.
	 * @param Phrp_DateTimeInterval $interval Specifies an interval to add.
	 * @return Phpr\DateTime
	 */
	public function add_interval(DateTime_Interval $interval)
	{
		$result = new DateTime(null, $this->timezone);
		$result->set_integer($this->int_value + $interval->get_integer());

		return $result;
	}

	/**
	 * Returns a new DateTime object that is the sum of the date and time 
	 * represented by this object and a number of days specified.
	 * @param float $value Specifies a number of days to add.
	 * @return Phpr\DateTime
	 */
	public function add_days($value)
	{
		return $this->add_interval_internal($value, DateTime::ml_seconds_in_day);
	}

	/**
	 * Returns a new DateTime object that is the sum of the date and time 
	 * represented by this object and a number of hours specified.
	 * @param float $hours Specifies a number of hours to add.
	 * @return Phpr\DateTime
	 */
	public function add_hours($hours)
	{
		return $this->add_interval_internal($hours, DateTime::ml_seconds_in_hour);
	}

	/**
	 * Returns a new DateTime object corresponding the sum of this object date and time
	 * and a number of minutes specified.
	 * @param Float $minutes Specifies a number of minutes to add.
	 * @return Phpr\DateTime
	 */
	public function add_minutes($minutes)
	{
		return $this->add_interval_internal($minutes, DateTime::ml_seconds_in_minute);
	}

	/**
	 * Returns a new DateTime object corresponding the sum of this object and a number of seconds specified.
	 * @param Float $seconds Specifies a number of seconds to add.
	 * @return Phpr\DateTime
	 */
	public function add_seconds($seconds)
	{
		return $this->add_interval_internal($seconds, DateTime::ml_seconds_in_second);
	}

	/**
	 * Compares this object with another Phpr\DateTime object, 
	 * Returns 1 if this object value is more than a specified value,
	 * 0 if values are equal and 
	 * -1 if this object value is less than a specified value.
	 * This method takes into account the time zones of the date time objects.
	 * @param Phpr\DateTime $value Specifies the Phpr\DateTime object to compare with.
	 * @return integer
	 */
	public function compare(DateTime $value)
	{
		if ($this->int_value > $value->get_integer())
			return 1;

		if ($this->int_value < $value->get_integer())
			return -1;

		return 0;
	}

	/**
	 * Compares two Phpr\DateTime values.
	 * Returns 1 if the first value is more than the second value,
	 * 0 if values are equal and 
	 * -1 if the first value is less than the second value.
	 * This method takes into account the time zones of the date time objects.
	 * @param DateTime $value1 Specifies the first value
	 * @param DateTime $value2 Specifies the second value
	 * @return integer
	 */
	public static function compare_dates(DateTime $value1, DateTime $value2)
	{
		if ($value1->get_integer() > $value2->get_integer())
			return 1;

		if ($value1->get_integer() < $value2->get_integer())
			return -1;

		return 0;
	}

	/**
	 * Determines whether a value of this object matches a value of a specified Phpr\DateTime object.
	 * This method takes into account the time zones of the date time objects.
	 * @param Phpr\DateTime $value Specifies a value to compare with
	 * @return boolean
	 */
	public function equals(DateTime $value)
	{
		return $this->int_value == $value->get_integer();
	}

	/**
	 * Returns the date component of a date and time value represented by the object.
	 * @return Phpr\DateTime
	 */
	public function get_date()
	{
		$result = new DateTime();
		$result->set_integer($this->int_value - $this->modulus($this->int_value, DateTime::int_in_day));

		return $result;
	}

	/**
	 * Returns the day of the week as a decimal number [1,7], with 1 representing Monday,
	 * for a date represented by this object.
	 * @return integer
	 */
	public function get_day_of_week()
	{
		$result = (($this->int_value / DateTime::int_in_day) + 1) % 7;

		if ($result == 0)
			$result = 7;

		return $result;
	}

	/**
	 * Returns the day of the year for a date represented by this object, zero-based.
	 * @return integer
	 */
	public function get_day_of_year()
	{
		return $this->convert_to_date_element(DateTime::element_day_of_year) - 1;
	}

	// Returns the week of the month assuming weeks start on Sunday
	public function get_week_of_month() 
	{
		$date = $this->to_sql_date();
		$date_parts = explode('-', $date);
		$date_parts[2] = '01';
		$first_of_month = implode('-', $date_parts);
		
		$day_of_first = date('N', strtotime($first_of_month)); // get_day_of_week
		$day_of_month = date('j', strtotime($date));
		return floor(($day_of_first + $day_of_month - 1) / 7) + 1;
	}


	/**
	 * Returns the number of days in the specified month of the specified year.
	 * @param integer $year Specifies the year
	 * @param integer $month Specifies the month
	 * @return integer
	 */
	public function days_in_month($year, $month)
	{
		if ($month < 1 || $month > 12)
			throw new ApplicationException("The Month argument is ouf range");

		$days_num = $this->year_is_leap($year) ? $this->days_to_month_leap : $this->days_to_month_reg;

		return $days_num[$month] - $days_num[$month - 1];
	}

	/**
	 * Determines whether the year is leap.
	 * @param integer $year Specifies the year
	 * @return boolean
	 */
	public static function year_is_leap($year)
	{
		if (($year % 4) != 0)
			return false;

		if (($year % 100) == 0)
			return ($year % 400) == 0;

		return true;
	}

	/**
	 * Returns a Phpr\DateTime object representing the date/and time value in GMT.
	 * @return Phpr\DateTime
	 */
	public function gmt()
	{
		$result = new DateTime(null, $this->timezone);
		$result->set_integer($this->int_value);
		$result->set_timezone(new DateTimeZone("GMT"));

		return $result;
	}

	/**
	 * Returns the Phpr\DateTime object corresponding the current GMT date and time.
	 * @param DateTimeZone $timezone Optional, specifies the time zone to assign to the instance.
	 * @return Phpr\DateTime
	 */
	public static function gmt_now(DateTimeZone $timezone = null)
	{
		$result = new DateTime(null, $timezone);
		$result->set_integer(time()*(DateTime::int_in_second) + DateTime::timestamp_offset);

		return $result;
	}

	/**
	 * Returns the instance of the Phpr\DateTime class representing the current local date and time.
	 * @return Phpr\DateTime
	 */
	public static function now()
	{
		return new DateTime();
	}

	/**
	 * Substructs a specified Phpr\DateTime object from this object value 
	 * and returns the date and time interval.
	 * This method takes into account the time zones of the date time objects.
	 * @param Phpr\DateTime $value Specifies the value to substract
	 * @return DateTime_Interval
	 */
	public function substract_datetime(DateTime $value)
	{
		$result = new DateTime_Interval();
		$result->set_integer($this->int_value - $value->get_integer());

		return $result;
	}

	/**
	 * Substructs a specified DateTime_Interval object from this 
	 * object value and returns a new DateTime instance.
	 * @param DateTime_Interval $value Specifies an interval to substract
	 * @return Phpr\DateTime
	 */
	public function substract_interval(DateTime_Interval $value)
	{
		$result = new DateTime();
		$result->set_integer($this->int_value - $value->get_integer());

		return $result;
	}

	/**
	 * @ignore
	 * This method is used by the PHPR internally.
	 * Changes the internal date time value.
	 * @param integer $value Specifies the integer value
	 */
	public function set_integer($value)
	{
		$this->int_value = $value;
		return $this;
	}

	/**
	 * @ignore
	 * This method is used by the PHPR internally.
	 * Returns the integer representation of a date.
	 * @return integer
	 */
	public function get_integer()
	{
		return $this->int_value;
	}

	/**
	 * Returns the DateTime_Interval object representing the interval elapsed since midnight.
	 * @return DateTime_Interval
	 */
	public function get_time_interval()
	{
		$result = new DateTime_Interval();
		$result->set_integer($this->modulus($this->int_value, DateTime::int_in_day));

		return $result;
	}

	/**
	 * Returns a string representation of the date and time, according the user language date/time format.
	 * @param string $format Specifies the formatting string. For example: %F %X.
	 * @return string
	 */
	public function format($format)
	{
		return DateTime_Format::format_datetime($this, $format);
	}

	/**
	 * Converts the Phpr\DateTime value to a string, according the full date format (%F format specifier).
	 * @return string
	 */
	public function to_short_date_format()
	{
		return $this->format('%x');
	}

	/**
	 * Converts the Phpr\DateTime value to a string, according the full date format (%F format specifier).
	 * @return string
	 */
	public function to_long_date_format()
	{
		return $this->format('%F');
	}

	/**
	 * Converts the Phpr\DateTime value to a string, according the time format (%X format specifier).
	 * @return string
	 */
	public function to_time_format()
	{
		return $this->format('%X');
	}

	/**
	 * Converts a string to a Phpr\DateTime object.
	 * If a specified string can not be converted to a date/time value, returns boolean false.
	 * @param string $str Specifies the string to parse. For example: %x %X.
	 * @param string $format Specifies the date/time format.
	 * @param DateTimeZone $timezone Optional. Specifies a time zone to assign to a new object.
	 * @return mixed
	 */
	public static function parse($str, $format = null, DateTimeZone $timezone = null)
	{
		if ($format == null)
			$format = self::universal_datetime_format;
		
		return DateTime_Format::parse_datetime($str, $format, $timezone);
	}

	/**
	 * @ignore
	 * This method is used by the PHPR internally.
	 * Evaluates an offset between time zones of two specified time zones.
	 * @param DateTimeZone $zone1 Specifies the first DateTimeZone instance.
	 * @param DateTimeZone $zone2 Specifies the second DateTimeZone instance.
	 */
	public static function get_zones_offset(DateTimeZone $zone1, DateTimeZone $zone2)
	{
		$temp = new \DateTime();
		return $zone1->getOffset($temp) - $zone2->getOffset($temp);
	}

	/**
	 * Determines whether the string specified is a database null date representation
	 */
	public static function is_db_null($str)
	{
		if (!strlen($str))
			return true;

		if (substr($str, 0, 10) == '0000-00-00')
			return true;

		return false;
	}
	
	/**
	 * Returns object value in SQL date-time format
	 */
	public function to_sql_datetime()
	{
		return $this->format(self::universal_datetime_format);
	}
	
	/**
	 * Returns object value in SQL date format
	 */
	public function to_sql_date()
	{
		return $this->format(self::universal_date_format);
	}
	
	/**
	 * Returns the integer value corresponding a current date and time.
	 * @return integer
	 */
	protected function get_current_datetime()
	{
		return ($this->timezone->getOffset(new \DateTime()) + time()) * (DateTime::int_in_second) + DateTime::timestamp_offset;
	}

	/**
	 * Converts the value to a date element.
	 * @param integer $element Specifies the element value
	 * @return integer
	 */
	protected function convert_to_date_element($element)
	{
		$days = floor($this->int_value/(DateTime::int_in_day));

		$years400 = floor($days/DateTime::days_in400_years);
		$days -= $years400 * DateTime::days_in400_years;

		$years100 = floor($days/DateTime::days_in100_years);
		if ($years100 == 4)$years100 = 3;
		$days -= $years100 * DateTime::days_in100_years;

		$years4 = floor($days/DateTime::days_in4_years);
		$days -= $years4 * DateTime::days_in4_years;

		$years = floor($days / 365);

		if ($years == 4) $years = 3;

		if ($element == DateTime::element_year)
			return ($years400 * 400) + ($years100 * 100) + ($years4 * 4) + $years + 1;

		$days -= $years * 365;

		if ($element == DateTime::element_day_of_year)
			return $days + 1;

		$days_num = ($years == 3 && ($years4 != 24 || $years100 == 3)) ? $this->days_to_month_leap : $this->days_to_month_reg;

		$shifted = $days >> 6;

		while ($days >= $days_num[$shifted])
			$shifted++;

		if ($element == DateTime::element_month)
			return $shifted;

		return $days - $days_num[$shifted - 1] + 1;
	}

	/**
	 * Adds a scaled value to a current internal value and returns a new DateTime object.
	 * @param Double $value Specifies a value to add.
	 * @param integer $scale_factor Specifies a scale factor.
	 * @return Phpr\DateTime
	 */
	protected function add_interval_internal($value, $scale_factor)
	{
		$value = $value * $scale_factor;

		if ($value <= DateTime::min_ml_seconds || $value >= DateTime::max_ml_seconds)
			throw new ApplicationException("AddInervalInternal: argument is out of range");

		$result = new DateTime(null, $this->timezone);
		$result->set_integer($this->int_value + $value * 10000);

		return $result;
	}

	/**
	 * Computes the remainder after dividing the first parameter by the second.
	 * @param integer $a Specifies the first parameter
	 * @param integer $b Specifies the second parameter
	 * @return Float
	 */
	protected function modulus($a, $b)
	{
		return $a - (floor($a / $b) * $b);
	}

	/**
	 * Converts a date value to the internal representation.
	 * @param integer $year Specifies the year
	 * @param integer $month Specifies the month
	 * @param string $day Specifies the day
	 * @return integer
	 */
	protected function convert_date_val($year, $month, $day)
	{
		if ($year < 1 || $year > 9999)
			throw new ApplicationException("Year is out of range");

		if ($month < 1 || $month > 12)
			throw new ApplicationException("Month is out of range");

		$dtm = !$this->year_is_leap($year) ? $this->days_to_month_reg : $this->days_to_month_leap;

		$diff = $dtm[$month] - $dtm[$month-1];

		if ($day < 1 || $day > $diff)
			throw new ApplicationException("Day is out of range");

		$year--;
		$days = floor($year * 365 + floor($year / 4) - floor($year / 100) + floor($year / 400) + $dtm[$month - 1] + $day - 1);

		return $days * DateTime::int_in_day;
	}

	/**
	 * Converts a time value to internal format
	 * @param integer $hour Specifies the hour
	 * @param integer $minute Specifies the minute
	 * @param string $second Specifies the second
	 * @return integer
	 */
	protected function convert_time_val($hour, $minute, $second)
	{
		if ($hour < 0 || $hour >= 24)
			throw new ApplicationException("Hour is out of range");

		if ($minute < 0 || $minute >= 60)
			throw new ApplicationException("Minute is out of range");

		if ($minute < 0 || $minute >= 60)
			throw new ApplicationException("Second is out of range");

		return DateTime_Interval::convert_time_val($hour, $minute, $second);
	}

	/**
	 * Creates an array with every possible time
	 *
	 * @param  Array  $add_items=array() Extra items to inject
	 * @return Array
	 */
	public static function time_array($add_items=array())
	{
		// Build an array of times
		$return_array = $add_items;
		$time = strtotime("00:00:00");
		$datetime = new DateTime();
		$datetime->set_php_datetime($time);
		$return_array["00:00:00"] = $datetime->format('%I:%M %p');
		for ($i = 1; $i < 48; $i++)
		{
			$time = strtotime("+ 30 minutes", $time);
			$datetime->set_php_datetime($time);
			$key = $datetime->format('%H:%M:%S');
			$return_array[$key] = $datetime->format('%I:%M %p');
		}

		return $return_array;
	}

	public static function interval_as_string($datetime, $word_day='day', $word_hour='hr', $word_minute='min',  $empty='less than a minute')
	{
		$days = $datetime->get_days();
		$hours = $datetime->get_hours();
		$minutes = $datetime->get_minutes();

		$word_day = strlen($word_day > 1) ? String::word_form($days, $word_day) : $word_day;
		$word_hour = strlen($word_hour > 1) ? String::word_form($days, $word_hour) : $word_hour;
		$word_minute = strlen($word_minute > 1) ? String::word_form($days, $word_minute) : $word_minute;

		$datetime_days = ($days > 0) ? $days . $word_day : "";
		$datetime_hours = ($hours > 0) ? $hours . $word_hour : "";
		$datetime_mins = ($minutes > 0) ? $minutes . $word_minute : "";

		$datetime = ($datetime_days=="" && $datetime_mins=="" && $datetime_hours=="") 
			? $empty 
			: trim($datetime_days . " " . $datetime_hours . " " . $datetime_mins);
			
		return $datetime;
	}

	public static function interval_to_now($datetime, $default_text='Some time')
	{
		if (!($datetime instanceof DateTime))
			return $default_text;

		return DateTime::now()->substract_datetime($datetime)->interval_as_string();		
	}

	public static function interval_from_now($datetime, $default_text='Some time')
	{
		if (!($datetime instanceof DateTime))
			return $default_text;

		return $datetime->substract_datetime(DateTime::now())->interval_as_string();		
	}

	public static function format_safe($value, $format='%x')
	{
		if ($value instanceof DateTime) 
			return $value->format($format);
		else
		{
			$length = strlen($value);
			if (!$length)
				return null;
			if ($length <= 10)
				$value .= ' 00:00:00';

			$value = new DateTime($value);
			return $value->format($format);
		}

		return null;
	}

	public static function ago($timestamp)
	{
		$now = time();

		if($now > $timestamp)
			$difference = $now - $timestamp;
		else
			$difference = $timestamp - $now;

		if ($difference < 60)
			return $difference . " " . String::word_form($difference, 'second');
		else 
		{
			$difference = round($difference / 60);
			
			if ($difference < 60)
				return $difference . " " . String::word_form($difference, 'minute');
			else
			{
				$difference = round($difference / 60);
				
				if ($difference < 24)
					return $difference . " " . String::word_form($difference, 'hour');
				else
				{
					$difference = round($difference / 24);
					
					if ($difference < 7)
						return $difference . " " . String::word_form($difference, 'day');
					else
					{
						$difference = round($difference / 7);
						return $difference . " " . String::word_form($difference, 'week');
					}
				}
			}
		}
	}

	/**
	 * @deprecated
	 */ 
	public function toLongDateFormat() { return $this->to_long_date_format(); }
	public function toTimeFormat() { return $this->to_time_format(); }
}
