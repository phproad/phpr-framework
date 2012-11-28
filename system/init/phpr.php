<?php

/**
 * PHPR Core class
 */

class Phpr 
{

    // Classloader
    public static $class_loader;


    // Config
    public static $config;

}


// Init auto loader
require_once('autoloader.php');

Phpr::$loader = new Phpr_Autoloader();

spl_autoload_register(function($name) {
    Phpr::$loader->load($name);
});

// Init others
Phpr::$config = new Phpr_Config();