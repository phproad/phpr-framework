<?php

class Db_Where extends Db_Where_Base
{
    public static function create()
    {
        return new self();
    }

    public function __toString()
    {
        return $this->build_where();
    }

    public function where() 
    {

    }    
}
