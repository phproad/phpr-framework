<?php

/**
 * PHPR user base class.
 *
 * Use this class to manage the application user list.
 */
class Phpr_User extends Db_ActiveRecord
{
	public $table_name = "users";
	public $primary_key = 'id';
	// public $has_and_belongs_to_many = array('groups'=>array('class_name'=>'Phpr_Group'));

	private $authorization_cache = array();

	/**
	 * Finds a user by the login name and password.
	 * @param string $login Specifies the user login name
	 * @param string $password Specifies the user password
	 * @return Phpr_User Returns the user instance or null
	 */
	public function find_user($login, $password)
	{
		return $this->where('login = lower(?)', $login)->where('password = ?', md5($password))->find();
	}

	/**
	 * Finds a user by the login name.
	 * @param string $login Specifies the user login name
	 * @return Phpr_User Returns the user instance or null
	 */
	public function find_user_by_login($login)
	{
		return $this->where('login = lower(?)', $login)->find();
	}

	/**
	 * Determines whether the user is allowed to have access to a specified resource.
	 * @param string $module Specifies the name of a module that owns the resource ("blog").
	 *
	 * @param string $resource Specifies the name of a recource ("post").
	 * You may omit this parameter to determine if user has access rights to any module resource.
	 *
	 * @param string $object Specifies the resource object ("1").
	 * You may omit this parameter to determine if user has accssess rights to any object in context of specified module resource.
	 *
	 * @return mixed
	 */
	public function authorize($module, $resource = null, $object = null)
	{
		$cache_resource = $resource === null ? '_NULL_' : $resource;
		$cache_object = $object === null ? '_NULL_' : $object;

		if (isset($this->authorization_cache[$module][$cache_resource][$cache_object]))
			return $this->authorization_cache[$module][$cache_resource][$cache_object];

		if ($object !== null)
			$result = self::$db->fetch_one(self::$db->select()->from('rights', 'MAX(Value)')
				->joinInner('groups_users', 'groups_users.group_id=rights.group_id')
				->where('groups_users.user_id=?', $this->id)
				->where('rights.module=?', $module)
				->where('rights.Resource=?', $resource)
				->where('rights.Object=?', $object));
		else
			if ($resource != null)
				$result = self::$db->fetch_one(self::$db->select()->from('rights', 'MAX(Value)')
					->joinInner('groups_users', 'groups_users.group_id=rights.group_id')
					->where('groups_users.user_id=?', $this->id)
					->where('rights.module=?', $module)
					->where('rights.Resource=?', $resource));
			else
				$result = self::$db->fetch_one(self::$db->select()->from('rights', 'MAX(Value) as RightValue')
					->joinInner('groups_users', 'groups_users.group_id=rights.group_id')
					->where('groups_users.user_id=?', $this->id)
					->where('rights.module=?', $module));

		if (!isset($this->authorization_cache[$module]))
			$this->authorization_cache[$module] = array();

		if (!isset($this->authorization_cache[$module][$cache_resource]))
			$this->authorization_cache[$module][$cache_resource] = array();

		$this->authorization_cache[$module][$cache_resource][$cache_object] = $result;

		return $result;
	}

	/**
	 * Returns the user name in format first name last name
	 */
	public function get_name()
	{
		return $this->first_name.' '.$this->last_name;
	}
}