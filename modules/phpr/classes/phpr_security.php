<?php

/**
 * PHPR security class.
 *
 * This class provides a basic security features based on cookies.
 *
 * The instance of this class is available in the Phpr global object: Phpr::$security.
 *
 * @see Phpr
 */
class Phpr_Security
{
	/**
	 * The name of the user class. 
	 * Change this name if you want to use a user class other than the Phpr_User.
	 * @var string
	 */
	public $user_class_name = "Phpr_User";

	/**
	 * The authentication cookie name.
	 * You may specify a value for this parameter in the configuration file:
	 * $CONFIG['AUTH_COOKIE_NAME'] = 'PHPR';
	 * @var string
	 */
	public $cookie_name = "PHPR";

	/**
	 * Specifies a number of days before the authentication coole expires.
	 * Default value is 2 days.
	 * You may specify a value for this parameter in the configuration file:
	 * $CONFIG['AUTH_COOKIE_LIFETIME'] = 5;
	 * @var int
	 */
	public $cookie_lifetime = "2";

	protected $cookie_lifetime_name = 'AUTH_COOKIE_LIFETIME';

	/**
	 * The path on the server in which the authentication cookie will be available on.
	 * You may specify a value for this parameter in the configuration file:
	 * $CONFIG['AUTH_COOKIE_PATH'] = '/blog/';
	 * @var string
	 */
	public $cookie_path = "/";

	/**
	 * The domain that the authentication cookie is available. 
	 * You may specify a value for this parameter in the configuration file:
	 * $CONFIG['AUTH_COOKIE_DOMAIN'] = '.your-site.com';
	 * @var string
	 */
	public $cookie_domain = "";

	/**
	 * The login name cookie name (see the RememberLoginName property).
	 * You may specify a value for this parameter in the configuration file:
	 * $CONFIG['LOGIN_COOKIE_NAME'] = 'PHPR_LOGIN';
	 * @var string
	 */
	public $login_cookie_name = "PHPR_LOGIN";

	/**
	 * Determines whether the user name must be saved in a cookie.
	 * Use this option if you want to implement a 
	 * "Remember my name in this computer" feature in a login form.
	 * Use the GetSavedLoginName() method to obtain a saved login name.
	 * See also the IsLoginNameSaved() method.
	 * @var boolean
	 */
	public $remember_login_name = false;
	
	public $cookies_updated = false;
	
	public $no_ip_check = false;
	
	protected $user = null;
	protected $_ticket = null;

	/**
	 * Returns a currently signed in user. If there is no signed user returns null.
	 * @return Phpr_User The class of a returning object depends on configuration settings.
	 * You may extend the Phpr_User and specify it in the application init.php file:
	 * Phpr::$security->user_class_name = "NewUserClass";
	 */
	public function get_user()
	{
		if ($this->user !== null)
			return $this->user;

		/*
		 * Determine whether the authentication cookie is available
		 */
		
		if ($this->_ticket !== null)
			$ticket = $this->_ticket;
		else
		{
			$cookie_name = Phpr::$config->get('AUTH_COOKIE_NAME', $this->cookie_name);
			$ticket = Phpr::$request->cookie($cookie_name);
		}

		if ($ticket === null)
			return null;

		/*
		 * Validate the ticket
		 */
		$ticket = $this->validate_ticket($ticket);
		if ($ticket === null)
			return null;

		/*
		 * Return the ticket user
		 */
		$user_obj = new $this->user_class_name();
		$user_id = trim(base64_decode($ticket['user']));
		if (!strlen($user_id))
			return null;

		return $this->user = $user_obj->find($user_id);
	}

	/**
	 * Validates user login name and password and logs user in.
	 *
	 * @param Phpr_Validation $validation Optional validation object to report errors.
	 * @param string $redirect Optional URL to redirect the user browser in case of successful login.
	 *
	 * @param string $login Specifies the user login name.
	 * If you omit this parameter the 'Login' POST variable will be used.
	 *
	 * @param string $password Specifies the user password
	 * If you omit this parameter the 'Password' POST variable will be used.
	 *
	 * @return boolean
	 */
	public function login(Phpr_Validation $validation = null, $redirect = null, $login = null, $password = null)
	{
		/*
		 * Load the login form data
		 */
		if ($login === null)
			$login = Phpr::$request->post_field('login');

		if ($password === null)
			$password = Phpr::$request->post_field('password');

		/*
		 * Validate the login name and password
		 */

		$user_obj = new $this->user_class_name();

		if (method_exists($user_obj, 'init_columns'))
			$user_obj->init_columns('login');

		$user = $user_obj->find_user($login, $password);

		$this->check_user($user);
		if ($user == null)
		{
			if ($validation !== null)
			{
				$validation->add('login');
				$validation->set_error(Phpr::$locale->get_string('phpr.security', "invalidcredentials"), 'login', true);
			}

			return false;
		}

		/*
		 * Save the login name
		 */
		$this->update_login_name($this->remember_login_name, $login);

		/*
		 * Update the authentication cookie
		 */
		$this->update_cookie($user->id);

		$this->user = $user;

		/*
		 * Prepare a clean user session
		 */
		
		$this->before_login_session_destroy($user);
		
		$session_id = null;
		if ($this->keep_session_data())
		{
			$session_id = session_id();
			Phpr::$session->store();
		}
		
		Phpr::$session->destroy();
		
		$this->after_login($user);

		/*
		 * Redirect browser to a target page
		 */
		if ($redirect !== null)
		{
			if ($session_id)
			{
				$session_id_param = Phpr::$config->get('SESSION_PARAM_NAME', 'phpr_session_id');
				$redirect .= '?'.$session_id_param.'='.urlencode($session_id);
			}
			
			Phpr::$response->redirect($redirect);
		}

		return true;
	}

