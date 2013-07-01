<?php namespace Db;

use Phpr;
use Phpr\SystemException;
use Phpr\ApplicationException;
use File\Image;
use File\Upload;
use Db\Helper as Db_Helper;

class File extends ActiveRecord
{
	public $table_name = 'db_files';
	public $simple_caching = true;

	public $uploaded_dir = '/uploaded';  // Public path
	public $uploaded_path = '/uploaded'; // Absolute path
	public $thumbnail_error =  null;

	public $implement = 'Db\AutoFootprints';

	protected $auto_mime_types = array(
		'docx' => 'application/msword',
		'xlsx' => 'application/excel',
		'gif'  => 'image/gif',
		'png'  => 'image/png',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'jpe'  => 'image/jpeg',
		'pdf'  => 'application/pdf'
	);

	public static $image_extensions = array(
		'jpg', 'jpeg', 'png', 'gif'
	);

	public $calculated_columns = array(
		'user_name' => array('sql'=>'concat(last_name, " ", first_name)', 'type'=>db_text, 'join'=>array('admin_users'=>'admin_users.id=db_files.created_user_id'))
	);

	public function __construct($values = null, $options = array())
	{
		$front_end = ActiveRecord::$execution_context == 'front-end';
		if ($front_end)
			unset($this->calculated_columns['user_name']);

		$phpr_url = Phpr::$config->get('PHPR_URL', 'phpr');
		if (!$this->thumbnail_error)
			$this->thumbnail_error = '/'.$phpr_url.'/assets/images/thumbnail_error.gif';

		parent::__construct($values, $options);
	}

	public static function create($values = null)
	{
		return new self($values);
	}

	public function from_xhr($file_info)
	{	
		$file_info = Upload::validate_xhr_info($file_info);
		
		$this->mime_type = $this->eval_mime_type($file_info);
		$this->size = $file_info['size'];
		$this->name = $file_info['name'];
		$this->disk_name = $this->get_disk_file_name($file_info);
		
		$results = Phpr::$events->fire_event('phpr:on_before_file_created_from_xhr', $this, $file_info);
		
		foreach ($results as $result) 
		{
			if ($result)
				return $this;
		}

		$dest_path = $this->get_file_save_path($this->disk_name);
		Upload::save_xhr_file($file_info, $dest_path);

		return $this;
	}

	public function from_post($file_info)
	{
		Upload::validate_uploaded_file($file_info);

		$this->mime_type = $this->eval_mime_type($file_info);
		$this->size = $file_info['size'];
		$this->name = $file_info['name'];
		$this->disk_name = $this->get_disk_file_name($file_info);

		$results = Phpr::$events->fire_event('phpr:on_before_file_created_from_post', $this, $file_info);

		foreach ($results as $result) 
		{
			if ($result)
				return $this;
		}

		$dest_path = $this->get_file_save_path($this->disk_name);

		if (!@move_uploaded_file($file_info["tmp_name"], $dest_path))
			throw new SystemException("Error copying file to $dest_path.");

		return $this;
	}

	public function from_file($file_path)
	{
		$results = Phpr::$events->fire_event('phpr:on_before_file_created_from_file', $this, $file_path);

		foreach ($results as $result) 
		{
			if ($result)
				return $this;
		}

		$file_info = array();
		$file_info['name'] = basename($file_path);
		$file_info['size'] = filesize($file_path);
		$file_info['type'] = null;

		$this->mime_type = $this->eval_mime_type($file_info);
		$this->size = $file_info['size'];
		$this->name = $file_info['name'];
		$this->disk_name = $this->get_disk_file_name($file_info);

		$dest_path = $this->get_file_save_path($this->disk_name);

		if (!@copy($file_path, $dest_path))
			throw new SystemException("Error copying file to $dest_path.");

		return $this;
	}

	protected function get_disk_file_name($file_info)
	{
		$ext = $this->get_file_extension($file_info);
		$name = uniqid(null, true);

		return $ext !== null ? $name.'.'.$ext : $name;
	}

	protected function eval_mime_type($file_info)
	{
		$type = $file_info['type'];
		$ext = $this->get_file_extension($file_info);

		$mime_types = array_merge($this->auto_mime_types, Phpr::$config->get('auto_mime_types', array()));

		if (array_key_exists($ext, $mime_types))
			return $mime_types[$ext];

		return $type;
	}

	protected function get_file_extension($file_info)
	{
		$path_info = pathinfo($file_info['name']);
		if (isset($path_info['extension']))
			return strtolower($path_info['extension']);

		return null;
	}

	public function get_file_save_path($disk_name)
	{
		if (!$this->is_public)
			return PATH_APP . $this->uploaded_path.'/'.$disk_name;
		else
			return PATH_APP . $this->uploaded_path.'/public/'.$disk_name;
	}

	public function after_create()
	{
		Db_Helper::query('update db_files set sort_order=:sort_order where id=:id', array(
			'sort_order' => $this->id,
			'id' => $this->id
		));
		$this->sort_order = $this->id;
	}

