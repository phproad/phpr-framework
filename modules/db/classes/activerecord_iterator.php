<?php namespace Db;

use Iterator;

class ActiveRecord_Iterator implements Iterator
{
	private $members = array();

	private $index = 0;

	public function __construct($object) 
	{
		$this->members = get_object_vars($object);
		unset($this->members['auto_save_associations']);
		unset($this->members['content_columns']);
		unset($this->members['errors']);
	}

	function current() 
	{
		return current($this->members);
	}

	function key() 
	{
		return key($this->members);
	}

	function next() 
	{
		return next($this->members);
	}

	function rewind() 
	{
		reset($this->members);
	}

	function valid()
	{
		return ($this->current() !== false);
	}

}
