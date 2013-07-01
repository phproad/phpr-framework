<?php namespace Phpr;

use Phpr\SystemException;

/**
 * PHPR Router Rule Class
 *
 * Represents a rule for mapping an URI string to the PHPR controller and action.
 * Do not use this class directly. Use the Phpr::$router->add_rule method instead.
 */
class Router_Rule
{
	public $uri = null;
	public $controller = null;
	public $action = null;
	public $defaults = array();
	public $checks = array();
	public $folder = null;
	public $converts = array();

	private $_params = array();

	/**
	 * Creates a new rule.
	 * Do not create rules directly. Use the Phpr::$router->add_rule method instead.
	 * @param string $uri Specifies the URI to be matched. No leading and trailing slashes. The :controller and :action names may be used. Example: :controller/:action/:id
	 * @return Phpr\Router_Rule
	 */
	public function __construct($uri)
	{
		$this->uri = $uri;
		$this->_params = Router::get_uri_params(explode("/", $this->uri));
	}

	/**
	 * Sets a name of the controller to be used if the requested URI matches this rule URI.
	 * @param string $controller Specifies a controller name.
	 * @return Phpr\Router_Rule
	 */
	public function controller($controller)
	{
		if ($this->controller !== null)
			throw new SystemException('Invalid router rule configuration. The controller is already specified: ['.$this->uri.']');

		if (Router::value_is_param($controller))
		{
			if (!isset($this->_params[$controller]))
				throw new SystemException('Invalid router rule configuration. The parameter "'.$controller.'" specified in the Controller instruction is not found in the rule URI: ['.$this->uri.']');
		}

		$this->controller = $controller;

		return $this;
	}

	/**
	 * Sets a name of the controller action be executed if the requested URI matches this rule URI.
	 * @param string $action Specifies an action name.
	 * @return Phpr\Router_Rule
	 */
	public function action($action)
	{
		if ($this->action !== null)
			throw new SystemException("Invalid router rule configuration. The action is already specified: [{$this->uri}]");

		if (Router::value_is_param($action))
		{
			if (!isset($this->_params[$action]))
				throw new SystemException('Invalid router rule configuration. The parameter "'.$action.'" specified in the Action instruction is not found in the rule URI: ['.$this->uri.']');
		}

		$this->action = $action;
		return $this;
	}

	/**
	 * Sets a default URI parameter value. This value will be used if the URI component is ommited.
	 * @param string $param Specifies a parameter name. The parameter must be present in the rule URI and prefixed with the colon character. For example "/date/:year".
	 * @param mixed $value Specifies a parameter value.
	 * @return Phpr\Router_Rule
	 */
	public function set_default($param, $value)
	{
		if (!isset($this->_params[$param]))
			throw new SystemException('Invalid router rule configuration. The default parameter "'.$param.'" is not found in the rule URI: ['.$this->uri.']');

		$this->defaults[$param] = $value;
		return $this;
	}
	
	/**
	 * Converts a parameter value according a specified regular expression match and replacement strings
	 * @param string $param Specifies a parameter name. The parameter must be present in the rule URI and prefixed with the colon character. For example "/date/:year".
	 * @param mixed $match Specifies a regular expression match value
	 * @param mixed $replace Specifies a regular expression replace value
	 * @return Phpr\Router_Rule
	 */
	public function convert($param, $match, $replace)
	{
		if (!isset($this->_params[$param]))
			throw new SystemException('Invalid router rule configuration. The convert parameter "'.$param.'" is not found in the rule URI: ['.$this->uri.']');

		$this->converts[$param] = array($match, $replace);
		return $this;
	}

	/**
	 * Sets the URI parameter value check.
	 * @param string $param Specifies a parameter name. The parameter must be present in the rule URI and prefixed with the colon character. For example "/date/:year".
	 * @param string $check Specifies a checking value as a Perl-Compatible Regular Expression pattern, for example "/^\d{1,2}$/"
	 * @return Phpr\Router_Rule
	 */
	public function check($param, $check)
	{
		if (!isset($this->_params[$param]))
			throw new SystemException('Invalid router rule configuration. The parameter "'.$param.'" specified in the Check instruction is not found in the rule URI: ['.$this->uri.']');

		$this->checks[$param] = $check;
		return $this;
	}

	/**
	 * Defines a path to the controller class file.
	 * @param string $folder Specifies a path to the file.
	 * You may use parameters from URI and default parameters here.
	 * Example: Phpr::$router->add_rule("catalog/:product")->def('product', 'books')->folder('controllers/:product');
	 * @return Phpr\Router_Rule
	 */
	public function folder($folder)
	{
		$folder = str_replace("\\", "/", $folder);

		// Validate the folder path
		$path_params = Router::get_uri_params(explode("/", $folder));
		foreach ($path_params as $param => $index)
		{
			if ($param != Router::_url_controller && $param != Router::_url_action && !isset($this->_params[$param]))
				throw new SystemException('Invalid router rule configuration. The parameter "'.$param.'" specified in the Folder instruction is not found in the rule URI: ['.$this->uri.']');
		}

		$this->folder = $folder;
		return $this;
	}

	/**
	 * @deprecated
	 */

	public function addRule($uri) { return $this->add_rule($uri); }
	public function def($param, $value) { return $this->set_default($param, $value); }

}