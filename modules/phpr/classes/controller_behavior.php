<?php namespace Phpr;

use ReflectionObject;

/**
 * Controller behaviors base class
 */
class Controller_Behavior extends Extension
{
	protected $_controller;
	protected $_view_path;
	protected $_controller_cache = array();
	protected $_alt_view_paths = array();

	protected $view_data = array();
	
	public function __construct($controller)
	{
		parent::__construct();

		$this->_controller = $controller;
		
		$ref_obj = new ReflectionObject($this);
		$this->_view_path = dirname($ref_obj->getFileName()).'/'.strtolower(\get_real_class($this)).'/partials';
	}
	
	/**
	 * Registers a controller protected methods. 
	 * These methods could be defined in a controller to override a behavior default action.
	 * Such methods should be defined as public, to allow the behavior object to access it.
	 * By default public methods of a controller are considered as actions.
	 * To prevent it such methods should be registered with this method.
	 * @param mixed $method_name Specifies a method name. Could be a string or array.
	 */
	protected function hide_action($method_name)
	{
		$methods = Util::splat($method_name, ',');
		foreach ($methods as $method)
			$this->_controller->_internal_methods[] = trim($method);
	}
	
	/**
	 * Allows to register alternative view paths. Please use application root relative path.
	 */
	protected function register_view_path($path)
	{
		$this->_alt_view_paths[] = $path;
	}

	/**
	 * This method allows to add event handlers to the behavior.
	 * @param string $event_name Specifies an event name. The behavior class must contain a method with the same name.
	 */
	protected function add_event_handler($event_name)
	{
		$this->_controller->add_dynamic_method($this, $this->_controller->get_event_handler($event_name), $event_name);
	}

	/**
	 * Returns true in case if a partial with a specified name exists in the controller.
	 * @param string $view_name Specifies a view name
	 * @return bool
	 */
	protected function controller_partial_exists($view_name)
	{
		$controller_view_path = $this->_controller->get_views_path().'/_'.$view_name.'.htm';
		return file_exists($controller_view_path);
	}
	
	/**
	 * Returns true in case if a specified method exists in the extended controller.
	 * @param string $method_name Specifies the method name
	 * @return bool
	 */
	protected function controller_method_exists($method_name)
	{
		return method_exists($this->_controller, $method_name);
	}

	/**
	 * Tries to render a controller partial, and if it does not exist, renders the behavior partial with the same name.
	 * @param string $view_name Specifies a view name
	 * @param array $params A list of parameters to pass to the partial file
	 * @param bool $override_controller Indicates that the controller partial should be overridden 
	 * by the behavior partial even if the controller partial does exist.
	 * @param bool $throw_not_found Indicates that an exception should be thrown in case if the partial does not exist
	 * @return bool
	 */
	protected function display_partial($view_name, $params = array(), $override_controller = false, $throw_not_found = true)
	{
		$this->display_partial_file($this->_controller->get_views_path(), $view_name, $params, $override_controller, $throw_not_found);
	}

	/*
	 * Does the same things as display_partial, but uses a specified controller class name for finding partials.
	 * @param string $controller_class Specifies a controller class name. If it is null, fallbacks to display_partial.
	 * @param string $view_name Specifies a view name
	 * @param array $params A list of parameters to pass to the partial file
	 * @param bool $override_controller Indicates that the controller partial should be overridden 
	 * by the behavior partial even if the controller partial does exist.
	 * @param bool $throw_not_found Indicates that an exception should be thrown in case if the partial does not exist
	 * @return bool
	 */
	protected function display_controller_partial($controller_class, $view_name, $params = array(), $override_controller = false, $throw_not_found = true)
	{
		if (!strlen($controller_class))
			return $this->display_partial($view_name, $params, $override_controller, $throw_not_found);

		if (array_key_exists($controller_class, $this->_controller_cache))
			$controller = $this->_controller_cache[$controller_class];
		else
		{
			Controller::$skip_permission_check = true;
			$controller = $this->_controller_cache[$controller_class] = new $controller_class();
			Controller::$skip_permission_check = false;
		}

		$this->display_partial_file($controller->get_views_path(), $view_name, $params, $override_controller, $throw_not_found);
	}
	
	private function display_partial_file($controller_view_path, $view_name, $params = array(), $override_controller = false, $throw_not_found = true)
	{
		$this->_controller->view_data = $this->view_data + $this->_controller->view_data;
		$controller_view_path = $controller_view_path.'/_'.$view_name.'.htm';

		if (!$override_controller && file_exists($controller_view_path))
			$this->_controller->display_partial($controller_view_path, $params, true, true);
		else
		{
			$view_path = null;
			foreach ($this->_alt_view_paths as $path)
			{
				if (file_exists($path.'/_'.$view_name.'.htm'))
				{
					$view_path = $path.'/_'.$view_name.'.htm';
					break;
				}
			}
			
			if (!$view_path)
			{
				$view_path = $this->_view_path.'/_'.$view_name.'.htm';
				if (!$throw_not_found && !file_exists($view_path))
					return;
			}

			$this->_controller->display_partial($view_path, $params, true, true);
		}
	}
}