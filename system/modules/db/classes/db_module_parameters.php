<?php

class Db_Module_Parameters
{
    private static $_cache = null;

    private static function init_cache()
    {
        if (self::$_cache != null)
            return;

        self::$_cache = array();

        $records = Db_DbHelper::objectArray('select * from moduleparams');
        foreach ($records as $param)
        {
            $name = $param->name;
            $module_id = $param->module_id;

            if (!isset(self::$_cache[$module_id]))
                self::$_cache[$module_id] = array();

            self::$_cache[$module_id][$name] = $param->value;
        }
    }

    public static function get($module_id, $name, $default = null)
    {
        self::init_cache();

        if (!isset(self::$_cache[$module_id]) || !isset(self::$_cache[$module_id][$name]))
            return $default;

        try
        {
            return @unserialize(self::$_cache[$module_id][$name]);
        }
        catch (Exception $ex)
        {
            return $default;
        }
    }

    public static function set($module_id, $name, $value)
    {
        self::init_cache();
        
        $value = serialize($value);

        self::$_cache[$module_id][$name] = $value;
        
        $bind = array(
            'module_id' => $module_id,
            'name'      => $name,
            'value'     => $value
        );
        
        Db_DbHelper::query('delete from moduleparams where module_id=:module_id and name=:name', $bind);
        Db_DbHelper::query('insert into moduleparams(module_id, name, value) values (:module_id,:name,:value)', $bind);
    }

}
