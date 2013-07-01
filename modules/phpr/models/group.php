<?php namespace Phpr;

use Db\ActiveRecord;

/**
 * PHPR group base class.
 *
 * Use this class to manage the application user groups.
 */
class Group extends ActiveRecord
{
	public $table_name = 'groups';
	public $primary_key = 'id';
	public $has_and_belongs_to_many = array('users'=>array('class_name'=>'Phpr_User'));

	/**
	 * Group database identifier.
	 * @var int
	 */
	public $id;

	/**
	 * Group name.
	 * @var string
	 */
	public $name = '';
}