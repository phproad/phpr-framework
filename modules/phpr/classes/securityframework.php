<?php namespace Phpr;

use Phpr;
use Phpr\SystemException;
use Phpr\ApplicationException;

class SecurityFramework
{
	private static $instance;

	private $mode_descriptor = null;
	private $config_content = null;
	private $salt;
	private $key;
	
	protected function __construct() {}
	
	public static function create()
	{
		if (!self::$instance)
			self::$instance = new self();
			
		return self::$instance;
	}
	
	public function reset_instance()
	{
		$this->mode_descriptor = null;
		$this->config_content = null;
		$this->salt = null;
		
		return self::$instance = new self();
	}
	
	public function __destruct()
	{
		if ($this->mode_descriptor)
			mcrypt_module_close($this->mode_descriptor);
	}
	
	public function encrypt($data, $key = null, $salt = null)
	{
		$data = serialize($data);
		
		$descriptor = $this->get_mode_descriptor();
		$key_size = mcrypt_enc_get_key_size($descriptor);
		
		if ($key === null)
			$key = $this->get_key();
		
		if ($salt === null)
			$salt = $this->salt($key);
		
		$strong_key = substr(md5($salt.$key), 0, $key_size);

		srand();
		$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($descriptor), MCRYPT_RAND);

		mcrypt_generic_init($descriptor, $strong_key, $iv);
		$encrypted  = mcrypt_generic($descriptor, $data);
		mcrypt_generic_deinit($descriptor);

		$iv_enc = $iv.$encrypted;
		return self::obfuscate_data($iv_enc, $strong_key);
	}
	
	public function decrypt($data, $key = null, $salt = null)
	{
		$descriptor = $this->get_mode_descriptor();

		if ($key === null)
			$key = $this->get_key();

		if ($salt === null)
			$salt = $this->salt($key);

		$key_size = mcrypt_enc_get_key_size($descriptor);
		$strong_key = substr(md5($salt.$key), 0, $key_size);

		$data = self::deobfuscate_data($data, $strong_key);

		$iv_size = mcrypt_enc_get_iv_size($descriptor);
		$iv = substr($data, 0, $iv_size);
		$data = substr($data, $iv_size);
		
		if (strlen($iv) < $iv_size)
			return null;

		mcrypt_generic_init($descriptor, $strong_key, $iv);
		$result = mdecrypt_generic($descriptor, $data);
		mcrypt_generic_deinit($descriptor);
		
		$res = null;
		try
		{
			$res = @unserialize($result);
		} catch (Exception $ex){}
		
		return $res;
	}
	
	protected function get_key()
	{
		if (!is_null($this->key))
			return $this->key;

		$config_data = $this->get_config_content();
		if (!array_key_exists('config_key', $config_data))
			throw new SystemException('Invalid configuration file.');
	
		return $this->key = $config_data['config_key'];
	}
	
	protected function obfuscate_data(&$data, &$key)
	{
		$strong_key = md5($key);

		$key_size = strlen($strong_key);
		$data_size = strlen($data);
		$result = str_repeat(' ', $data_size);

		$key_index = $data_index = 0;

		while ($data_index < $data_size)
		{
			if ($key_index >= $key_size) 
				$key_index = 0;

			$result[$data_index] = chr((ord($data[$data_index]) + ord($strong_key[$key_index])) % 256);

			++$data_index;
			++$key_index;
		}

		return $result;
	}
	
	protected function deobfuscate_data(&$data, &$key)
	{
		$strong_key = md5($key);

		$result = str_repeat(' ', strlen($data));
		$key_size = strlen($strong_key);
		$data_size  = strlen($data);

		$key_index = $data_index = 0;

		while ($data_index < $data_size)
		{
			if ($key_index >= $key_size)
				$key_index = 0;
				
			$byte = ord($data[$data_index]) - ord($strong_key[$key_index]);
			if ($byte < 0) 
				$byte += 256;
				
			$result[$data_index] = chr($byte);
			++$data_index;
			++$key_index;
		}
		
		return $result;
	}
	
	protected function get_mode_descriptor()
	{
		if ($this->mode_descriptor == null)
			$this->mode_descriptor = mcrypt_module_open(MCRYPT_RIJNDAEL_256, null, MCRYPT_MODE_CBC, null);
			
		return $this->mode_descriptor;
	}

	public function set_config_content($content)
	{
		$this->config_content = $content;

		$file_path = Phpr::$config->get('SECURE_CONFIG_PATH', PATH_APP.'/config/config.dat');
		
		$data = $this->encrypt(
			$content, 
			Phpr::$config->get('CONFIG_KEY1', '@#$7as23'), 
			Phpr::$config->get('CONFIG_KEY2', '#0qw4-3dk')
		);

		// @chmod($file_path, Phpr::$config->get('FILE_FOLDER_PERMISSIONS'));
		file_put_contents($file_path, $data);
	}
	
	public function get_config_content()
	{
		if ($this->config_content)
			return $this->config_content;
		
		$file_path = Phpr::$config->get('SECURE_CONFIG_PATH', PATH_APP.'/config/config.dat');
		if (!file_exists($file_path))
			throw new ApplicationException('Secure configuration file is not found.');

		try
		{
			$data = $this->decrypt(
				file_get_contents($file_path), 
				Phpr::$config->get('CONFIG_KEY1', '@#$7as23'), 
				Phpr::$config->get('CONFIG_KEY2', '#0qw4-3dk')
			);
		} 
		catch (Exception $ex)
		{
			throw new SystemException('Error loading configuration file.');
		}
			
		if (!is_array($data))
			return array();
			
		return $this->config_content = $data;
	}
	
	public function salt($salt_key = null)
	{
		if ($salt_key)
			return md5($salt_key);
			
		if (strlen($this->salt))
			return $this->salt;

		$config_data = $this->get_config_content();
		if (!array_key_exists('config_key', $config_data))
			throw new SystemException('Invalid configuration file.');

		return $this->salt = md5($config_data['config_key']);
	}
	
	public function salted_hash($value, $salt_key = null)
	{
		return md5($this->salt($salt_key).$value);
	}
}