	public function after_delete()
	{
		$results = Phpr::$events->fire_event('phpr:on_before_file_deleted', $this);

		foreach ($results as $result) {
			if ($result)
				return;
		}

		$dest_path = $this->get_file_save_path($this->disk_name);

		if (file_exists($dest_path))
			@unlink($dest_path);

		$thumb_path = PATH_APP . $this->uploaded_path.'/thumbnails/db_file_img_'.$this->id.'_*.jpg';
		$thumbs = glob($thumb_path);
		if (is_array($thumbs))
		{
			foreach ($thumbs as $filename)
				@unlink($filename);
		}
	}

	public function output($disposition = 'inline')
	{
		$results = Phpr::$events->fire_event('phpr:on_before_file_output', $this);

		foreach ($results as $result) 
		{
			if ($result)
				return;
		}

		$path = $this->get_file_save_path($this->disk_name);
		if (!file_exists($path))
			throw new ApplicationException('Error: file not found.');

		$encoding = Phpr::$config["FILESYSTEM_CODEPAGE"];
		$filename = mb_convert_encoding($this->name, $encoding);

		$mime_type = $this->mime_type;
		if (!strlen($mime_type) || $mime_type == 'application/octet-stream')
		{
			$file_info = array('type'=>$mime_type, 'name'=>$filename);
			$mime_type = $this->eval_mime_type($file_info);
		}

		header("Content-type: ".$mime_type);
		header('Content-Disposition: '.$disposition.'; filename="'.$filename.'"');
		header('Cache-Control: private');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Cache-Control: pre-check=0, post-check=0, max-age=0');
		header('Accept-Ranges: bytes');
		header('Content-Length: '.$this->size);
		//header("Connection: close");

		readfile($path);
	}

	public function get_thumbnail_path($width, $height, $return_jpeg = true, $params = array('mode' => 'keep_ratio'))
	{
		$processed_images = Phpr::$events->fire_event('phpr:on_process_image', $this, $width, $height, $return_jpeg, $params);
		foreach ($processed_images as $image) {
			if (strlen($image)) {
				if (!preg_match(',^(http://)|(https://),', $image))
					return root_url($image);
				else
					return $image;
			}
		}

		$ext = $return_jpeg ? 'jpg' : 'png';

		$thumb_path = $this->uploaded_dir.'/thumbnails/db_file_img_'.$this->id.'_'.$width.'x'.$height.'.'.$ext;
		$thumb_file = PATH_APP.$thumb_path;

		if (file_exists($thumb_file))
			return root_url($thumb_path);

		try {
			Image::make_thumb($this->get_file_save_path($this->disk_name), $thumb_file, $width, $height, false, $params['mode'], $return_jpeg);
		}
		catch (Exception $ex) {
			@copy(PATH_APP . $this->thumbnail_error, $thumb_file);
		}

		return root_url($thumb_path);
	}

	public function get_path()
	{
		if (!$this->is_public)
			return $this->uploaded_dir.'/'.$this->disk_name;
		else
			return $this->uploaded_dir.'/public/'.$this->disk_name;
	}

	public function copy()
	{
		$src_path = $this->get_file_save_path($this->disk_name);
		$dest_name = $this->get_disk_file_name(array('name' => $this->disk_name));

		$obj = new self();
		$obj->mime_type = $this->mime_type;
		$obj->size = $this->size;
		$obj->name = $this->name;
		$obj->disk_name = $dest_name;
		$obj->description = $this->description;
		$obj->sort_order = $this->sort_order;
		$obj->is_public = $this->is_public;

		if (!copy($src_path, $obj->get_file_save_path($dest_name)))
			throw new SystemException('Error copying file');

		return $obj;
	}

	public static function set_orders($item_ids, $item_orders)
	{
		if (is_string($item_ids))
			$item_ids = explode(',', $item_ids);

		if (is_string($item_orders))
			$item_orders = explode(',', $item_orders);

		foreach ($item_ids as $index=>$id)
		{
			$order = $item_orders[$index];
			Db_Helper::query('update '.$this->table_name.' set sort_order=:sort_order where id=:id', array(
				'sort_order' => $order,
				'id' => $id
			));
		}
	}

	public function is_image()
	{
		$path_info = pathinfo($this->name);
		$extension = null;
		if (isset($path_info['extension']))
			$extension = strtolower($path_info['extension']);

		return in_array($extension, self::$image_extensions);
	}

	public function before_create($session_key = null)
	{
		Phpr::$events->fire_event('phpr:on_file_before_create', $this);
	}

	/**
	 * @deprecated 
	 */ 
	
	public function getThumbnailPath($width, $height, $return_jpeg = true, $params = array('mode' => 'keep_ratio')) { Phpr::$deprecate->set_function('getThumbnailPath', 'get_thumbnail_path'); return $this->get_thumbnail_path($width, $height, $return_jpeg, $params); }
	public function getPath() { Phpr::$deprecate->set_function('getPath', 'get_path'); return $this->get_path(); }
	public function fromXhr($file_path) { Phpr::$deprecate->set_function('fromXhr', 'from_xhr'); return $this->from_xhr($file_path); }
	public function fromPost($file_path) { Phpr::$deprecate->set_function('fromPost', 'from_post'); return $this->from_post($file_path); }
	public function fromFile($file_path) { Phpr::$deprecate->set_function('fromFile', 'from_file'); return $this->from_file($file_path); }

}
