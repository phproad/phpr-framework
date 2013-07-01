<?php namespace Net;

/**
 * Class for receiving a Net_Request response
 * @package PHPR
 */
class Response 
{
	public $data;
	public $request;
	public $info;
	public $status_code;
	public $headers;

	public function __construct() 
	{
		$this->data = '';
		$this->info = array();
		$this->status_code = 0;
		$this->request = null;
		$this->headers = array();
	}
}