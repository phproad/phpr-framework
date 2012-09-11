<?php

abstract class Phpr_ControllerBase extends Phpr_Extendable
{

    public $view_data = array();

    public function __construct()
    {
        parent::__construct();
    }

    public function add_javascript($path)
    {

    }
    
    public function add_css($path)
    {

    }

    public function render_partial($view, $params = array())
    {
        
    }

}