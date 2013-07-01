<?php

class File_Module extends Core_Module_Base
{
  protected function set_module_info()
  {
    return new Core_Module_Detail(
      "File",
      "File system interface",
      "PHPRoad",
      "http://phproad.com/"
    );
  }
}
