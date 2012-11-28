<?php

/**
 * Error Reporting Level
 * ----------------------------------------------------------
 * By default PHPR will run with this set to ALL. You should
 * look at lowering this for a production site.
 */

error_reporting(E_ALL);

/**
 * Extra Config Settings
 * ----------------------------------------------------------
 * For example to support PHP versions before 5.3.0
 */

ini_set('magic_quotes_runtime', 0);
ini_set('auto_detect_line_endings', true);

/**
 * Native timezone
 * ----------------------------------------------------------
 * Sets the base timezone to manipulate time. Default: GMT
 */

date_default_timezone_set('GMT');

/**
 * System Folder
 * ----------------------------------------------------------
 * Defines the system folder where PHPR is located.
 */

$system_folder = 'system';

/**
 * Application Folder
 * ----------------------------------------------------------
 * If you want your application to run in a folder other than
 * the root directory, define it here.
 */

$app_folder = 'app';

/**
 * Public Folder
 * ----------------------------------------------------------
 * Location of the public web folder.
 */

$public_folder = 'public';

/**
 * Application Constants
 * ----------------------------------------------------------
 * DS          - Directory separator shorthand
 * PHPR_VERSION- Library version
 * PHPR_EXT    - File extensions (eg: .php)
 * PATH_BOOT   - Path to bootstrap location
 * PATH_SYSTEM - Path to the PHPR system directory
 * PATH_APP    - Path to the application directory
 */

defined('DS') ? null : define('DS', DIRECTORY_SEPARATOR);
define('PHPR_VERSION', '2.0.0');
define('PHPR_EXT', pathinfo(__FILE__, PATHINFO_EXTENSION));
define('PATH_BOOT', __FILE__);
define('PATH_SYSTEM', realpath(dirname(dirname(__FILE__))).'/'.$system_folder);
define('PATH_APP', realpath(dirname(dirname(__FILE__))).'/'.$app_folder);
define('PATH_PUBLIC', realpath(dirname(dirname(__FILE__))).'/'.$public_folder);

// ------------------------------------------------------------------------
// Load PHPR
// ------------------------------------------------------------------------

require_once(PATH_SYSTEM.DS.'core'.DS.'init.php');
