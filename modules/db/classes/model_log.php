<?php namespace Db;

use DOMDocument;
use DOMElement;

use Phpr\Extension;
use Phpr\Xml;
use Db\Helper as Db_Helper;

/**
 * Adds logging functionality to model classes.
 * Only fields defined with the method define_column are considered.
 */

class Model_Log extends Extension
{
	const type_create = 'create';
	const type_update = 'update';
	const type_delete = 'delete';
	const type_custom = 'custom';
	
	protected $_model;
	protected $_loaded_data = array();
	protected $_auto_logging = true;
	protected $_disabled = false;
	protected $_max_records = null;
	
	public function __construct($model)
	{
		parent::__construct();
		
		$this->_model = $model;

		if (isset($model->model_log_auto))
			$this->_auto_logging = $model->model_log_auto;

		if (isset($model->model_log_max_records))
			$this->_max_records = $model->model_log_max_records;

		if ($this->_auto_logging)
		{
			$this->_model->add_event('db:on_after_create', $this, 'model_log_created');
			$this->_model->add_event('db:on_after_update', $this, 'model_log_updated');
			$this->_model->add_event('db:on_after_delete', $this, 'model_log_deleted');
		}

		$this->_model->add_event('db:on_after_load', $this, 'model_log_loaded');
	}
	
	public function model_log_created()
	{
		if ($this->_disabled)
			return; 

		$dom = new DOMDocument('1.0', 'utf-8');
		$record = new DOMElement('record');
		$dom->appendChild($record);

		$new_model = $this->get_reloaded_model();

		$new_values = $new_model 
			? $this->get_display_values($new_model) 
			: null;

		foreach ($new_values as $db_name=>$value)
		{
			if (!strlen($value))
				continue;
			
			$display_name = $db_name;
			$type = db_text;
			$this->get_field_name_and_type($db_name, $type, $display_name);

			$field_node = new DOMElement('field');
			$record->appendChild($field_node);
			$field_node->setAttribute('name', $db_name);
			$field_node->setAttribute('display_name', $display_name);
			$field_node->setAttribute('type', $type);

			$new = new DOMElement('new', $value);
			$field_node->appendChild($new);
		}
		
		$this->create_log_record(self::type_create, $dom->saveXML());
	}
	
	public function model_log_updated()
	{
		if ($this->_disabled)
			return; 

		$dom = new DOMDocument('1.0', 'utf-8');
		$record = new DOMElement('record');
		$dom->appendChild($record);

		$new_model = $this->get_reloaded_model();
		
		$new_values = $new_model 
			? $this->get_display_values($new_model) 
			: null;
		
		$fields_added = 0;

		foreach ($this->_loaded_data as $db_name=>$value)
		{
			$new_value = null;
			if (array_key_exists($db_name, $new_values))
				$new_value = $new_values[$db_name];
				
			if (strcmp($value, $new_value) != 0)
			{
				$display_name = $db_name;
				$type = db_text;
				$this->get_field_name_and_type($db_name, $type, $display_name);

				$field_node = new DOMElement('field');
				$record->appendChild($field_node);
				$field_node->setAttribute('name', $db_name);
				$field_node->setAttribute('display_name', $display_name);
				$field_node->setAttribute('type', $type);

				$old = new DOMElement('old', $value);
				$field_node->appendChild($old);

				$new = new DOMElement('new', $new_value);
				$field_node->appendChild($new);
				$fields_added++;
			}
		}

		if ($fields_added)
			$this->create_log_record(self::type_update, $dom->saveXML());
	}
	
	public function model_log_deleted()
	{
		if ($this->_disabled)
			return; 
		
		$this->create_log_record(self::type_delete, null);
		return $this->_model;
	}
	
	public function model_log_loaded()
	{
		$this->_loaded_data = $this->get_display_values();
		return $this->_model;
	}

	public function model_log_custom($name, $params=array())
	{
		$params['message'] = $name;
		$param_data = Xml::from_plain_array($params, 'record', true);
		$this->create_log_record(self::type_custom, $param_data);
		return $this->_model;
	}

	public function model_log_cleanup($number_to_keep = null)
	{
		$this->delete_old_records($number_to_keep);
		return $this->_model;
	}

	public function model_log_disable()
	{
		$this->_disabled = true;
		return $this->_model;
	}

	public function model_log_fetch_all()
	{
		return $this->model_log_find()->find_all();
	}

	public function model_log_find()
	{
		$primary_key = $this->_model->primary_key;
		$records = Model_Log_Record::create();
		$records->where('master_object_class=?', get_class($this->_model));
		$records->where('master_object_id=?', $this->_model->$primary_key);
		return $records;
	}

	// Internals
	// 
	
	private function create_log_record($type, $data)
	{
		$primary_key = $this->_model->primary_key;
		$record = Model_Log_Record::create();
		$record->master_object_class = get_class($this->_model);
		$record->master_object_id = $this->_model->$primary_key;
		$record->param_data = $data;
		$record->type = $type;
		$record->save();
	}

	private function delete_old_records($number_to_keep = null)
	{
		if (!$number_to_keep && ($this->_max_records === null || $this->_max_records = 0))
			return;

		if (!$number_to_keep)
			$number_to_keep = $this->_max_records;
		
		$primary_key = $this->_model->primary_key;
		$where = 'master_object_class=:class AND master_object_id=:id';
		$bind = array('class'=>get_class($this->_model), 'id'=>$this->_model->$primary_key);

		$count = Db_Helper::scalar('select count(*) from db_model_logs where '.$where, $bind);
		$offset = $count - $number_to_keep;

		if ($offset <= 0)
			return;

		Db_Helper::query('delete from db_model_logs where '.$where.' order by record_datetime limit '.$offset, $bind);
	}

	private function get_display_values($model = null)
	{
		$model = $model ? $model : $this->_model; 
		
		$skip_fields = array_merge(
			$model->auto_create_timestamps, 
			$model->auto_update_timestamps,
			array('created_user_id', 'updated_user_id'));
		
		$result = array();
		$fields = $model->get_column_definitions();
		foreach ($fields as $db_name=>$definition)
		{
			if (!$definition->log)
			{
				if ($definition->is_calculated || 
					$definition->is_custom || 
					in_array($db_name, $skip_fields) || 
					$definition->no_log)
					continue;
			}

			$result[$db_name] = $model->display_field($db_name);
		}
			
		return $result;
	}

	private function get_field_name_and_type($db_name, &$type, &$display_name)
	{
		$fields = $this->_model->get_column_definitions();

		if (array_key_exists($db_name, $fields))
		{
			$display_name = $fields[$db_name]->display_name;
			$type = $fields[$db_name]->type;
			
			if ($fields[$db_name]->is_reference)
			{
				if ($fields[$db_name]->reference_type == 'has_many' || 
					$fields[$db_name]->reference_type == 'has_and_belongs_to_many')
					$type = 'list';
				elseif ($type == db_text || $type == db_varchar)
					$type = null;
			}
		}
	}

	private function get_reloaded_model()
	{
		$class_name = get_class($this->_model);
		$new_model = new $class_name();
		$new_model->simple_caching = false;
		$primary_key = $new_model->primary_key;
		return $new_model->find($this->_model->$primary_key);
	}	
}
