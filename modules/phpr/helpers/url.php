<?php namespace Phpr;

use Phpr;

/**
 * PHPR URL helper
 *
 * This class contains functions that may be useful for working with URLs.
 */
class Url
{
	/**
	 * Returns an URL of a specified resource relative to the application domain root
	 */
	public static function root_url($resource = '/', $add_host_name_and_protocol = false, $protocol = null)
	{
		if (substr($resource, 0, 1) == '/')
			$resource = substr($resource, 1);

		$result = Phpr::$request->get_subdirectory().$resource;
		if ($add_host_name_and_protocol)
			$result = Phpr::$request->get_root_url($protocol).$result;
			
		return $result;
	}
	
	/**
	 * Returns the URL of the website, as specified in the configuration WEBSITE_URL parameter.
	 * @param string $resource Optional path to a resource. 
	 * Use this parameter to obtain the absolute URL of a resource.
	 * Example: Phpr_Url::site_url('images/button.gif') will return http://www.your-company.com/images/button.gif
	 * @param boolean $suppress_protocol Indicates whether the protocol name (http, https) must be suppressed.
	 * @return string
	 */
	public static function site_url($resource = null, $suppress_protocol = false)
	{
		$url = Phpr::$config->get('WEBSITE_URL', null);

		if ($suppress_protocol)
		{
			$url = str_replace('https://', '', $url);
			$url = str_replace('http://', '', $url);
		}

		if ($resource === null || !strlen($resource))
			return $url;

		if ($url !== null)
		{
			if ($resource{0} == '/')
				$resource = substr($resource, 1);

			return $url.'/'.$resource;
		}

		return $resource;
	}
	
	public static function get_params($url) 
	{
		if(strpos($url, '/') === 0)
			$url = substr($url, 1);
		
		$segments = explode('/', $url);
		$params = array();
		
		foreach ($segments as $segment) 
		{
			if(strlen($segment))
				$params[] = $segment;
		}
		
		return $params;
	}
}