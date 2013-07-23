<?php namespace Db;

use Phpr;
use Db\Helper as Db_Helper;

/**
 * @deprecated 
 * See Db_Helper
 */
class DbHelper extends Db_Helper 
{ 

	public static function init()
	{
		Phpr::$deprecate->set_class('Db_DbHelper', 'Db_Helper');
	}

	public static function listTables() 
	{ 
		Phpr::$deprecate->set_function('listTables', 'list_tables');
		return self::list_tables(); 
	}
	
	public static function tableExists($table_name) 
	{ 
		Phpr::$deprecate->set_function('tableExists', 'table_exists');
		return self::table_exists($table_name); 
	}
	
	public static function executeSqlScript($file_path, $separator = ';') 
	{
		Phpr::$deprecate->set_function('executeSqlScript', 'execute_sql_from_file');
		return self::execute_sql_from_file($file_path, $separator);
	}

	public static function scalarArray($sql, $bind = array())
	{
		Phpr::$deprecate->set_function('scalarArray', 'scalar_array');
		return self::scalar_array($sql, $bind);
	}

	public static function queryArray($sql, $bind = array())
	{
		Phpr::$deprecate->set_function('queryArray', 'query_array');
		return self::query_array($sql, $bind);
	}

	public static function objectArray($sql, $bind = array())
	{
		Phpr::$deprecate->set_function('objectArray', 'object_array');
		return self::object_array($sql, $bind);
	}

	public static function getTableStruct($table_name)
	{
		Phpr::$deprecate->set_function('getTableStruct', 'get_table_struct');
		return self::get_table_struct($table_name);
	}

	public static function getTableDump($table_name, $file_handle = null, $separator = ';')
	{
		Phpr::$deprecate->set_function('getTableDump', 'get_table_dump');
		return self::get_table_dump($table_name, $file_handle, $separator);
	}

	public static function createDbDump($path, $options = array())
	{
		Phpr::$deprecate->set_function('createDbDump', 'export_sql_to_file');
		return self::export_sql_to_file($path, $options);
	}

	public static function getUniqueColumnValue($model, $column_name, $column_value, $case_sensitive = false)
	{
		Phpr::$deprecate->set_function('getUniqueColumnValue', 'get_unique_copy_value');
		return self::get_unique_copy_value($model, $column_name, $column_value, $case_sensitive);
	}

	public static function formatSearchQuery($query, $fields, $min_length = null)
	{
		Phpr::$deprecate->set_function('formatSearchQuery', 'format_search_query');
		return self::format_search_query($query, $fields, $min_length);
	}

}
