<?php

/**
 * PHPR Router Class
 *
 * Router maps an URI string to the PHPR controllers and actions.
 */
class Phpr_Router
{
	private $_rules = array();
	private $_action_index = false;
	private $_segments = array();

	/**
	 * @var array
	 * A list of URI parameters names a and values. 
	 * The URI "archive/:year/:month/:day" will produce 3 parameters: year, month and day.
	 */
	public $parameters = array();

	/**
	 * @var string
	 * Contains a current Controller name. This variable is set during the Rout method call.
	 */
	public $controller = null;

	/**
	 * @var string
	 * Contains a current Action name. This variable is set during the Rout method call.
	 */
	public $action = null;

	const _url_controller = 'controller';
	const _url_action = 'action';
	const _url_module = 'module';

	/**
	 * Parses an URI and finds the controller class name, action and parameters.
	 * @param string $uri Specifies the URI to parse.
	 * @param string &$controller The controller name
	 * @param string &$action The controller action name
	 * @param array &$parameters A list of the action parameters
	 * @param string &$folder A path to the controller folder
	 */
	public function route($uri, &$controller, &$action, &$parameters, &$folder)
	{
		$controller = null;
		$action = null;
		$parameters = array();

		if ($uri{0} == '/') 
			$uri = substr($uri, 1);

		$this->_segments = $segments = $this->segment_uri($uri);
		$segment_count = count($segments);

		foreach ($this->_rules as $rule)
		{
			if (strlen($rule->uri))
				$rule_segments = explode("/", $rule->uri);
			else
				$rule_segments = array();

			try
			{
				$rule_segment_count = count($rule_segments);
				$rule_params = $this->get_uri_params($rule_segments);

				// Check whether the number of URI segments matches
				//
				$minSegmentNum = $rule_segment_count - count($rule->defaults);

				if (!($segment_count >= $minSegmentNum && $segment_count <= $rule_segment_count))
					continue;

				// Check whether the static segments matches
				//
				foreach ($rule_segments as $index => $rule_segment)
				{
					if (!$this->value_is_param($rule_segment))
						if (!isset($segments[$index]) || $segments[$index] != $rule_segment)
							continue 2;
				}

				// Validate checks
				//
				foreach ($rule->checks as $param => $pattern)
				{
					$param_index = $rule_params[$param];

					// Do not check default parameter values
					//
					if (!isset($segments[$param_index]))
						continue;

					// Match the parameter value
					//
					if (!preg_match($pattern, $segments[$param_index]))
						continue 2;
				}
				
				$this->_action_index = false;

				// Evaluate the controller parameters
				//
				foreach ($rule_params as $param_name => $param_index)
				{
					if ($this->_action_index === false && $param_name == self::_url_action)
						$this->_action_index = $param_index;

					if ($param_name == self::_url_controller || $param_name == self::_url_action)
						continue;

					$value = $this->evaluate_parameter_value($param_name, $param_index, $segments, $rule->defaults);

					if ($param_name != self::_url_module)
						$parameters[] = $value;

					$this->parameters[$param_name] = $value;
				}

				// Evaluate the controller and action values
				//
				$controller = $this->evaluate_target_value(self::_url_controller, $rule_params, $rule, $segments);
				$action = $this->evaluate_target_value(self::_url_action, $rule_params, $rule, $segments);
				if (!strlen($action))
					$action = 'index';

				$this->controller = $controller;
				$this->action = $action;

				// Evaluate the controller path
				//
				$folder = $rule->folder;

				if ($rule->folder !== null)
				{
					$folderParams = Phpr_Router::get_uri_params(explode("/", $rule->folder));
					foreach ($folderParams as $param_name => $param_index)
					{
						if ($param_name == self::_url_controller)
							$param_value = $controller;
						elseif ($param_name == self::_url_action)
							$param_value = $action;
						else
							$param_value = $this->parameters[$param_name];

						$folder = strtolower(str_replace(':'.$param_name, $param_value, $folder));
					}
				}

				break;
			}
			catch (Exception $ex)
			{
				throw new Phpr_SystemException("Error routing rule [".$rule->uri."]: ".$ex->getMessage());
			}
		}
	}

	/**
	 * This function takes an URI and returns its segments as array.
	 * @param URI Specifies the URI to process.
	 * @return array
	 */
	protected function segment_uri($uri)
	{
		$result = array();

		foreach (explode("/", preg_replace("|/*(.+?)/*$|", "\\1", $uri)) as $segment)
		{
			$segment = trim($segment);
			if ($segment != '')
				$result[] = $segment;
		}

		return $result;
	}

