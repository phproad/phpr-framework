<?php namespace Phpr;

use DateTimeZone;
use Phpr;

/*
 * TimeZone helper
 */

class TimeZone
{
    public static $timezones_array = null;

    public static function is_valid_timezone($time_zone) {
       if(empty($time_zone)){
           return FALSE;
       }
        try{
            new \DateTimeZone($time_zone);
        }catch(Exception $e){
            return FALSE;
        }
        return TRUE;
    }

    public static function get_timezone_list()
    {

        if (self::$timezones_array === null) {
            self::$timezones_array = Phpr::$session->get('Phpr_TimeZone::get_timezone_list', null);


            if (self::$timezones_array  === null) {
                self::$timezones_array = array();
                $offsets = array();
                $now = new \DateTime();

                foreach (\DateTimeZone::listIdentifiers() as $timezone) {
                    $now->setTimezone(new \DateTimeZone($timezone));
                    $offsets[] = $offset = $now->getOffset();
                    self::$timezones_array[$timezone] = '(' . self::format_GMT_offset($offset) . ') ' . self::format_timezone_name($timezone);
                }

                array_multisort($offsets, self::$timezones_array );
                Phpr::$session->set('Phpr_TimeZone::get_timezone_list', self::$timezones_array );
            }
        }

        return self::$timezones_array ;
    }

    protected function format_GMT_offset($offset)
    {
        $hours = intval($offset / 3600);
        $minutes = abs(intval($offset % 3600 / 60));
        return 'GMT' . ($offset ? sprintf('%+03d:%02d', $hours, $minutes) : '');
    }

    protected function format_timezone_name($name)
    {
        $name = str_replace('/', ', ', $name);
        $name = str_replace('_', ' ', $name);
        $name = str_replace('St ', 'St. ', $name);
        return $name;
    }
}
