<?php

class File
{
	public static function get_permissions()
	{
		$permissions = Phpr::$config->get('FILE_PERMISSIONS');
		if ($permissions)
			return $permissions;
			
		$permissions = Phpr::$config->get('FILE_FOLDER_PERMISSIONS');
		if ($permissions)
			return $permissions;
			
		return 0777;
	}

	/**
	 * Returns a file size as string (203 Kb)
	 * @param int $size Specifies a size of a file in bytes
	 * @return string
	 */
	public static function size_from_bytes($size)
	{
		if ($size < 1024)
			return $size.' byte(s)';
		
		if ($size < 1024000)
			return ceil($size/1024).' Kb';

		if ($size < 1024000000)
			return round($size/1024000, 1).' Mb';

		return round($size/1024000000, 1).' Gb';
	}

	/**
	 * Returns the file name without extension
	 */
	public static function get_name($file_path)
	{
		return pathinfo($file_path, PATHINFO_FILENAME);
	}

	/**
	 * Returns the file extension component
	 */
	public static function get_extension($file_path)
	{
		return pathinfo($file_path, PATHINFO_EXTENSION);
	}
}
