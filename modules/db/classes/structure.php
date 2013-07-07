<?php namespace Db;

use Phpr;
use Phpr\SystemException;
use Db\ActiveRecord;
use Db\Helper as Db_Helper;

/**
 * PHPR Database Structure Class
 * 
 * Example usage:
 * 
 *   $users = Db_Structure::table('users_table');
 *   $users->primary_key('id');
 *   $users->column('username', db_varchar, 100)->defaults('funnyman');
 *   $users->column('email', db_varchar, 100);
 *   $users->column('group_id', db_number)->index();
 *   $users->add_key('usermail', array('username', 'email'))->unique();
 *   $users->footprints();
 *   $users->save();
 * 
 * Resulting SQL:
 * 
 *   CREATE TABLE `users_table` (
 *     `id` int(11) NOT NULL AUTO_INCREMENT,
 *     `username` varchar(100) DEFAULT 'funnyman',
 *     `email` varchar(100),
 *     `group_id` int(11),
 *     `created_user_id` int(11),
 *     `updated_user_id` int(11),
 *     `created_at` datetime,
 *     `updated_at` datetime,
 *     `deleted_at` datetime,
 *     UNIQUE KEY `usermail` (`username`,`email`),
 *     PRIMARY KEY (`id`),
 *     KEY `group_id` (`group_id`)
 *   ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
 * 
 * Subsequent usage:
 * 
 *   $users = Db_Structure::table('users_table');
 *   $users->primary_key('id');
 *   $users->column('username', db_varchar, 125)->defaults('superman');
 *   $users->column('email', db_varchar, 100);
 *   $users->column('password', db_varchar, 100);
 *   $users->column('group_id', db_number);
 *   $users->footprints();
 *   $users->save();
 * 
 * Resulting SQL:
 * 
 *    ALTER TABLE `users_table` 
 *      CHANGE `username` `username` varchar(125) DEFAULT 'superman',
 *      ADD `password` varchar(100);
 * 
 *    ALTER TABLE `users_table` DROP INDEX `usermail`;
 *    ALTER TABLE `users_table` DROP INDEX `group_id`;
 * 
 * Exending another modules structure:
 * 
 * public function subscribe_events() {
 *     Phpr::$events->add_event('user:on_extend_users_table_table', $this, 'extend_users_table');
 * }
 * 
 * public function extend_users_table($table) {
 *     $table->column('description', db_text);
 * }
 * 
 * Manually update a module:
 * 
 * Db\Update_Manager::apply_db_structure(PATH_APP, 'user');
 * Db\Structure::save_all();
 * 
 */

class Structure 
{
	// Use for debugging
	const debug_mode = false;

	const primary_key = 'PRIMARY';

	public static $module_id = null; // Module identifier
	public static $modules = array(); // Module tables
	public $capture_only = false; // Perform a dry run
	public $safe_mode = false; // Only create, don't delete

	protected $_keys = array();
	protected $_columns = array();

	protected $_charset;
	protected $_engine;
	protected $_table_name;
	protected $_table_exists;

	private $_built_sql = '';

	public function __construct() 
	{
		if (!class_exists('ActiveRecord'))
			Phpr::$class_loader->load('\Db\ActiveRecord');
		
		$this->reset();
	}

	public static function extend_table($module_id, $name) 
	{
		$prev_module_id = self::$module_id;

		self::$module_id = $module_id;

		$table = self::table($name);

		self::$module_id = $prev_module_id;

		return $table;
	}

	public static function save_all() 
	{
		foreach (self::$modules as $module_id => $tables)
		{
			foreach ($tables as $table_name => $table)
			{
				self::$module_id = $module_id;
			
				$table->save();

				self::$module_id = null;
			}
		}
	}

	public static function table($name) 
	{
		if (!isset(self::$modules[self::$module_id]))
			self::$modules[self::$module_id] = array();

		if (!isset(self::$modules[self::$module_id][$name])) {
			$obj = new self();
			$obj->_table_name = $name;

			self::$modules[self::$module_id][$name] = $obj;
		}
		
		return self::$modules[self::$module_id][$name];
	}

	public function execute_sql($sql) 
	{
		$this->_built_sql .= $sql = $sql.';'.PHP_EOL;
		
		if (self::debug_mode)
			Phpr::$trace_log->write($sql);
		else if (!$this->capture_only)
			Db_Helper::query($sql);
	}

	public function reset() 
	{
		$this->_charset = 'utf8';
		$this->_engine = 'MyISAM';
		$this->_table_name = '';
		$this->_keys = array();
		$this->_columns = array();
		$this->_built_sql = '';
	}

	//
	// Primary Keys
	// 

	public function primary_keys($columns) 
	{
		if (is_string($columns))
			$columns = func_get_args();
		
		foreach ($columns as $column) {
			$this->column($column, db_number)->not_null();
		}

		// Add primary key
		return $this->add_key(null, $columns)->primary();
	}

