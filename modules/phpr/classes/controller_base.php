<?php namespace Phpr;

use Phpr;
use Phpr\SystemException;

/**
 * PHPR Controller Base Class
 *
 * Controller_Base is a base class for the application and component controllers.
 */
class Controller_Base extends Validate_Extension
{
	protected $_suppress_view = false;
	protected $_event_post_prefix = 'ev';
	protected $_partial_extensions = array('php', 'htm');
	protected $_resources = array('javascript'=>array(), 'css'=>array(), 'rss'=>array());

	public $_internal_methods = array('remote_event_handler', 'load_view', 'render_partial', 'display_partials', 'event_handler', 'remote_event_handler', 'get_views_path', 'execute_action', 'execute_internal_action');

	/**
	 * Use ViewData to pass data to the template.
	 * @var array ViewData as a data bridge between the controller and a view.
	 */
	public $view_data = array();

	/**
	 * The Validation object. Use it to validate a form data.
	 * @var Phpr\Validation
	 */
	public $validation;

	/**
	 * Creates a new controller instance
	 */
	public function __construct()
	{
		$this->validation = new Validation($this);
		
		parent::__construct();
	}

	/**
	 * Returns a value from the View Data array. 
	 * If the index specified does not exist, returns null.
	 * @param string $index Specifies the View Data index.
	 * @param mixed $default Specifies a default value
	 * @param boolean $inspect_post Indicates whether the function must look in the POST array as well
	 * in case if the value is not found in the View Data.
	 * @return mixed.
	 */
	protected function view_data_element($index, $default = null, $inspect_post = false)
	{
		if (isset($this->view_data[$index]))
			return $this->view_data[$index];
		else
			if (!$inspect_post)
				return $default;

		return Phpr::$request->post_field($index, $default);
	}

	/**
	 * Loads a view with the name specified.
	 * The view file must be situated in the views directory, and has the extension "php" or "htm".
	 * @param string $view Specifies the view name, without extension: "archive". 
	 */
	public function load_view($view)
	{
		$this->display_partial($view, null, false);
	}

	/**
	 * Renders the specified view without applying a layout. 
	 * The view file must be situated in the views directory, and has the extension "php" or "htm".
	 * @param string $view Specifies the view name, without extension: "archive". 
	 * @param array $params An optional list of parameters to pass to the view.
	 * @param bool $partial_mode Determines whether this method is used directly or from another method (load_view etc.)
	 * @param bool $force_path Use the path passed in the $view parameter instead of using the own views path directory
	 */
	public function display_partial($view, $params = null, $partial_mode = true, $force_path = false)
	{
		extract($this->view_data);

		if (is_array($params))
			extract($params);

		if (strpos($view, '/') !== false)
			$force_path = true;

		if (!$force_path) {
			
			if ($partial_mode)
				$view = '_'.$view;

			foreach ($this->_partial_extensions as $ext) {

				$view_file = $this->get_views_path()."/".strtolower($view).'.'.$ext;
				if (file_exists($view_file))
					break;
			}
		} 
		else {
			$view_file = $view;
		}

		if (file_exists($view_file)) {
			include $view_file;
		} 
		else if ($partial_mode) {
			throw new SystemException('Partial file not found: '.$view_file);
		}
	}

	/**
	 * Renders multiple view files. This method works in conjunction 
	 * with the Ajax multiupdate feature
	 * @param array $parts Specifies a list of views or strings to render and element identifiers to update.
	 * Example: $this->display_partials( array('Photos'=>'@photos', 'Sidebar'=>'@sidebar', 'Message'=>'File not found') );
	 */
	public function display_partials($parts)
	{
		foreach ($parts as $element => $part)
		{
			echo '>>#'.$element.'<<';
			if (strlen($part) && $part{0} == '@')
				$this->display_partial(substr($part, 1), null, false);
			else
				echo $part;
		}
	}

	/**
	 * Returns the event handler information for using with the 
	 * control helpers like Phpr_Form::Button or Phpr_Form::Anchor.
	 * @param string $event_name Specifies the event name. 
	 * The event name must be a name of the controller method.
	 * @return array Event handler information.
	 */
	public function event_handler($event_name)
	{
		return array('handler' => $this->get_event_post_name($event_name), 'remote'=>false);
	}

	/**
	 * Returns the remote event handler information for using with the 
	 * control helpers like Phpr_Form::Button or Phpr_Form::Anchor.
	 * Remote events are called using the AJAX.
	 * @param string $event_name Specifies the event name. 
	 * The event name must be a name of the controller method.
	 * @param array $options Specifies a list of Mootools AJAX request options: onComplete, evalScripts
	 * @return array Event handler information.
	 */
	public function remote_event_handler($event_name, $options = null)
	{
		$result = array('handler' => $this->get_event_post_name($event_name), 'remote'=>true);

		if ($options !== null)
			$result = array_merge($result, $options);

		return $result;
	}

