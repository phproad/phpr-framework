<?php

class Db_Secure_Settings
{
	public static function get()
	{
		$framework = Phpr_SecurityFramework::create();
		$config_content = $framework->get_config_content();

		$mysql_params = array_key_exists('mysql_params', $config_content) 
			? $config_content['mysql_params'] 
			: array();
			
		if (!$mysql_params)
		{
			return array(
				'host' => null, 
				'database' => null, 
				'user' => null, 
				'port' => null, 
				'password' => null
			);
			
		}

		return $mysql_params;
	}

	public static function set($parameters)
	{
		$framework = Phpr_SecurityFramework::create();
		
		$config_content = $framework->get_config_content();
		$config_content['mysql_params'] = $parameters;
		$framework->set_config_content($config_content);
	}
}