	public function primary_key($column, $type = db_number, $size = null) 
	{
		if (is_array($column)) 
			return $this->primary_keys($column, $type, $size);

		$this->add_key(null, $column)->primary();
		return $this->column($column, $type, $size)->not_null();
	}

	//
	// Regular keys
	// 

	public function add_key($name, $columns) 
	{
		if (!$name)
			$name = self::primary_key;

		if (is_string($columns))
			$columns = array($columns);

		$existing_key = $this->find_key($name);
		if ($existing_key) {
			$existing_key->add_columns($columns);
			return $existing_key;
		} else {
			$obj = new Structure_Key($this);
			$obj->name = $name;
			$obj->add_columns($columns);
			return $this->_keys[$name] = $obj;
		}

	}

	public function find_key($name)
	{
		return (isset($this->_keys[$name])) ? $this->_keys[$name] : false;
	}

	//
	// Columns
	// 

	public function column($name, $type, $size = null) 
	{
		$obj = new Structure_Column($this);
		$obj->name = $name;
		$obj->type = $type = $this->get_db_type($type);
		
		if (is_array($size) && count($size) > 1) 
		{
			$obj->length = $size[0];
			$obj->precision = $size[1];
		}
		else if ($size !== null)
			$obj->length = $size;
		
		if (strpos($type, '(') && strpos($type, ')')) 
		{
			$this->length = $this->get_type_length($type);
			$this->precision = $this->get_type_precision($type);
		}

		return $this->_columns[$name] = $obj;
	}

	public function find_column($name)
	{
		return (isset($this->_columns[$name])) ? $this->_columns[$name] : false;
	}

	//
	// Automatic Footprints
	// 

	public function footprints($include_user = true) 
	{
		if ($include_user) {
			$this->column('created_user_id', db_number)->index();
			$this->column('updated_user_id', db_number)->index();
		}
		$this->column('created_at', db_datetime);
		$this->column('updated_at', db_datetime);
	}

	//
	// Business Logic
	// 

	protected function process_keys()
	{
		// Make single integer primary keys auto increment
		// 
		if ($primary_key = $this->find_key(self::primary_key)) {
			$key_columns = $primary_key->get_columns();
			if (count($key_columns) == 1) {
				if ($col = $this->find_column($key_columns[0])) {
					if ($col->type == $this->get_db_type(db_number))
						$col->auto_increment();
				}
			}
		}

	}

	public function save() 
	{
		if (!strlen($this->_table_name))
			throw new SystemException('You must specify a table name before calling commit()');

		if (!count($this->_columns))
			throw new SystemException('You must provide at least one column before calling commit()');

		$module_id = (self::$module_id) ? self::$module_id : 'db';
		$event_name = $module_id.':on_extend_' . $this->_table_name . '_table';
		Phpr::$events->fire_event($event_name, $this);

		$this->process_keys();

		if (Db_Helper::table_exists($this->_table_name))
			$this->commit_modify();
		else
			$this->commit_create();
	}

	public function build_sql() 
	{
		$this->capture_only = true;
		$this->save();
		$sql = $this->_built_sql;
		$this->capture_only = false;
		return $sql;
	}

	public function commit_modify() 
	{
		// Column management
		// 
		
		$col_sql = array();
		$alter_prefix = 'ALTER TABLE `'.$this->_table_name.'` '.PHP_EOL;
		$existing_columns = $this->get_exisiting_columns();

		// Remove columns not listed
		if (!$this->safe_mode) 
		{
			$columns_to_remove = array_diff(array_keys($existing_columns), array_keys($this->_columns));
			foreach ($columns_to_remove as $column) 
			{
				$col_sql[] = 'DROP COLUMN `'.$column.'`';
			}
		}

		// Add non-existing columns
		foreach ($this->_columns as $column_name => $column) 
		{
			if (array_key_exists($column_name, $existing_columns)) 
			{
				
				$existing_column = $existing_columns[$column_name];
				$existing_column_definition = $existing_column->build_sql();
				$column_definition = $column->build_sql();

				// Debug
				if (self::debug_mode && $column_definition != $existing_column_definition) {
					Phpr::$trace_log->write('----------VS-------------');
					Phpr::$trace_log->write('NEW: '.$column_definition);
					Phpr::$trace_log->write('OLD: '.$existing_column_definition);
					Phpr::$trace_log->write('-------------------------');
				}

				if ($column_definition != $existing_column_definition) 
					$col_sql[] = 'CHANGE `'.$column_name.'` '.$column->build_sql();
				
			} else 
				$col_sql[] = 'ADD '.$column->build_sql();
		}

		// Execute
		if (count($col_sql)) 
		{
			$col_sql_string = $alter_prefix . implode(','.PHP_EOL, $col_sql);
			$this->execute_sql($col_sql_string);
		}

		// Index / Key management
		// 
		
		$key_sql = array();
		$existing_index = $this->get_existing_keys();

		// Remove indexes not listed
		if (!$this->safe_mode) 
		{
			$keys_to_remove = array_diff(array_keys($existing_index), array_keys($this->_keys));
			foreach ($keys_to_remove as $key_name) 
			{
				$key_sql[] = $alter_prefix . 'DROP INDEX `'.$key_name.'`';
			}
		}

		// Add non-existing indexes
		foreach ($this->_keys as $key_name => $key_obj) 
		{
			if (array_key_exists($key_name, $existing_index)) 
			{
				
				$existing_key = $existing_index[$key_name];
				$exisiting_key_definition = $existing_key->build_sql();
				$key_definition = $key_obj->build_sql();
				
				if ($key_definition != $exisiting_key_definition) 
				{
					$key_sql[] = $alter_prefix . 'DROP INDEX ' . $key_name;
					$key_sql[] = $alter_prefix . 'ADD ' . $key_obj->build_sql();
				}
			} else
				$key_sql[] = $alter_prefix . 'ADD ' . $key_obj->build_sql();
		}

		// Execute
		foreach ($key_sql as $sql) {
			$this->execute_sql($sql);
		}
	}

