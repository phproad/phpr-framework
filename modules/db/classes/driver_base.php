<?php

/**
 * PHPR Database Driver base class
 */

class Db_Driver_Base
{
	protected $config = array();

	public function connect() 
	{
		if (Db::$connection) 
			return;

		// TODO: Excellent point for multitenancy

		// Set defaults
		// 
		if (count($this->config) == 0) 
		{
			if (Phpr::$config->get('DB_CONFIG_MODE', 'secure') != 'secure')
				$config_source = Phpr::$config->get('DB_CONNECTION', array());
			else
			{
				$params = Db_Secure_Settings::get();
				$config_source = array(
					'host'     => $params['host'],
					'port'     => $params['port'],
					'database' => $params['database'],
					'username' => $params['user'],
					'password' => $params['password'],
					'locale'   =>'utf8'
				);
			}

			$this->config = array_merge(
				array(
					'host'     => '',
					'port'     => '',
					'database' => '',
					'username' => '',
					'password' => '',
				),
				$config_source);
		}
	}
	
	public function reconnect()
	{
		
	}

	public function execute($sql) 
	{
		return 0;
	}

	public function fetch($result, $col = null) 
	{
		return false;
	}
	
	public function free_query_result($resource)
	{
		return null;
	}

	public function row_count() 
	{
		return 0;
	}

	public function last_insert_id($table_name = null, $primary_key = null) 
	{
		return -1;
	}

	public function describe_table($table) 
	{
		return array();
	}

	public function limit($offset, $count = null) 
	{
	}
	
	public function quote_object_name($name)
	{
		return $name;
	}
}
