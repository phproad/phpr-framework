<?php namespace Phpr;

use Phpr\String;
use Phpr\ApplicationException;

/**
 * PHPR Time Class
 *
 * Phpr\Time class for handling simple 24 hour time fields (local times). For timezones use DateTime
 */
class Time
{
	protected $time;
    protected $int_value;


	/**
	 * Represents the universal time format: 20:00:00
	 * @var string
	 */		
	const universal_time_format = 'H:i:s';

	
	/**
	 * Creates a new Time instance. If no time give, system time is used
	 * @param string $time Optional. Specifies the time in format '23:00:00' to assign to the instance.
     * @throws ApplicationException if invalid time given
	 */
	public function __construct($time=null){
        if(!$time){
            $time = date('H:i:s');
        }
			$time = $this->format_time($time, self::universal_time_format);
			if (!$time)
				throw new ApplicationException("Can not parse time string: ".$time);

			$this->time = $time;
            $this->int_value = str_replace(':','',$this->time);
	}

    /**
     * Returns the time as a string.
     * @return string
     */
	public function __toString()
	{
		return $this->time;
	}

    /**
     * Returns the time as an integer.
     * @return int
     */
    public function __toInt(){
        return $this->int_value;
    }

    /**
     * Returns the time in locale format.
     * @param string locale format
     * @return string
     */
    public function __toLocale($format='%X'){
        return strftime($format,strtotime($this->time) );
    }


    /**
     * Changes a time string into given format
     * @param string a valid time sting
     * @param string time format eg. H:i:s
     * @return string
     */
    public function format_time($time, $format=self::universal_time_format){

        $p1 = '/^(0?\d|1\d|2[0-3]):[0-5]\d:[0-5]\d$/';
        $p2 = '/^(0?\d|1[0-2]):[0-5]\d\s(am|pm)$/i';
        $valid_time = preg_match($p1, $time) || preg_match($p2, $time);
        if ($valid_time) {
            return date($format, strtotime($time));
        }
        return false;
    }


	/**
	 * Returns the hour component of the time represented by the object.
	 * @return integer
	 */
	public function get_hour()
	{
		return $this->format_time($this->time,'G');
	}

	/**
	 * Returns the minute component of the time represented by the object.
	 * @return integer
	 */
	public function get_minute()
	{
		return intval($this->format_time($this->time,'i'));
	}

	/**
	 * Returns the second element of the time represented by the object.
	 * @return integer
	 */
	public function get_second()
	{
        return intval($this->format_time($this->time,'s'));
	}

	/**
	 * Returns a new Time object with hours added on.
	 * @param int $hours Specifies a number of hours to add.
	 * @return Time
     * @throws ApplicationException if invalid param given
	 */
	public function add_hours($hours)
	{
        if(!is_numeric($hours)){
            throw new ApplicationException('Invalid Hour value: '.$hours);
        }
        $time = date('H:i:s', strtotime(date('Y-m-d '.$this->__toString()) . ' + '.$hours.' hours'));
        return new Time($time);
	}

    /**
     * Returns a new Time object with minutes added on.
     * @param int $minutes Specifies a number of minutes to add.
     * @return Time
     * @throws ApplicationException if invalid param given
     */
	public function add_minutes($minutes)
	{
        if(!is_numeric($minutes)){
            throw new ApplicationException('Invalid Minute value: '.$minutes);
        }
        $time = date('H:i:s', strtotime(date('Y-m-d '.$this->__toString()) . ' + '.$minutes.' minutes'));
        return new Time($time);
	}

    /**
     * Returns a new Time object with seconds added on.
     * @param int $seconds Specifies a number of seconds to add.
     * @return Time
     * @throws ApplicationException if invalid param given
     */
	public function add_seconds($seconds)
	{
        if(!is_numeric($seconds)){
            throw new ApplicationException('Invalid Seconds value: '.$seconds);
        }
        $time = date('H:i:s', strtotime(date('Y-m-d '.$this->__toString()) . ' + '.$seconds.' seconds'));
        return new Time($time);
	}

	/**
	 * Compares this object with another Phpr\Time object, 
	 * Returns 1 if this object value is more than a specified value,
	 * 0 if values are equal and 
	 * -1 if this object value is less than a specified value.
	 * The scope of this class is any given 24 hour period, so times are assumed the same day.
	 * @param Time $value Specifies the Phpr\Time object to compare with.
	 * @return integer
	 */
	public function compare(Time $value)
	{
		if ($this->__toInt() > $value->__toInt())
			return 1;

		if ($this->__toInt() < $value->__toInt())
			return -1;

		return 0;
	}


	/**
	 * Determines whether a value of this object matches a value of another specified Phpr\Time object.
	 * @param Time $value Specifies object to compare with
	 * @return boolean
	 */
	public function equals(Time $value)
	{
		return $this->__toInt() == $value->__toInt();
	}


    /**
     * Get time difference between two times
     * @param Time $value to compare with
     * @return string universal format time string
     */
    public function time_diff(Time $value){
        $diff = strtotime($this->__toString()) - strtotime($value->__toString());
        return date(self::universal_time_format, $diff);
    }



	/**
	 * Returns a string representation of the time
	 * @param string $format Specifies the formatting string. For example: 'g:i A'
	 * @return string
	 */
	public function format($format)
	{
		return $this->format_time($this->time, $format);
	}

    /**
     * Returns object value in SQL time format
     * @return string
     */
    public function to_sql_time()
    {
        return $this->format(self::universal_time_format);
    }

	/**
	 * Determines whether the string specified is a database null time representation
     * @param string $str
     * @return string
	 */
	public static function is_db_null($str)
	{
		if (!strlen($str))
			return true;

		return false;
	}

    /**
     * Shows the time in locale
     * @param Time $time_obj
     * @param string $format Locale format
     * @return string
     */
    public static function display($time_obj, $format){
        if (!$time_obj)
            return null;

        return $time_obj->__toLocale($format);
    }
	

	


}
