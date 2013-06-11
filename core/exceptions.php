<?php/** * PHPR Exception base class * * Phpr_Exception is a base class for all PHPR exceptions and will * automatically record to the error log unless the class name is  * excluded via the config: *  *   $CONFIG['ERROR_IGNORE'] = array('Phpr_ApplicationException', 'Phpr_DeprecateException'); *  */class Phpr_Exception extends Exception{	public $log_id;	public $log_status;	public $hint_message;		/**	 * Creates a new Phpr_Exception instance.	 * @param string $message Message of exception.	 * @param int $code Code of exception.	 */	public function __construct($message = null, $code = 0)	{		parent::__construct($message, $code);		if (Phpr::$error_log !== null) {			try			{				$result = Phpr::$error_log->log_exception($this);								$this->log_id = $result['id'];				$this->log_status = $result['status'];			} 			catch (Exception $ex)			{				// Prevent loop			}		}	}	/**	 * Formats a trace string for display in HTML or text format.	 * @param Exception $exception Specifies an exception to format the trace for.	 * @param boolean @Html Indicates whether the result must be in HTML format.	 * @return string	 */	public static function trace_to_string($exception, $html = true)	{		$result = null;		$trace_info = $exception->getTrace();		$last_index = count($trace_info) - 1;		// Begin the event list		if ($html)			$result .= PHP_EOL."<ol>".PHP_EOL."<li>";		$char_newline = $html ? "</li>".PHP_EOL."<li>" : PHP_EOL;		foreach ($trace_info as $index=>$event) 		{			$function_name = (isset($event['class']) && strlen($event['class'])) 				? $event['class']."->".$event['function'] 				: $event['function'];			// Do not include the handlers to the trace			if ($function_name == 'Phpr_SysErrorHandler' || $function_name == 'Phpr_SysExceptionHandler')				continue;			$file = isset($event['file']) ? basename($event['file']) : null;			$line = isset($event['line']) ? $event['line'] : null;			// Prepare the argument list			$args = null;			if (isset($event['args']) && count($event['args']))				$args = self::format_trace_arguements($event['args'], $html);			if (!is_null($file))				$result .= sprintf('%s(%s) in %s, line %s', $function_name, $args, $file, $line);			else				$result .= $function_name."($args)";			if ($index < $last_index)				$result .= $char_newline;		}		// End the event list		if ($html)			$result .= "</li>".PHP_EOL."</ol>".PHP_EOL;		return $result;	}	/**	 * Prepares a function or method argument list for display in HTML or text format	 * @param array &$arguments A list of the function or method arguments	 * @param boolean @Html Indicates whether the result must be in HTML format.	 * @return string	 */	public static function format_trace_arguements(&$arguments, $html = true)	{		$args_array = array();		foreach ($arguments as $argument)		{			$arg = null;						if (is_array($argument)) 			{				$items = array();							foreach ($argument as $index => $obj) 				{					switch ($obj)					{						case (is_array($obj)):							$value = 'array('.count($obj).')';							break;						case (is_object($obj)):							$value = 'object('.get_class($obj).')';							break;												case (is_integer($obj)):							$value = $obj;							break;						case ($obj === null):							$value = "null";							break;						default:							$value = "'".($html ? Phpr_Html::encode($obj) : $obj)."'";							break;					}											$items[] = $index . ' => ' . $value;				}							if (count($items))					$arg = 'array(' . count($argument) . ') [' . implode(', ', $items) . ']';				else					$arg = 'array(0)';			}			else if (is_object($argument))				$arg = 'object('.get_class($argument).')';			else if ($argument === null) 				$arg = "null";			else if (is_integer($argument)) 				$arg = $argument;			else 				$arg = "'".($html ? Phpr_Html::encode($argument) : $argument)."'";							if ($html)				$arg = '<span style="color:#398999">'.$arg.'</span>';							$args_array[] = $arg;		}		return implode(', ', $args_array);	}}/** * PHPR System Exception base class * * Phpr_SystemException is a base class for system exceptions. */class Phpr_SystemException extends Phpr_Exception{}/** * PHPR Application Exception base class * * Phpr_ApplicationException is a base class for application exceptions. */class Phpr_ApplicationException extends Phpr_Exception{}/** * PHPR Database Exception base class * * Phpr_DatabaseException is a base class for database exceptions. */class Phpr_DatabaseException extends Phpr_Exception{}/** * PHPR Database Exception base class * * Phpr_DatabaseException is a base class for database exceptions. */class Phpr_DeprecateException extends Phpr_Exception{	public $prev_trace;	public $class_name;	public $code_line;	public $code_file;	public function __construct($message = null, $code = 0)	{		$trace = $this->getTrace();		if (!isset($trace[1]))			return;		$this->prev_trace = $prev_trace = (object)$trace[1];		$this->class_name = isset($prev_trace->class) ? $prev_trace->class : null;		$this->code_file = isset($prev_trace->file) ? $prev_trace->file : null;		$this->code_line = isset($prev_trace->line) ? $prev_trace->line : null;		parent::__construct($message, $code);	}}/** * PHPR Database Exception base class * * Phpr_DatabaseException is a base class for HTTP exceptions. */class Phpr_HttpException extends Phpr_ApplicationException{	public $http_code;		protected static $status_messages = array(		100 => 'Continue',		101 => 'Switching Protocols',		200 => 'OK',		201 => 'Created',		202 => 'Accepted',		203 => 'Non-Authoritative Information',		204 => 'No Content',		205 => 'Reset Content',		206 => 'Partial Content',		300 => 'Multiple Choices',		301 => 'Moved Permanently',		302 => 'Found',		303 => 'See Other',		304 => 'Not Modified',		305 => 'Use Proxy',		307 => 'Temporary Redirect',		400 => 'Bad Request',		401 => 'Unauthorized',		402 => 'Payment Required',		403 => 'Forbidden',		404 => 'Not Found',		405 => 'Method Not Allowed',		406 => 'Not Acceptable',		407 => 'Proxy Authentication Required',		408 => 'Request Time-out',		409 => 'Conflict',		410 => 'Gone',		411 => 'Length Required',		412 => 'Precondition Failed',		413 => 'Request Entity Too Large',		414 => 'Request-URI Too Large',		415 => 'Unsupported Media Type',		416 => 'Requested range not satisfiable',		417 => 'Expectation Failed',		500 => 'Internal Server Error',		501 => 'Not Implemented',		502 => 'Bad Gateway',		503 => 'Service Unavailable',		504 => 'Gateway Time-out',		505 => 'HTTP Version not supported'	);		/**	 * Creates a new Phpr_Exception instance.	 * @param int $http_code HTTP code.	 * @param string $message message of the exception.	 * @param int $code Code of exception.	 */	public function __construct($http_code, $message = null, $code = 0)	{		$this->http_code = $http_code;		parent::__construct($message, $code);	}		/**	 * Outputs the error message along with a corresponding HTTP header.	 * @param boolean $stop Stop script execution after outputting the message.	 * @param boolean $output_message Indicates whether the error message should be outputted before the. 	 */	public function output($stop = true, $output_message = true)	{		self::output_custom($this->http_code, $this->getMessage(), $stop, $output_message);	}		/**	 * Outputs custom HTTP header with a message	 * @param int $http_code HTTP code.	 * @param string $message message of the exception.	 * @param boolean $stop Stop script execution after outputting the message.	 * @param boolean $output_message Indicates whether the error message should be outputted before the. 	 */	public static function output_custom($http_code, $message, $stop = true, $output_message = true)	{		$header_text = 'HTTP/1.1 '.$http_code.' ';		if (isset(self::$status_messages[$http_code]))			$header_text .= self::$status_messages[$http_code];		else			$header_text .= $message;					header($header_text);				if ($output_message)			echo $message;				if ($stop)			die();	}}/** * PHPR PHP Exception class * * Phpr_PhpException represents the PHP Error.  * PHPR automatically converts all errors to exceptions of this class. * Use the getCode() method to obtain the PHP error number (E_WARNING, E_NOTICE and others). */class Phpr_PhpException extends Phpr_SystemException{	/**	 * Creates a new Phpr_Exception instance.	 * @param string $message Message of exception.	 * @param int $type Type of the PHP error. 	 * @param string $file Source filename.	 * @param string $line Source line.	 */	public function __construct($message, $type, $file, $line)	{		$this->file = $file;		$this->line = $line;		parent::__construct($message, $type);	}	/**	 * Outputs a formatted exception string for display.	 * @return string	 */	public function __toString()	{		$result = null;		$error_names = array(			E_WARNING      => 'PHP Warning', 			E_NOTICE       => 'PHP Notice', 			E_STRICT       => 'PHP Strict Error', 			E_USER_ERROR   => 'PHP User Error', 			E_USER_WARNING => 'PHP User Warning', 			E_USER_NOTICE  => 'PHP User Notice'		);		$result = isset($error_names[$this->code]) 			? $error_names[$this->code] 			: "PHP Error";		return $result.": ".$this->getMessage();	}}/** * PHPR system error handler. * PHPR automatically converts all errors to exceptions of class Phpr_PhpException. */function Phpr_SysErrorHandler($errno, $errstr, $errfile, $errline){	// Throw the PHP Exception if it is listed in the ERROR_REPORTING configuration value	if (Phpr::$config !== null && (Phpr::$config->get("ERROR_REPORTING", E_ALL) & $errno))		throw new Phpr_PhpException($errstr, $errno, $errfile, $errline);	else	{		// Otherwise throw and catch the exception to log it		try		{			throw new Phpr_PhpException($errstr, $errno, $errfile, $errline);		}		catch (Exception $ex)		{			// Do nothing		}	}}/** * PHPR system exception handler. * PHPR uses this function to catch the unhandled exceptions and display the error page. */function Phpr_SysExceptionHandler($exception){	try	{		Phpr::$response->open_error_page($exception);	}	catch (Exception $e)	{		print get_class($e)." thrown within the exception handler. Message: ".$e->getMessage()." on line ".$e->getLine()." of ".$e->getFile();	}}/** * Validation exception class. * * Phpr_ValidationException represents a data validation error. */class Phpr_ValidationException extends Phpr_ApplicationException{	public $validation;	/**	 * Creates a new Phpr_ValidationException instance	 * @param Phpr_Validation $validation Validation object that caused the exception.	 */	public function __construct(Phpr_Validation $validation)	{		parent::__construct();		$this->message = null;		$this->validation = $validation;		if ($validation->error_message !== null)			$this->message = $validation->error_message;		if (count($validation->field_errors)) {			$keys = array_keys($validation->field_errors);			if (strlen($this->message)) 				$this->message .= PHP_EOL;						$this->message .= $validation->field_errors[$keys[0]];		}	}}/** * Set the error and exception handlers */set_error_handler('Phpr_SysErrorHandler');set_exception_handler('Phpr_SysExceptionHandler');