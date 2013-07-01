<?php namespace File;

use Phpr;
use Phpr\SystemException;
use Phpr\ApplicationException;
use File\Directory;

class Image
{
	/**
	 * Creates a thumbnail from a source image
	 * @param string $source_path Specifies the source image path
	 * @param string $destination_path Specifies the destination thumbnail path
	 * @param mixed $width Specifies a destination image width. Can have integer value or string 'auto'
	 * @param mixed $height Specifies a destination image height. Can have integer value or string 'auto'
	 * @param string $mode Specifies a scaling mode. Possible values: keep_ratio, crop and fit. It works only if both width and height are specified as numbers
	 * @param string $return_jpeg - returns JPEG (true) or PNG image (false)
	 */
	public static function make_thumb($source_path, $destination_path, $width, $height, $force_gd = false, $mode = 'keep_ratio', $return_jpeg = true)
	{
		$extension = null;
		$path_info = pathinfo($source_path);

		if (isset($path_info['extension']))
			$extension = strtolower($path_info['extension']);
			
		$allowed_extensions = array('gif', 'jpeg', 'jpg','png');

		if (!in_array($extension, $allowed_extensions))
			throw new ApplicationException('Unknown image thumb format');
			
		if (!preg_match('/^[0-9]*!?$/', $width) && $width != 'auto')
			throw new ApplicationException("Invalid thumb width: Please use integer or 'auto' value");

		if (!preg_match('/^[0-9]*!?$/', $height) && $height != 'auto')
			throw new ApplicationException("Invalid thumb height: Please use integer or 'auto' value");

		list($src_width, $src_height) = getimagesize($source_path);
	
		$centerImage = false;

		// Automatic (no change)
		if ($width == 'auto' && $height == 'auto')
		{
			$optimal_width = $width = $src_width;
			$optimal_height = $height = $src_height;
		}
		// Portrait
		elseif ($width == 'auto' && $height != 'auto')
		{
			if (substr($height, -1) == '!')
			{
				$height = substr($height, 0, -1);
				$optimal_height = $height;
			}
			else
				$optimal_height = $src_height > $height ? $height : $src_height;

			$optimal_width = $width = self::get_size_by_fixed_height($src_width, $src_height, $optimal_height);
		} 
		// Landscape
		elseif ($height == 'auto' && $width != 'auto')
		{
			if (substr($width, -1) == '!')
			{
				$width = substr($width, 0, -1);
				$optimal_width = $width;
			}
			else
				$optimal_width = $src_width > $width ? $width : $src_width;

				
			$optimal_height = $height = self::get_size_by_fixed_width($src_width, $src_height, $optimal_width);
		}
		// Exact
		else
		{
			switch ($mode) {
				case 'keep_ratio':
					$option_array = self::get_optimal_ratio($src_width, $src_height, $width, $height);
					$optimal_height = $option_array['optimal_height'];
					$optimal_width = $option_array['optimal_width'];
					$centerImage = true;
					break;

				case 'crop':
					$option_array = self::get_optimal_crop($src_width, $src_height, $width, $height);
					$optimal_height = $option_array['optimal_height'];
					$optimal_width = $option_array['optimal_width'];
					break;

				default:
				case 'fit':
					$optimal_height = $height;
					$optimal_width = $width;                   
					break;
			}
		}

		if (!Phpr::$config->get('IMAGEMAGICK_ENABLED') || $force_gd)
		{
			$canvas_width = ($mode=="crop") ? $optimal_width : $width;
			$canvas_height = ($mode=="crop") ? $optimal_height : $height;

			// Create image canvas
			$image_p = imagecreatetruecolor($canvas_width, $canvas_height);

			$image = self::create_image($extension, $source_path);
			if ($image == null)
				throw new ApplicationException('Error loading the source image');

			if (!$return_jpeg)
			{
				imagealphablending($image_p, false);
				imagesavealpha($image_p, true);
			}

			$white = imagecolorallocate($image_p, 255, 255, 255);
			imagefilledrectangle($image_p, 0, 0, $canvas_width, $canvas_height, $white);

			$dest_x = 0;
			$dest_y = 0;

			if ($centerImage)
			{
				$dest_x = ceil(($width / 2) - ($optimal_width / 2));
				$dest_y = ceil(($height / 2) - ($optimal_height / 2));
			}

			imagecopyresampled($image_p, $image, $dest_x, $dest_y, 0, 0, $optimal_width, $optimal_height, $src_width, $src_height);
			
			if ($mode == "crop")
				self::crop_image($image_p, $optimal_width, $optimal_height, $width, $height);

			if ($return_jpeg)
				imagejpeg($image_p, $destination_path, Phpr::$config->get('IMAGE_JPEG_QUALITY', 70));
			else
				imagepng($image_p, $destination_path, 8);
			
			imagedestroy($image_p);
			imagedestroy($image);
		} 
		else
			self::im_resample($source_path, $destination_path, $optimal_width, $optimal_height, $width, $height, $return_jpeg);
	}

