<?php namespace Db;

use Phpr\Extension;

/**
 * Adds created_at, update_at, created_user_name, updated_user_name invisible columns to model
 */
class AutoFootprints extends Extension
{
	protected $_model;
	
	public $auto_footprints_visible = false;
	public $auto_footprints_default_invisible = true;
	public $auto_footprints_created_at_name = 'Created At';
	public $auto_footprints_created_user_name = 'Created By';
	public $auto_footprints_updated_at_name = 'Updated At';
	public $auto_footprints_updated_user_name = 'Updated By';
	public $auto_footprints_date_format = '%x %H:%M';
	public $auto_footprints_user_model = 'Admin_User';
	public $auto_footprints_user_name_fields = array('first_name', 'last_name');

	public $auto_footprints_user_not_found_name = '';
	
	public function __construct($model)
	{
		parent::__construct();

		$this->_model = $model;
		$this->_model->add_event('db:on_define_columns', $this, 'auto_footprints_columns_defined');
	}
	
	public function auto_footprints_columns_defined()
	{
		if (ActiveRecord::$execution_context == 'front-end')
			return;
		
		$user_model = new $this->auto_footprints_user_model();
		$user_table = $user_model->table_name;			

		$this->_model->add_relation('belongs_to', 'updated_user', array('class_name' => $user_model));
		
		$has_update_fields = $this->_model->column('updated_user_id');
		
		$model_table = $this->_model->table_name;

		$name_string = "trim(concat(";
		foreach ($this->auto_footprints_user_name_fields as $key => $field)
		{
			$name_string .= "ifnull(" . $field . ", '')";
			$name_string .= ($key == count($this->auto_footprints_user_name_fields)-1) ? '' : ", ' ', "; 
		}
		$name_string .= "))";
		
		if ($has_update_fields)
			$this->_model->calculated_columns['updated_user_name'] = "select $name_string from $user_table where {$user_table}.id={$model_table}.updated_user_id";
			
		$this->_model->calculated_columns['created_user_name'] = "trim(ifnull((select $name_string from $user_table where {$user_table}.id={$model_table}.created_user_id), '{$this->_model->auto_footprints_user_not_found_name}'))";

		$field = $this->_model->define_column('created_at', $this->_model->auto_footprints_created_at_name)->date_format($this->_model->auto_footprints_date_format);
		if (!$this->_model->auto_footprints_visible)
			$field->invisible();
		if ($this->_model->auto_footprints_default_invisible)
			$field->default_invisible();

		$field = $this->_model->define_column('created_user_name', $this->_model->auto_footprints_created_user_name)->no_log()->type(db_varchar);
		if (!$this->_model->auto_footprints_visible)
			$field->invisible();
		if ($this->_model->auto_footprints_default_invisible)
			$field->default_invisible();

		if ($has_update_fields)
		{
			$field = $this->_model->define_column('updated_at', $this->_model->auto_footprints_updated_at_name)->date_format($this->_model->auto_footprints_date_format);
			if (!$this->_model->auto_footprints_visible)
				$field->invisible();
			if ($this->_model->auto_footprints_default_invisible)
				$field->default_invisible();
		}
		
		if ($has_update_fields)
		{
			$field = $this->_model->define_column('updated_user_name', $this->_model->auto_footprints_updated_user_name)->no_log();
			if (!$this->_model->auto_footprints_visible)
				$field->invisible();
			if ($this->_model->auto_footprints_default_invisible)
				$field->default_invisible();
		}
	}
}
