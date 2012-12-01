<?php

class File_Path
{
    // Returns a public path from an absolute one
    // eg: /home/mysite/public_html/welcome -> /welcome
    public static function find_public_path($path)
    {
        $result = null;

        if (strpos($path, PATH_PUBLIC) === 0)
            $result = str_replace("\\", "/", substr($path, strlen(PATH_PUBLIC)));

        return $result;
    }

    // Finds the path to a class
    public static function find_path_to_class($class_name)
    {
        $class_info = new ReflectionClass($class_name);
        return dirname($class_info->getFileName());
    }
}