<?php

class Phpr_Controller extends Phpr_Controller_Base
{
    public function get_event_handler($name)
    {
        return Phpr::$router->action.'_'.$name;
    }
}