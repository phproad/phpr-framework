<?php

class Phpr_ExtensionBase
{

    protected $extension_hidden_fields = array();
    protected $extension_hidden_methods = array();

    protected function extension_hide_field($name)
    {
        $this->extension_hidden_fields[] = $name;
    }

    protected function extension_hide_method($name)
    {
        $this->extension_hidden_methods[] = $name;
    }

    protected function extension_is_hidden_field($name)
    {
        return in_array($name, $this->extension_hidden_fields);
    }

    protected function extension_is_hidden_method($name)
    {
        return in_array($name, $this->extension_hidden_methods);
    }

}