	/**
	 * @ignore
	 * Returns a list of parameters in the URI. Parameters are prefixed with the colon character.
	 * @param array $segments A list of URI segments
	 * @return array
	 */
	public static function get_uri_params($segments)
	{
		$result = array();

		foreach ($segments as $index => $val)
		{
			if (self::value_is_param($val))
				$result[substr($val, 1)] = $index;
		}

		return $result;
	}

	/**
	 * Returns URL of the current controller
	 * @return string Controller URL
	 */
	public function get_controller_root_url()
	{
		if ($this->_action_index === false)
			return null;

		$result = array();
		foreach ($this->_segments as $index => $value)
		{
			if ($index < $this->_action_index)
				$result[] = $value;
		}
		
		return implode('/', $result);
	}

	/**
	 * @ignore
	 * Determines whether value is parameter.
	 * @param string $segment Specifies the segment name to check.
	 * @return boolean
	 */
	public static function value_is_param($segment)
	{
		return (strlen($segment) && substr($segment, 0, 1) == ':');
	}

	/**
	 * Returns a name of the controller or action.
	 * @param string $TargetType Specifies a type of the target - controller or action.
	 * @param array &$rule_params List of the rule parameters.
	 * @param Phpr_Router_Rule &$rule Specifies the rule.
	 * @param array &$segments A list of the URI segments.
	 * @return string
	 */
	protected function evaluate_target_value($target_name, &$rule_params, &$rule, &$segments)
	{
		$field_name = strtolower($target_name);

		// Check whether the target value is specified explicitly in the rule target settings.
		//
		if (!isset($rule_params[$target_name]))
		{
			if (strlen($rule->$field_name))
			{
				$target_value = $rule->$field_name;

				if ($this->value_is_param($target_value))
				{
					$target_value = substr($target_value, 1);
					return strtolower($this->evaluate_parameter_value($target_value, $rule_params[$target_value], $segments, $rule->defaults));
				} 
				else
					return strtolower($target_value);
			}
		} 
		else 
		{
			// Extract the target value from the URI or try to find a default value
			//
			if (isset($segments[$rule_params[$target_name]]))
			{
				return strtolower($this->evaluate_converted_value($target_name, strtolower($segments[$rule_params[$target_name]]), $segments, $rule_params, $rule->defaults, $rule->converts));
			}
			else
			{
				$value = $this->evaluate_parameter_value($target_name, $target_name, $segments, $rule->defaults);
				return strtolower($this->evaluate_converted_value($target_name, strtolower($value), $segments, $rule_params, $rule->defaults, $rule->converts));
			}
		}
	}

	/**
	 * Returns a specified or default value of the parameter.
	 * @param string $param_name Specifies a name of the parameter.
	 * @index int $index Specifies the index of the parameter.
	 * @param array &$segments A list of the URI segments.
	 * @param array &$defaults Specifies the rule parameters defaults.
	 * @return string
	 */
	protected function evaluate_parameter_value($param_name, $index, &$segments, &$defaults)
	{
		if (isset($segments[$index]))
			return $segments[$index];

		if (isset($defaults[$param_name]))
			return $defaults[$param_name];

		return null;
	}
	
	protected function evaluate_converted_value($param_name, $param_value, &$segments, &$rule_params, &$defaults, &$converts)
	{
		if (isset($converts[$param_name]))
		{
			$convert_rule = $converts[$param_name];

			foreach ($rule_params as $name => $index)
			{
				if (isset($segments[$index]))
					$value = $segments[$index];
				else
					$value = $this->evaluate_parameter_value($name, null, $segments, $defaults);

				$convert_rule[1] = str_replace(":".$name, $value, $convert_rule[1]);
			}
			return preg_replace($convert_rule[0], $convert_rule[1], $param_value);
		}
		
		return $param_value;
	}

	/**
	 * Adds a routing rule.
	 * Use this method to define custom URI mappings to your application controllers.
	 * After adding a rule use the Phpr_Router_Rule class methods to configure the rule. For example: AddRule("archive/:year")->controller("blog")->action("Archive")->def("year", 2006).
	 * @return Phpr_Router_Rule
	 */
	public function add_rule($uri)
	{
		return $this->_rules[] = new Phpr_Router_Rule($uri);
	}

	/**
	 * Returns a URI parameter by its name.
	 * @param string $name Specifies the parameter name
	 * @param string $default Default parameter value
	 * @return string
	 */
	public function param($name, $default = null)
	{
		return isset($this->parameters[$name]) 
			? $this->parameters[$name] 
			: $default;
	}

	/*
	 * Returns a requested URI
	 */
	public function get_uri()
	{
		$result = $this->controller.'/'.$this->action;

		foreach ($this->parameters as $param_value)
		{
			if (strlen($param_value))
				$result .= '/'.$param_value;
			else
				break;
		}

		return $result;
	}

	/**
	 * @deprecated
	 */ 

	public function addRule($uri) { return $this->add_rule($uri); }	
}
