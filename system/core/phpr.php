<?php

/**
 * PHPR Core class
 * 
 * This class provides access to the PHPR core objects.
 */

class Phpr 
{

    // Phpr_ClassLoader
    public static $class_loader;


    // Phpr_Config
    public static $config;

    // Phpr_Response
    public static $response;

}


// Init class loader
require_once('classloader.php');

Phpr::$class_loader = new Phpr_ClassLoader();

spl_autoload_register(function($name) {
    Phpr::$class_loader->load($name);
});

// Init others
Phpr::$config = new Phpr_Config();