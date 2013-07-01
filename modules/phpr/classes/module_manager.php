<?php namespace Phpr;

use DirectoryIterator;

use Phpr;

/**
 * PHPR module manager
 * 
 * Used to locate and interact with modules
 */

class Module_Manager
{
	protected static $module_objects = null;

	// Returns all available modules as an array
	public static function get_modules($allow_caching = true, $return_disabled_only = false)
	{
		if ($allow_caching && !$return_disabled_only)
		{
			if (self::$module_objects != null)
				return self::$module_objects;
		}

		if (!$return_disabled_only)
			self::$module_objects = array();

		$disabled_module_list = array();

		$disabled_modules = Phpr::$config->get('DISABLE_MODULES', array());
		$application_paths = Phpr::$config->get('APPLICATION_PATHS', array(PATH_APP, PATH_SYSTEM));

		foreach ($application_paths as $app_path)
		{
			if ($app_path == PATH_SYSTEM)
				continue;

			$modules_path = $app_path.DS.PHPR_MODULES;

			if (!file_exists($modules_path))
				continue;

			$iterator = new DirectoryIterator($modules_path);
			foreach ($iterator as $dir)
			{
				if ($dir->isDir() && !$dir->isDot())
				{
					$dir_path = $modules_path.DS.$dir->getFilename();
					$module_id = $dir->getFilename();

					$disabled = in_array($module_id, $disabled_modules);

					if (($disabled && !$return_disabled_only) || (!$disabled && $return_disabled_only))
						continue;

					if (isset(self::$module_objects[$module_id]))
						continue;

					$module_path = $dir_path.DS.'classes'.DS.$module_id."_module.php";

					if (!file_exists($module_path))
						continue;

					if (Phpr::$class_loader->load($class_name = $module_id."_Module", true))
					{
						if ($disabled)
						{
							$disabled_module_list[$module_id] = new $class_name($return_disabled_only);
							$disabled_module_list[$module_id]->dir_path = $dir_path;
						}
						else
						{
							self::$module_objects[$module_id] = new $class_name($return_disabled_only);
							self::$module_objects[$module_id]->dir_path = $dir_path;
							self::$module_objects[$module_id]->subscribe_events();
						}
					}
				}
			}

		}

		if ($return_disabled_only)
			$result = $disabled_module_list;
		else
			$result = self::$module_objects;
	 
		uasort($result, array('Phpr_Module_Manager', 'sort_modules_by_name'));

		// Add sorted collection back to cache
		if (!$return_disabled_only)
			self::$module_objects = $result;

		return $result;
	}

	// Returns a module object by its identifier
	public static function get_module($module_id)
	{
		$modules = self::get_modules();

		if (isset($modules[$module_id]))
			return $modules[$module_id];

		return null;
	}

	// Returns a module directory as an absolute path or null if not found
	public static function get_module_path($module_id) 
	{
		$module_id = strtolower($module_id);

		$application_paths = Phpr::$config->get('APPLICATION_PATHS', array(PATH_APP, PATH_SYSTEM));

		foreach ($application_paths as $base_path)
		{
			$module_path = $base_path.DS.PHPR_MODULES.DS.$module_id;

			if (file_exists($module_path))
				return $module_path;
		}

		return null;
	}

	// Checks the existence of a module
	public static function module_exists($module_id)
	{
		return self::get_module($module_id) ? true : false;
	}

	// Helper methods
	// 
	
	private static function sort_modules_by_name($a, $b)
	{
		return strcasecmp($a->get_module_info()->name, $b->get_module_info()->name);
	}      

	// @deprecated
	//   

	public static function find_by_id($module_id) { return self::get_module($module_id); }
	public static function find_modules() { return self::get_modules(); }

}