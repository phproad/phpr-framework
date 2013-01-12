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

class Db_Model_Dynamic extends Phpr_Extension_Base
{

    protected $_model;
    public $added_dynamic_fields = array();
    public $added_dynamic_columns = array();
    public $dynamic_model_field = "config_data";

    public function __construct($model)
    {
        $this->_model = $model;

        if (isset($model->dynamic_model_field))
            $this->dynamic_model_field = $model->dynamic_model_field;

        $this->_model->add_event('onAfterLoad', $this, 'load_dynamic_data');
        $this->_model->add_event('onBeforeUpdate', $this, 'set_dynamic_data');
        $this->_model->add_event('onBeforeCreate', $this, 'set_dynamic_data');
    }

    public function define_dynamic_column($code, $title, $type = db_text)
    {
        return $this->added_dynamic_columns[$code] = $this->_model->define_custom_column($code, $title, $type);
    }

    public function add_dynamic_field($code, $side = 'full')
    {
        return $this->added_dynamic_fields[$code] = $this->_model->add_form_field($code, $side)->optionsMethod('get_added_field_options');
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
            Phpr_Xml::create_dom_element($document, $field_element, 'id', $field_id);
            Phpr_Xml::create_dom_element($document, $field_element, 'value', $value, true);            
        }

        $dynamic_field = $this->dynamic_model_field;
        $this->_model->{$dynamic_field} = $document->asXML();
    }

    public function load_dynamic_data()
    {
        $dynamic_field = $this->dynamic_model_field;

        if (!strlen($this->_model->{$dynamic_field}))
            return;

        $object = new SimpleXMLElement($this->_model->{$dynamic_field});
        foreach ($object->children() as $child)
        {
            $field_id = (string)$child->id;
            try 
            {
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
