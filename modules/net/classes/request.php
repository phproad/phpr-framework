<?php namespace Net;

/**
 * Class for handling creating outgoing socket requests
 * @package PHPR
 */
class Request 
{
	protected $options;

	/**
	 * Constructor
	 * @param string $url URL to open
	 * @param array $params Parameters
	 *  - set_default: optional (default true). Sets default options as in {@link set_defaults}
	 */
	public function __construct($url = null, $params = array('set_default' => true)) 
	{
		if (isset($params['set_default']) && $params['set_default'])
			$this->set_default();
		
		if ($url)
			$this->set_url($url);
	}
	
	/**
	 * Static constructor
	 * @param string $url URL to open
	 * @param array $params Parameters
	 * @return object Net_Request
	 */
	public static function create($url = null, $params = array('set_default' => true)) 
	{
		return new self($url, $params);
	}
	
	/**
	 * Opens a new socket and sends the request through the network service.
	 * @return object Net_Response
	 * @example 1
	 * Create an HTTP request for http://google.com/ and return a Net_Response object `$r1`:
	 * $r1 = Net_Request::create('http://google.com/')->send();
	 */
	public function send() 
	{
		return Service::create()->run($this);
	}

	/**
	 * Applies default CURL options
	 *  - CURLOPT_RETURNTRANSFER: true
	 *  - CURLOPT_FOLLOWLOCATION: true
	 *  - CURLOPT_HEADER: false
	 *  - CURLOPT_USERAGENT: Mozilla/5.0
	 *  - CURLOPT_CONNECTTIMEOUT: 15
	 *  - CURLOPT_TIMEOUT: 15
	 *  - CURLOPT_CUSTOMREQUEST: GET
	 *  - CURLOPT_MAXREDIRS: 4
	 */
	public function set_default() 
	{
		$this->options[CURLOPT_RETURNTRANSFER] = true;
		$this->options[CURLOPT_FOLLOWLOCATION] = true;
		$this->options[CURLOPT_HEADER] = false;
		$this->options[CURLOPT_USERAGENT] = "Mozilla/5.0 (Windows; U; Windows NT 5.2; en-US; rv:1.9.1.7) Gecko/20091221 Firefox/3.5.7"; // use FF user agent by default
		$this->options[CURLOPT_CONNECTTIMEOUT] = 15;
		$this->options[CURLOPT_TIMEOUT] = 15;
		$this->options[CURLOPT_CUSTOMREQUEST] = 'GET';
		$this->options[CURLOPT_MAXREDIRS] = 4;
		return $this;
	}

	/**
	 * Sets the default timeout interval
	 * @param int $timeout Interval in seconds
	 */
	public function set_timeout($timeout) 
	{
		$this->options[CURLOPT_CONNECTTIMEOUT] = $timeout;
		$this->options[CURLOPT_TIMEOUT] = $timeout;
		return $this;
	}

	/**
	 * Sets multiple request options
	 * @param array $options CURL options
	 */
	public function set_options($options) 
	{
		foreach ($options as $key => $value)
		{
			$this->options[$key] = $value;
		}
		return $this;
	}

	/**
	 * Set a single request option
	 * @param string $key CURL option
	 * @param string $value Value to set
	 * @return type
	 */
	public function set_option($key, $value) 
	{
		$this->options[$key] = $value;
		return $this;
	}

	/**
	 * Returns an exisiting option's current value
	 * @param string $key CURL option
	 * @return string Current value
	 */
	public function get_option($key) 
	{
		if (!isset($this->options[$key]))
			return null;

		return $this->options[$key];
	}

	/**
	 * Returns an array of all option values
	 * @return array
	 */
	public function get_options() 
	{
		return $this->options;
	}

	/**
	 * Sets the outgoing address to request
	 * @param string $url URL
	 */
	public function set_url($url) 
	{
		$this->options[CURLOPT_URL] = $url;

		if (strpos($url, 'https') === 0)
			$this->options[CURLOPT_SSL_VERIFYPEER] = false;

		return $this;
	}

	/**
	 * Sets referral address
	 * @param string $url URL
	 */
	public function set_referer($url) 
	{
		$this->options[CURLOPT_REFERER] = $url;
		return $this;
	}

