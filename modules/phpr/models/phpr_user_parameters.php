<?php

class Phpr_User_Parameters
{
	protected static $cache = null;

	private static function init_cache()
	{
		if (self::$cache != null)
			return;

		self::$cache = array();

		$records = Db_Helper::object_array('select * from phpr_user_params');
		foreach ($records as $param)
		{
			$name = $param->name;
			$user_id = $param->user_id;

			if (!isset(self::$cache[$user_id]))
				self::$cache[$user_id] = array();

			self::$cache[$user_id][$name] = $param->value;
		}
	}

	public static function get($name, $user_id = null, $default = null, $force_db = false)
	{
		if (Phpr::$config->get('USER_PARAMS_USE_SESSION') && !$force_db)
		{
			$params = Phpr::$session->get('phpr_user_params', array());
			
			return isset($params[$name]) 
				? $params[$name] 
				: self::get($name, $user_id, $default, true);
		}

		self::init_cache();

		if ($user_id == null)
		{
			$user = Phpr::$security->get_user();
			if (!$user)
				return $default;

			$user_id = $user->id;
		}

		if (!isset(self::$cache[$user_id]) || !isset(self::$cache[$user_id][$name]))
			return $default;

		return unserialize(self::$cache[$user_id][$name]);
	}

	public static function set($name, $value, $user_id = null)
	{       
		if (Phpr::$config->get('USER_PARAMS_USE_SESSION'))
		{
			$params = Phpr::$session->get('phpr_user_params', array());
			$params[$name] = $value;
			Phpr::$session->set('phpr_user_params', $params);
			return;
		}

		self::init_cache();

		if ($user_id == null)
		{
			$user = Phpr::$security->get_user();
			if (!$user)
				return;

			$user_id = $user->id;
		}

		$value = serialize($value);

		self::$cache[$user_id][$name] = $value;
		
		$bind = array(
			'user_id' => $user_id, 
			'name'    => $name, 
			'value'   => $value
		);
		
		Db_Helper::query('delete from phpr_user_params where user_id=:user_id and name=:name', $bind);
		Db_Helper::query('insert into phpr_user_params(user_id, name, value) values (:user_id,:name,:value)', $bind);
	}

	public static function reset($user_id)
	{
		if (!$user_id)
			return;

		$bind = array('user_id' => $user_id);
		Db_Helper::query('delete from phpr_user_params where user_id=:user_id', $bind);
	}
}
