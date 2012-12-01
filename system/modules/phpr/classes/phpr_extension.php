<?php

/**
 * This is an attempt to spoof "Traits" in PHP 5.3
 */

// Equivilent of trait definition, extend this class instead
// 
// Eg: 
// trait my_model_class
// { }
// 
// 
// Would be:
// class my_model_class extends Phpr_Extendable 
// { }
// 

class Phpr_Extension extends Phpr_Extension_Base 
{

    // Equivilent of "use", define this attribute instead
    // 
    // Eg:
    // use my_model_class;
    // 
    // Would be:
    // public $implement = 'my_model_class';
    // 
    public $implement;

    protected $extension_data = array(
        'extensions' => array(),
        'methods' => array(),
        'dynamic_methods' => array()
    );

    public function __construct()
    {
        if (!$this->implement)
            return;

        switch ($this->implement)
        {
            case (is_string($this->implement)):
                $uses = explode(',', $this->implement);
                break;

            case (is_array($this->implement)):
                $uses = $this->implement;
                break;

            default:
                throw new PHpr_SystemException('Class '.get_class($this).' contains an invalid $implement value ');
                break;
        }
        
        foreach ($uses as $use)
        {
            $this->extend_with(trim($use));
        }
    }

    public function extend_with($extension_name, $is_recurring = true)
    {
        if (!strlen($extension_name))
            return $this;

        if (array_key_exists($extension_name, $this->extension_data['extensions']))
            throw new Exception('Class '. get_class($this) .' has already been extended with '. $extension_name);

        $this->extension_data['extensions'][$extension_name] = $extension_object = new $extension_name($this);
        $this->extension_extract_methods($extension_name, $extension_object);

        if ($is_recurring && is_subclass_of($extension_object, 'Phpr_Extension'))
            $extension_object->extend_with(get_class($this), false);
    }

    public function add_dynamic_method($extension, $dynamic_name, $actual_name) 
    {
        $this->extension_data['dynamic_methods'][$dynamic_name] = array($extension, $actual_name);
    }

    protected function extension_extract_methods($extension_name, $extension_object)
    {
        $extension_methods = get_class_methods($extension_name);
        foreach ($extension_methods as $method_name)
        {
            if ($method_name == '__construct' || $extension_object->extension_is_hidden_method($method_name))
                continue;

            $this->extension_data['methods'][$method_name] = $extension_name;
        }
    }

    public function is_extended_with($name) 
    {
        foreach ($this->extension_data['extensions'] as $class_name => $extension)
        {
            if ($class_name == $name)
                return true;
        }
                
        return false;
    }

    public function get_extension($name) 
    {
        return (array_key_exists($name, $this->extension_data['extensions'])) 
            ? $this->extension_data['extensions'][$name]
            : null;
    }

    public function method_exists($name) 
    {
        return (method_exists($this, $name) 
            || isset($this->extension_data['methods'][$name]) 
            || isset($this->extension_data['dynamic_methods'][$name]));        
    }

    // Magic
    // 

    public function __get($name) 
    {
        if (property_exists($this, $name))
            return $this->{$name};

        foreach ($this->extension_data['extensions'] as $extension_object)
        {
            if (property_exists($extension_object, $name))
                return $extension_object->{$name};
        }       
    }

    public function __set($name, $value) 
    {
        if (property_exists($this, $name))
            return $this->{$name} = $value;

        foreach ($this->extension_data['extensions'] as $extension_object)
        {
            if (!isset($extension_object->{$name}))
                continue;

            return $extension_object->{$name} = $value;
        }       
    }    

    public function __call($name, $params = null) 
    {
        if (method_exists($this, $name))
            return call_user_func_array(array($this, $name), $params);

        if (isset($this->extension_data['methods'][$name]))
        {
            $extension = $this->extension_data['methods'][$name];
            $extension_object = $this->extension_data['extensions'][$extension];

            if (method_exists($extension, $name))
                return call_user_func_array(array($extension_object, $name), $params);
        }

        if (isset($this->extension_data['dynamic_methods'][$name]))
        {
            $extension_object = $this->extension_data['dynamic_methods'][$name][0];
            $actual_name = $this->extension_data['dynamic_methods'][$name][1];

            if (method_exists($extension_object, $actual_object))
                return call_user_func_array(array($extension_object, $actual_name), $params);
        }

        throw new Exception('Class '. get_class($this) .' does not have a method definition for ' . $name);
    }

}