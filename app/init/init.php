<?php

// Define constants
define('PATH_MODULES', PATH_APP."/modules");

if (!isset($PHPR_NO_SESSION) || !$PHPR_NO_SESSION)
{
    // Override CMS security object
    Phpr::$frontend_security = new Cms_Security();

    // Override admin security object
    Phpr::$security = new Admin_Security();

    // Start session object
    Phpr::$session->start();
}

// Include routing
require_once('routes.php');

// Include helpers
require_once('helpers.php');

// Default application config
if (!isset($APP_CONF))
    $APP_CONF = array();

$APP_CONF['UPDATE_SEQUENCE'] = array('core');
$APP_CONF['JAVASCRIPT_URL'] = "framework/javascript";
$APP_CONF['PHPR_URL'] = "framework";