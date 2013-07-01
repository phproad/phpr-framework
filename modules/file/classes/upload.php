<?php

class File_Upload
{
	/**
	 * Returns a number of bytes allowed for uploading through POST requests
	 */
	public static function max_upload_size()
	{
		$max_file_size = (int)ini_get('upload_max_filesize') * 1024000;
		$max_post_size = (int)ini_get('post_max_size') * 1024000;

		return min($max_file_size, $max_post_size);
	}

	public static function validate_uploaded_file($file_info)
	{
		switch ($file_info['error'])
		{
			case UPLOAD_ERR_INI_SIZE :
				$max_size_allowed = File::size_from_bytes(File_Upload::max_upload_size());
				throw new Phpr_ApplicationException('File size exceeds maximum allowed size ('.$max_size_allowed.').');
				break;

			case UPLOAD_ERR_PARTIAL : 
				throw new Phpr_ApplicationException('Error uploading file. Only a part of the file was uploaded.');
				break;

			case UPLOAD_ERR_NO_FILE :
				throw new Phpr_ApplicationException('Error uploading file.');
				break;

			case UPLOAD_ERR_NO_TMP_DIR : 
				throw new Phpr_ApplicationException('PHP temporary file directory does not exist.');
				break;

			case UPLOAD_ERR_CANT_WRITE : 
				throw new Phpr_ApplicationException('Error writing file to disk.');
				break;
		}
	}
	
	public static function extract_mutli_file_info($multi_file_info)
	{
		$result = array();
		if (!array_key_exists('name', $multi_file_info))
			return $result;
			
		$info_components = array_keys($multi_file_info);
			
		$file_count = count($multi_file_info['name']);
		for ($i=0; $i<$file_count; $i++)
		{
			$result[$i] = array();
			
			foreach ($info_components as $component_name)
				$result[$i][$component_name] = $multi_file_info[$component_name][$i];
		}
		
		return $result;
	}

	// XHR File handling
	// 

	/**
	 * Produces file information array from XHR submission
	 */
	
	public static function validate_xhr_info($file_info)
	{
		$max_size = File_Upload::max_upload_size();
		$max_size_text = File::size_from_bytes($max_size);

		$file_info = array();
		$file_info['name'] = $file_info;
		$file_info['type'] = null;
		
		// Get XHR file size
		if (isset($_SERVER['CONTENT_LENGTH']))
			$file_info['size'] = (int)$_SERVER['CONTENT_LENGTH'];
		else 
			throw new Phpr_ApplicationException('Unable to get content length from XHR upload.');
		
		if ($file_info['size'] > $max_size)
			throw new Phpr_ApplicationException('File size exceeds maximum allowed size ('.$max_size_text.').');

		return $file_info;
	}

	public static function save_xhr_file($file_info, $dest_path)
	{
		// Read file
		$input = fopen('php://input', 'r');
		$tmp_file = tmpfile();
		$actual_size = stream_copy_to_stream($input, $tmp_file);
		fclose($input);

		if ($actual_size != $file_info['size'])
			throw new Phpr_ApplicationException('The XHR file uploaded is corrupt. Please try again.');

		$target = fopen($dest_path, "w");
		fseek($tmp_file, 0, SEEK_SET);
		stream_copy_to_stream($tmp_file, $target);
		fclose($target);
	}

}