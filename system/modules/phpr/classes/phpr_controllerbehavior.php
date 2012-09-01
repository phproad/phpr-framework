<?php

class Phpr_ControllerBehavior extends Phpr_Extension
{

    protected $_controller;
    protected $view_data = array();

    public function __construct($controller)
    {
        $this->_controller = $controller;
    }

    protected function register_view_path($path)
    {
        
    }

    protected function add_event_handler($name)
    {

    }

    protected function render_partial($name, $params = array())
    {

    }

}