	/**
	 * Logs user out.
	 * @param string $redirect Optional URL to redirect the user browser.
	 */
	public function logout($redirect = null)
	{
		$cookie_name = Phpr::$config->get('AUTH_COOKIE_NAME', $this->cookie_name);
		$cookie_path = Phpr::$config->get('AUTH_COOKIE_PATH', $this->cookie_path);
		$cookie_domain = Phpr::$config->get('AUTH_COOKIE_DOMAIN', $this->cookie_domain);

		Phpr::$response->delete_cookie($cookie_name, $cookie_path, $cookie_domain);

		$this->user = null;

		Phpr::$session->destroy();

		if ($redirect !== null)
			Phpr::$response->redirect($redirect);
	}

	/**
	 * Determines whether a currently signed in user is allowed to have access to a specified resource.
	 * @param string $module Specifies the name of a module that owns the resource ("blog").
	 *
	 * @param string $resource Specifies the name of a recource ("post").
	 * You may omit this parameter to determine if user has accssess rights to any module resource.
	 *
	 * @param string $object Specifies the resource object ("1").
	 * You may omit this parameter to determine if user has accssess rights to any object in context of specified module resource.
	 *
	 * @return mixed
	 */
	public function authorize($module, $resource = null, $object = null)
	{
		/*
		 * Validate the session host
		 */
		if (!$this->check_session_host())
			return false;
		
		/*
		 * Validate the user
		 */
		
		$user = $this->get_user();

		if ($user === null)
			return false;

		$res = $user->authorize($module, $resource, $object);
		if ($res)
		{
			$this->update_cookie($user->id);
			return true;
		}

		return false;
	}
	
	/**
	 * Checks whether the session has been started on this host
	 */
	public function check_session_host()
	{
		$session_host = Phpr::$session->get('phpr_session_host');
		if (!strlen($session_host))
		{
			Phpr::$session->set('phpr_session_host', $_SERVER['SERVER_NAME']);
			return true;
		}

		if ($session_host != $_SERVER['SERVER_NAME'])
			return false;
			
		return true;
	}

	/**
	 * Returns a user login name saved during last login.
	 * @param boolean $html Indicates whether the result value must be prepared for HTML output.
	 * @return string
	 */
	public function get_saved_login_name($html = true)
	{
		$cookie_name = Phpr::$config->get('LOGIN_COOKIE_NAME', $this->login_cookie_name);
		$result = Phpr::$request->cookie($cookie_name);

		return $html ? Phpr_Html::encode($result) : $result;
	}

	/**
	 * Indicates whether a login name was saved during last login.
	 * @return boolean
	 */
	public function is_login_name_saved()
	{
		$cookie_name = Phpr::$config->get('LOGIN_COOKIE_NAME', $this->login_cookie_name);
		return Phpr::$request->cookie($cookie_name) !== null;
	}

	/**
	 * Updates or deleted the user login name in a cookie
	 * @param string $login Specifies the user login name
	 */
	protected function update_login_name($save, $login)
	{
		$cookie_name = Phpr::$config->get('LOGIN_COOKIE_NAME', $this->login_cookie_name);
		$cookie_path = Phpr::$config->get('AUTH_COOKIE_PATH', $this->cookie_path);
		$cookie_domain = Phpr::$config->get('AUTH_COOKIE_DOMAIN', $this->cookie_domain);

		if ($save)
			Phpr::$response->cookie($cookie_name, $login, 365, $cookie_path, $cookie_domain);
		else
			Phpr::$response->delete_cookie($cookie_name, $cookie_path, $cookie_domain);
	}