	public function commit_create() 
	{
		$sql = array();
		$create_tmpl = ''
			.'CREATE TABLE `'.$this->_table_name.'` ('.PHP_EOL
			.'%s'.PHP_EOL
			.') ENGINE='.$this->_engine.' DEFAULT CHARSET='.$this->_charset.';';

		foreach ($this->_columns as $column) 
			$sql[] = $column->build_sql();
		
		foreach ($this->_keys as $key) 
			$sql[] = $key->build_sql();
		
		$sql_string = sprintf($create_tmpl, implode(','.PHP_EOL, $sql));
		$this->execute_sql($sql_string);
	}

	//
	// Helpers
	// 

	private function get_db_type($type) 
	{
		if (strpos($type, '(') && strpos($type, ')'))
			return $this->simplified_type($type);

		$db_type = $this->column_to_db_type($type);
		return $db_type;
	}

	private function column_to_db_type($type) 
	{
		switch ($type) 
		{
			case db_number: return 'int';
			case db_bool: return 'tinyint';
			case db_varchar: return 'varchar';
			case db_datetime: return 'datetime';
			case db_float: return 'decimal';
			case db_date: return 'date';
			case db_time: return 'time';
			case db_text: return 'text';
			default: return $type;
		}
	}

	private function simplified_type($sql_type) 
	{
		$sql_type = strtolower($sql_type);

		preg_match_all('/(\w+)\((\d+)(?:,*)(\d*)\)/i', $sql_type, $matches);

		if (!isset($matches[1][0]))
			return $sql_type;

		return $matches[1][0];
	}

	private function get_type_length($sql_type)
	{
		preg_match_all('/(\w+)\((\d+)(?:,*)(\d*)\)/i', $sql_type, $matches);

		if (!isset($matches[2][0]))
			return null;

		return $matches[2][0];
	}

	private function get_type_precision($sql_type)
	{
		preg_match_all('/(\w+)\((\d+)(?:,*)(\d*)\)/i', $sql_type, $matches);

		if (!isset($matches[3][0]))
			return null;

		return $matches[3][0];
	}

	private function get_type_values($sql_type)
	{
		preg_match_all('/(\w+)(\(\d\))*/i', $sql_type, $matches);
		return $matches[0];
	}

	private function get_existing_keys() 
	{
		$existing_keys = array();
		$key_arr = Sql::create()->describe_index($this->_table_name);
		foreach ($key_arr as $key) 
		{
			$obj = new Structure_Key();
			$obj->name = $name = $key['name'];
			$obj->key_columns = $key['columns'];

			if ($key['primary'])
				$obj->primary();

			if ($key['unique'])
				$obj->unique();

			$existing_keys[$name] = $obj;
		}
		
		return $existing_keys;
	}

	private function get_exisiting_columns() 
	{
		$existing_columns = array();
		$table_arr = Sql::create()->describe_table($this->_table_name);
		$primary_arr = array();

		foreach ($table_arr as $col) {
			$obj = new Structure_Column();
			$sql_type = $col['sql_type'];
			$obj->name = $name = $col['name'];
			$obj->type = $type = $col['type'];
			
			if (strlen($col['default']))
				$obj->defaults($col['default']);

			if ($col['notnull'] === true)
				$obj->not_null();

			if ($col['primary'] === true)
				$primary_arr[] = $obj;

			if ($type == 'enum') 
				$obj->enum_values(array_slice($this->get_type_values($sql_type), 1));
			else 
			{
				$obj->length = $this->get_type_length($sql_type);
				$obj->precision = $this->get_type_precision($sql_type);
			}

			$existing_columns[$name] = $obj;
		}

		// Single PK, set auto increment
		$single_primary_key = (count($primary_arr) == 1);
		foreach ($primary_arr as $obj) {
			if ($obj->type != $this->get_db_type(db_number))
				continue;

			$obj->auto_increment($single_primary_key);
		}

		return $existing_columns;
	}

}
