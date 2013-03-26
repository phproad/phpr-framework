<?php

class Phpr_Extension_Base
{
    protected $extension_hidden = array(
        'fields' => array(),
        'methods' => array('extension_is_hidden_field', 'extension_is_hidden_field')
    );

    protected function extension_hide_field($name)
    {
        $this->extension_hidden['fields'][] = $name;
    }

    protected function extension_hide_method($name)
    {
        $this->extension_hidden['methods'][] = $name;
    }

    public function extension_is_hidden_field($name)
    {
        return in_array($name, $this->extension_hidden['fields']);
    }

    public function extension_is_hidden_method($name)
    {
        return in_array($name, $this->extension_hidden['methods']);
    }

}