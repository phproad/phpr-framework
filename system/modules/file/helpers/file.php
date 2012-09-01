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

}