<?php

class Phpr_Module extends Core_Module_Base
{
  protected function set_module_info()
  {
    return new Core_Module_Detail(
      "PHPR",
      "Core framework",
      "PHPRoad",
      "http://phproad.com/"
    );
  }
}
