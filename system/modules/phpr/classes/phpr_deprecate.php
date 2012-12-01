<?php

/**
 * PHPR Deprecate class
 *  
 * Used for deprecating classes, methods and arguements internally
 */
class Phpr_Deprecate 
{
    public function set_class($class_name, $replacement = null) 
    {
        if ($replacement)
            $message = $class_name.' is a deprecated file. Please use '.$replacement.' instead.';
        else
            $message = $class_name.' is a deprecated file. Sorry, there is no alternative.';
        
        try 
        {
            throw new Phpr_DeprecateException($message);
        }
        catch (Phpr_DeprecateException $ex) 
        {
            Phpr::$error_log->log_exception($ex);
        }
    }

    public function set_function($func_name, $replacement = null) 
    {
        if ($replacement)
            $message = $func_name.' is deprecated. Please use '.$replacement.' instead.';
        else
            $message = $func_name.' is deprecated. Sorry, there is no alternative.';

        try 
        {
            throw new Phpr_DeprecateException($message);
        }
        catch (Phpr_DeprecateException $ex) 
        {
            Phpr::$error_log->log_exception($ex);
        }
    }
    
    public function set_argument($arg_name, $argument_name, $replacement = null) 
    {
        if ($replacement)
            $message = $arg_name.' was called with an argument that is deprecated: '.$argument_name.'. Please use '.$replacement.' instead.';
        else
            $message = $arg_name.' was called with an argument that is deprecated: '.$argument_name.'. Sorry, there is no alternative.';
        
        try 
        {
            throw new Phpr_DeprecateException($message);
        }
        catch (Phpr_DeprecateException $ex) 
        {
            Phpr::$error_log->log_exception($ex);
        }
    }
}