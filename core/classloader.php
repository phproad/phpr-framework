<?php

/**
 * Class loader
 * 
 * This class is used by the PHPR internally for finding and loading classes.
 * The instance of this class is available in the Phpr global object: Phpr::$class_loader.
 */
class Phpr_ClassLoader 
{
    private $paths;
    private $auto_init = null;
    private $cache;

    public function __construct() 
    {
        global $CONFIG;
        
        $this->cache = array();
        $this->paths = array(
            'application' => isset($CONFIG['APPLICATION_PATHS']) ? $CONFIG['APPLICATION_PATHS'] : array(PATH_APP, PATH_SYSTEM),
            'library' => array('controllers', 'classes', 'models'),
            'module' => array('widgets', 'classes', 'helpers', 'models', 'behaviors', 'controllers')
        );
    }

    /**
     * Loads a class
     * @param string $class Class name
     * @return bool If it loaded the class
     */
    public function load($class, $force_disabled = false)
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
            $loaded = $this->load_module($class, $force_disabled);

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
        $file_name = strtolower($class).'.'.PHPR_EXT;

        foreach ($this->paths['library'] as $path)
        {
            $full_path = $path.DS.$file_name;

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
    private function load_module($class, $force_disabled = false)
    {
        global $CONFIG;
        $disabled_modules = isset($CONFIG['DISABLE_MODULES']) ? $CONFIG['DISABLE_MODULES'] : array();

        $file_name = strtolower($class).'.'.PHPR_EXT;
        $underscore_pos = strpos($class, '_');
        $module_name = strtolower(($underscore_pos) 
            ? substr($class, 0, $underscore_pos)
            : $class);

        // Is disabled?
        if (in_array($module_name, $disabled_modules) && !$force_disabled)
            return false;

        foreach ($this->paths['application'] as $module_path)
        {
            foreach ($this->paths['module'] as $path)
            {
                $full_path = $module_path.DS 
                    . PHPR_MODULES.DS 
                    . $module_name.DS 
                    . $path.DS 
                    . $file_name;

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
     * Loads an application controller by the class name and returns the controller instance.
     * @param string $class_name Specifies a name of the controller to load.
     * @param string $controller_path Specifies a path to the controller directory.
     * @return Phpr_Controller The controller instance or null.
     */
    public function load_controller($class_name, $controller_directory = null) 
    {
        foreach ($this->paths['application'] as $path) 
        {
            $controller_path = ($controller_directory != null) ? $path.DS.$controller_directory : $path.DS.'controllers';
            $controller_path = realpath($controller_path.DS.strtolower($class_name).'.'.PHPR_EXT);

            if (!strlen($controller_path))
                continue;

            if (!class_exists($class_name)) 
            {
                require_once $controller_path;

                if (!class_exists($class_name))
                    continue;
                
                Phpr_Controller::$current = new $class_name();
                
                Phpr::$events->fire_event('phpr:on_configure_' . Phpr_Inflector::underscore($class_name) . '_controller', Phpr_Controller::$current);
                
                return Phpr_Controller::$current;
            }

            // Make sure the class requested is in the application controllers directory
            $class_info = new ReflectionClass($class_name);
            if ($class_info->getFileName() !== $controller_path)
                continue;
                
            Phpr_Controller::$current = new $class_name();
                
            Phpr::$events->fire_event('phpr:on_configure_' . Phpr_Inflector::underscore($class_name) . '_controller', Phpr_Controller::$current);
            
            return Phpr_Controller::$current;
        }
    }

    /**
     * Registers a class library directory.
     * Use this method to register a directory containing your application classes.
     * @param string $path Specifies a full path to the directory. No trailing slash.
     */
    public function add_library_directory($path) 
    {
        array_unshift($this->paths['library'], $path);
    }
    
    /**
     * Registers a application directory.
     * Use this method to register a directory containing your application classes.
     * @param string $path Specifies a full path to the directory. No trailing slash.
     */
    public function add_application_directory($path) 
    {
        array_unshift($this->paths['application'], $path);
    }
    
    /**
     * Registers a module directory.
     * Use this method to register a directory containing your module classes.
     * @param string $path Specifies a full path to the directory. No trailing slash.
     */
    public function add_module_directory($path) 
    {
        array_unshift($this->paths['module'], $path);
    }
    
    public function get_library_directories() 
    {
        return $this->paths['library'];
    }
    
    public function get_application_directories() 
    {
        return $this->paths['application'];
    }
    
    public function get_module_directories() 
    {
        return $this->paths['module'];
    }

    /**
     * Looks up a specific file path located in an application directory
     * @param string $path File to locate
     * @return string
     * @example 1
     * Look up file init.php
     * $path = Phpr::$class_loader->find_path('init/init.php');
     */
    public function find_path($path) 
    {
        global $CONFIG;

        foreach ($this->paths['application'] as $application_path) {
            $real_path = realpath($application_path.DS.$path);

            if($real_path && file_exists($real_path))
                return $real_path;
        }
    }

    /**
     * Returns all application paths for a given folder
     * @param string $path Folder name
     * @return array
     * @example 1
     * Find all module directories
     * $dirs = Phpr::$class_loader->find_paths('modules');
     */
    public function find_paths($path) 
    {
        global $CONFIG;

        $paths = array();

        foreach ($this->paths['application'] as $application_path) {
            $real_path = realpath($application_path.DS.$path);

            if($real_path && file_exists($real_path))
                $paths[] = $real_path;
        }

        return $paths;
    }

    /**
     * Check the existence of a file, whilst caching directories
     * @param string $path Absolute path to file
     * @return bool If file exists
     */
    private function file_exists($path)
    {

        try 
        {       
            $dir = dirname($path);
            $base = basename($path);

            if (!isset($this->cache[$dir]))
            {
                $this->cache[$dir] = (is_dir($dir)) 
                    ? scandir($dir)
                    : array();
            }
        } 
        catch (exception $ex) 
        {
            echo $file_path.' '.$ex->getMessage();
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