	private static function im_resample($source_path, $destination_path, $width, $height, $canvas_width, $canvas_height, $return_jpeg = true)
	{
		try
		{
			$current_dir = 'im'.(time()+rand(1, 100000));
			$tmp_dir = PATH_APP.'/temp/';

			if (!file_exists($tmp_dir) || !is_writable($tmp_dir))
				throw new SystemException('Directory '.$tmp_dir.' is not writable for PHP.');

			if (!@mkdir($tmp_dir.$current_dir))
				throw new SystemException('Error creating temp directory in '.$tmp_dir.$current_dir);

			@chmod($tmp_dir.$current_dir, Directory::get_permissions());
			
			$im_path = Phpr::$config->get('IMAGEMAGICK_PATH');
			$sys_paths = getenv('PATH');

			if (strlen($im_path))
			{
				$sys_paths .= ':'.$im_path;
				putenv('PATH='.$sys_paths);
			}

			$output_file = './output';
			$output = array();
			
			chdir($tmp_dir.$current_dir);

			if (strlen($im_path))
				$im_path .= '/';

			$jpeg_quality = Phpr::$config->get('IMAGE_JPEG_QUALITY', 70);

			$convert_binaries = array('convert', 'convert.exe');
			$convert_strings = array();
			
			foreach ($convert_binaries as $convert_binary) 
			{
				if ($return_jpeg)
					$convert_string = '"'.$im_path.$convert_binary.'" "'.$source_path.'" -colorspace RGB -antialias -quality '.$jpeg_quality.' -thumbnail "'.$width.'x'.$height.'>" -bordercolor white -border 1000 -gravity center -crop '.$canvas_width.'x'.$canvas_height.'+0+0 +repage JPEG:'.$output_file;
				else
					$convert_string = '"'.$im_path.$convert_binary.'" "'.$source_path.'" -antialias -background none -thumbnail "'.$width.'x'.$height.'>" -gravity center -crop '.$canvas_width.'x'.$canvas_height.'+0+0 +repage PNG:'.$output_file;
					
				try 
				{
					$res = shell_exec($convert_string);
				}
				catch (Exception $ex) 
				{
					$res = exec($convert_string);
				}
				
				$result_file_dir = $tmp_dir . $current_dir;

				$file1_exists = file_exists($result_file_dir . '/output');
				$file2_exists = file_exists($result_file_dir . '/output-0');
				
				if (!$file1_exists && !$file2_exists) 
					$convert_strings[] = $convert_string;
				else 
					continue;
			}
			
			if (!$file1_exists && !$file2_exists)
				throw new ApplicationException("Error converting image with ImageMagick. IM commands: \n\n" . implode($convert_strings, "\n\n") . "\n\n");
			
			if ($file1_exists)
				copy($result_file_dir.'/output', $destination_path);
			else    
				copy($result_file_dir.'/output-0', $destination_path);
				
			if (file_exists($destination_path))
				@chmod($destination_path, File::get_permissions());
			
			if (file_exists($tmp_dir.$current_dir))
				Directory::delete($tmp_dir.$current_dir);
		}
		catch (Exception $ex)
		{
			if (file_exists($tmp_dir.$current_dir))
				Directory::delete($tmp_dir.$current_dir);

			throw $ex;
		}
	}

	/**
	 * Returns a thumbnail file name, unique for the specified dimensions and file name
	 * @param string $path Specifies a source image path.
	 * @param mixed $width Specifies a thumbnail width. Can have integer value or string 'auto'.
	 * @param mixed $height Specifies a thumbnail height. Can have integer value or string 'auto'.
	 * @param string $mode Specifies a scaling mode. 
	 * @return string
	 */
	public static function create_thumb_name($path, $width, $height, $mode = 'keep_ratio')
	{
		return md5(dirname($path)).basename($path).'_'.filemtime(PATH_PUBLIC.$path).'_'.$width.'x'.$height.'_'.$mode.'.jpg';
	}

