<?php

/**
 * Data object for Module parameters
 */

class Phpr_Module_Parameters
{
	protected static $cache = null;

	private static function init_cache()
	{
		if (self::$cache != null)
			return;

		self::$cache = array();

		$records = Db_Helper::object_array('select * from phpr_module_params');
		foreach ($records as $param)
		{
			$name = $param->name;
			$module_id = $param->module_id;

			if (!isset(self::$cache[$module_id]))
				self::$cache[$module_id] = array();

			self::$cache[$module_id][$name] = $param->value;
		}
	}

	public static function get($module_id, $name, $default = null)
	{
		self::init_cache();

		if (!isset(self::$cache[$module_id]) || !isset(self::$cache[$module_id][$name]))
			return $default;

		try
		{
			return @unserialize(self::$cache[$module_id][$name]);
		}
		catch (Exception $ex)
		{
			return $default;
		}
	}

	public static function set($module_id, $name, $value)
	{
		self::init_cache();
		
		$value = serialize($value);

		self::$cache[$module_id][$name] = $value;
		
		$bind = array(
			'module_id' => $module_id,
			'name'      => $name,
			'value'     => $value
		);
		
		Db_Helper::query('delete from phpr_module_params where module_id=:module_id and name=:name', $bind);
		Db_Helper::query('insert into phpr_module_params(module_id, name, value) values (:module_id,:name,:value)', $bind);
	}
}
