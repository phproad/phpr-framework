<?php

/**
 * Data object for Module versions
 */

class Phpr_Version extends Db_ActiveRecord
{
	public $table_name = 'phpr_module_versions';

	protected static $build_cache = null;

	public static function create()
	{
		return new self();
	}

	public static function get_module_version($module_id)
	{
		$module_id = strtolower($module_id);

		$version = self::create()->find_by_module_id($module_id);
		if ($version)
			return $version->version_str;

		return '1.0.0';
	}

	public static function get_module_build($module_id)
	{
		$module_id = strtolower($module_id);

		$version = self::create()->find_by_module_id($module_id);
		if ($version)
			return $version->version;

		return 0;
	}

	public static function get_module_build_cached($module_id)
	{
		if (self::$build_cache != null)
			return array_key_exists($module_id, self::$build_cache) ? self::$build_cache[$module_id] : 0;

		self::$build_cache = array();
		$versions = Db_Helper::object_array('select * from phpr_module_versions');
		foreach ($versions as $version)
		{
			if (!isset($version->module_id))
				continue;
			
			self::$build_cache[$version->module_id] = $version->version;
		}

		return array_key_exists($module_id, self::$build_cache) ? self::$build_cache[$module_id] : 0;
	}


}

