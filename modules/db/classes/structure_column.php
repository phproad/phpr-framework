<?php namespace Db;

use Phpr;

class Structure_Column
{
	public static $default_length = array(
		'int'     => 11,
		'varchar' => 255,
		'decimal' => 15,
		'float'   => 10,
		'tinyint' => 4
	);

	public static $default_precision = array(
		'decimal' => 2,
		'float'   => 6
	);

	private $_host; // Host Db\Structure object

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
	
	public function __construct($host = null)
	{
		$this->_host = $host;
	}

	public function primary() 
	{ 
		if (!$this->_host)
			return false;

		return $this->_host->add_key(null, $this->name);
	}

	public function index($name = null) 
	{ 
		if (!$this->_host)
			return false;
	
		if (!$name)
			$name = $this->name;

		return $this->_host->add_key($name, $this->name);
	}

	public function defaults($value) 
	{ 
		$this->default_value = $value;
		return $this;
	}

	public function enum_values($values) 
	{
		$this->enumeration = $values;
	}

	public function not_null($flag = false) 
	{ 
		$this->allow_null = $flag;
		return $this;
	}

	public function auto_increment($flag = true) 
	{ 
		$this->auto_increment = $flag;
		return $this;
	}

	public function build_sql() 
	{
		$this->set_defaults();

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

	/**
	 * @deprecated
	 */ 

	public function set_default($value) { Phpr::$deprecate->set_function('set_default', 'defaults'); return $this->defaults($value); }
} 