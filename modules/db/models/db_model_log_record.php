<?php

/**
 * DB Model log record class
 */

class Db_Model_Log_Record extends Db_ActiveRecord
{
    public $table_name = "model_log";

    public $custom_columns = array('message' => db_varchar);

    public $model_log_create_name = 'Created Record';
    public $model_log_update_name = 'Updated Record';
    public $model_log_delete_name = 'Deleted Record';
    public $model_log_custom_name = 'Custom Event';

    protected $data_array = null;

    public static function create() { return new self(); }

    public function before_validation_on_create($deferred_session_key = null)
    {
        $this->record_datetime = Phpr_DateTime::now();
    }
    
    public function define_columns($context = null)
    {
        $this->define_column('id', 'ID');
        $this->define_column('record_datetime', 'Date and Time')->order('desc')->dateFormat('%x %X');
        $this->define_column('message', 'Message');
    }

    public function define_form_fields($context = null)
    {
        $this->add_form_field('message');
    }

    public function eval_message()
    {
        switch ($this->type)
        {
            case Db_Model_Log::type_create: return $this->model_log_create_name; break;
            case Db_Model_Log::type_update: return $this->model_log_update_name; break;
            case Db_Model_Log::type_delete: return $this->model_log_delete_name; break;
            case Db_Model_Log::type_custom: return $this->get_data_value('message', $this->model_log_custom_name); break;
        }
        return "";
    }

    public function is_custom()
    {
        return ($this->type == Db_Model_Log::type_custom);
    }

    // Custom Log Type
    // 

    public function data_as_array()
    {
        if ($this->data_array !== null)
            return $this->data_array;
        
        $result = array();
        if (strlen($this->param_data))
        {
            try
            {
                $document = new DOMDocument();
                $document->loadXML($this->param_data);
                $result = Phpr_Xml::to_plain_array($document, true);
            }
            catch (Exception $ex) 
            {
                // Do nothing
            }
        }
        
        return $this->data_array = $result;
    }

    public function get_data_value($field, $default = null)
    {
        $fields = $this->data_as_array();
        
        if (!array_key_exists($field, $fields))
            return $default;

        return $fields[$field];
    }    
}