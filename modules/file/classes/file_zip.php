<?php

$zip_helper_exceptions = array();

class File_Zip
{
	public static $chmod_error = false;
	
	protected static $initialized = false;
	
	public static function zip_file($file, $archive_path)
	{
		self::init_zip();
		chdir(dirname($file));

		$archive = new PclZip($archive_path);
		$res = $archive->create(array());

		$fileName = basename($file);
		$archive->add($fileName);
		@chmod($archive_path, Phpr_Files::get_file_permissions());
	}
	
	/**
	 * Archives a directory
	 * @param $exceptions Array of folders, files or masks to exclude
	 * Masks could include the following:
	 * images/* - will exclude all folders and files from images directory
	 * images/[files] - file exclude all files from images directory and its subdirectories
	 */
	public static function zip_directory($path, $archive_path, $exceptions = array(), $archive = null)
	{
		self::init_zip();
		chdir($path);
		
		global $zip_helper_exceptions;
		$zip_helper_exceptions = $exceptions;

		if (!$archive)
		{
			$archive = new PclZip($archive_path);
			$res = $archive->create(array());
		}

		$d = dir($path);
		while (false !== ($entry = $d->read())) 
		{
			if ($entry == '.' || $entry == '..')
				continue;

			$archive->add($entry, PCLZIP_CB_PRE_ADD, 'zip_helper_pre_add');
		}

		$d->close();
		@chmod($archive_path, Phpr_Files::get_file_permissions());
	}
	
	public static function unzip($path, $archive_path, $replace_files = true, $set_permissions = true)
	{
		if (!file_exists($archive_path))
			throw new Phpr_SystemException('Archive file is not found');

		if (!is_writable($path))
			throw new Phpr_SystemException('No writing permissions for directory '.$path);

		self::init_zip();
		$archive = new PclZip($archive_path);

		if ($set_permissions && $replace_files)
		{
			if (!@$archive->extract(PCLZIP_OPT_PATH, $path, PCLZIP_OPT_REPLACE_NEWER, PCLZIP_CB_POST_EXTRACT, 'zip_helper_post_extract'))
				throw new Phpr_SystemException('Error extracting data from archive');
		} 
		else if ($set_permissions && !$replace_files)
		{
			if (!@$archive->extract(PCLZIP_OPT_PATH, $path, PCLZIP_CB_POST_EXTRACT, 'zip_helper_post_extract'))
				throw new Phpr_SystemException('Error extracting data from archive');
		}
		else if (!$set_permissions && $replace_files)
		{
			if (!@$archive->extract(PCLZIP_OPT_PATH, $path, PCLZIP_OPT_REPLACE_NEWER))
				throw new Phpr_SystemException('Error extracting data from archive');
		}
		else if (!$set_permissions && !$replace_files)
		{
			if (!@$archive->extract(PCLZIP_OPT_PATH, $path))
				throw new Phpr_SystemException('Error extracting data from archive');
		}
	}
	
	public static function init_zip()
	{
		if (self::$initialized)
			return;
			
		global $zip_helper_exceptions;
		$zip_helper_exceptions = array();
		
		if (!defined('PATH_INSTALL'))
			require_once(PATH_SYSTEM."/modules/file/vendor/pclzip/pclzip.lib.php");

		if (!defined('PCLZIP_TEMPORARY_DIR'))
		{
			if (!is_writable(PATH_APP.'/temp/'))
				throw new Phpr_SystemException('No writing permissions for directory '.PATH_APP.'/temp');
			
			define('PCLZIP_TEMPORARY_DIR', PATH_APP.'/temp/');
		}
			
		self::$initialized = true;
	}
}

function zip_helper_pre_add($p_event, &$p_header)
{
	global $zip_helper_exceptions;

	$path_parts = pathinfo($p_header['stored_filename']);

	if (
		(
			isset($path_parts['basename']) && 
			(
				$path_parts['basename'] == '.DS_Store' || 
				$path_parts['basename'] == '.svn' || 
				$path_parts['basename'] == '.git' || 
				$path_parts['basename'] == '.gitignore'
				)
		) || 
		(
			strpos($path_parts['dirname'], '.svn') !== false ||
			strpos($path_parts['dirname'], '.git') !== false
		)
	)
		return 0;
		
	$stored_file_name = str_replace('\\', '/', $p_header['stored_filename']);

	foreach ($zip_helper_exceptions as $exception)
	{
		if (substr($exception, -2) == '/*')
		{
			$effective_path = substr($exception, 0, -2);
			$len = strlen($effective_path);

			if ($effective_path == substr($stored_file_name, 0, $len) && substr($stored_file_name, $len, 1) == '/')
				return 0;
		}
		
		if (substr($exception, -8) == '/[files]' && !$p_header['folder'])
		{
			$effective_path = substr($exception, 0, -8);
			$len = strlen($effective_path);

			if ($effective_path == substr($stored_file_name, 0, $len) && substr($stored_file_name, $len, 1) == '/')
				return 0;
		}
		
		if ($exception == $stored_file_name)
			return 0;
	}

	return 1;
}

function zip_helper_post_extract($p_event, &$p_header)
{
	if (file_exists($p_header['filename']))
	{
		$is_folder = array_key_exists('folder', $p_header) ? $p_header['folder'] : false;

		if (!File_Zip::$chmod_error)
		{
			$mode = $is_folder ? File_Directory::get_permissions() : File::get_permissions();
			try
			{
				@chmod($p_header['filename'], $mode);
			} 
			catch (Exception $ex)
			{
				File_Zip::$chmod_error = true;
			}
		}
	}

	return 1;
}
