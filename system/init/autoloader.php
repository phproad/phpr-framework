<?php

/**
 * PHPR Autoloader
 */

class Phpr_Autoloader 
{

    const module_directory = 'modules';

    private $paths;
    private $auto_init = null;
    private $cache;

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
        $loaded = false;        

        if (!$this->auto_init)
            $this->auto_init = $class;

        // Class already exists, no need to reload
        if (class_exists($class))
        {
            $this->init_class($class);
            $loaded = true;
        }

        if (!$loaded)
            $loaded = $this->load_local($class);
        
        if (!$loaded) 
            $loaded = $this->load_module($class);

        // Prevents a failed init from breaking workflow
        if ($this->auto_init == $class)
            $this->auto_init = null;

        return $loaded;
    }

    /**
     * Look for a class locally
     * @param string $class Class name
     * @return bool If the class is found
     */
    private function load_local($class)
    {
        $file_name = strtolower($class);

        foreach ($this->paths['library'] as $path)
        {
            $full_path = $path . DS . $file_name . PHPR_EXT;

            if (!$this->file_exists($full_path))
                continue;

            include($full_path);

            if (class_exists($class))
            {
                $this->init_class($class);
                return true;
            }
        }

        return false;
    }

    /**
     * Looks for a class located within a module
     * @param string $class Class name
     * @return bool If the class is found
     */
    private function load_module($class)
    {
        $file_name = strtolower($class);
        $underscore_pos = strpos($class, '_');
        $module_name = ($underscore_pos) 
            ? substr($class, 0, $underscore_pos)
            : $class;

        foreach ($this->paths['application'] as $module_path)
        {
            foreach ($this->paths['module'] as $path)
            {
                $full_path = $module_path . DS 
                    . self::module_directory . DS 
                    . $module_name . DS 
                    . $path . DS 
                    . $file_name . PHPR_EXT;

                if (!$this->file_exists($full_path))
                    continue;

                include($full_path);

                if (class_exists($class))
                {
                    $this->init_class($class);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check the existence of a file, whilst caching directories
     * @param string $path Absolute path to file
     * @return bool If file exists
     */
    private function file_exists($path)
    {
        $dir = dirname($path);
        $base = basename($path);

        if (!isset($this->cache[$dir]))
        {
            $this->cache[$dir] = (is_dir($dir)) 
                ? scandir($dir)
                : array();
        }

        return in_array($base, $this->cache[$dir]);
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