	/**
	 * Deletes thumbnails of a specified image
	 * @param string $path Specifies a source image path.
	 */
	public static function delete_image_thumbs($path)
	{
		$thumb_name = md5(dirname($path)).basename($path).'_*.jpg';

		$thumb_path = PATH_PUBLIC.'/uploaded/thumbnails/'.$thumb_name;
		$thumbs = glob($thumb_path);

		if (is_array($thumbs))
		{
			foreach ($thumbs as $filename) 
				@unlink($filename);
		}
	}

	/**
	 * Helpers
	 */

	private static function create_image($extension, $source_path)
	{
		switch ($extension) 
		{
			case 'jpeg' :
			case 'jpg' :
				return @imagecreatefromjpeg($source_path);
			case 'png' : 
				return @imagecreatefrompng($source_path);
			case 'gif' :
				return @imagecreatefromgif($source_path);
		}
		
		return null;
	}

	private static function crop_image(&$image_resized, $optimal_width, $optimal_height, $new_width, $new_height)
	{
		// Find center
		$crop_x = ($optimal_width / 2) - ($new_width / 2);
		$crop_y = ($optimal_height / 2) - ($new_height / 2);

		$crop = $image_resized;

		// Crop from center to exact requested size
		$image_resized = imagecreatetruecolor($new_width, $new_height);
		imagecopyresampled($image_resized, $crop, 0, 0, $crop_x, $crop_y, $new_width, $new_height, $new_width, $new_height);
	}

	private static function get_size_by_fixed_height($width, $height, $new_height)
	{
		$ratio = $width / $height;
		$new_width = $new_height * $ratio;
		return $new_width;
	}

	private static function get_size_by_fixed_width($width, $height, $new_width)
	{
		$ratio = $height / $width;
		$new_height = $new_width * $ratio;
		return $new_height;
	}

	// Reserved for future use
	private static function get_size_by_auto($width, $height, $new_width, $new_height)
	{
		if ($height < $width)
		// Image to be resized is wider (landscape)
		{
			$optimal_width = $new_width;
			$optimal_height = self::get_size_by_fixed_width($width, $height, $new_width);
		}
		elseif ($height > $width)
		// Image to be resized is taller (portrait)
		{
			$optimal_width = self::get_size_by_fixed_height($width, $height, $new_height);
			$optimal_height = $new_height;
		}
		else
		// Source image is a square
		{
			if ($new_height < $new_width) 
			{
				$optimal_width = $new_width;
				$optimal_height = self::get_size_by_fixed_width($width, $height, $new_width);
			} else if ($new_height > $new_width) 
			{
				$optimal_width = self::get_size_by_fixed_height($width, $height, $new_height);
				$optimal_height = $new_height;
			} else {
				// Square resized to a square
				$optimal_width = $new_width;
				$optimal_height= $new_height;
			}
		}

		return array('optimal_width' => $optimal_width, 'optimal_height' => $optimal_height);
	}

	private static function get_optimal_crop($width, $height, $new_width, $new_height)
	{

		$height_ratio = $height / $new_height;
		$width_ratio  = $width / $new_width;

		if ($height_ratio < $width_ratio) 
		{
			$optimal_ratio = $height_ratio;
		} else 
		{
			$optimal_ratio = $width_ratio;
		}

		$optimal_height = $height / $optimal_ratio;
		$optimal_width  = $width / $optimal_ratio;

		return array('optimal_width' => $optimal_width, 'optimal_height' => $optimal_height);
	}

	private static function get_optimal_ratio($width, $height, $new_width, $new_height)
	{

		$src_ratio = ($width / $height);
		$dest_ratio = ($new_width / $new_height);

		if ($dest_ratio > $src_ratio) 
		{
			$optimal_width = $new_height * $src_ratio;
			$optimal_height = $new_height;
		} else 
		{
			$optimal_height = $new_width / $src_ratio;
			$optimal_width = $new_width;
		}

		return array('optimal_width' => $optimal_width, 'optimal_height' => $optimal_height);
	}

}