	/**
	 * Set this to disallow the request from being redirected.
	 * This option is required if you are using basedir restrictions in PHP.
	 */
	public function disable_redirects() 
	{
		$this->options[CURLOPT_FOLLOWLOCATION] = false;
		return $this;
	}

	/**
	 * Send request with authentication enabled
	 * @param string $username Login name
	 * @param string $password Password
	 * @param string $authentication_method CURL type (default CURLAUTH_ANY)
	 */
	public function set_authentication($username, $password, $authentication_method = CURLAUTH_ANY) 
	{
		$this->options[CURLOPT_USERPWD] = $username . ':' . $password;
		$this->options[CURLOPT_HTTPAUTH] = $authentication_method;
		return $this;
	}

	/**
	 * Send request with cookies
	 * @param string $file_path Absolute path to JAR cookie file
	 */
	public function set_cookies($file_path) 
	{
		$this->options[CURLOPT_COOKIEJAR] = $file_path;
		$this->options[CURLOPT_COOKIEFILE] = $file_path;
		return $this;
	}

	/**
	 * Use a proxy for this request
	 * @param string $type required. Which proxy protocol to use
	 * - http: Standard HTTP proxy
	 * - socks4: Socks Layer 4
	 * - socks5: Socks Layer 5
	 * @param string $host Hostname
	 * @param int $port Port
	 * @param string $username optional. Username
	 * @param string $password optional. Password
	 */
	public function set_proxy($type, $host, $port, $username = null, $password = null) 
	{
		if ($type === 'http')
			$this->options[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
		else if ($type === 'socks4')
			$this->options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
		else if ($type === 'socks5')
			$this->options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;

		$this->options[CURLOPT_PROXY] = $host . ':' . $port;
	
		if ($username && $password)
			$this->options[CURLOPT_PROXYUSERPWD] = $username . ':' . $password;

		return $this;
	}

	/**
	 * Send post data with request
	 * @param array $data 
	 */
	public function set_post($data) 
	{
		$this->options[CURLOPT_CUSTOMREQUEST] = 'POST';
		$this->options[CURLOPT_POST] = true;
		$this->options[CURLOPT_POSTFIELDS] = $data;
		return $this;
	}

	/**
	 * Send get data with request
	 * Set the parameters with the URL.
	 */
	public function set_get() 
	{
		$this->options[CURLOPT_CUSTOMREQUEST] = 'GET';
		$this->options[CURLOPT_POST] = false;
		$this->options[CURLOPT_POSTFIELDS] = '';
		return $this;
	}

	/**
	 * Send put data with request
	 * @param array $data 
	 */
	public function set_put($data) 
	{
		$f1 = tmpfile();
		fwrite($f1, $data);
		rewind($f1);

		$this->options[CURLOPT_CUSTOMREQUEST] = 'PUT';
		$this->options[CURLOPT_PUT] = true;
		$this->options[CURLOPT_BINARYTRANSFER] = true;
		$this->options[CURLOPT_INFILE] = $f1;
		$this->options[CURLOPT_INFILESIZE] = strlen($data);
		$this->options[CURLOPT_UPLOAD] = true;
		return $this;
	}

	/**
	 * Send header data with request
	 * Use {@link set_header} to set parameters.
	 */
	public function set_head() 
	{
		$this->options[CURLOPT_CUSTOMREQUEST] = 'HEAD';
		$this->options[CURLOPT_POST] = false;
		$this->options[CURLOPT_POSTFIELDS] = '';
		$this->options[CURLOPT_NOBODY] = true;
		return $this;
	}
	
	/**
	 * Sets header flag to receive headers in Net_Response
	 * @param bool $value 
	 */
	
	public function enable_headers()
	{ 
		$this->options[CURLOPT_HEADER] = true;
		return $this;
	}
	
	public function disable_headers()
	{ 
		$this->options[CURLOPT_HEADER] = false;
		return $this;
	}

	/**
	 * Sets header data with request
	 * @param array $data 
	 */

	public function set_headers($data)
	{ 
		$this->headers = $data; 
		$this->options[CURLOPT_HTTPHEADER] = $this->headers;
		return $this;
	}
	
	public function set_header($key, $value)
	{ 
		$this->headers[$key] = $value; 
		$this->options[CURLOPT_HTTPHEADER] = $this->headers;
		return $this;
	}

}
