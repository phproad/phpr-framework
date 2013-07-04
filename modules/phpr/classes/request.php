<?php namespace Phpr;

use Phpr;
use Phpr\String;

class Request
{
	public $get_fields = null;

	protected $_remote_event_indicator = 'HTTP_PHPR_REMOTE_EVENT';
	protected $_postback_indicator = 'HTTP_PHPR_POSTBACK';

	private $_ip = null;
	private $_language = null;
	private $_subdirectory = null;
	private $_cached_uri = null;
	private $_cached_root_url = null;
	private $_cached_event_params = null;

	/**
	 * Creates a new Phpr_Request instance.
	 * Do not create the Request objects directly. Use the Phpr::$request object instead.
	 * @see Phpr
	 */
	public function __construct()
	{
		$this->preprocess_globals();
	}

	/**
	 * Returns if the HTTP request was an AJAX request.
	 * @return boolean
	 */
	public function is_ajax()
	{
		return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
	}

	/**
	 * Returns a value of the POST variable. 
	 * If the variable with specified name does not exist, returns null or a value specifies in the $default parameter.
	 * @param string $name Specifies a variable name.
	 * @param mixed $default Specifies a default value.
	 * @return mixed
	 */
	public function post_field($name = null, $default = null)
	{
		if ($name === null)
			return $_POST;
		
		if (!array_key_exists($name, $_POST))
			return $default;

		return $_POST[$name];
	}

	/**
	 * Finds an array in the POST variable by its name and then finds and returns the array element by its key.
	 * If the array or the element key do not exist, returns null or a value specifies in the $default parameter.
	 * @param string $array_name Specifies the array name.
	 * @param string $name Specifies the array element key name.
	 * @param mixed $default Specifies a default value.
	 * @return mixed
	 */
	public function post_array($array_name, $name, $default = null)
	{
		if (!array_key_exists($array_name, $_POST))
			return $default;

		if (!array_key_exists($name, $_POST[$array_name]))
			return $default;

		return $_POST[$array_name][$name];
	}
	
	/**
	 * Returns a GET parameter value
	 */
	public function get_field($name, $default = false)
	{
		return array_key_exists($name, $this->get_fields) ? $this->get_fields[$name] : $default;
	}

	public function get_root_url($protocol = null)
	{
		if (!isset($_SERVER['SERVER_NAME']))
			return null;
			
		$protocol_specified = strlen($protocol);
		if (!$protocol_specified && $this->_cached_root_url !== null)
			return $this->_cached_root_url;

		if ($protocol === null)
			$protocol = $this->get_protocol();

		$port = $this->get_port();

		$current_protocol = $this->get_protocol();
		if ($protocol_specified && strtolower($protocol) != $current_protocol)
			$port = '';

		$https = strtolower($protocol) == 'https';

		if (!$https && $port == 80)
			$port = '';

		if ($https && $port == 443)
			$port = '';

		$port = !strlen($port) ? "" : ":".$port;

		$result = $protocol."://".$_SERVER['SERVER_NAME'].$port;
		
		if (!$protocol_specified)
				$this->_cached_root_url = $result;
		
		return $result;
	}

	public function get_current_url()
	{
		$protocol = $this->get_protocol();
		$port = ($_SERVER["SERVER_PORT"] == "80") 
			? ""
			: (":".$_SERVER["SERVER_PORT"]);
			
		return $protocol."://".$_SERVER['SERVER_NAME'].$port.$_SERVER['REQUEST_URI'];
	}

	/**
	 * Returns the URL of the current request
	 */
	public function get_request_uri() 
	{
		$provider = Phpr::$config->get("URI_PROVIDER", null);

		if ($provider !== null)
			return getenv($provider);
		else
		{
			// Pick the provider from the server variables
			$providers = array('REQUEST_URI', 'PATH_INFO', 'ORIG_PATH_INFO');
			foreach ($providers as $provider)
			{
				$val = getenv($provider);
				
				if ($val != '')
					return $val;
			}
		}
		
		return null;
	}

