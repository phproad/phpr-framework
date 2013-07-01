<?php

class Net_Service 
{
	protected $settings;
	protected $mc;
	protected $connections;
	protected $jobs;
	protected $last_request;
	protected $last_response;

	public function __construct($options = array('active' => true, 'max_connections' => 30)) 
	{
		$this->settings = $options;
		$this->connections = array();
		$this->jobs = array();

		$this->mc = curl_multi_init();
	}
	
	public static function create($options = array('active' => true, 'max_connections' => 30)) 
	{
		return new self($options);
	}

	private function get_data_and_headers_from_response($response_data)
	{
		$headers = array();

		$blocks = explode("\r\n\r\n", $response_data);
		$total_blocks = count($blocks);

		if ($total_blocks > 1) {
			// Use the last header block, ignoring redirects and such
			$header_data = $blocks[$total_blocks-2]; 

			// The last block is the data
			$body_data = $blocks[$total_blocks-1]; 
		}
		else {
			// Exception handling
			$header_data = '';
			$body_data = $response_data;
		}

		foreach (explode("\r\n", $header_data) as $header_data_item) {
			$items = explode(": ", $header_data_item);

			// Except for the first line, not sure why this header doesn't have a key and value
			if (count($items) < 2)
				continue; 

			$key = $items[0];
			array_shift($items);

			$headers[$key] = implode(": ", $items);
		}

		return array($body_data, $headers);
	}

	public function run($request, $callback = null) 
	{
		$curl = curl_init();
		$request_options = $request->get_options();

		foreach ($request_options as $key => $value)
		{
			curl_setopt($curl, $key, $value);
		}

		// Attempt async
		if ($callback) 
		{
			$this->jobs[] = array('request' => $request, 'handle' => $curl, 'callback' => $callback);
		} 
		else // Or not
		{
			$response = new Net_Response();

			ob_start();
			$response_data = curl_exec($curl);
			ob_end_clean();

			if ($request_options[CURLOPT_HEADER])
				list($data, $headers) = $this->get_data_and_headers_from_response($response_data);
			else
				list($data, $headers) = array($response_data, null);

			$response->data = $data;
			$response->headers = $headers;
			$response->request = $request;
			$response->info = curl_getinfo($curl);
			$response->status_code = $response->info['http_code'];

			curl_close($curl);

			return $response;
		}

		$this->last_request = $request;
	}

	public function update() 
	{
		if (!$this->settings['active'])
			return;
		
		while (count($this->connections) < $this->settings['max_connections'] && count($this->jobs) > 0) 
		{
			$job = array_shift($this->jobs);

			$host = $job['request']->get_option(CURLOPT_URL);

			if (!$host)
				return $job['callback'](null);

			if (strpos($host, 'http') !== 0)
				$job['request']->set_option(CURLOPT_URL, 'http://' . $host);

			$host = parse_url($job['request']->get_option(CURLOPT_URL), PHP_URL_HOST);

			// Checks if the domain is bad and will block multicurl
			if (!$this->is_host_active($host)) 
			{
				if ($job['callback'] != null)
					if (phpversion() >= 5.3)
						$job['callback'](null);
					else
						call_user_func_array($job['callback'], array(null));

				continue;
			}

			$this->connections[$job['handle']] = array('request' => $job['request'], 'handle' => $job['handle'], 'callback' => $job['callback']);

			curl_multi_add_handle($this->mc, $job['handle']);
		}

		while (($status = curl_multi_exec($this->mc, $running)) == CURLM_CALL_MULTI_PERFORM)
		{
			continue;
		}

		if ($status != CURLM_OK)
			return;

		while ($item = curl_multi_info_read($this->mc)) 
		{
			// Don't hog CPU
			usleep(20000);
			
			$handle = $item['handle'];

			$connection = $this->connections[$handle];
		
			$info = curl_getinfo($handle);

			$response_data = curl_multi_getcontent($handle);

			curl_multi_remove_handle($this->mc, $handle);

			unset($this->connections[$handle]);

			list($data, $headers) = $this->get_data_and_headers_from_response($response_data);

			$response = new Net_Response();
			$response->request = $connection['request'];
			$response->headers = $headers;
			$response->data = $data;
			$response->info = $info;
			$response->status_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

			$this->last_response = $response;

			if ($connection['callback'] != null)
			{
				if (phpversion() >= 5.3)
					$connection['callback']($response);
				else
					call_user_func_array($connection['callback'], array($response));
			}
		}
	}

	public function is_host_active($host) 
	{
		if (!$host)
			return false;
			
		// If this isn't linux don't check it
		if (!stristr(PHP_OS, 'linux'))
			return true;

		// If this is an IP don't check it
		if (long2ip(ip2long($host)) == $host)
			return true;

		return true;
	}

	public function get_last_request() 
	{
		return $this->last_request;
	}

	public function get_last_response() 
	{
		return $this->last_response;
	}

	public function set_settings($settings) 
	{
		foreach ($settings as $name => $value)
			$this->settings[$name] = $value;
	}

	public function set_setting($name, $value) 
	{
		$this->settings[$name] = $value;
	}

	public function get() 
	{
		return $this->mc;
	}
}