<?php

/**
 * PHPR Error Log Class
 *
 * Allows writing the error messages to the error log file.
 *
 * To enable the error logging set the ERROR_LOG config value to true: 
 * 
 *   $CONFIG['ERROR_LOG'] = true;
 *
 * By default the error log file is located in the logs directory (logs/errors.txt).
 * You can specify a different location by setting the ERROR_LOG_FILE config value:
 * 
 *   $CONFIG['ERROR_LOG_FILE'] = "/home/logs/private_errors.txt". 
 *
 */
class Phpr_Error_Log
{
	private $log_file_name;
	private $is_enabled;
	private $ignore_exceptions;
	
	public static $disable_db_logging = false;

	public function __construct()
	{
		$this->load_config();
	}

	// Load config
	protected function load_config()
	{
		$this->is_enabled = Phpr::$config != null && Phpr::$config->get("ERROR_LOG", false);

		if ($this->is_enabled)
		{
			$this->log_file_name = Phpr::$config->get("ERROR_LOG_FILE", PATH_APP."/logs/errors.txt");

			// Check whether file and folders are writable
			if (file_exists($this->log_file_name))
			{
				if (!is_writable($this->log_file_name))
				{
					$exception = new Phpr_SystemException('The error log file is not writable: '.$this->log_file_name);
					$exception->hint_message = 'Please assign writing permissions on the error log file for the Apache user.';
					throw $exception;
				}
			}
			else
			{
				$directory = dirname($this->log_file_name);
				if (!is_writable($directory))
				{
					$exception = new Phpr_SystemException('The error log file directory is not writable: '.$directory);
					$exception->hint_message = 'Please assign writing permissions on the error log directory for the Apache user.';
					throw $exception;
				}
			}
		}

		// Load the ignored exceptions list
		$this->ignore_exceptions = (Phpr::$config != null) 
			? Phpr::$config->get("ERROR_IGNORE", array()) 
			: array();
	}


	// Formats an exception for writing to the log file
	public function log_exception(Exception $exception)
	{
		if (!$this->is_enabled)
			return false;

		foreach ($this->ignore_exceptions as $ignored_exception_class)
		{
			if ($exception instanceof $ignored_exception_class)
				return false;
		}

		switch ($exception)
		{
			case ($exception instanceof Phpr_DeprecateException):
				$message = sprintf("%s: %s. ", 
					get_class($exception), 
					$exception->getMessage());

				if ($exception->code_file && $exception->code_line)
					$message .= sprintf("In %s, line %s",
						$exception->code_file,
						$exception->code_line);
				break;

			case ($exception instanceof Cms_ExecutionException):
				$message = sprintf("%s: %s. In %s, line %s", 
					get_class($exception), 
					$exception->getMessage(),
					$exception->location_desc,
					$exception->code_line);			
				break;

			default:
				$message = sprintf("%s: %s. In %s, line %s", 
					get_class($exception), 
					$exception->getMessage(),
					$exception->getFile(),
					$exception->getLine());			
				break;

		}
		
		$error = self::get_exception_details($exception);
		$log_to_db = !($exception instanceof Phpr_DatabaseException);
		
		$details = null;
		
		if (Phpr::$config->get('ENABLE_DB_ERROR_DETAILS', true))
			$details = self::encode_error_details($error);

		return $this->write_to_log($message, $log_to_db, $details);
	}
	
	// Writes a message to the error log file and/or database
	protected function write_to_log($message, $log_to_db = true, $details = null)
	{
		$record_id = null;
		
		if (!class_exists('File_Log') && !Phpr::$class_loader->load('File_Log'))
			echo $message;
		
		if ((Phpr::$config->get('LOG_TO_DB') || $this->log_file_name == null) && Db::$connection && !self::$disable_db_logging && $log_to_db)
		{
			if (!class_exists('Phpr_Trace_Log_Record') && !Phpr::$class_loader->load('Phpr_Trace_Log_Record'))
				return;
			
			$record_id = Phpr_Trace_Log_Record::add('ERROR', $message, $details)->id;
		}
		
		if (Phpr::$config->get('ENABLE_ERROR_STRING', true))
			$message .= ($details) ? ' Encoded details: ' . $details : '';
		
		return array('id' => $record_id, 'status' => File_Log::write_line($this->log_file_name, $message));
	}