	/**
	 * Returns the URI of the current request relative to the application root directory.
	 * @param bool $routing Determines whether the Uri is requested for the routing process
	 * @return string
	 */
	public function get_current_uri($routing = false)
	{
		if (!$routing && $this->_cached_uri !== null)
			return $this->_cached_uri;

		$request_param_name = Phpr::$config->get('REQUEST_PARAM_NAME', 'q');
		$bootstrap_path_base = pathinfo(PATH_BOOT, PATHINFO_BASENAME);
		$uri = $this->get_field($request_param_name);

		// Postprocess the URI
		if (strlen($uri))
		{
			if (($pos = strpos($uri, '?')) !== false)
				$uri = substr($uri, 0, $pos);

			if ($uri[0] == '/') 
				$uri = substr($uri, 1);

			$length = strlen($bootstrap_path_base);
			if (substr($uri, 0, $length) == $bootstrap_path_base)
			{
				$uri = substr($uri, $length);
				if ($uri[0] == '/') 
					$uri = substr($uri, 1);
			}

			$length = strlen($uri);
			if ($length > 0 && $uri[$length-1] == '/') $uri = substr($uri, 0, $length-1);
		}

		$uri = "/".$uri;

		if (!$routing)
			$this->_cached_uri = $uri;

		return $uri;
	}

	public function get_hostname()
	{
		return isset($_SERVER["SERVER_NAME"]) ? $_SERVER["SERVER_NAME"] : null;
	}

	public function get_protocol()
	{
		if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https')
			$s = 's';
		else
			$s = (empty($_SERVER["HTTPS"]) || ($_SERVER["HTTPS"] === 'off')) ? '' : 's';

		return $this->strleft(strtolower($_SERVER["SERVER_PROTOCOL"]), "/").$s;
	}

	public function get_port()
	{
		if (Phpr::$config->get('STANDARD_HTTP_PORTS'))
			return null;
			
		if (array_key_exists('HTTP_HOST', $_SERVER))
		{
			$matches = array();
			if (preg_match('/:([0-9]+)/', $_SERVER['HTTP_HOST'], $matches))
				return $matches[1];
		}

		return isset($_SERVER["SERVER_PORT"]) ? $_SERVER["SERVER_PORT"] : null;
	}
	
	/**
	 * Returns a value of the COOKIE variable. 
	 * If the variable with specified name does not exist, returns null;
	 * @param string $name Specifies a variable name.
	 * @return mixed
	 */
	public function cookie($name)
	{
		if (!isset($_COOKIE[$name]))
			return null;

		return $_COOKIE[$name];
	}

	/**
	 * Determines whether the remote event handling requested.
	 * @return boolean.
	 */
	public function is_remote_event()
	{
		return isset($_SERVER[$this->_remote_event_indicator]);
	}

	/**
	 * Determines whether the page is loaded in response to a client postback.
	 * @return boolean.
	 */
	public function is_post_back()
	{
		return isset($_SERVER[$this->_postback_indicator]);
	}
	
	/**
	 * Returns true if the user is in the backend admin panel.
	 * @return boolean
	 */
	public function is_backend() 
	{
		$request_param_name = Phpr::$config->get('REQUEST_PARAM_NAME', 'q');
		$backend_url = '/' . String::normalize_uri(Phpr::$config->get('ADMIN_URL', 'admin'));
		$current_url = '/' . String::normalize_uri(isset($_REQUEST[$request_param_name]) ? $_REQUEST[$request_param_name] : '');

		return (stristr($current_url, $backend_url) !== false);
	}

