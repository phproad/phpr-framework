<?php

/*
 * Act_as_tree extension
 */

/*
 * Usage in model:
 * In the model class definition: public $implement = 'Db_Act_As_Tree';
 * To extract parent children $Obj->list_children();
 * To extract root elements $Obj->list_root_children()
 */

class Db_Act_As_Tree extends Phpr_Extension
{
	private $_model_class;
	private $_model;
	private static $_object_cache = array();
	private static $_parent_cache = array();
	private static $_cache_sort_column = array();
	
	public $act_as_tree_parent_key = 'parent_id';
	public $act_as_tree_name_field = 'name';
	public $act_as_tree_level = 0;
	public $act_as_tree_sql_filter = null;

	public function __construct($model, $proxy_model_class = null)
	{
		parent::__construct();
		
		$this->_model_class = $proxy_model_class ? $proxy_model_class : get_class($model);
		$this->_model = $model;
	}
	
	public static function clear_cache()
	{
		self::$_object_cache = array();
		self::$_parent_cache = array();
		self::$_cache_sort_column = array();
	}
	
	public function list_children($order_by = 'name')
	{
		if (!$this->cache_exists($order_by))
			$this->init_cache($order_by);

		$cache_key = $this->get_cache_key($order_by);

		if (isset(self::$_object_cache[$this->_model_class][$cache_key][$this->_model->id]))
			return new Db_Data_Collection(self::$_object_cache[$this->_model_class][$cache_key][$this->_model->id]);

		return new Db_Data_Collection();
	}
	

	public function list_root_children($order_by = 'name')
	{
		if (!$this->cache_exists($order_by))
			$this->init_cache($order_by);

		$cache_key = $this->get_cache_key($order_by);

		if (isset(self::$_object_cache[$this->_model_class][$cache_key][-1]))
			return new Db_Data_Collection(self::$_object_cache[$this->_model_class][$cache_key][-1]);

		return new Db_Data_Collection();
	}
	
	public function list_all_children($order_by = 'name')
	{
		$result = $this->list_all_children_recursive($order_by);

		return $result;
	}

	public function list_all_children_recursive($order_by)
	{
		$result = array();
		$children = $this->_model->list_children($order_by);

		foreach ($children as $child)
		{
			$result[] = $child;

			$child_result = $child->list_all_children_recursive($order_by);
			foreach ($child_result as $sub_child)
			 	$result[] = $sub_child;
		}
		
		return $result;
	}

	public function get_path($separator = " > ", $include_this = true, $order_by = 'name')
	{
		$parents = $this->get_parents($include_this, $order_by);
		$parents = array_reverse($parents);
		
		$result = array();
		foreach ($parents as $parent)
			$result[] = $parent->{$this->_model->act_as_tree_name_field};
			
		return implode($separator, array_reverse($result));
	}
	

	public function get_parent($order_by = 'name')
	{
		if (!$this->cache_exists($order_by))
			$this->init_cache($order_by);

		$cache_key = $this->get_cache_key($order_by);
			
		$parent_key = $this->_model->act_as_tree_parent_key;
		if (!$this->_model->$parent_key)
			return null;
			
		if (!isset(self::$_parent_cache[$this->_model_class][$cache_key][$this->_model->$parent_key]))
			return null;
			
		return self::$_parent_cache[$this->_model_class][$cache_key][$this->_model->$parent_key];
	}
	

	public function get_parents($include_this = false, $order_by = 'name')
	{
		$parent = $this->_model->get_parent($order_by);
		$result = array();

		if ($include_this)
			$result[] = $this->_model;
		
		while ($parent != null)
		{
			$result[] = $parent;
			$parent = $parent->get_parent($order_by);
		}
		
		return array_reverse($result);
	}
	
	private function init_cache($order_by = 'name')
	{
		if (Phpr::$config->get('USE_PROXY_MODELS'))
		{
			$model = clone $this->_model;
			$cache_key = $this->get_cache_key($order_by);

			if ($model->act_as_tree_sql_filter)
				$model->where($model->act_as_tree_sql_filter);

			$model->apply_calculated_columns();
			$sql = $model->order($order_by)->build_sql();

			$records = Db_Helper::query_array($sql);

			$_object_cache = array();
			$_parent_cache = array();

			$parent_key = $this->_model->act_as_tree_parent_key;
			foreach ($records as $record_data)
			{
				$record_data['act_as_tree_parent_key'] = $parent_key;
				$record_data['act_as_tree_sql_filter'] = $model->act_as_tree_sql_filter;

				$record = new Db_ActiveRecord_Proxy($record_data['id'], $this->_model_class, $record_data);
				$record->extend_with('Db_Act_As_Tree', false, $this->_model_class);

				$parent_id = $record->$parent_key != null ? $record->$parent_key : -1;

				if (!isset($_object_cache[$parent_id]))
					$_object_cache[$parent_id] = array();

				$_object_cache[$parent_id][] = $record;
				$_parent_cache[$record->id] = $record;
			}

			self::$_object_cache[$this->_model_class][$cache_key] = $_object_cache;
			self::$_parent_cache[$this->_model_class][$cache_key] = $_parent_cache;
			self::$_cache_sort_column[$this->_model_class][$cache_key] = $order_by;

			return;
		}
		
		$class_name = $this->_model_class;
		$cache_key = $this->get_cache_key($order_by);

		$model = clone $this->_model;

		$model->order($order_by);

		if ($model->act_as_tree_sql_filter)
			$model->where($model->act_as_tree_sql_filter);
			
		$records = $model->find_all();
		$_object_cache = array();
		$_parent_cache = array();

		$parent_key = $this->_model->act_as_tree_parent_key;
		foreach ($records as $record)
		{
			$parent_id = $record->$parent_key !== null ? $record->$parent_key : -1;
			
			if (!isset($_object_cache[$parent_id]))
				$_object_cache[$parent_id] = array();

			$_object_cache[$parent_id][] = $record;
			$_parent_cache[$record->id] = $record;
		}

		self::$_object_cache[$this->_model_class][$cache_key] = $_object_cache;
		self::$_parent_cache[$this->_model_class][$cache_key] = $_parent_cache;
		self::$_cache_sort_column[$this->_model_class][$cache_key] = $order_by;
	}
	
	private function get_cache_key($order_by)
	{
		return $order_by . $this->_model->act_as_tree_sql_filter;
	}
	
	private function cache_exists($order_by)
	{
		$cache_key = $this->get_cache_key($order_by);
		return array_key_exists($this->_model_class, self::$_object_cache) && array_key_exists($cache_key, self::$_object_cache[$this->_model_class]);
	}
	
	private function cache_key_match($order_by)
	{
		if (!array_key_exists($this->_model_class, self::$_cache_sort_column))
			return false;

		return self::$_cache_sort_column[$this->_model_class] == $order_by;
	}
}
