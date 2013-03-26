<?php

class Db_MySQL_Driver extends Db_Driver_Base 
{
    public static function create() 
    {
        return new self();
    }
}