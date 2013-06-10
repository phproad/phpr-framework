<?php

/**
 * Dynamic Column Extension
 *
 * Adds a special field type dynamic_data that stores flexible data.
 *
 * Usage:
 *
 * public $implement = 'Db_Model_Dynamic';
 *
 */

class Db_Model_Dynamic extends Phpr_Extension
{

	protected $_model;
	protected $_field_name = "config_data";
	
	public $added_dynamic_fields = array();
	public $added_dynamic_columns = array();

	public function __construct($model)
	{
		parent::__construct();
		
		$this->_model = $model;

		if (isset($model->dynamic_model_field))
			$this->_field_name = $model->dynamic_model_field;

		$this->_model->add_event('db:on_after_load', $this, 'load_dynamic_data');
		$this->_model->add_event('db:on_before_update', $this, 'set_dynamic_data');
		$this->_model->add_event('db:on_before_create', $this, 'set_dynamic_data');
	}

	public function add_dynamic_field($code, $title, $side = 'full', $type = db_text)
	{
		$this->define_dynamic_column($code, $title, $type);
		return $this->add_dynamic_form_field($code, $side);
	}

	public function define_dynamic_column($code, $title, $type = db_text)
	{
		return $this->added_dynamic_columns[$code] = $this->_model->define_custom_column($code, $title, $type);
	}

	public function add_dynamic_form_field($code, $side = 'full')
	{
		return $this->added_dynamic_fields[$code] = $this->_model->add_form_field($code, $side)
			->options_method('get_added_field_options')
			->option_state_method('get_added_field_option_state');
	}

	public function set_dynamic_field($field)
	{
		return $this->added_dynamic_columns[$field];
	}

	public function set_dynamic_data()
	{
		$document = new SimpleXMLElement('<data></data>');
		foreach ($this->added_dynamic_columns as $field_id=>$value)
		{
			$value = serialize($this->_model->{$field_id});
			$field_element = $document->addChild('field');
			Phpr_Xml::create_node($document, $field_element, 'id', $field_id);
			Phpr_Xml::create_node($document, $field_element, 'value', $value, true);
		}

		$dynamic_field = $this->_field_name;
		$this->_model->{$dynamic_field} = $document->asXML();
	}

	public function load_dynamic_data()
	{
		$dynamic_field = $this->_field_name;

		if (!strlen($this->_model->{$dynamic_field}))
			return;

		$object = new SimpleXMLElement($this->_model->{$dynamic_field});
		foreach ($object->children() as $child)
		{
			$field_id = (string)$child->id;
			try 
			{
				if (!strlen($field_id))
					continue;
				
				$this->_model->$field_id = unserialize($child->value);
				$this->_model->fetched[$field_id] = unserialize($child->value);
			}
			catch (Exception $ex)
			{
				$this->_model->$field_id = "NaN";
				$this->_model->fetched[$field_id] = "NaN";
				trace_log(sprintf('Db_Model_Dynamic was unable to parse %s in %s', $field_id, get_class($this->_model)));
			}
		}
	}

	/* @deprecated */
	public function define_config_column($code, $title, $type = db_text) { return $this->define_dynamic_column($code, $title, $type); }
	public function add_config_field($code, $side = 'full') { return $this->add_dynamic_field($code, $side); }
	public function set_config_field($field) { return $this->set_dynamic_field($field); }
}
