<?php

/**
 * PHPR Data Filter
 * 
 * Currently used as a base class for List Filters 
 */

class Db_Data_Filter
{
	public $model_class_name = null;
	public $model_filters = null;
	public $list_columns = array();

	public function prepare_list_data()
	{
		$class_name = $this->model_class_name;
		$result = new $class_name();

		if ($this->model_filters)
			$result->where($this->model_filters);
		
		return $result;
	}
	
	public function apply_to_model($model, $keys, $context = null)
	{
		return $model;
	}
	
	protected function keys_to_str($keys)
	{
		return "('".implode("','", $keys)."')";
	}
	
	public function as_string($keys, $context = null)
	{
		return null;
	}
}
