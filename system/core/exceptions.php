<?php

/**
 * PHPR Exception base class
 *
 * Phpr_Exception is a base class for all PHPR exceptions and will
 * automatically record to the error log unless the class name is 
 * excluded via the config:
 * 
 *   $CONFIG['ERROR_IGNORE'] = array('Phpr_ApplicationException', 'Phpr_DeprecateException');
 * 
 */
class Phpr_Exception extends Exception
{
    public $log_id;
    public $log_status;
    public $hint_message;

    public function __construct($message = null, $code = 0)
    {
        parent::__construct($message, $code);

        if (Phpr::$error_log !== null)
        {
            try
            {
                $result = Phpr::$error_log->log_exception($this);
                
                $this->log_id = $result['id'];
                $this->log_status = $result['status'];
            } 
            catch(Exception $ex)
            {
                // Do nothing
            }
        }
    }

}

class Phpr_SystemException extends Phpr_Exception
{
}

class Phpr_ApplicationException extends Phpr_Exception
{
}

class Phpr_DatabaseException extends Phpr_Exception
{
}

/**
 * PHPR Database Exception base class
 *
 * Phpr_DatabaseException is a base class for database exceptions.
 */
class Phpr_DeprecateException extends Phpr_Exception
{
    public $prev_trace;
    public $class_name;
    public $code_line;
    public $code_file;

    public function __construct($message = null, $code = 0)
    {
        $trace = $this->getTrace();

        if (!isset($trace[1]))
            return;

        $this->prev_trace = $prev_trace = (object)$trace[1];
        $this->class_name = $prev_trace->class;
        $this->code_file = $prev_trace->file;
        $this->code_line = $prev_trace->line;

        parent::__construct($message, $code);
    }
}