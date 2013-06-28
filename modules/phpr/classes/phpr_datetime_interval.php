<?php

/**
 * PHPR DateTimeInterval Class
 * 
 * Phpr_DateTime_Interval class represents a period, or interval of time.
 */
class Phpr_DateTime_Interval
{
	protected $int_value = 0;

	const min_seconds_value = -922337203685;
	const max_seconds_value = 922337203685;

	/**
	 * Creates a new Phpr_DateTime_Interval instance.
	 * @param integer $days Specifies a number of days
	 * @param integer $hours Specifies a the number of hours
	 * @param integer $minutes Specifies a number of minutes
	 * @param integer $seconds Specifies a number of seconds
	 */
	public function __construct($days = 0, $hours = 0, $minutes = 0, $seconds = 0)
	{
		$this->set_as_days_and_time($days, $hours, $minutes, $seconds);
	}

	/**
	 * @ignore
	 * This method is used by the PHPR internally.
	 * Converts a time value to internal format.
	 * @param integer $hour Specifies a hour
	 * @param integer $minute Specifies a minute
	 * @param string $second Specifies a second
	 * @return integer
	 */
	public static function convert_time_val($hour, $minute, $second)
	{
		$seconds = $hour*3600 + $minute*60 + $second;

		if ($seconds > Phpr_DateTime_Interval::max_seconds_value || $seconds < Phpr_DateTime_Interval::min_seconds_value)
			throw new Phpr_SystemException("Datetime interval is out of range");

		return $seconds*Phpr_DateTime::int_in_second;
	}

	/**
	 * @ignore
	 * This method is used by the PHPR internally
	 * Returns the integer representation of the value
	 * @return integer
	 */
	public function get_integer()
	{
		return $this->int_value;
	}

	/**
	 * @ignore
	 * This method is used by the PHPR internally.
	 * Sets the interval value to the value corresponding the integer value specified.
	 * @param integer $value Specifies a integer value
	 */
	public function set_integer($value)
	{
		$this->int_value = $value;
	}

	/**
	 * Returns a number of whole days in the interval.
	 * @return integer
	 */
	public function get_days()
	{
		return $this->floor($this->int_value / Phpr_DateTime::int_in_day);
	}

	/**
	 * Returns a number of whole hours in the interval.
	 * @return integer
	 */
	public function get_hours()
	{
		return $this->floor(($this->int_value / Phpr_DateTime::int_in_hour) % 24);
	}

	/**
	 * Returns a number of whole minutes in the interval.
	 * @return integer
	 */
	public function get_minutes()
	{
		return $this->floor(($this->int_value / Phpr_DateTime::int_in_minute) % 60);
	}

	/**
	 * Returns a number of whole seconds in the interval.
	 * @return integer
	 */
	public function get_seconds()
	{
		return $this->floor($this->modulus($this->int_value / Phpr_DateTime::int_in_second, 60));
	}

	/**
	 * Returns a total number of days in the interval.
	 * @return float
	 */
	public function get_days_total()
	{
		return $this->int_value/Phpr_DateTime::int_in_day;
	}

	/**
	 * Returns a total number of seconds in the interval.
	 * @return float
	 */
	public function get_seconds_total()
	{
		return $this->int_value / Phpr_DateTime::int_in_second;
	}

	/**
	 * Returns a total number of minutes in the interval.
	 * @return float
	 */
	public function get_minutes_total()
	{
		return $this->int_value / Phpr_DateTime::int_in_minute;
	}

	/**
	 * Returns a total number of hours in the interval.
	 * @return float
	 */
	public function get_hours_total()
	{
		return $this->int_value / Phpr_DateTime::int_in_hour;
	}

	/**
	 * Returns a positive length of the interval.
	 * @return Phpr_DateTime_Interval
	 */
	public function length()
	{
		$result = new Phpr_DateTime_Interval;

		if ($this->int_value < 0)
			$result->set_integer($this->int_value*(-1));
		else
			$result->set_integer($this->int_value);

		return $result;
	}

	/**
	 * Compares this object with another Phpr_DateTime_Interval object, 
	 * Returns:
	 * 1 if this object value is more than the value specified,
	 * 0 if values are equal,
	 * -1 if this object value is less than the value specified.
	 * @param Phpr_DateTime_Interval $value Value to compare with
	 * @return integer
	 */
	public function compare(Phpr_DateTime_Interval $value)
	{
		if ($this->int_value > $value->get_integer())
			return 1;

		if ($this->int_value < $value->get_integer())
			return -1;

		return 0;
	}

