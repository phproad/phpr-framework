<?php

class Db_ListBehavior extends Phpr_ControllerBehavior
{
    public function __construct($controller)
    {
        parent::__construct($controller);

        if (!$controller)
            return;

        $this->form_load_assets();
    }

    public function list_render()
    {

    }

    protected function form_load_assets()
    {
        
    }

}
