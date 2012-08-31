<?php

/**
 * PHPR Core class
 */

class Phpr 
{

    // Autoloader
    public static $loader;


    // Config
    public static $config;

}


// Init auto loader
require_once('autoloader.php');

Phpr::$loader = new Phpr_Autoloader();

spl_autoload_register(function() {
    Phpr::$loader->load($name);
});

// Init others
Phpr::$config = new Phpr_Config();