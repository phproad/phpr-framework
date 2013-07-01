<?php namespace Phpr;

use ArrayAccess;

/**
 * PHPR configuration base class
 *
 * Loads the configuration from the application configuration files.
 *
 * The instance of this class is available in the Phpr global object: Phpr::$Config.
 *
 * @see Phpr
 */
class Config implements ArrayAccess
{
	protected $_configuration = array();

	/**
	 * Creates a new config instance and load the configuration files.
	 */
	public function __construct()
	{
		$this->load_configuration();
	}

	/**
	 * Loads the configuration and populates the internal array.
	 * Override this method in the inherited configuration classes.
	 */
	protected function load_configuration()
	{
		$config_found = false;
		global $APP_CONF;

		// Define the configuration array
		//
		$CONFIG = array();
		
		if (isset($APP_CONF) && is_array($APP_CONF))
			$CONFIG = $APP_CONF;

		// Look in the application config directory
		//
		$path = PATH_APP . '/config';
		if (file_exists($path) && is_dir($path))
		{
			if ($dh = opendir($path))
			{
				while (($file = readdir($dh)) !== false)
					if ($file != '.' && $file != '..' && (pathinfo($file, PATHINFO_EXTENSION) == PHPR_EXT))
					{
						$file_path = $path."/".$file;
						if (!is_dir($file_path))
						{
							include ($file_path);
							$config_found = true;
						}
					}

				closedir($dh);
			}
		}

		if ($config_found) 
		{
			$this->_configuration = $CONFIG;
			return;
		}

		// Look in the application parent directory
		//
		$path = realpath(PATH_APP . '/../config.php');
		if ($path && file_exists($path))
			include($path);

		$this->_configuration = $CONFIG;
	}

	/**
	 * Returns a value of the configuration option with the specified name. Allows to specify the default option value.
	 * @param string $option_name Specifies the name of option to return.
	 * @param mixed $default Optional. Specifies default option value.
	 * @return mixed Returns the option value or default value. If option does not exist returns null.
	 */
	public function get($option_name, $default = null)
	{
		if (isset($this->_configuration[$option_name]))
			return $this->_configuration[$option_name];

		return $default;
	}

	// ArrayAccess implementation
	//

	public function offsetExists($offset)
	{
		return isset($this->_configuration[$offset]);
	}

	public function offsetGet($offset)
	{
		if (isset($this->_configuration[$offset]))
			return $this->_configuration[$offset];
		else
			return null;
	}

	public function offsetSet($offset, $value) {}

	public function offsetUnset($offset) {}
}