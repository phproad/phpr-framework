<?php

class Db_Form_Partial extends Db_Form_Element
{
	public $path;
	
	public function __construct($path)
	{
		$this->path = $path;
	}
}
