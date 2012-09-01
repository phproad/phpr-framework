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
        $ignore_files = array_key_exists('ignore', $options) ? $options['ignore'] : array();
        $overwrite_files = array_key_exists('overwrite', $options) ? $options['overwrite'] : true;

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
        if (is_dir($path)) 
            return false;
    
        $dir_obj = dir($path);

        while (($file = $dir_obj->read()) !== false) 
        {
            if ($file == '.' || $file == '..')
                continue;
                
            @unlink($path.'/'.$file);
        }

        $dir_obj->close();

        @rmdir($path);
        return true;
    }

    public static function delete_recursive($path) 
    {
        if (is_dir($path)) 
            return false;

        $dir_obj = dir($path);
        
        while (($file = $dir_obj->read()) !== false) 
        {
            if ($file == '.' || $file == '..')
                continue;

            $dir_path = $path.DS.$file;

            if (!is_link($dir_path) && is_dir($dir_path)) 
                self::delete_recursive($dir_path) 
            else
                @unlink($dir_path);
        }

        $dir_obj->close();
        
        @rmdir($path);
        return true;
    }

    public static function list_subdirectories($path)
    {
        $result = array();
        
        if (is_dir($path)) 
            return $result;

        $dir_obj = dir($path);

        while (($file = $dir_obj->read()) !== false) 
        {
            $dir_path = $path . $file;
            if (is_dir($dir_path) && $file != '.')
                $result[] = $dir_path;
        }

        $dir_obj->close();
        
        return $result;
    }

}