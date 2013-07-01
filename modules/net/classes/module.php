<?php namespace Net;

class Module extends Core_Module_Base
{
	protected function set_module_info()
	{
		return new Core_Module_Detail(
			"Net",
			"Network interface",
			"PHPRoad",
			"http://phproad.com/"
		);
	}
}