	public static function get_exception_details($exception) 
	{
		$error = (object)array(
			'call_stack'     => array(),
			'class_name'     => get_class($exception),
			'log_id'         => isset($exception->log_id) ? $exception->log_id : '',
			'log_status'     => isset($exception->log_status) ? $exception->log_status : '',
			'message'        => ucfirst(nl2br(htmlentities($exception->getMessage()))),
			'hint'           => isset($exception->hint_message) && strlen($exception->hint_message) ? $exception->hint_message : null,
			'is_document'    => $exception instanceof Cms_ExecutionException,
			'document'       => $exception instanceof Cms_ExecutionException ? $exception->document_name() : File_Path::get_public_path($exception->getFile()),
			'document_type'  => $exception instanceof Cms_ExecutionException ? $exception->document_type() : 'PHP document',
			'line'           => $exception instanceof Cms_ExecutionException ? $exception->code_line : $exception->getLine(),
			'code_highlight' => (object)array(
				'brush' => $exception instanceof Cms_ExecutionException ? 'php' : 'php',
				'lines' => array()
			)
		);
		
		// Code highlight
		// 
		$code_lines = null;
		
		if ($exception instanceof Cms_ExecutionException)
		{
			$code_lines = explode(PHP_EOL, $exception->document_code());

			foreach ($code_lines as $i => $line)
				$code_lines[$i] .= PHP_EOL;

			$error_line = $exception->code_line-1;
		} 
		else
		{
			$file = $exception->getFile();
			if (file_exists($file) && is_readable($file))
			{
				$code_lines = @file($file);
				$error_line = $exception->getLine()-1;
			}
		}
		
		if ($code_lines)
		{
			$start_line = $error_line-6;
			if ($start_line < 0)
				$start_line = 0;
				
			$end_line = $start_line + 12;
			$line_num = count($code_lines);
			if ($end_line > $line_num-1)
				$end_line = $line_num-1;

			$code_lines = array_slice($code_lines, $start_line, $end_line-$start_line+1);
			
			$error->code_highlight->start_line = $start_line;
			$error->code_highlight->end_line = $end_line;
			$error->code_highlight->error_line = $error_line;
			
			foreach ($code_lines as $i => $line) 
			{
				$error->code_highlight->lines[$start_line+$i] = $line;
			}
		}
		
		// Stack trace
		// 
		if ($error->is_document) {
			$last_index = count($exception->call_stack) - 1;
			
			foreach ($exception->call_stack as $index=>$stack_item) {
				$error->call_stack[] = (object) array(
					'id'       => $last_index-$index+1,
					'document' => h($stack_item->name),
					'type'     => h($stack_item->type)
				);
			}
		}	
		else 
		{
			$trace_info = $exception->getTrace();
			$last_index = count($trace_info) - 1;
			
			foreach ($trace_info as $index => $event) {
				$function_name = (isset($event['class']) && strlen($event['class'])) 
					? $event['class'].$event['type'].$event['function'] 
					: $event['function'];

				if ($function_name == 'Phpr_SysErrorHandler' || $function_name == 'Phpr_SysExceptionHandler')
					continue;
				
				$file = isset($event['file']) ? File_Path::get_public_path($event['file']) : null;
				$line = isset($event['line']) ? $event['line'] : null;

				$args = null;
				if (isset($event['args']) && count($event['args']))
					$args = Phpr_Exception::format_trace_arguements($event['args'], false);
				
				$error->call_stack[] = (object) array(
					'id' => $last_index-$index+1,
					'function_name' => $function_name,
					'args' => $args ? $args : '',
					'document' => $file,
					'line' => $line
				);
			}	
		}
		
		return $error;
	}

	// Encoding 
	// 
	
	public static function encode_error_details($value) 
	{
		
		$value = json_encode($value);

		if (class_exists('Phpr_SecurityFramework')) 
		{
			$security = Phpr_SecurityFramework::create();
			list($key_1, $key_2) = Phpr::$config->get('ADDITIONAL_ENCRYPTION_KEYS', array('jd$5ka#1', '9ao@!d4k'));
			$value = $security->encrypt($value, $key_1, $key_2);
		}

		return base64_encode($value);
	}
	
	public static function decode_error_details($value) 
	{
		
		$value = base64_decode($value);

		if (class_exists('Phpr_SecurityFramework')) 
		{
			$security = Phpr_SecurityFramework::create();
			list($key_1, $key_2) = Phpr::$config->get('ADDITIONAL_ENCRYPTION_KEYS', array('jd$5ka#1', '9ao@!d4k'));
			$value = $security->decrypt($value, $key_1, $key_2);
		}

		return json_decode($value);
	}
}
