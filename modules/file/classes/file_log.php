<?php

/**
 * Logging helper class
 */
class File_Log
{
    // Writes a logged line to defined text file
    public static function write_line($file_path, $message)
    {
        $message_arr = array();
        $message_arr[] = "[".date("Y-m-d H:i:s")."] ";
        $message_arr[] = $message;
        $message_arr[] = PHP_EOL;
        $message = implode('', $message_arr);

        if (!($file_handler = @fopen($file_path, 'a')))
            return false;

        // Lock file
        flock($file_handler, LOCK_EX);

        if (!@fwrite($file_handler, $message))
        {
            fclose($file_handler);
            return false;
        }

        // Unlock file
        flock($file_handler, LOCK_UN);
        fclose($file_handler);
        return true;
    }
}
