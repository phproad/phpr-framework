<?php

/**
 * This model type allows you to combine various models in a query,
 * paginate them and return as one data set.
 */

class Db_DataFeed
{
    public $context_var = 'context_name';
    public $classname_var = 'class_name';

    protected $collection = array(); // Empty model collection
    protected $context_list = array(); // Used for "tagging" models, returned as $model->context_name
    protected $remove_duplicates = false;

    protected $limit_count = null;
    protected $limit_offset = null;

    protected $order_use_timestamp = true; // Merge created_at and updated_at as timestamp_at
    protected $order_timestamp = array('created_at,updated_at'); // This is used to override timestamp_at
    protected $order_direction = 'DESC';

    public $aliases = array();

    public static function create()
    {
        return new self();
    }

    /**
     * Add a ActiveRecord model before find_all()
     */
    public function add($record, $context = null)
    {
        $this->collection[] = clone $record;
        $this->context_list[] = $context;
    }

    /**
     * Creates a lean sql query to return id, class_name and time stamps
     */
    public function build_sql()
    {
        $sql = array();
        $count = 0;
        foreach ($this->collection as $key => $record)
        {
            if ($count++ != 0)
                $sql[] = ($this->remove_duplicates) ? "UNION" : "UNION ALL";
         
            // Pass Class name
            $record_obj = $record->from($record->table_name, 'id', true);
            $record_obj->select("(SELECT '".get_class($record)."') as ".$this->classname_var);

            // Pass Context name
            $context_name = $this->context_list[$key];
            $record_obj->select("(SELECT '".$context_name."') as ".$this->context_var);

            // Pass Aliases
            foreach ($this->aliases as $alias_name => $alias_string)
                $record_obj->select($alias_string." as ".$alias_name);

            if ($this->order_use_timestamp)
                $record_obj->select('ifnull('.$record->table_name.'.updated_at, '.$record->table_name.'.created_at) as timestamp_at');
            else
                $record_obj->select($record->table_name, implode(',',$this->order_timestamp));

            $sql[] = "(".$record_obj->build_sql().")";

        }

        if ($this->order_use_timestamp)
            $sql[] = "ORDER BY timestamp_at ". $this->order_direction;
        else
            $sql[] = "ORDER BY ".implode(' '.$this->order_direction.', ', $this->order_timestamp). ' '. $this->order_direction;

        if ($this->limit_count !== null && $this->limit_offset !== null)
            $sql[] = "LIMIT ".$this->limit_offset.", ".$this->limit_count;

        $sql = implode(' ', $sql);

        return $sql;
    }

    public function set_alias($name, $query)
    {
        $this->aliases[$name] = $query;
        return $this;
    }

    public function count_sql()
    {
        $sql = array();

        $sql[] = "SELECT COUNT(*) AS total FROM (";
        $count = 0;
        foreach ($this->collection as $record)
        {
            if ($count++ != 0)
                $sql[] = ($this->remove_duplicates) ? "UNION" : "UNION ALL";
            
            $record_obj = $record->from($record->table_name, 'id', true);
            $sql[] = "(".$record_obj->build_sql().")";
        }

        $sql[] = ") as records";
        $sql = implode(' ', $sql);

        return $sql;        
    }

    public function find_all()
    {
        // Build lean SQL statement
        $collection = Db_Helper::object_array($this->build_sql());

        // Build a collection of class_names and the id we need
        $mixed_array = array();
        foreach ($collection as $record)
        {
            $class_name = $record->{$this->classname_var}; 
            $mixed_array[$class_name][] = $record->id;
        }

        // Eager load our data collection
        $collection_array = array();
        foreach ($mixed_array as $class_name => $ids)
        {
            $obj = new $class_name();
            $collection_array[$class_name] = $obj->where('id in (?)', array($ids))->find_all();
        }

        // Now load our data objects into a final array
        $data_array = array();
        foreach ($collection as $record)
        {
            // Set Class name
            $class_name = $record->{$this->classname_var};
            $obj = $collection_array[$class_name]->find($record->id);
            $obj->{$this->classname_var} = $class_name;
            
            // Set Context name
            $context_name = $record->{$this->context_var};
            $obj->{$this->context_var} = $context_name;
            
            $data_array[] = $obj;
        }

        return new Db_DataCollection($data_array);
    }

    /**
     * Service methods
     */

    public function paginate($page_index, $records_per_page)
    {
        $pagination = new Phpr_Pagination($records_per_page);
        $pagination->setRowCount($this->requestRowCount());
        $pagination->setCurrentPageIndex($page_index);

        $this->limit($records_per_page, ($records_per_page * $page_index)); 

        return $pagination;
    }

    public function requestRowCount()
    {
        return Db_Helper::scalar($this->count_sql());
    }

    public function order($timestamp = null, $direction = null)
    {
        if (is_null($timestamp) && is_null($direction)) 
            return $this;

        $this->order_use_timestamp = false;

        if ($timestamp=='timestamp_at')
            $this->order_use_timestamp = true;
        else if (is_string($timestamp))
            $this->order_timestamp = explode(',', $timestamp);
        else 
            $this->order_timestamp = $timestamp;

        $this->order_direction = ($direction) ? $direction : $this->order_direction;

        return $this;
    }

    public function limit($count = null, $offset = null) 
    {
        if (is_null($count) && is_null($offset)) 
            return $this;
            
        $this->limit_count = (int)$count;
        $this->limit_offset = (int)$offset;

        return $this;
    }

}