	/**
	 * Creates or updates the user authentication ticket
	 * @param int $id Specifies the user identifier.
	 */
	protected function update_cookie($id)
	{
		/*
		 * Prepare the authentication ticket
		 */
		$ticket = $this->get_ticket($id);

		/*
		 * Set a cookie
		 */
		$cookie_name = Phpr::$config->get('AUTH_COOKIE_NAME', $this->cookie_name);
		$cookie_lifetime = Phpr::$config->get($this->cookie_lifetime_name, $this->cookie_lifetime);
		$cookie_path = Phpr::$config->get('AUTH_COOKIE_PATH', $this->cookie_path);
		$cookie_domain = Phpr::$config->get('AUTH_COOKIE_DOMAIN', $this->cookie_domain);

		Phpr::$response->cookie($cookie_name, $ticket, $cookie_lifetime, $cookie_path, $cookie_domain);
		$this->cookies_updated = true;
	}

	/*
	 * Returns the authorization ticket for a specified user
	 * @param int $id Specifies a user identifier
	 * @return string
	 */
	public function get_ticket($id = null)
	{
		if ($id === null)
		{
			$user = $this->get_user();
			if (!$user)
				return null;

			$id = $user->id;
		}

		$lifetime = Phpr::$config->get($this->cookie_lifetime_name, $this->cookie_lifetime);
		$lifetime = $lifetime > 0 ? $lifetime * 24 * 3600 : 3600;
		
		$expiration = time() + $lifetime;

		$key = hash_hmac('md5', $id.$expiration, Phpr_SecurityFramework::create()->salt());
		$hash = hash_hmac('md5', $id.$expiration, $key);
		$ticket = base64_encode(base64_encode($id).'|'.$expiration.'|'.$hash);

		return $ticket;
	}

	/**
	 * Validates authorization ticket
	 * @param string $ticket Specifies an authorization ticket
	 * @return array Returns parsed ticket information if it is valid or null
	 */
	public function validate_ticket($ticket, $cache_ticket = false)
	{
		if ($cache_ticket)
			$this->_ticket = $ticket;
			
		$ticket = base64_decode($ticket);

		$parts = explode('|', $ticket);
		if (count($parts) < 3)
			return null;

		list($id, $expiration, $hmac) = explode('|', $ticket);

		$id_decoded = base64_decode($id);
		
		if ($expiration < time())
			return null;

		$key = hash_hmac('md5', $id_decoded.$expiration, Phpr_SecurityFramework::create()->salt());
		$hash = hash_hmac('md5', $id_decoded.$expiration, $key);

		if ($hmac != $hash)
			return null;

		return array('user' => $id);
	}

	/**
	 * Checks a user object before logging in
	 * @param mixed $user Specifies a user to check
	 */
	protected function check_user($user)
	{
		
	}
	
	protected function after_login($user)
	{
	}

	protected function before_login_session_destroy($user)
	{
	}
	
	protected function keep_session_data()
	{
		return false;
	}
	
	public function store_ticket()
	{
		$ticket_exists = true;
		$ticket_id = null;
		
		while ($ticket_exists) {
			$ticket_id = str_replace('.', '', uniqid('', true));
			$ticket_exists = Db_Helper::scalar('select count(*) from db_saved_tickets where ticket_id=:ticket_id', array('ticket_id'=>$ticket_id));
		}
		
		$bind = array(
			'ticket_id'=>$ticket_id,
			'ticket_data'=>$this->get_ticket()
		);

		Db_Helper::query('insert into db_saved_tickets(ticket_id, ticket_data, created_at) values (:ticket_id, :ticket_data, NOW())', $bind);
			
		return $ticket_id;
	}
	
	public function restore_ticket($ticket_id)
	{
		$ticket_id = trim($ticket_id);
		
		if (!strlen($ticket_id))
			return null;
		
		$ttl = (int)Phpr::$config->get('STORED_SESSION_TTL', 3);

		Db_Helper::query('delete from db_saved_tickets where created_at < DATE_SUB(now(), INTERVAL :seconds SECOND)', array('seconds'=>$ttl));
		
		$data = Db_Helper::scalar('select ticket_data from db_saved_tickets where ticket_id=:ticket_id', array('ticket_id'=>$ticket_id));
		if (!$data)
			return null;
			
		Db_Helper::query('delete from db_saved_tickets where ticket_id=:ticket_id', array('ticket_id'=>$ticket_id));
			
		return $data;
	}

	public function remove_ticket($ticket_id)
	{
		$ticket_id = trim($ticket_id);
		
		if (!strlen($ticket_id))
			return null;

		Db_Helper::query('delete from db_saved_tickets where ticket_id=:ticket_id', array('ticket_id'=>$ticket_id));
	}	
}
