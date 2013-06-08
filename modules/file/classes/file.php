<?php

class File
{

    public static function get_permissions()
    {
        $permissions = Phpr::$config->get('FILE_PERMISSIONS');
        if ($permissions)
            return $permissions;
            
        $permissions = Phpr::$config->get('FILE_FOLDER_PERMISSIONS');
        if ($permissions)
            return $permissions;
            
        return 0777;
    }

    /**
     * Returns a file size as string (203 Kb)
     * @param int $size Specifies a size of a file in bytes
     * @return string
     */
    public static function size_from_bytes($size)
    {
        if ($size < 1024)
            return $size.' byte(s)';
        
        if ($size < 1024000)
            return ceil($size/1024).' Kb';

        if ($size < 1024000000)
            return round($size/1024000, 1).' Mb';

        return round($size/1024000000, 1).' Gb';
    }    

}