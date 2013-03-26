<?php

class File_Directory
{

    public static function get_permissions()
    {
        $permissions = Phpr::$config->get('FOLDER_PERMISSIONS');
        if ($permissions)
            return $permissions;
            
        $permissions = Phpr::$config->get('FILE_FOLDER_PERMISSIONS');
        if ($permissions)
            return $permissions;
            
        return 0777;
    }

    public static function copy($source, $destination, &$options = array())
    {
        $ignore_files = isset($options['ignore']) ? $options['ignore'] : array();
        $overwrite_files = isset($options['overwrite']) ? $options['overwrite'] : true;

        if (is_dir($source))
        {
            if (!file_exists($destination))
                @mkdir($destination);

            $dir_obj = dir($source);

            while (($file = $dir_obj->read()) !== false) 
            {
                if ($file == '.' || $file == '..')
                    continue;

                if (in_array($file, $ignore_files))
                    continue;

                $dir_path = $source . '/' . $file;
                if (!is_dir($dir_path))
                {
                    $dest_path = $destination . '/' . $file;
                    if ($overwrite_files || !file_exists($dest_path))
                        copy($dir_path, $dest_path);
                }
                else
                {
                    self::copy($dir_path, $destination . '/' . $file, $options);
                }
            }

            $dir_obj->close();
        } 
        else 
        {
            copy($source, $destination);
        }
    }

    public static function delete($path)
    {
        if (!is_dir($path)) 
            return false;

        $iterator = new DirectoryIterator($path);
        foreach ($iterator as $file) 
        {
            if ($file->isDot() || $file->isDir())
                continue;

            @unlink($file->getPathname());
        }
        
        @rmdir($path);
        return true;
    }

    public static function delete_recursive($path) 
    {
        if (!is_dir($path)) 
            return false;

        $iterator = new DirectoryIterator($path);
        foreach ($iterator as $file) 
        {
            if ($file->isDot())
                continue;

            $dir_path = $file->getPathname();

            if (!is_link($dir_path) && $file->isDir()) 
                self::delete_recursive($dir_path);
            else
                @unlink($dir_path);
        }
        
        @rmdir($path);
        return true;
    }

    public static function list_subdirectories($path)
    {
        $result = array();
        
        if (!is_dir($path)) 
            return $result;

        $iterator = new DirectoryIterator($path);
        foreach ($iterator as $file) 
        {
            if ($file->isDir())
                $result[] = $file->getPathname();
        }
        
        return $result;
    }
}