	/**
	 * Returns the visitor IP address.
	 * @return string
	 */
	public function get_user_ip()
	{
		if ($this->_ip !== null)
			return $this->_ip;

		$ip_keys = Phpr::$config->get('REMOTE_IP_HEADERS', array('HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'));
		foreach ($ip_keys as $ip_key)
		{
			if (isset($_SERVER[$ip_key]) && strlen($_SERVER[$ip_key]))
			{
				$this->_ip = $_SERVER[$ip_key];
				break;
			}
		}

		if (strlen(strstr($this->_ip, ',')))
		{
			$ips = explode(',', $this->_ip);
			$this->_ip = trim(reset($ips));
		}
			
		if ($this->_ip == '::1')
			$this->_ip = '127.0.0.1';

		return $this->_ip;
	}
	
	/**
	 * Returns the visitor language preferences.
	 * @return string
	 */
	public function get_user_language()
	{
		if ($this->_language !== null)
			return $this->_language;

		if (!array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER))
			return null;

		$languages = explode(",", $_SERVER['HTTP_ACCEPT_LANGUAGE']);
		$language = $languages[0];

		if (($pos = strpos($language, ";")) !== false)
			$language = substr($language, 0, $pos);

		return $this->_language = str_replace("-", "_", $language);
	}
	
	/**
	 * Returns a subdirectory path, starting from the server 
	 * root directory to application directory root.
	 * Example. Application installed to the subdirectory /application of a domain
	 * Then the method will return the '/subdirectory/' string
	 */
	public function get_subdirectory()
	{
		if ($this->_subdirectory !== null)
			return $this->_subdirectory;
			
		$request_param_name = Phpr::$config->get('REQUEST_PARAM_NAME', 'q');
		
		$uri = $this->get_request_uri(); 
		$path = $this->get_field($request_param_name);

		$uri = urldecode($uri);
		$uri = preg_replace('|/\?(.*)$|', '/', $uri);

		$pos = strpos($uri, '?');
		if ($pos !== false)
			$uri = substr($uri, 0, $pos);

		$pos = strpos($uri, '/&');
		if ($pos !== false)
			$uri = substr($uri, 0, $pos+1);
		
		$path = mb_strtolower($path);
		$uri = mb_strtolower($uri);

		$pos = mb_strrpos($uri, $path);
		$subdir = '/';
		if ($pos !== false && $pos == mb_strlen($uri)-mb_strlen($path))
			$subdir = mb_substr($uri, 0, $pos).'/';
			
		if (!strlen($subdir))
			$subdir = '/';
			
		return $this->_subdirectory = $subdir;
	}

	public function get_value_array($name, $default = array())
	{
		if (array_key_exists($name, $this->get_fields))
			return $this->get_fields[$name];

		if (!isset($_SERVER['QUERY_STRING']))
			return $default;

		$vars = explode('&', $_SERVER['QUERY_STRING']);

		$result = array();
		foreach ($vars as $var_data)
		{
			$var_data = urldecode($var_data);

			$var_parts = explode('=', $var_data);
			if (count($var_parts) == 2)
			{
				if ($var_parts[0] == $name.'[]' || $var_parts[0] == $name.'%5B%5D')
					$result[] = $var_parts[1];
			}
		}
		
		if (!count($result))
			return $default;
			
		return $result;
	}
	
	public static function array_strip_slashes(&$value)
	{
		$value = stripslashes($value); 
	}

	/**
	 * Cleans the unput array keys and values.
	 * @param array &$array Specifies an array to clean.
	 */
	private function cleanup_array(&$array)
	{
		if (!is_array($array))
			return;

		foreach ($array as $var_name => &$var_value)
		{
			if (is_array($var_value))
				$this->cleanup_array($var_value);
			else
				$array[$this->cleanup_array_key($var_name)] = $this->cleanup_array_value($var_value);
		}
	}

	/**
	 * Check the input array key for invalid characters and adds slashes.
	 * @param string $key Specifies the key to process.
	 * @return string
	 */
	private function cleanup_array_key($key)
	{
		if (!preg_match("/^[0-9a-z:_\/-\{\}|]+$/i", $key))
			return null;

		return get_magic_quotes_gpc() ? $key : addslashes($key);
	}

	/**
	 * Fixes the new line characters in the specified value.
	 * @param mixed $value Specifies a value to process.
	 * return mixed
	 */
	private function cleanup_array_value($value)
	{
		if (!is_array($value))
			return preg_replace("/\015\012|\015|\012/", "\n", $value);

		$result = array();
		foreach ($value as $var_name => $var_value)
		{
			$result[$var_name] = $this->cleanup_array_value($var_value);
		}

		return $result;
	}

	/**
	 * @ignore
	 * Returns a list of the event parameters, or a specified parameter value.
	 * This method is used by the PHPR internally.
	 *
	 * @param string $name Optional name of parameter to return.
	 * @return mixed
	 */
	public function get_event_params($name = null)
	{
		if ($this->_cached_event_params == null)
		{
			$this->_cached_event_params = array();

			if (isset($_POST['phpr_handler_params']))
			{
				$pairs = explode('&', $_POST['phpr_handler_params']);
				foreach ($pairs as $pair)
				{
					$parts = explode("=", urldecode($pair));
					$this->_cached_event_params[$parts[0]] = $parts[1];
				}
			}
		}

		if ($name === null)
			return $this->_cached_event_params;

		if (isset($this->_cached_event_params[$name]))
			return $this->_cached_event_params[$name];

		return null;
	}

	/**
	 * Returns Page referer.
	 * If user referer is not availale returns defined default value;
	 * @return string.
	 */
	public function get_referer($default = null)
	{
		return (isset($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : $default;
	}
	
	/**
	 * Returns Requested Method value.
	 * If user request method is not availale returns null;
	 * @return string.
	 */
	public function get_request_method()
	{
		return (isset($_SERVER['REQUEST_METHOD'])) ? strtoupper($_SERVER['REQUEST_METHOD']) : null;
	}
	
	/**
	 * Returns a name of the User Agent.
	 * If user agent data is not availale returns null;
	 * @return mixed.
	 */
	public function get_user_agent()
	{
		return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
	}

	/**
	 * Returns SSL Session Id value.
	 * @return string.
	 */
	public function get_ssl_session_id()
	{
		return (isset($_SERVER["SSL_SESSION_ID"])) ? $_SERVER["SSL_SESSION_ID"] : null;
	}

	/**
	 * Cleans the _POST and _COOKIE data and unsets the _GET data.
	 * Replaces the new line charaters with \n.
	 */
	private function preprocess_globals()
	{
		// Unset the global variables
		$this->get_fields = $_GET;

		// Remove query param
		unset($_GET['q']);
		
		// Remove magic quotes
		if (ini_get('magic_quotes_gpc') || Phpr::$config->get('REMOVE_GPC_SLASHES'))
		{
			array_walk_recursive($_GET, array('Phpr_Request', 'array_strip_slashes')); 
			array_walk_recursive($_POST, array('Phpr_Request', 'array_strip_slashes')); 
			array_walk_recursive($_COOKIE, array('Phpr_Request', 'array_strip_slashes'));
		}

		// Clean the POST and COOKIE data
		$this->cleanup_array($_POST);
		$this->cleanup_array($_COOKIE);
	}
	
	/**
	 * Unsets the global variables created with from the POST, GET or COOKIE data.
	 * @param array &$array The array containing a list of variables to unset.
	 */
	private function unset_globals(&$array)
	{
		if (!is_array($array))
			unset($$array);
		else
			foreach ($array as $var_name => $var_value)
				unset($$var_name);
	}
	
	private function strleft($str1, $str2) 
	{
		return substr($str1, 0, strpos($str1, $str2));
	}

	/**
	 * @deprecated
	 */ 

	public function getSubdirectory() { Phpr::$deprecate->set_function('getSubdirectory', 'get_subdirectory'); return $this->get_subdirectory(); }
	public function getRootUrl() { Phpr::$deprecate->set_function('getRootUrl', 'get_root_url'); return $this->get_root_url(); }
	public function post() { Phpr::$deprecate->set_function('post', 'post_field'); return $this->post_field(); }

}
