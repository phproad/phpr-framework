<?php

class Db_Sql extends Db_Sql_Base 
{
    public static function create() 
    {
        return new self();
    }

    public function __toString() 
    {
        return $this->build_sql();
    }

    public function select() 
    {

    }
}
