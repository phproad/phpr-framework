<?php

/**
 * PHPR driver manager
 * 
 * Used to locate and interact with module drivers
 */

class Phpr_Driver_Manager
{

	const drivers_directory = 'drivers';

	private static $_object_cache = array();
	private static $_class_cache = array();
	
	/**
	 * Returns a list of drivers.
	 * @return array of driver class names
	 */
	public static function get_class_names($driver_class)
	{

		if (!property_exists($driver_class, 'driver_folder'))
			throw new Exception('Please create a static definintion '. $driver_class .'::$driver_folder which declares the drivers folder name (eg: payment_types)');

		if (!property_exists($driver_class, 'driver_suffix'))
			throw new Exception('Please create a static definintion '. $driver_class .'::$driver_suffix which declares the drivers file name suffix (eg: _type)');

		$driver_folder = new ReflectionProperty($driver_class, 'driver_folder');
		$driver_folder = $driver_folder->getValue();

		$driver_suffix = new ReflectionProperty($driver_class, 'driver_suffix');
		$driver_suffix = $driver_suffix->getValue();

		if (array_key_exists($driver_class, self::$_class_cache))
			return self::$_class_cache[$driver_class];

		$modules = Core_Module_Manager::get_modules();
		foreach ($modules as $id => $module_info)
		{
			$class_path = PATH_APP."/".PHPR_MODULES."/".$id."/".self::drivers_directory."/".$driver_folder;
			
			if (!file_exists($class_path))
				continue;

			$iterator = new DirectoryIterator($class_path);

			foreach ($iterator as $file)
			{
				if (!$file->isDir() && preg_match('/^'.$id.'_[^\.]*'.preg_quote($driver_suffix).'.php$/i', $file->getFilename()))
					require_once($class_path.'/'.$file->getFilename());
			}
		}

		$classes = get_declared_classes();
		$driver_classes = array();
		foreach ($classes as $class_name) {
			if (get_parent_class($class_name) != $driver_class)
				continue;

			$driver_classes[] = $class_name;            
		}

		return self::$_class_cache[$driver_class] = $driver_classes;
	}

	public static function get_drivers($driver_class)
	{
		if (array_key_exists($driver_class, self::$_object_cache))
			return self::$_object_cache[$driver_class];

		$driver_objects = array();
		foreach (self::get_class_names($driver_class) as $class_name)
		{
			$obj = new $class_name();

			// get_info() method must exist, and return an array
			if (is_array($obj->get_info()))
				$driver_objects[] = $obj;
		}
		
		return self::$_object_cache[$driver_class] = $driver_objects;
	}

	/**
	 * Finds a given driver
	 * @param $id the id of the driver declared in get_info
	 * @param $only_enabled discard non-enabled drivers from the search
	 */
	public static function get_driver($driver_class, $code)
	{
		$drivers = self::get_drivers($driver_class);
		foreach ($drivers as $driver)
		{
			if ($driver->get_code() == $code)
				return $driver;
		}
		return new Phpr_Driver_Base();
	}    

}