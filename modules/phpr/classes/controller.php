<?php namespace Phpr;

use Phpr;
use Phpr\SystemException;

/**
 * PHPR Controller Base Class
 */
class Controller extends Controller_Base
{
	/**
	 * Contains a reference to a currently executing controller.
	 */ 
	public static $current = null;

	/**
	 * Specifies a name of the layout.
	 * @var string
	 */
	public $layout = null;

	/**
	 * Specifies a path to the controller views directory.
	 * If this field is empty (null) the default path is used.
	 * @var string
	 */
	protected $view_path = null;
	
	/**
	 * Specifies a path to the controller layouts directory.
	 * If this field is empty (null) the default path is used.
	 * @var string
	 */
	protected $layout_path = null;
	
	protected $global_handlers = array();

	public static $skip_permission_check = false;

	/**
	 * Returns a path to the controller views directory. No trailing slashes.
	 * @return string
	 */	
	
	public function get_views_path()
	{
		// @deprecated (mixed)
		if (!$this->view_path && $this->viewPath) $this->view_path = $this->viewPath;

		return $this->view_path === null ? Phpr::$class_loader->find_path('partials/' . strtolower(get_class($this))) : $this->view_path;
	}
	
	/**
	 * Allows to set the controller views directory. Use application root paths.
	 */
	
	public function set_views_path($path)
	{
		$this->view_path = Phpr::$class_loader->find_path($path);
	}

	/** 
	 * This method is used by the PHPR internally.
	 * Dispatches events, invokes the controller action or event handler and loads a corresponding view.
	 * @param string $action_name Specifies the action name.
	 * @param array $parameters A list of the action parameters.
	 */
	public function _run($action_name, $parameters)
	{
		if (Phpr::$request->is_remote_event())
			$this->suppress_view();

		// If no event was handled, execute the action requested in URI
		//
		if (!$this->dispatch_events($action_name, $parameters))
			$this->execute_action($action_name, $parameters);
	}

	/**
	 * Loads a view with the name specified. Applies layout if its name is provided by the controller.
	 * The view file must be situated in the views directory, and has the extension "htm".
	 * @param string $view Specifies the view name, without extension: "archive". 
	 * @param boolean $suppress_layout Determines whether the view must be loaded without layout.
	 * @param boolean $suppress_default Indicates whether the default action view must be suppressed.
	 */
	public function load_view($view, $suppress_layout = false, $suppress_default = false)
	{
		// If there is no layout provided, just render the view
		if ($this->layout == '' || $suppress_layout)
		{
			Controller_Base::load_view($view);
			return;
		}

		// Catch the layout blocks
		View::begin_block("outside_block");
		parent::load_view($view);
		View::end_block();

		View::append_block('view', View::get_block('outside_block'));

		// Render the layout
		$this->display_layout();

		if ($suppress_default)
			$this->suppress_view();
	}

	/**
	 * This method is used by the PHPR internally.
	 * Invokes the controller action or event handler and loads corresponding view.
	 * @param string $method_name Specifies a method name to execute.
	 * @param array $parameters A list of the action parameters.
	 */
	public function execute_action($method_name, $parameters)
	{
		// Execute the action
		call_user_func_array(array(&$this, $method_name), $parameters);

		// Load the view
		if (!$this->_suppress_view)
			$this->load_view($method_name);
	}

	/**
	 * This method is used by the PHPR internally by the RequestAction method.
	 * Invokes the controller action or event handler and loads corresponding view.
	 * @param string $method_name Specifies a method name to execute.
	 * @param array $parameters A list of the action parameters.
	 * @return mixed
	 */
	public function execute_internal_action($method_name, $parameters)
	{
		$result = $this->$method_name($parameters);

		if (!$this->_suppress_view)
			$this->load_view($method_name, true, true);

		return $result;
	}

	/**
	 * @ignore
	 * Executes an event handler.
	 * This method is used by the PHPR internally.
	 * @param string $method_name Specifies a method name to execute.
	 * @param string $action_name Specifies the action name.
	 * @param array $parameters A list of the action parameters.
	 */
	public function _exec_event_handler($method_name, $parameters = array(), $action_name = null)
	{
		parent::_exec_event_handler($method_name, $parameters);

		// Load the view
		if (!$this->_suppress_view)
			$this->load_view($action_name);
	}

	/**
	 * @ignore
	 * This method is used by the PHPR internally.
	 * Determines whether an action with the specified name exists.
	 * Action must be a class public method. Action name can not be prefixed with the underscore character.
	 * @param string $action_name Specifies the action name.
	 * @param bool $internal_call Allow protected actions
	 * @return boolean
	 */
	public function _action_exists($action_name, $internal_call = false)
	{
		if (!strlen($action_name) || substr($action_name, 0, 1) == '_' || !$this->method_exists($action_name))
			return false;

		foreach ($this->_internal_methods as $method)
		{
			if ($action_name == strtolower($method))
				return false;
		}

		$own_method = method_exists($this, $action_name);

		if ($own_method)
		{
			$method_info = new \ReflectionMethod($this, $action_name);
			$public = $method_info->isPublic();
			if ($public)
				return true;
		}
		
		if ($internal_call && (($own_method && $method_info->isProtected()) || !$own_method))
			return true;
		
		if (!$own_method)
			return true;

		return false;
	}

