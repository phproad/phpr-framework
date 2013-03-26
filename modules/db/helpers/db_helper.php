<?php

/**
 * Database helper class
 */

class Db_Helper
{
    protected static $driver = false;
    
    // Scalar
    // 

    public static function scalar($sql, $bind = array())
    {
        return Db_Sql::create()->fetchOne($sql, $bind); 
    }
    
    public static function scalar_array($sql, $bind = array())
    {
        $values = self::query_array($sql, $bind);

        $result = array();
        foreach ($values as $value)
        {
            $keys = array_keys($value);
            if ($keys)
                $result[] = $value[$keys[0]];
        }
            
        return $result;
    }
    
    // Queries
    // 

    public static function query($sql, $bind = array())
    {
        $obj = Db_Sql::create();
        return $obj->query($obj->prepare($sql, $bind));
    }
    
    public static function query_array($sql, $bind = array())
    {
        return Db_Sql::create()->fetchAll($sql, $bind);
    }
    
    // Objects
    // 

    public static function object($sql, $bind = array())
    {
        $result = self::object_array($sql, $bind);
        if (!count($result))
            return null;
            
        return $result[0];
    }

    public static function object_array($sql, $bind = array())
    {
        $record_set = self::query_array($sql, $bind);
        
        $result = array();
        foreach ($record_set as $record)
            $result[] = (object)$record;
            
        return $result;
    }

    // Tables
    // 
    
    public static function list_tables()
    {
        return Db_Sql::create()->fetchCol('SHOW TABLES');
    }
    
    public static function table_exists($table_name)
    {
        $tables = self::list_tables();
        return in_array($table_name, $tables);
    }
    
    public static function get_table_struct($table_name)
    {
        $sql = Db_Sql::create();
        $result = $sql->query($sql->prepare("SHOW CREATE TABLE `".$table_name."`"));
        return $sql->driver()->fetch($result, 1);
    }

    public static function get_table_dump($table_name, $file_handle = null, $separator = ';')
    {
        $sql = Db_Sql::create();
        $query = $sql->query("SELECT * FROM `".$table_name."`");
        
        $result = null;
        $columnNames = null;
        while ($row = $sql->driver()->fetch($query))
        {
            if ($columnNames === null)
                $columnNames = '`'.implode('`,`', array_keys($row)).'`';

            if (!$file_handle)
            {
                $result .= "INSERT INTO `".$table_name."` (".$columnNames.") VALUES (";
                $result .= $sql->quote(array_values($row));
                $result .= ")".$separator."\n";
            } else
            {
                fwrite($file_handle, "INSERT INTO `".$table_name."` (".$columnNames.") VALUES (");
                fwrite($file_handle, $sql->quote(array_values($row)));
                fwrite($file_handle, ")".$separator."\n");
            }
        }

        return $result;
    }

    // Import / Export
    // 
    
    public static function execute_sql_from_file($file_path, $separator = ';')
    {
        $file_contents = file_get_contents($file_path);
        $file_contents = str_replace("\r\n", "\n", $file_contents);
        $statements = explode($separator."\n", $file_contents);

        $sql = Db_Sql::create();

        foreach ($statements as $statement)
        {
            if (strlen(trim($statement)))
                $sql->execute($statement);
        }
    }

    public static function export_sql_to_file($path, $options = array())
    {
        @set_time_limit(600);
        
        $tables_to_ignore = isset($options['ignore']) ? $options['ignore'] : array();
        $separator = isset($options['separator']) ? $options['separator'] : ';';
        
        $file_handle = @fopen($path, "w");
        if (!$file_handle)
            throw new Phpr_SystemException('Error opening file for writing: '.$path);
        
        $sql = Db_Sql::create();

        try
        {
            fwrite($file_handle, "SET NAMES utf8".$separator."\n\n");
            $tables = self::list_tables();

            foreach ($tables as $table_name)
            {
                if (in_array($table_name, $tables_to_ignore))
                    continue;
                
                fwrite($file_handle, '# TABLE '.$table_name."\n#\n");
                fwrite($file_handle, 'DROP TABLE IF EXISTS `'.$table_name."`".$separator."\n");
                fwrite($file_handle, self::get_table_struct($table_name).$separator."\n\n");
                self::get_table_dump($table_name, $file_handle, $separator);
                $sql->driver()->reconnect();
            }
        
            @fclose($file_handle);
            @chmod($path, File::get_permissions());
        }
        catch (Exception $ex)
        {
            @fclose($file_handle);
            throw $ex;
        }
    }
    
    // Helpers
    // 

    // Formats a query for searching specified fields for specified words or phrases
    // $fields is an array of fields to search
    // This will return a true statement (1=1) if min_length is not reached
    public static function format_search_query($query, $fields, $min_length = null)
    {
        if (!is_array($fields))
            $fields = array($fields);
        
        $words = Phpr_Strings::split_to_words($query);

        $word_queries = array();
        foreach ($words as $word)
        {
            if (!strlen($word))
                continue;
                
            if ($min_length && mb_strlen($word) < $min_length)
                continue;

            $word = trim(mb_strtolower($word));
            $word_queries[] = '%1$s LIKE \'%2$s'.mysql_real_escape_string($word).'%2$s\'';
        }

        $field_queries = array();
        foreach ($fields as $field)
        {
            if ($word_queries)
                $field_queries[] = '('.sprintf(implode(' AND ', $word_queries), $field, '%').')';
        }

        if (!$field_queries)
            return '1=1';
            
        return '('.implode(' OR ', $field_queries).')';
    }
    
    // Generates an unique column value by adding suffix _copy_1, _copy_2, etc
    // $column_value of "Person" would generate "Person_copy_1"
    public static function get_unique_copy_value($model, $column_name, $column_value, $case_sensitive = false)
    {
        return self::get_unique_column_value($model, $column_name, $column_value, $case_sensitive, '_copy_');
    }
    
    // Slugifys and caps a string to a safe length
    // Returns a URI code
    public static function get_unique_slugify_value($model, $column_name, $string, $max_length = null)
    {
        $table_name = $model->table_name;
        $slug = Phpr_Inflector::slugify($string);
        if ($max_length)
            $slug = substr($slug, 0, $max_length);

        return self::get_unique_column_value($model, $column_name, $slug);
    }

    // Checks db for exisiting code and returns one not in use
    // Eg: post-name, post-name-1, post-name-2
    public static function get_unique_column_value($model, $column_name, $column_value, $case_sensitive = false, $separator = '-')
    {
        $counter = 1;
        $table_name = $model->table_name;
        $column_value = preg_replace('/'.preg_quote($separator).'[0-9]+$/', '', trim($column_value));        
        $original_value = $column_value;

        $query = $case_sensitive 
            ? "select count(*) from ".$table_name." where ".$column_name."=:value" 
            : "select count(*) from ".$table_name." where lower(".$column_name.")=lower(:value)";

        while (Db_DbHelper::scalar($query, array('value'=>$column_value)))
        {
            $counter++;
            $column_value = $original_value.$separator.$counter;
        }

        return $column_value;
    }

    // Services
    // 

    public static function fetch_next($resource)
    {
        return self::driver()->fetch($resource);
    }
    
    public static function free_result($resource)
    {
        self::driver()->free_query_result($resource);
    }

    public static function reset_driver()
    {
        self::$driver = false;
    }
    
    // Internals
    // 

    protected static function driver()
    {
        if (!self::$driver)
        {
            $sql = Db_Sql::create();
            return self::$driver = $sql->driver();
        }
        
        return self::$driver;
    }
}
