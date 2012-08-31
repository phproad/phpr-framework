<?php

/**
 * PHPR Autoloader
 */

class Phpr_Autoloader 
{

    private $paths;
    private $auto_init = null;

    public function __construct() 
    {
        $this->paths = array(
            'application' => array(PATH_APP, PATH_SYSTEM),
            'library' => array('classes', 'controllers', 'models'),
            'module' => array('behaviors', 'classes', 'controllers', 'helpers', 'models'),
        );
    }

    /**
     * Loads a class
     * @param string $class Class name
     * @return bool If it loaded the class
     */
    public function load($class)
    {

        if (!$this->auto_init)
            $this->auto_init = $class;

        // Class already exists, no need to reload
        if (class_exists($class))
        {
            $this->init_class($class);
            $loaded = true;
        }

        // TODO: Scan directories or load from cache
        // 

        // TODO: Load class file
        // 


        // Prevents a failed init from breaking workflow
        if ($this->auto_init == $class)
            $this->auto_init = null;

        return $loaded;
    }

    /**
     * Checks to see if the given class has a static init() method. 
     * If so then it calls it.
     * @param string class name
     */
    protected function init_class($class)
    {
        if ($this->auto_init === $class)
        {
            $this->auto_init = null;

            if (method_exists($class, 'init') && is_callable($class.'::init'))
                call_user_func($class.'::init');
        }
    }    

}