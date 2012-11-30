<?php

class Phpr_Controller_Behavior extends Phpr_Extension
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
        $this->_controller->add_dynamic_method($this, $this->_controller->get_event_handler($name), $name);
    }

    protected function render_partial($name, $params = array())
    {

    }

}