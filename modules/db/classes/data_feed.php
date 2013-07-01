<?php namespace Db;

use Phpr\Pagination;
use Db\Helper as Db_Helper;

/**
 * This model type allows you to combine various models in a query,
 * paginate them and return as one data set.
 */

class Data_Feed
{
	public $context_var = 'context_name';
	public $classname_var = 'class_name';

	protected $collection = array(); // Empty model collection
	protected $context_list = array(); // Used for "tagging" models, returned as $model->context_name
	protected $order_list = array(); // Used for applying sort rules to each model
	protected $select_list = array(); // Used to pass a common alias and use it as a condition
	protected $having_list = array(); // Used to pass a common condition

	protected $remove_duplicates = false;
	protected $limit_count = null;
	protected $limit_offset = null;

	protected $use_custom_timestamp = false; // (Used internally) Merge created_at and updated_at as timestamp_at
	protected $order_timestamp = null;
	protected $order_direction = 'DESC';

	// Example:
	// 
	//   $feed->select('1 + 2 as answer');
	//   $feed->having('answer = 3');
	//   
	//   OR
	//
	//   $model->having('answer = 3');
	//   $feed->add($model, 'tag_name', '@sent_at');

	public static function create()
	{
		return new self();
	}

	/**
	 * Add a ActiveRecord model before find_all()
	 */
	public function add($record, $context_name = null, $order_by_field = null)
	{
		$this->collection[] = clone $record;
		$this->context_list[] = $context_name;
		$this->order_list[] = $order_by_field;
		return $this;
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

			// Pass Select aliases
			foreach ($this->select_list as $select_string)
				$record_obj->select($select_string);

			// Apply Having conditions
			foreach ($this->having_list as $having_string)
				$record_obj->having($having_string);

			// Ordering
			if ($this->use_custom_timestamp)
				$record_obj->select(str_replace('@', $record->table_name.'.', $this->order_timestamp).' as timestamp_at');
			else if ($this->order_list[$key] !== null)
				$record_obj->select(str_replace('@', $record->table_name.'.', $this->order_list[$key]).' as timestamp_at');
			else
				$record_obj->select('ifnull('.$record->table_name.'.updated_at, '.$record->table_name.'.created_at) as timestamp_at');

			$sql[] = "(".$record_obj->build_sql().")";
		}

		$sql[] = "ORDER BY timestamp_at ". $this->order_direction;

		if ($this->limit_count !== null && $this->limit_offset !== null)
			$sql[] = "LIMIT ".$this->limit_offset.", ".$this->limit_count;

		$sql = implode(' ', $sql);

		return $sql;
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

		return new Data_Collection($data_array);
	}

	public function paginate($page_index, $records_per_page)
	{
		$pagination = new Pagination($records_per_page);
		$pagination->set_row_count($this->get_row_count());
		$pagination->set_current_page_index($page_index);

		$this->limit($records_per_page, ($records_per_page * $page_index)); 

		return $pagination;
	}

	public function get_row_count()
	{
		return Db_Helper::scalar($this->count_sql());
	}

	public function select($query)
	{
		$this->select_list[] = $query;
		return $this;
	}

	public function having($query)
	{
		$this->having_list[] = $query;
		return $this;
	}

	public function order($order_by_field = null, $direction = null)
	{
		if (is_null($order_by_field) && is_null($direction)) 
			return $this;

		$this->use_custom_timestamp = true;

		if ($order_by_field == 'timestamp_at' || $order_by_field === null)
			$this->use_custom_timestamp = false;
		else 
			$this->order_timestamp = $timestamp;

		$this->order_direction = ($direction) 
			? $direction 
			: $this->order_direction;

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