<?php

/**
 * PHPR module driver base class
 * 
 * This class assists in working with module drivers
 */
class Phpr_Driver_Base extends Phpr_Extension_Base
{
	// Driver folder name
	public static $driver_folder;

	// Driver file suffix
	public static $driver_suffix;

	protected $_host_object = null;

	public function __construct($obj = null)
	{
		$this->_host_object = $obj;
	}

	public function get_host_object()
	{
		return $this->_host_object;
	}

	/**
	 * Returns full relative path to a resource file situated in the driver's resources directory.
	 * @param string $path Specifies the relative resource file name, for example '/assets/javascript/widget.js'
	 * @return string Returns full relative path, suitable for passing to the controller's add_css() or add_javascript() method.
	 */
	public function get_vendor_path($path)
	{
		if (substr($path, 0, 1) != '/')
			$path = '/'.$path;
		
		$class_name = get_class($this);
		$class_path = File_Path::get_path_to_class($class_name);
		return $class_path.'/'.strtolower($class_name).'/vendor'.$path;
	}  

	public function get_partial_path($partial_name = null)
	{
		$class_name = get_class($this);
		$class_path = File_Path::get_path_to_class($class_name);
		return $class_path.'/'.strtolower($class_name).'/partials/'.$partial_name;
	}

	public function get_public_asset_path($partial_name = null)
	{
		$class_name = get_class($this);
		$class_path = File_Path::get_path_to_class($class_name);
		$local_path = $class_path.'/'.strtolower($class_name).'/assets/'.$partial_name;
		return File_Path::get_public_path($local_path);
	}    
}