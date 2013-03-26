<?php
/**
 * PHPR Database Structure Class
 * 
 * Example usage:
 * 
 *   $users = Db_Structure::table('users_table');
 *   $users->primary_key('id');
 *   $users->column('username', db_varchar, 100)->set_default('funnyman');
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
 *   $users->column('username', db_varchar, 125)->set_default('superman');
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
 * Db_Update_Manager::apply_db_structure(PATH_APP, 'user');
 * 
 */

class Db_Structure 
{
    
    public static $module_id = null; // Module identifier
    public $capture_only = false; // Perform a dry run
    public $safe_mode = false; // Only create, don't delete

    protected $_keys = array();
    protected $_columns = array();
    protected $_charset;
    protected $_engine;
    protected $_table_name;
    protected $_table_exists;
    protected $_primary_key = null;

    private $_built_sql = '';

    public function __construct() 
    {

        // This must be included to obtain the global constants
        // it's class file declares 
        Db_ActiveRecord::create();
        
        $this->reset();
    }

    public static function table($name) 
    {
        $obj = new self();
        $obj->_table_name = $name;
        return $obj;
    }

    public function execute_sql($sql) 
    {
        $this->_built_sql .= $sql = $sql.';'.PHP_EOL;
        
        //trace_log($sql);
        if (!$this->capture_only)
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

    public function column($name, $type, $size = null) 
    {
        $obj = new Db_Structure_Object();
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

    // public function add_primary_key($columns)
    // {
    //     $this->add_key(null, $columns, true);
    // }

    public function add_key($name, $columns, $primary = false) 
    {
        if (is_string($columns))
            $columns = array($columns);

        $obj = new Db_Structure_Object();
        $obj->name = $name;
        $obj->key_columns = $columns;
        $obj->is_key = true;
        
        if ($primary)
            return $this->_keys['PRIMARY'] = $obj->primary();
        else            
            return $this->_keys[$name] = $obj;
    }

    // Services
    // 

    public function primary_keys($columns) 
    {
        if (is_string($columns))
            $columns = func_get_args();

        if ($this->_primary_key !== null)
            throw new Phpr_SystemException('Primary key in Db_Structure already exists as ' . implode(',', $this->_primary_key));

        $this->_primary_key = $columns;
        
        foreach ($columns as $column) {
            $this->column($column, db_number)->not_null();
        }

        // Add primary key
        return $this->add_key(null, $columns, true);
    }

    public function primary_key($column) 
    {
        if (is_array($column)) 
            return $this->primary_keys($column);

        if ($this->_primary_key !== null)
            throw new Phpr_SystemException('Primary key in Db_Structure already exists as ' . $this->_primary_key);

        $this->_primary_key = $column;
        return $this->column($column, db_number)->primary();
    }

    public function footprints($include_user = true) 
    {
        if ($include_user) {
            $this->column('created_user_id', db_number)->index();
            $this->column('updated_user_id', db_number)->index();
        }
        $this->column('created_at', db_datetime);
        $this->column('updated_at', db_datetime);
    }

    // Business Logic
    // 

    public function save() 
    {
        if (!strlen($this->_table_name))
            throw new Phpr_SystemException('You must specify a table name before calling commit()');

        if (!count($this->_columns))
            throw new Phpr_SystemException('You must provide at least one column before calling commit()');

        $module_id = (self::$module_id) ? self::$module_id : 'db';
        $event_name = $module_id.':on_extend_' . $this->_table_name . '_table';        
        Phpr::$events->fire_event($event_name, $this);

        $this->process_column_keys();

        if (Db_Helper::table_exists($this->_table_name))
            $this->commit_modify();
        else
            $this->commit_create();
    }

    public function build_sql() 
    {
        $this->capture_only = true;
        $this->save();
        return $this->_built_sql;
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
                if (false && $column_definition != $existing_column_definition) {
                    trace_log('----------VS-------------');
                    trace_log('NEW: '.$column_definition);
                    trace_log('OLD: '.$existing_column_definition);
                    trace_log('-------------------------');
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
        foreach ($key_sql as $sql)
            $this->execute_sql($sql);
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

    // Helpers
    // 

    private function process_column_keys() 
    {
        foreach ($this->_columns as $column) 
        {
            if ($column->has_index) {
                $key = $this->add_key($column->name, $column->name);
                if ($column->is_unique)
                    $key->unique();
            }
            else if ($column->is_primary)
                $this->add_key($column->name, $column->name, true);
        }
    }

    private function get_db_type($type) 
    {
        if (strpos($type, '(') && strpos($type, ')'))
            return $this->simplified_type($type);

        $db_type = $this->column_to_db_type($type);
        return $db_type;
    }

    private function column_to_db_type($type) {
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
        if (preg_match('/([\w]+)(\(\d\))*/i', $sql_type, $matches))
            return strtolower($matches[1]);

        return strtolower($sql_type);
    }    

    private function get_type_length($sql_type)
    
    {
        $matches = $this->get_type_values($sql_type);
        return (isset($matches[1])) ? $matches[1] : null;
    }

    private function get_type_precision($sql_type)
    
    {
        $matches = $this->get_type_values($sql_type);
        return (isset($matches[2])) ? $matches[2] : null;
    }

    private function get_type_values($sql_type)
    
    {
        preg_match_all('/([\w]+)(\(\d\))*/i', $sql_type, $matches);
        return $matches[0];
    }

    private function get_existing_keys() 
    {
        $existing_keys = array();
        $key_arr = Db_Sql::create()->describe_index($this->_table_name);
        foreach ($key_arr as $key) 
        {
            $obj = new Db_Structure_Object();
            $obj->name = $name = $key['name'];
            $obj->key_columns = $key['columns'];
            $obj->is_key = true;

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
        $table_arr = Db_Sql::create()->describe_table($this->_table_name);
        $primary_arr = array();

        foreach ($table_arr as $col) 
        {
            $obj = new Db_Structure_Object();
            $sql_type = $col['sql_type'];
            $obj->name = $name = $col['name'];
            $obj->type = $type = $col['type'];
            
            if (strlen($col['default']))
                $obj->set_default($col['default']);

            if ($col['notnull'] === true)
                $obj->not_null();

            if ($col['primary'] === true)
                $primary_arr[] = $obj;

            if ($type == "enum") 
                $obj->enum_values(array_slice($this->get_type_values($sql_type), 1));
            else 
            {
                $obj->length = $this->get_type_length($sql_type);
                $obj->precision = $this->get_type_precision($sql_type);
            }

            $existing_columns[$name] = $obj;
        }

        $single_primary_key = (count($primary_arr) <= 1);
        foreach ($primary_arr as $obj) {
            $obj->primary($single_primary_key);
        }

        return $existing_columns;
    }

}

class Db_Structure_Object 
{

    public static $default_length = array(
        'int' => 11,
        'varchar' => 255,
        'decimal' => 15,
        'float' => 10
    );

    public static $default_precision = array(
        'decimal' => 2,
        'float' => 6
    );

    public $name;
    public $type;
    public $length;
    public $precision;
    public $enumeration;
    public $default_value;
    public $is_unique = false;
    public $unsigned = false;
    public $allow_null = true;
    public $auto_increment = false;
    
    public $is_key = false; // Standalone key
    public $key_columns; // Standalone column reference

    public $is_primary; // Primary key
    public $has_index; // Column with key

    public function primary($auto_increment = true) { 
        $this->is_primary = true;
        $this->auto_increment = $auto_increment;
        $this->allow_null = false;
        return $this;
    }

    public function index() { 
        $this->has_index = true;
        return $this;
    }

    public function set_default($value) { 
        $this->default_value = $value;
        return $this;
    }

    public function enum_values($values) 
    {
        $this->enumeration = $values;
    }

    public function unique() { 
        $this->is_unique = true;
        return $this;
    }

    public function not_null() { 
        $this->allow_null = false;
        return $this;
    }

    public function build_sql() 
    {
        $this->set_defaults();

        return ($this->is_key) 
            ? $this->build_key()
            : $this->build_column();
    }

    public function build_column() 
    {
        $str = '`'.$this->name.'` '.$this->type;

        if ($this->length && $this->precision)
            $str .= '('.$this->length.','.$this->precision.')';
        else if ($this->length)
            $str .= '('.$this->length.')';

        if ($this->unsigned)
            $str .= ' UNSIGNED';

        if ($this->enumeration)
            $str .= "('".implode("','", $this->enumeration)."')";

        if (!$this->allow_null)
            $str .= ' NOT NULL';

        if (strlen($this->default_value))
            $str .= ' DEFAULT '.$this->prepare_value($this->default_value);

        if ($this->auto_increment)
            $str .= ' AUTO_INCREMENT';

        return $str;
    }

    public function build_key() 
    {
        $str = '';

        if ($this->is_primary) 
            $str .= 'PRIMARY KEY';
        else if ($this->is_unique)
            $str .= 'UNIQUE KEY `'.$this->name.'`';
        else
            $str .= 'KEY `'.$this->name.'`';

        $str .= " (`".implode("`,`", $this->key_columns)."`)";
        return $str;
    }

    private function set_defaults() 
    {
        if (!strlen($this->precision) && isset(self::$default_precision[$this->type]))
            $this->precision = self::$default_precision[$this->type];

        if (!strlen($this->length) && isset(self::$default_length[$this->type]))
            $this->length = self::$default_length[$this->type];
    }

    private function prepare_value($value) 
    {
        if (is_bool($value)) 
            return $value ? '1' : '0';
        else if (is_numeric($value)) 
            return $value;
        else
            return "'".str_replace("'", "''", $value)."'";
    }

}