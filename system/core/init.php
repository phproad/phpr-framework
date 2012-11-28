<?php

// Load initial configuration. This is included more than once.
if ($path = realpath(PATH_APP . '/config/config.php'))
    include($path); 

// Core PHPR class
require_once('phpr.php');

// Initialize auto class loading
// 

require_once('classloader.php');

Phpr::$class_loader = new Phpr_ClassLoader();

function phpr_autoload_internal($name) 
{
    if (!Phpr::$class_loader->load($name)) 
    {
        // Load failed
    }
}

if (function_exists('spl_autoload_register')) 
{
    spl_autoload_register('phpr_autoload_internal');
} 
else 
{
    function __autoload($name) 
    {
        phpr_autoload_internal($name);
    }
}

// Exception handling
require_once('exceptions.php');

// Process resource requests (may terminate thread)
Phpr_Response::process_resource_request();


// Init others
Phpr::$config = new Phpr_Config();