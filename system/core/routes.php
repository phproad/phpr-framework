<?php

/**
 * PHPR Router
 */

// Admin routes
// 

$route = Phpr::$router->addRule($backend_url."/:module/:controller/:action/:param1/:param2/:param3/:param4");
$route->folder('modules/:module/controllers');
$route->def('module', 'admin');
$route->def('controller', 'index');
$route->def('action', 'index');
$route->def('param1', null);
$route->def('param2', null);
$route->def('param3', null);
$route->def('param4', null);
$route->convert('controller', '/^.*$/', ':module_$0');

// Public routes
// 

$route = Phpr::$router->addRule("/:param1/:param2/:param3/:param4/:param5/:param6");
$route->def('param1', null);
$route->def('param2', null);
$route->def('param3', null);
$route->def('param4', null);
$route->def('param5', null);
$route->def('param6', null);
$route->controller('application');
$route->action('index');