	/**
	 * Compares two intervals.
	 * Returns 1 if the first value is more than the second value,
	 * 0 if values are equal,
	 * -1 if the first value is less than the second value.
	 * @param Phpr_DateTime_Interval $value1 Specifies the first interval
	 * @param Phpr_DateTime_Interval $value2 Specifies the second interval
	 * @return integer
	 */
	public static function compare_intervals(Phpr_DateTime_Interval $value1, Phpr_DateTime_Interval $value2)
	{
		if ($value1->get_integer() > $value2->get_integer())
			return 1;

		if ($value1->get_integer() < $value2->get_integer())
			return -1;

		return 0;
	}

	/**
	 * Determines whether a value of this object matches a value of the Phpr_DateTime_Interval object specified.
	 * @param Phpr_DateTime_Interval $value Specifies a value to compare with
	 * @return boolean
	 */
	public function equals(Phpr_DateTime_Interval $value)
	{
		return $this->int_value == $value->get_integer();
	}

	/**
	 * Determines whether the value of this object matches the value of the Phpr_DateTime_Interval object specified.
	 * @param Phpr_DateTime_Interval $value Value to compare with
	 * @return boolean
	 */
	public function add(Phpr_DateTime_Interval $value)
	{
		$result = new Phpr_DateTime_Interval();

		$result->set_integer($this->int_value + $value->get_integer());

		return $result;
	}

	/**
	 * Substructs the specified Phpr_DateTime_Interval object from this object value 
	 * and returns a new Phpr_DateTime_Interval instance.
	 * @param Phpr_DateTime_Interval $value Specifies the interval to substract
	 * @return Phpr_DateTime_Interval
	 */
	public function substract(Phpr_DateTime_Interval $value)
	{
		$result = new Phpr_DateTime_Interval();

		$result->set_integer($this->int_value - $value->get_integer());

		return $result;
	}

	/**
	 * Sets the interval value to the specified number of hours, minutes and seconds.
	 * @param integer $hours Specifies the number of hours
	 * @param integer $minutes Specifies the number of minutes
	 * @param integer $seconds Specifies the number of seconds
	 */
	public function set_as_time($hours, $minutes, $seconds)
	{
		$this->int_value = $this->convert_time_val($hours, $minutes, $seconds);
	}

	/**
	 * Sets the interval value to the specified number of days, hours, minutes and seconds.
	 * @param integer $days Specifies the number of days
	 * @param integer $hours Specifies the number of hours
	 * @param integer $minutes Specifies the number of minutes
	 * @param integer $seconds Specifies the number of seconds
	 */
	public function set_as_days_and_time($days, $hours, $minutes, $seconds)
	{
		$this->int_value = $days*(Phpr_DateTime::int_in_day) + $this->convert_time_val($hours, $minutes, $seconds);
	}
	
	/**
	 * Returns the interval value as string.
	 * Example: less than a minute
	 */
	public function interval_as_string()
	{
		$mins = floor($this->get_minutes_total());
		if ($mins < 1)
			return Phpr::$locale->get_string('phpr.dates', 'interval_now');

		$minute_str = Phpr::$locale->get_string('phpr.dates', 'minute_str');
		$hour_str = Phpr::$locale->get_string('phpr.dates', 'hour_str');
		$day_str = Phpr::$locale->get_string('phpr.dates', 'day_str');
		$interval_prefix = Phpr::$locale->get_string('phpr.dates', 'interval_prefix');
		$interval_suffix = Phpr::$locale->get_string('phpr.dates', 'interval_suffix');

		if ($mins < 60)
			return $interval_prefix.' '.Phpr_String::word_form($mins, $minute_str, true).' '.$interval_suffix;

		$hours = floor($this->get_hours_total());
		if ($hours < 24)
			return $interval_prefix.' '.Phpr_String::word_form($hours, $hour_str, true).' '.$interval_suffix;

		$days = floor($this->get_days_total());
		return Phpr_String::word_form($days, $day_str, true);
	}

	/**
	 * Rounds variables toward negative infinity.
	 * @param Float $value Specifies the value
	 * @return integer
	 */
	protected function floor($value)
	{
		if ($value > 0)
			return floor($value);
		else
			return ceil($value);
	}

	/**
	 * Computes the remainder after dividing the first parameter by the second.
	 * @param integer $a Specifies the first parameter
	 * @param integer $b Specifies the second parameter
	 * @return float
	 */
	protected function modulus($a, $b)
	{
		$neg = $a < 0;
		if ($neg)
			$a *= -1;

		$res = $a-floor($a/$b)*$b;

		if ($neg)
			$res *= -1;

		return $res;
	}
}
