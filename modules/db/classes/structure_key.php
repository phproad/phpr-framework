<?php namespace Db;

class Structure_Key 
{		
	public $is_primary = false;
	public $is_unique = false;
	public $name = null;
	public $key_columns = array();

	public function __construct($host = null)
	{
		$this->_host = $host;
	}

	public function unique() 
	{ 
		$this->is_unique = true;
		return $this;
	}

	public function primary() 
	{ 
		$this->is_primary = true;		
		return $this;
	}

	public function add_columns($names)
	{
		foreach ($names as $name) {
			$this->add_column($name);
		}
	}

	public function add_column($name)
	{
		if (is_array($name))
			return $this->add_columns($name);

		$this->key_columns[] = $name;
	}

	public function get_columns()
	{
		return $this->key_columns;
	}

	public function build_sql() 
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

}