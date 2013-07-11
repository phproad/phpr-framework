<?php namespace Db;

use Phpr\Extension;
use File\Upload;

/**
 * Adds file attachment functionality to models. 
 */

class Model_Attachments extends Extension
{
	protected $_model;
	
	public function __construct($model)
	{
		parent::__construct();
		
		$this->_model = $model;
	}
	
	/**
	 * Adds a file attachments column to the model
	 * @param string $column_name Specify the column name
	 * @param string $display_name Specify the column display name
	 * @param bool $show_in_lists Determines if the field should be hidden from lists
	 * @return Form_Field_Definition
	 */
	public function add_attachments_field($column_name, $display_name, $show_in_lists = false)
	{
		$this->_model->add_relation('has_many', $column_name, array(
			'class_name'  => 'Db_File',
			'foreign_key' => 'master_object_id', 
			'conditions'  => "master_object_class='".get_class_id($this->_model)."' and field='".$column_name."'",
			'order'       => 'sort_order, id',
			'delete'      => true
		));

		$column = $this->_model->define_multi_relation_column($column_name, $column_name, $display_name, '@name');
		
		if (!$show_in_lists)
			$column->invisible();

		$column->validation();
			
		return $this->_model->add_form_field($column_name)->display_as(frm_file_attachments);
	}

	/**
	 * Saves and attaches a postback file to a model
	 * @param string $field Field name for the file relationship
	 * @param array $file_info The file postback array. Eg: $_FILE[$field]
	 * @param bool $delete Determines whether any exisiting attachments should be deleted first
	 * @param string $session_key Defined session key for deferred bindings
	 * @return Db\File
	 */
	public function save_attachment_from_post($field='files', $file_info, $delete = false, $session_key = null)
	{
		if ($session_key === null)
			$session_key = post('session_key');

		if (!array_key_exists('error', $file_info) || $file_info['error'] == UPLOAD_ERR_NO_FILE)
			return;

		Upload::validate_uploaded_file($file_info);

		$this->_model->init_columns();

		if ($delete) 
		{
			$files = $this->_model->get_all_deferred($field, $session_key);
			foreach ($files as $existing_file)
			{
				$this->_model->{$field}->delete($existing_file, $session_key);
			}
		}

		$file = File::create();
		$file->is_public = true;

		$file->from_post($file_info);
		$file->master_object_class = get_class_id($this->_model);
		$file->master_object_id = $this->_model->id;
		$file->field = $field;
		$file->save(null, $session_key);

		$this->_model->{$field}->add($file, $session_key);
		return $file;
	}

}