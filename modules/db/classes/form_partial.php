<?php namespace Db;

class Form_Partial extends Form_Element
{
	public $path;
	
	public function __construct($path)
	{
		$this->path = $path;
	}
}
