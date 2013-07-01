<?php namespace Db;

use Db;
use Phpr;
use Phpr\Error_Log;
use Phpr\SystemException;
use Phpr\DatabaseException;

class MySQL_Driver extends Driver_Base 
{
	private static $locale_set = false;

	public static function create() 
	{
		return new self();
	}

	public function connect() 
	{
		if (Db::$connection) 
			return;

		try
		{
			Error_Log::$disable_db_logging = true;

			// Load the configuration
			parent::connect();
			
			// Execute custom connection handlers
			$external_connection = Phpr::$events->fire_event('phpr:on_before_database_connect', $this);
			$external_connection_found = false;
			foreach ($external_connection as $connection) 
			{
				if ($connection)
				{
					Db::$connection = $connection;
					$external_connection_found = true;
					break;
				}
			}
			
			if (!Db::$connection)
			{
				// Connect
				try
				{
					if (Phpr::$config->get('MYSQL_PERSISTENT', true))
					{
						Db::$connection = @mysql_pconnect(
							$this->config['host'],
							$this->config['username'],
							$this->config['password'],
							isset($this->config['flags']) ? $this->config['flags'] : 0
						);
					} 
					else
					{
						Db::$connection = @mysql_connect(
							$this->config['host'],
							$this->config['username'],
							$this->config['password'],
							isset($this->config['flags']) ? $this->config['flags'] : 0
						);
					}
				} 
				catch (Exception $ex)
				{
					throw new DatabaseException('Error connecting to the database.');
				}
			}
		
			$err = 0;

			if ((Db::$connection == null) || (Db::$connection === false) || ($err = @mysql_errno(Db::$connection) != 0))
				throw new DatabaseException('MySQL connection error: '.@mysql_error());

			if (!$external_connection_found)
			{
				if ((@mysql_select_db($this->config['database'], Db::$connection) === false) || ($err = @mysql_errno(Db::$connection) != 0))
					throw new DatabaseException('MySQL error selecting database '.$this->config['database'].': '.@mysql_error());
			}

			// Set charset
			// 
			if (isset($this->config['locale']) && (trim($this->config['locale']) != ''))
			{
				@mysql_query("SET NAMES '" . $this->config['locale'] . "'", Db::$connection);
				if ($err = @mysql_errno(Db::$connection) != 0)
					throw new DatabaseException('MySQL error setting character set: '.@mysql_error(Db::$connection));
			}
	
			// Set SQL Mode
			// 
			@mysql_query('SET sql_mode=""');
			
			Error_Log::$disable_db_logging = false;
		} 
		catch (Exception $ex)
		{
			$exception = new DatabaseException($ex->getMessage());
			$exception->hint_message = 'This problem could be caused by MySQL connection configuration errors. Please check the database connection parameters. Also please make sure that MySQL server is running.';
			throw $exception;
		}
	}

	public function reconnect()
	{
		if (Db::$connection) 
		{
			@mysql_close(Db::$connection);
			Db::$connection = null;
		}
		
		$this->connect();
	}

	public function execute($sql) 
	{
		parent::execute($sql);
		$this->connect();

		// Execute the statement
		// 
		$handle = @mysql_query($sql, Db::$connection);

		// If error, generate exception
		// 
		if ($err = @mysql_errno(Db::$connection) != 0) 
		{
			$exception = new DatabaseException('MySQL error: "'.@mysql_error(Db::$connection).'"  executing query: '.$sql);
			$exception->hint_message = 'This problem could be caused by MySQL connection configuration errors. Please check the database connection parameters. Also please make sure that MySQL server is running.';
			throw $exception;
		}

		return $handle;
	}

	/* Fetch methods */

	public function fetch($result, $col = null) 
	{
		parent::fetch($result, $col);

		if ($row = @mysql_fetch_assoc($result)) 
		{
			if ($err = @mysql_errno(Db::$connection) != 0)
				throw new DatabaseException('MySQL error fetching data: '.@mysql_error(Db::$connection));

			if ($col !== null) 
			{
				if (is_string($col))
					return isset($row[$col]) ? $row[$col] : false;
				else 
				{
					$keys = array_keys($row);
					$col = array_key_exists($col, $keys) ? $keys[$col] : $keys[0];

					return isset($row[$col]) ? $row[$col] : false;
				}
			} else
				return $row;
		}
		return false;
	}
	
	public function free_query_result($resource)
	{
		if ($resource)
			mysql_free_result($resource);
	}

	/* Utility routines */

	public function row_count() 
	{
		if (!Db::$connection)
			throw new DatabaseException('MySQL count error - no connection');

		return @mysql_affected_rows(Db::$connection);
	}

	public function last_insert_id($table_name = null, $primary_key = null) 
	{
		if (!Db::$connection)
			throw new DatabaseException('MySQL error last_insert_id - no connection');

		return @mysql_insert_id(Db::$connection);
	}

	public function limit($offset, $count = null)
	{
		if (is_null($count))
			return 'LIMIT ' . $offset;
		else
			return 'LIMIT ' . $offset . ', ' . $count;
	}

	/**
	 * Returns the column descriptions for a table.
	 * @return array
	 */
	public function describe_table($table) 
	{
		if (isset(Db::$describe_cache[$table]))
			return Db::$describe_cache[$table];
		else 
		{
			$sql = 'DESCRIBE ' . $table;
			Phpr::$trace_log->write($sql, 'SQL');
			$result = $this->fetch_all($sql);
			$result_array = array();
			foreach ($result as $key => $val) 
			{
				$result_array[$val['Field']] = array(
					'name'     => $val['Field'],
					'sql_type' => $val['Type'],
					'type'     => $this->simplified_type($val['Type']),
					'notnull'  => (bool)($val['Null'] != 'YES'), // Not null is NO or empty, null is YES
					'default'  => $val['Default'],
					'primary'  => (strtolower($val['Key']) == 'pri'),
				);
			}

			Db::$describe_cache[$table] = $result_array;
			return $result_array;
		}
	}

	/**
	 * Returns the index descriptions for a table.
	 * @return array
	 */
	public function describe_index($table) 
	{
		$sql = 'SHOW INDEX FROM ' . $table;
		Phpr::$trace_log->write($sql, 'SQL');
		$result = $this->fetch_all($sql);
		$result_array = array();
		foreach ($result as $key => $val) 
		{
			$key_name = $val['Key_name'];
			if (array_key_exists($key_name, $result_array)) {
				$result_array[$key_name]['columns'][] = $val['Column_name'];
			} else {

				$result_array[$key_name] = array(
					'name'     => $key_name,
					'columns'  => array($val['Column_name']),
					'unique'   => (bool)($val['Non_unique'] != '1'),
					'primary'  => (bool)($key_name == 'PRIMARY')
				);
			}
		}

		return $result_array;
	}

	/* Service routines */

	protected function fetch_all($sql) 
	{
		$data = array();
		$handle = $this->execute($sql);
		while ($row = $this->fetch($handle))
			$data[] = $row;

		return $data;
	}

	protected function simplified_type($sql_type) 
	{
		if (preg_match('/([\w]+)(\(\d\))*/i', $sql_type, $matches))
			return strtolower($matches[1]);

		return strtolower($sql_type);
	}
	
	public function quote_object_name($name)
	{
		$name = trim($name);
		if (strpos('`', $name) === 0)
			$name = substr($name, 0);

		if (substr($name, -1) == '`')
			$name = substr($name, 0, -1);
			
		if (strpos($name, '`') !== false)
			throw new SystemException('Invalid database object name: '.$name);
			
		return '`'.$name.'`';
	}
}
