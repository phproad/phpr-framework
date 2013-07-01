<?php namespace Db;

class Form_Custom_Area extends Form_Element
{
	public $id;
	
	public function __construct($id)
	{
		$this->id = $id;
	}
}