	/**
	 * Executes a controller action, renders its view and returns the action resutl
	 * @param string $uri Specifies an action URI
	 * @param mixed $params Optional. Any parameters to pass to the action
	 * @return mixed
	 */
	protected function request_action($uri, $params = null)
	{
		$controller = null;
		$action = null;
		$parameters = null;
		$folder = null;

		Phpr::$Router->route($uri, $controller, $action, $parameters, $folder);
		$obj = Phpr::$class_loader->load_controller($controller, $folder);

		if (!$obj)
			throw new SystemException("Controller ".$controller." is not found");

		if (!$obj->_action_exists($action, true))
			throw new SystemException("Action ".$action." is not found in the controller ".$controller);

		if ($params !== null)
			$parameters = $params;

		return $obj->execute_internal_action($action, $parameters);
	}

	/**
	 * Prevents the automatic view display.
	 * Call this method in the controller action or event handler 
	 * if you do not want the view to be displayed.
	 */
	public function suppress_view()
	{
		$this->_suppress_view = true;
	}

	/**
	 * Returns a name of the POST variable assigned with the controller event.
	 * @param string $event_name Specifies the event name.
	 * @return string
	 */
	protected function get_event_post_name($event_name)
	{
		return $this->_event_post_prefix."{".$event_name."}";
	}

	/**
	 * @ignore
	 * Executes an event handler.
	 * This method is used by the PHPR internally.
	 * @param string $method_name Specifies a method name to execute.
	 * @param array $parameters A list of the action parameters.
	 */
	public function _exec_event_handler($method_name, $parameters = array(), $action = null)
	{
		if (!$this->method_exists($method_name))
			throw new SystemException("The event handler ".$method_name." does not exist in the controller.");

		foreach ($parameters as &$param) {
			$param = str_replace("\"", "\\\"", $param);
		}


		$parameters = count($parameters) ? "\"".implode("\",\"", $parameters)."\"" : null;
		eval("\$this->$method_name($parameters);");
		
		if (post('phpr_popup_form_request'))
			Phpr::$response->add_remote_resources($this->_resources['css'], $this->_resources['javascript']);
	}
	
	/**
	 * Adds JavaScript resource to the resource list. Call $this->load_resources in a view to output corresponding markup.
	 * @param string $script_path Specifies a path (URL) to the script
	 */
	public function add_javascript($script_path)
	{
		if (substr($script_path, 0, 1) == '/')
			$script_path = Phpr::$request->get_subdirectory().substr($script_path, 1);
		
		if (!in_array($script_path, $this->_resources['javascript']))
			$this->_resources['javascript'][] = $script_path;
	}
	
	/**
	 * Adds CSS resource to the resource list. Call $this->load_resources in a view to output corresponding markup.
	 * @param string $css_path Specifies a path (URL) to the script
	 */
	public function add_css($css_path)
	{
		if (substr($css_path, 0, 1) == '/')
			$css_path = Phpr::$request->get_subdirectory().substr($css_path, 1);

		if (!in_array($css_path, $this->_resources['css']))
			$this->_resources['css'][] = $css_path;
	}

	/**
	 * Adds RSS link to the resource list. Call $this->load_resources in a view to output corresponding markup.
	 * @param string $rss_path Specifies a path (URL) to the RSS channel
	 */
	public function add_rss($rss_path)
	{
		if (!in_array($rss_path, $this->_resources['rss']))
			$this->_resources['rss'][] = $rss_path;
	}
	
	/**
	 * Outputs <link> and <script> tags to load resources previously added with add_javascript and add_css method calls
	 * @return string
	 */
	public function load_resources()
	{
		$result = null;
		
		foreach ($this->_resources['css'] as $file)
			$result .= '<link rel="stylesheet" href="'.$file.'" type="text/css"/>'."\n";
		
		foreach ($this->_resources['rss'] as $file)
			$result .= '<link title="RSS" rel="alternate" href="'.$file.'" type="application/rss+xml"/>'."\n";
			
		foreach ($this->_resources['javascript'] as $file)
			$result .= '<script type="text/javascript" src="'.$file.'"></script>'."\n";

		return $result;
	}

	/**
	 * Returns a path to the controller views directory. No trailing slashes.
	 * @return string
	 */
	public function get_views_path() { }
}