	/**
	 * Renders multiple view files. This method works in conjunction 
	 * with the Ajax multiupdate feature
	 * @param array $parts Specifies a list of views or strings to render and element identifiers to update.
	 * Example: $this->render_multiple( array('Photos'=>'@photos', 'Sidebar'=>'@sidebar', 'Message'=>'File not found') );
	 */
	public function display_partials($parts)
	{
		foreach ($parts as $element=>$part)
		{
			echo '>>#'.$element.'<<';
			
			if (strpos($part, '@@') === 0)
				$this->display_layout(substr($part, 2));
			elseif (strlen($part) && $part{0} == '@')
				$this->display_partial(substr($part, 1), null, false);
			else
				echo $part;
		}
	}
	
	/**
	 * Renders a specific partial in a specific page element
	 * @param string $element_id Specifies a page element identifier
	 * @param string $partial Specifies a partial name to render
	 */
	public function prepare_partial_multi($element_id, $partial)
	{
		echo '>>#'.$element_id.'<<';
		$this->display_partial($partial, null, false);
	}

	/**
	 * Prepares the view engine to rendering a partial in a specific element
	 * @param string $element_id Specifies a page element identifier
	 */
	public function prepare_partial($element_id)
	{
		echo '>>#'.$element_id.'<<';
	}

	/**
	 * Renders the layout.
	 * @param string $name Specifies the layout name.
	 * If this parameter is omitted, the $layout property will be used.
	 */
	protected function display_layout($name = null)
	{
		// @deprecated (mixed)
		if (!$this->layout_path && $this->layoutsPath) $this->layout_path = $this->layoutsPath;

		extract($this->view_data);
		$layout = $name === null ? $this->layout : $name;

		if ($layout == '')
			return;
			
		$dir_path = $this->layout_path != null 
			? $this->layout_path 
			: PATH_APP."/layouts";

		if (strpos($layout, '/') === false)
			$layout_path = $dir_path.'/'.$layout.".htm";
		else
			$layout_path = $layout;

		if (!file_exists($layout_path))
			throw new SystemException('The layout file "'.$layout_path.'" does not exist');

		include $layout_path;
	}
	
	/**
	 * Returns a name of event handler with added action name. 
	 * Use it when you have multiple event handers on different pages (actions).
	 * You may use this method in JavaScript "$.phpr.post()" method call as a handler name.
	 * @param string $event_name Specifies an event name
	 */
	public function get_event_handler($event_name)
	{
		return Phpr::$router->action.'_'.$event_name;
	}

	// Mixed @deprecated logic begin
	// When obsolete only remove single line below @deprecated definition
	// 

	/**
	 * Adds global event handler to the controller event handler list.
	 * @param string $name Specifies the event handler name.
	 */
	public function add_global_event_handler($name)
	{
		$this->global_handlers[] = $name;

		// @deprecated
		$this->globalHandlers[] = $name;
	}

	/**
	 * Finds and executed a handler for an event triggered by client.
	 * @param string $action_name Specifies the action name.
	 * @param array $parameters A list of the action parameters.
	 * @return boolean Determines whether the event was handled.
	 */
	protected function dispatch_events($action_name, $parameters)
	{
		// @deprecated
		$this->global_handlers = array_merge($this->global_handlers, $this->globalHandlers);

		$handler_name = isset($_SERVER['HTTP_PHPR_EVENT_HANDLER']) ? $_SERVER['HTTP_PHPR_EVENT_HANDLER'] : null;

		if (!$handler_name)
			return false;
		
		$matches = null;

		foreach ($this->global_handlers as $global_handler)
		{
			if ($this->_event_post_prefix.'{'.$global_handler.'}' == $handler_name)
			{
				$this->_exec_event_handler($global_handler, $parameters, $action_name);
				return true;
			}
		}

		if (preg_match("/^".$this->_event_post_prefix."\{(?P<handler>".$action_name."_on[a-zA-Z_]*)\}$/i", $handler_name, $matches))
		{
			$this->_exec_event_handler($matches['handler'], $parameters, $action_name);
			return true;
		}
	}

	// @deprecated
	protected $globalHandlers = array();
	protected $layoutsPath = null;
	protected $viewPath = null;
	public function getViewsDirPath() { return $this->get_views_path(); }
	public function setViewsDirPath($path) { $this->set_views_path($path); }
}