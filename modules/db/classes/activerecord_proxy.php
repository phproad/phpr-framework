<?php

class Db_ActiveRecord_Proxy extends Phpr_Extension
{
	private $model_class;
	private $fields = array();
	private $key;
	private $obj;

	protected static $proxiable_methods = array();
	
	public function __construct($key, $model_class, $fields)
	{
		parent::__construct();
		
		$this->key = $key;
		$this->model_class = $model_class;
		$this->fields = $fields;
	}
	
	public function __set($field, $value)
	{
		if (array_key_exists($field, $this->fields))
		{
			$this->fields[$field] = $value;
			return;
		}
		
		$this->get_object()->$field = $value;
	}
	
	public function __get($field)
	{
		if (array_key_exists($field, $this->fields))
			return $this->fields[$field];

		return $this->get_object()->$field;
	}
	
	public function __call($method, $arguments = array())
	{
		/*
		 * Try to call extension methods
		 */
		
		if (array_key_exists($method, $this->extensible_data['methods']))
			return parent::__call($method, $arguments);
		
		/*
		 * Try to call a proxiable method
		 */
		
		$proxiable_method_name = $method.'_proxiable';

		if (
			array_key_exists($this->model_class, self::$proxiable_methods) && 
			array_key_exists($method, self::$proxiable_methods[$this->model_class]) 
		)
			$proxiable = self::$proxiable_methods[$this->model_class][$method];
		else {
			$proxiable = method_exists($this->model_class, $proxiable_method_name);
			if (array_key_exists($this->model_class, self::$proxiable_methods))
				self::$proxiable_methods[$this->model_class] = array();

			self::$proxiable_methods[$this->model_class][$method] = $proxiable;
		}
			
		if ($proxiable)
		{
			array_unshift($arguments, $this);
			return call_user_func_array(array($this->model_class, $proxiable_method_name), $arguments);
		}
		
		/*
		 * Create the model object and call its method
		 */
		
		return call_user_func_array(array($this->get_object(), $method), $arguments);
	}
	
	protected function get_object()
	{
		if ($this->obj)
			return $this->obj;

		$class = $this->model_class;
		
		$obj = new $class();
		return $obj->find($this->key);
	}
}
