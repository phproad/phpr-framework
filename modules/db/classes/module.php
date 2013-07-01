<?php namespace Db;

class Module extends Core_Module_Base
{
	protected function set_module_info()
	{
		return new Core_Module_Detail(
			"DB",
			"Database interface",
			"PHPRoad",
			"http://phproad.com/"
		);
	}
}
