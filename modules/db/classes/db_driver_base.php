<?php

/**
 * PHPR Database Driver base class
 */

class Db_Driver_Base
{
    public function connect() 
    {

    }

    public function reconnect()
    {

    }

    public function execute($sql) 
    {
        return 0;
    }

    public function fetch($result, $col = null) 
    {
        return false;
    }
    
    public function free_query_result($resource)
    {
        return null;
    }

    public function row_count() 
    {
        return 0;
    }

    public function last_insert_id($table_name = null, $primary_key = null) 
    {
        return -1;
    }

    public function describe_table($table) 
    {
        return array();
    }

    public function limit($offset, $count = null) 
    {
    }
    
    public function quote_object_name($name)
    {
        return $name;
    }
}