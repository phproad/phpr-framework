<?php

/**
 * JavaScript/CSS resources combining
 */

if (!isset($_GET['file']))
	exit();

function minify_css($css)
{
	$css = preg_replace('#\s+#', ' ', $css);
	$css = preg_replace('#/\*.*?\*/#s', '', $css);
	$css = str_replace('; ', ';', $css);
	$css = str_replace(': ', ':', $css);
	$css = str_replace(' {', '{', $css);
	$css = str_replace('{ ', '{', $css);
	$css = str_replace(', ', ',', $css);
	$css = str_replace('} ', '}', $css);
	$css = str_replace(';}', '}', $css);
	
	return trim($css);
}

function is_remote_file($path)
{
	return substr($path, 0, 7) === 'http://' || substr($path, 0, 8) === 'https://';
}

// Load the config when it has access to PATH_APP for $allowed_paths
include(PATH_APP . '/config/config.php'); 

$allowed_types = isset($CONFIG['ALLOWED_RESOURCE_EXTENSIONS']) ? $CONFIG['ALLOWED_RESOURCE_EXTENSIONS'] : array('js', 'css');
$allowed_paths = isset($CONFIG['ALLOWED_RESOURCE_PATHS']) ? $CONFIG['ALLOWED_RESOURCE_PATHS'] : array(PATH_APP);

$recache = isset($_GET['reset_cache']);
$skip_cache = isset($_GET['skip_cache']);
$src_mode = isset($_GET['src_mode']);

$files = $_GET['file'];
$assets = array();

/*
 * Prepare the asset list
 */

$type = null;

foreach ($files as $url)
{
	// Is this file allowed to be an asset?
	$allowed = false;

	$file = $orig_url = str_replace(chr(0), '', urldecode($url));
	$type = pathinfo(strtolower($file), PATHINFO_EXTENSION);

	if (!in_array($type, $allowed_types))
		continue;

	if (!is_remote_file($file)) 
	{

		$file = str_replace('\\', '/', realpath(PATH_APP . $file));

		foreach ($allowed_paths as $allowed_path) 
		{
			// We already found this file in the allowed paths
			if ($allowed)
				continue; 

			$is_relative = strpos($allowed_path, '/') !== 0 && strpos($allowed_path, ':') !== 1;

			if ($is_relative)
				$allowed_path = PATH_APP . '/' . $allowed_path; // allow relative path such as ../

			$allowed_path = str_replace('\\', '/', realpath($allowed_path));

			if (strpos($file, $allowed_path) === 0) 
			{
				// The file is allowed to be an asset because it matches the requirements (allowed paths)
				$allowed = true;
			}
		}
	} 
	else
	{
		// Always allow remote files
		$allowed = true; 
	}

	// Not allowed
	if (!$allowed)
		continue;

	$assets[$orig_url] = $file;
}

/*
 * Check whether GZIP is supported by the browser
 */
$supports_gzip = false;
$encodings = array();
if (isset($_SERVER['HTTP_ACCEPT_ENCODING']))
	$encodings = explode(',', strtolower(preg_replace('/\s+/', '', $_SERVER['HTTP_ACCEPT_ENCODING'])));

if (
	(in_array('gzip', $encodings) || in_array('x-gzip', $encodings) || isset($_SERVER['---------------']))
	&& function_exists('ob_gzhandler')
	&& !ini_get('zlib.output_compression')
)
{
	$enc = in_array('x-gzip', $encodings) ? 'x-gzip' : 'gzip';
	$supports_gzip = true;
}

/*
 * Caching
 */

$mime = 'text/plain';

switch ($type)
{
    case 'js':
        $mime = 'text/javascript';
    break;

    case 'css':
        $mime = 'text/css';
    break;

}

$cache_path = PATH_APP . '/temp/asset_cache';
if (!file_exists($cache_path))
	mkdir($cache_path);

$cache_hash = sha1(implode(',', $assets));

$cache_filename = $cache_path.'/'.$cache_hash.'.'.$type;
if ($supports_gzip)
	$cache_filename .= '.gz';

$cache_exists = file_exists($cache_filename);

if ($recache && $cache_exists)
	@unlink($cache_filename);

$assets_mod_time = 0;
foreach ($assets as $file)
{
	if (!is_remote_file($file))
	{
		if (file_exists($file))
			$assets_mod_time = max($assets_mod_time, filemtime($file));
	} else
	{
		/*
		 * We cannot reliably check the modification time of a remote resource,
		 * because time on the remote server could not exactly match the time
		 * on this server.
		 */

		//$assets_mod_time = 0;
	}
}

$cached_mod_time = $cache_exists ? (int) @filemtime($cache_filename) : 0;

if ($type == 'css')
	require PATH_SYSTEM.'/vendor/csscompressor/UriRewriter.php';

if ($type == 'js')
	require PATH_SYSTEM.'/vendor/javascriptpacker/JSpp.php';

$enable_remote_resources = !isset($CONFIG['ENABLE_REMOTE_RESOURCES']) || $CONFIG['ENABLE_REMOTE_RESOURCES'];

$content = '';
if ($skip_cache || $cached_mod_time < $assets_mod_time || !$cache_exists)
{
	foreach ($assets as $orig_url=>$file)
	{
		$is_remote = is_remote_file($file);

		if ($is_remote && !$enable_remote_resources)
			continue;

        // Determine if the script lives in a sub folder
        $sub_directory = dirname(dirname($_SERVER['REQUEST_URI']));
        if ($sub_directory == '\\') $sub_directory = '/';

		if (file_exists($file) || $is_remote)
		{
			$data = @file_get_contents($file) . "\r\n";

			if ($type == 'css')
			{
				if (!$is_remote)
				{
					$data = Minify_CSS_UriRewriter::rewrite(
						$data,
						dirname($file),
						PATH_APP .'/',
						array('/' . $sub_directory => isset($CONFIG['CSS_RESOURCE_DIRECTORY']) ? $CONFIG['CSS_RESOURCE_DIRECTORY'] : PATH_APP) // CSS_RESOURCE_DIRECTORY for symlinks
					);
				} 
				else
				{
					$data = Minify_CSS_UriRewriter::prepend(
						$data,
						dirname($file).'/'
					);
				}
			}
			else if ($type == 'js')
			{
				$data = JSpp::parse($data, dirname($file));
			}

			$content .= $data;
		}
		else
			$content .= sprintf("\r\n/* Asset Error: asset %s not found. */\r\n", $orig_url);
	}

	if ($type == 'js' && !$src_mode)
	{
		require PATH_SYSTEM.'/vendor/javascriptpacker/JSMin.php';
		$content = JSMin::minify($content);
	} 
	else if (($type == 'css') && !$src_mode)
	{
		$content = minify_css($content);
	}

	if ($supports_gzip)
		$content = gzencode($content, 9, FORCE_GZIP);

	if (!$skip_cache)
		@file_put_contents($cache_filename, $content);
}
else if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $assets_mod_time <= strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']))
{
	header('Content-Type: ' . $mime);
	if (php_sapi_name() == 'CGI')
		header('Status: 304 Not Modified');
	else
		header('HTTP/1.0 304 Not Modified');

	exit();
}
else if (file_exists($cache_filename))
	$content = @file_get_contents($cache_filename);

/*
 * Output
 */

header('Content-Type: ' . $mime);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $assets_mod_time).' GMT');

if ($supports_gzip)
{
	header('Vary: Accept-Encoding');  // Handle proxies
	header('Content-Encoding: ' . $enc);
}

echo $content;
