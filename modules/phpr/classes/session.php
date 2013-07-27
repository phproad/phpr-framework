<?php namespace Phpr;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Countable;

use Phpr;
use Db\Helper as Db_Helper;

/**
 * PHPR Session Class
 *
 * This class incapsulates the PHP session.
 *
 * The instance of this class is available in the Phpr global object: Phpr::$session.
 */
class Session implements ArrayAccess, IteratorAggregate, Countable
{
	/**
	 * Flash object
	 *
	 * @var Phpr\Flash
	 */
	public $flash = null;

	/**
	 * Begins a session.
	 * You must always start the session before use any session data.
	 * You may achieve the "auto start" effect by adding the following line to the application init.php script:
	 * Phpr::$session->start();
	 *
	 * @return boolean
	 */
	public function start()
	{
		$path = ini_get('session.cookie_path');
		if (!strlen($path))
			$path = '/';

		$secure = false;

		if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https')
			$secure = true;
		else
			$secure = (empty($_SERVER["HTTPS"]) || ($_SERVER["HTTPS"] === 'off')) ? false : true;
			
		session_set_cookie_params(ini_get('session.cookie_lifetime') , $path, ini_get('session.cookie_domain'),  $secure);
		
		if ($result = session_start())
		{
			$this->flash = new Flash();
			if ($this->flash)
			{
				if (array_key_exists('flash_partial', $_POST) && strlen($_POST['flash_partial']))
					$this->flash['system'] = 'flash_partial:'.$_POST['flash_partial'];
			}
		}
			
		return $result;
	}
	
	public function restore_db_data()
	{
		$session_id_param = Phpr::$config->get('SESSION_PARAM_NAME', 'phpr_session_id');
		$session_id = Phpr::$request->get_field($session_id_param);
		
		if ($session_id)
		{
			$this->restore($session_id);
		}
	}

	/*
	 * Sessions in the database
	 */
	
	public function reset_db_sessions()
	{
		$ttl = (int)Phpr::$config->get('STORED_SESSION_TTL', 3);
		Db_Helper::query('delete from db_session_data where created_at < DATE_SUB(now(), INTERVAL :seconds SECOND)', array('seconds'=>$ttl));
	}

	public function store()
	{
		$session_id = session_id();
		
		Db_Helper::query('delete from db_session_data where session_id=:session_id', array('session_id'=>$session_id));
		
		$data = serialize($_SESSION);
		Db_Helper::query('insert into db_session_data(session_id, session_data, created_at, client_ip) values (:session_id, :session_data, NOW(), :client_ip)', array(
			'session_id'=>$session_id,
			'session_data'=>$data,
			'client_ip'=>Phpr::$request->get_user_ip()
		));
	}

	public function restore($session_id)
	{
		$data = Db_Helper::scalar('select session_data from db_session_data where session_id=:session_id and client_ip=:client_ip', array(
			'session_id'=>$session_id,
			'client_ip'=>Phpr::$request->get_user_ip()
		));
		
		Db_Helper::query('delete from db_session_data where session_id=:session_id', array('session_id'=>$session_id));

		if (strlen($data))
		{
			try
			{
				$data = unserialize($data);
				if (is_array($data))
				{
					foreach ($data as $key => $value)
					{
						$this->set($key, $value);
					}
				}
			} catch (\Exception $ex) {}
		}
	}

	/**
	 * Destroys all data registered to a session 
	 */
	public function destroy()
	{
		if (!session_id())
			session_start();

		$_SESSION = array();
		session_destroy();
	}

	/**
	 * Determines whether the session contains a value
	 * @param string $name Specifies a value name
	 * @return boolean
	 */
	public function has($name)
	{
		return isset($_SESSION[$name]);
	}

	/**
	 * Returns a value from the session.
	 * @param string $name Specifies a value name
	 * @param mixed $default Specifies a default value
	 * @return mixed
	 */
	public function get($name, $default = null)
	{
		if ($this->has($name))
			return $_SESSION[$name];

		return $default;
	}

	/**
	 * Writes a value to the session.
	 * @param string $name Specifies a value name
	 * @param mixed $value Specifies a value to write.
	 */
	public function set($name, $value = null)
	{
		if ( $value === null )
			unset($_SESSION[$name]);
		else
			$_SESSION[$name] = $value;
	}

	/**
	 * Removes a value from the session.
	 * @param string $name Specifies a value name
	 */
	public function remove($name)
	{
		$this->set($name, null);
	}

	/**
	 * Resets the session object.
	 */
	public function reset()
	{
		foreach ($_SESSION as $name=>$value)
			unset($_SESSION[$name]);
			
		$this->reset_db_sessions();
	}

	/**
	 * Iterator implementation
	 */
	
	function offsetExists($offset)
	{
		return isset($_SESSION[$offset]);
	}
	
	function offsetGet($offset)
	{
		return $this->get($offset, null);
	}
	
	function offsetSet($offset, $value)
	{
		$this->set($offset, $value);
	}
	
	function offsetUnset($offset)
	{
		unset($_SESSION[$offset]);
	}
	
	function getIterator()
	{
		return new ArrayIterator($_SESSION);
	}

	/**
	 * Returns a number of flash items
	 * @return integer
	 */
	public function count()
	{
		return count($_SESSION);
	}	
}