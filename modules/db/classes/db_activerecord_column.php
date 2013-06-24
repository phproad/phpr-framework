<?php

class Db_ActiveRecord_Column 
{
	public $name = '';
	public $type = 'text';
	public $length = null;
	public $calculated;
	public $custom;
	public $sql_type;

	public function __construct($column_info) 
	{
		$options = array();

		$this->name = $column_info['name'];
		$this->type = isset($column_info['type']) ? $column_info['type'] : 'varchar';
		$this->calculated = isset($column_info['calculated']) ? $column_info['calculated'] : false;
		$this->custom = isset($column_info['custom']) ? $column_info['custom'] : false;

		if (isset($column_info['sql_type']))
		{
			$this->sql_type = $column_info['sql_type'];
			$matches = array();
			if (preg_match('/^varchar\(([0-9]*)\)$/', $column_info['sql_type'], $matches))			 
				$this->length = $matches[1];
		}

		switch ($this->type) 
		{
			case 'char':
			case 'varchar':
				$this->type = db_varchar;
				break;
			case 'int':
			case 'smallint':
			case 'mediumint':
			case 'bigint':
				$this->type = db_number;
				break;
			case 'double':
			case 'decimal':
			case 'float':
				$this->type = db_float;
				break;
			case 'bool':
			case 'tinyint':
				$this->type = db_bool;
				break;
			case 'datetime':
				$this->type = db_datetime;
				break;
			case 'date':
				$this->type = db_date;
				break;
			case 'time':
				$this->type = db_time;
				break;
		}
	}

}
