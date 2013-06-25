<?php

/**
 * PHPR Trace Log Class
 *
 * Allows writing of traceable messages to trace log files and/or database
 *
 * To configure the trace log use the TRACE_LOG parameter in the application configuration file:
 * 
 *   $CONFIG["TRACE_LOG"]["BLOG"] = PATH_APP."/logs/blog.txt";
 *   $CONFIG["TRACE_LOG"]["DEBUG"] = PATH_APP."/logs/debug.txt";
 * 
 * The second-level key determines the listener name. Use the listener names to write tracing
 * messages to different files: 
 * 
 *   Phpr::$trace_log->write('My traceable message', 'BLOG');
 *   trace_log('My traceable message', 'BLOG');
 * 
 * You can instruct PHPR to write to the database only by setting the file path to null:
 * 
 *   $CONFIG["TRACE_LOG"]["BLOG"] = null;
 *
 */

class Phpr_Trace_Log
{
	private $listeners;

	public function __construct()
	{
		$this->load_config();
	}

	// Load config
	protected function load_config()
	{
		$this->listeners = array();

		foreach (Phpr::$config->get('TRACE_LOG', array()) as $listener_name => $file_path)
		{
			$this->add_listener($listener_name, $file_path);
		}
	}

	// Adds a listener type
	public function add_listener($listener_name, $file_path) 
	{
		if (!Phpr::$config->get('NO_TRACELOG_CHECK'))
		{
			if ($file_path != null)
			{
				// Check whether the file or directory is writable
				if (file_exists($file_path))
				{
					if (!is_writable($file_path))
					{
						$exception = new Phpr_SystemException( 'The trace log file is not writable: '.$file_path );
						$exception->hint_message = 'Please assign writing permissions on the trace log file for the Apache user.';
						throw $exception;
					}
				}
				else
				{
					$directory = dirname($file_path);
					if (!is_writable($directory))
					{
						$exception = new Phpr_SystemException( 'The trace log file directory is not writable: '.$directory );
						$exception->hint_message = 'Please assign writing permissions on the trace log directory for the Apache user.';
						throw $exception;
					}
				}
			}
		}

		$this->listeners[$listener_name] = $file_path;
	}

	// Looks up a listener and passes the message 
	public function write($message, $listener = null)
	{
		if (!count($this->listeners))
			return false;

		// Evaluate the listener name and ensure it exists
		if ($listener == null)
		{
			$keys = array_keys($this->listeners);
			$listener = $keys[0];
		} 
		else if (!isset($this->listeners[$listener]))
		{
			return false;
		}

		// Detect an object/array and beautify
		if (is_object($message) || is_array($message))
			$message = print_r($message, true);

		// Write the message
		return $this->write_to_log($message, $listener);
	}
	
	// Writes a message to the error log file and/or database
	protected function write_to_log($message, $listener)
	{
		if ($this->listeners[$listener] != null)
			return File_Log::write_line($this->listeners[$listener], $message);
		else
		{
			if (!class_exists('Phpr_Trace_Log_Record') && !Phpr::$class_loader->load('Phpr_Trace_Log_Record'))
				return;

			Phpr_Trace_Log_Record::add($listener, $message);
		}
	}

}