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


    public function __construct()
    {
        $this->load_config();
    }

    // Load config
    protected function load_config()
    {
        $this->is_enabled = Phpr::$config != null && Phpr::$config->get("ERROR_LOG", false);

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

            default:
                $message = sprintf("%s: %s. In %s, line %s", 
                    get_class($exception), 
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine());         
                break;

        }
        
        // @todo
        $error = self::get_exception_details($exception);
        $log_to_db = !($exception instanceof Phpr_DatabaseException);
        
        $details = null;

        return $this->write_to_log($message, $log_to_db, $details);
    }

    // Writes a message to the error log file
    protected function write_to_log($message, $log_to_db = true, $details = null)
    {
        $record_id = null;
        
        if (!class_exists('File_Log') && !Phpr::$class_loader->load('File_Log'))
            echo $message;
        
        return array('id' => $record_id, 'status' => File_Log::write_line($this->log_file_name, $message));
    }

}