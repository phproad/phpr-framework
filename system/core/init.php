<?php

// Load initial configuration. This is included again below.
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

// Event handling
Phpr::$events = new Phpr_Events();

// Reponse object
Phpr::$response = new Phpr_Response();

// Session handling
Phpr::$session = new Phpr_Session();

// Security system
Phpr::$security = new Phpr_Security();

// Internal deprecation
Phpr::$deprecate = new Phpr_Deprecate();

// Configure the application and initialize the request object
if (Phpr::$router === null)
    Phpr::$router = new Phpr_Router();

// Load config for usage
if ($path = realpath(PATH_APP . '/config/config.php'))
    include($path);

