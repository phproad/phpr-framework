<?php

/**
 * PHPR module manager
 * 
 * Used to locate and interact with modules
 */

class Phpr_Module_Manager
{

    // Returns all available modules as an array
    public static function find_modules()
    {
    }

    // Returns a module directory as an absolute path or null if not found
    public static function find_module($module) 
    {
    }

    // Returns a module object by its identifier
    public static function find_by_id($module_id)
    {
    }    

    // Checks the existence of a module
    public static function module_exists($module_id)
    {
        return self::find_by_id($module_id) ? true : false;
    }
    
}