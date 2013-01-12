<?php

/**
 * PHPR Database base class
 */

class Db
{
    public static $connection = null;
    public static $describe_cache = array();

    public static function sql() 
    {
        return new Db_Sql();
    }

    public static function select() 
    {
        $args = func_get_args();
        $sql = new Db_Sql();
        return call_user_func_array(array(&$sql, 'select'), $args);
    }

    public static function where()
    {
        $args = func_get_args();
        $where = new Db_Where();
        return call_user_func_array(array(&$where, 'where'), $args);
    }
}
