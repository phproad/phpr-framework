<?php namespace Db;

use Phpr\Extension;

/**
 * Eventful behavior
 *
 * Adds standard extensible events to a modal 
 *
 * Events generated:
 * 
 * PREFIX:on_extend_NAME_model
 * PREFIX:on_extend_NAME_form
 * PREFIX:on_get_NAME_field_options
 * PREFIX:on_after_load_NAME
 * PREFIX:on_before_update_NAME
 * PREFIX:on_before_create_NAME
 * PREFIX:on_before_save_NAME
 *
 * Usage:
 *
 * public $implement = 'Db\Model_Eventful';
 * 
 * public $eventful_model_prefix = "location";
 * public $eventful_model_name = "country";
 *
 */

class Model_Eventful extends Extension
{
	protected $_model;
	protected $api_added_columns = array();
	private $_prefix;
	private $_name;

	public function __construct($model)
	{
		$this->_model = $model;

		if (isset($model->eventful_model_prefix) && isset($model->eventful_model_name)) {
			// Allow manual setting
			$this->_prefix = $model->eventful_model_prefix;
			$this->_name = $model->eventful_model_name;
		}
		else {
			// Detects the prefix and name from the model class name
			// Location_Country = prefix: location | name: country
			// Service_Quote_Status  = prefix: service | name: quote_status
			$model_class = get_class($model);
			$first_break_pos = strpos($model_class, '_');
			$this->_prefix = strtolower(substr($model_class, 0, $first_break_pos));
			$this->_name = strtolower(substr($model_class, $first_break_pos+1));
		}

		$this->_model->add_event('db:on_define_columns', $this, 'eventful_model_define_columns');
		$this->_model->add_event('db:on_after_load', $this, 'eventful_model_after_load');
		$this->_model->add_event('db:on_before_update', $this, 'eventful_model_before_update');
		$this->_model->add_event('db:on_before_create', $this, 'eventful_model_before_create');
		$this->_model->add_event('db:on_define_form_fields', $this, 'eventful_model_define_form_fields');
	}

	public function eventful_model_after_load()
	{
		Phpr::$events->fire_event($this->_prefix.':on_after_load_'.$this->_name, $this->_model);
	}

	public function eventful_model_before_update($deferred_session_key = null)
	{
		Phpr::$events->fire_event($this->_prefix.':on_before_update_'.$this->_name, $this->_model, $deferred_session_key);
		Phpr::$events->fire_event($this->_prefix.':on_before_save_'.$this->_name, $this->_model, $deferred_session_key);
	}

	public function eventful_model_before_create($deferred_session_key = null)
	{
		Phpr::$events->fire_event($this->_prefix.':on_before_create_'.$this->_name, $this->_model, $deferred_session_key);
		Phpr::$events->fire_event($this->_prefix.':on_before_save_'.$this->_name, $this->_model, $deferred_session_key);
	}

	public function eventful_model_define_columns($context = null)
	{

		$this->_model->defined_column_list = array();
		Phpr::$events->fire_event($this->_prefix.':on_extend_'.$this->_name.'_model', $this->_model, $context);
		$this->api_added_columns = array_keys($this->_model->defined_column_list);
	}

	public function eventful_model_define_form_fields($context = null)
	{
		Phpr::$events->fire_event($this->_prefix.':on_extend_'.$this->_name.'_form', $this->_model, $context);
		foreach ($this->api_added_columns as $column_name)
		{
			$form_field = $this->_model->find_form_field($column_name);
			if ($form_field)
				$form_field->options_method('get_added_field_options');
		}
	}

	public function eventful_model_get_added_field_options($db_name, $current_key_value = -1)
	{
		$result = Phpr::$events->fire_event($this->_prefix.':on_get_'.$this->_name.'_field_options', $db_name, $current_key_value);
		foreach ($result as $options)
		{
			if (is_array($options) || (strlen($options && $current_key_value != -1)))
				return $options;
		}

		return false;
	}
}
