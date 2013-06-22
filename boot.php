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

$system_folder = 'framework';

/**
 * Application Folder
 * ----------------------------------------------------------
 * If you want your application to run in a folder other than
 * the root directory, define it here.
 */

$app_folder = '';

/**
 * Public Folder
 * ----------------------------------------------------------
 * Location of the public web folder.
 */

$public_folder = '';

/**
 * Modules Name
 * ----------------------------------------------------------
 * Name of modules folder. Used in various places.
 */

$modules_name = 'modules';

/**
 * Application Constants
 * ----------------------------------------------------------
 * PHPR_VERSION- Library version
 * DS          - Directory separator shorthand
 * PATH_BOOT   - Path to bootstrap location
 * PATH_APP    - Path to the application directory
 * PATH_SYSTEM - Path to the PHPR system directory
 * PHPR_EXT    - File extensions (eg: .php)
 * PHPR_MODULES- Modules folder (eg: modules)
 */

define('PHPR_VERSION', '2.0.0');
defined('DS')           ? null : define('DS', DIRECTORY_SEPARATOR);
defined('PATH_BOOT')    ? null : define('PATH_BOOT', dirname(__FILE__));
defined('PATH_APP')     ? null : define('PATH_APP', realpath(dirname(PATH_BOOT)).DS.$app_folder);
defined('PATH_SYSTEM')  ? null : define('PATH_SYSTEM', realpath(dirname(PATH_BOOT)).DS.$system_folder);
defined('PATH_PUBLIC')  ? null : define('PATH_PUBLIC', realpath(dirname(PATH_BOOT)).DS.$public_folder);
defined('PHPR_EXT')     ? null : define('PHPR_EXT', pathinfo(__FILE__, PATHINFO_EXTENSION));
defined('PHPR_MODULES') ? null : define('PHPR_MODULES', $modules_name);

// ------------------------------------------------------------------------
// Handle asset requests
// ------------------------------------------------------------------------

if (array_key_exists('q', $_GET) && (strpos($_GET['q'], 'javascript_combine/') !== false || strpos($_GET['q'], 'css_combine/') !== false))
{
	include(PATH_SYSTEM.DS.'core'.DS.'combine_assets.php');
	die();
}

// ------------------------------------------------------------------------
// Load PHPR
// ------------------------------------------------------------------------

require_once(PATH_SYSTEM.DS.'core'.DS.'init.php');
