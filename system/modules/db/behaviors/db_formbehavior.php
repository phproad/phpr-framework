<?php

class Db_FormBehavior extends Phpr_ControllerBehavior
{
    public $form_file_model_class = 'Db_File';

    public function __construct($controller)
    {
        parent::__construct($controller);

        if (!$controller)
            return;

        $this->form_load_assets();

        $this->add_event_handler('on_dropdown_create_popup');
        $this->add_event_handler('on_dropdown_create_submit');        
    }

    public function form_register_view_path($path)
    {
        $this->register_view_path($path);
    }

    public function form_render_field_container($model, $db_name)
    {
    }

    public function form_get_edit_session_key()
    {

    }

    public function reset_form_edit_session_key()
    {

    }

    /**
     * Dropdown Create
     */

    public function on_dropdown_create_popup()
    {
        try
        {
            $this->view_data['field_name'] = $field_name = post('field_name');
            $this->view_data['db_name'] = $db_name = post('db_name');
            $this->view_data['parent_model_class'] = $parent_model_class = $this->_controller->form_model_class;

            $this->form_model_class = $model_class = post('model_class');
            if (!$model_class)
                throw new Exception("Model class missing");

            $this->_controller->reset_form_edit_session_key();

            $model = new $model_class();
            $model->define_form_fields();

            $this->view_data['form_model'] = $model;
            $parent_model = new $parent_model_class();
            $parent_model->init_columns_info();
            $parent_model->define_form_fields();
            $this->view_data['parent_model'] = $parent_model;

        }
        catch (Exception $ex)
        {
            $this->_controller->handle_page_error($ex);
        }

        $this->render_partial('form_dropdown_create');
    }

    public function on_dropdown_create_submit($id=null)
    {
        try
        {
            $model_class = post('model_class');
            $parent_model_class = post('parent_model_class');
            $field_name = post('field_name');
            $db_name = post('db_name');

            if (!$model_class||!$parent_model_class)
                throw new Exception("Model classes missing");

            // Create our new child object
            $form_model = new $model_class();
            $form_model->init_columns_info();
            $form_model->define_form_fields();
            $form_model->save(post($model_class), $this->form_get_edit_session_key());

            // Populate the parent object with our new child
            $parent_obj = new $parent_model_class();
            $parent_obj->find($id);

            // Required to render the field container
            $parent_obj->init_columns_info();
            $parent_obj->define_form_fields();

            $parent_obj->$db_name = $form_model->id;

            // Fill our container
            $field_container_id = "form_field_container_".$db_name.$parent_model_class;
            echo ">>".$field_container_id."<<";

            // Render the field
            $this->form_render_field_container($parent_obj, $field_name);
        }
        catch (Exception $ex)
        {
            Phpr::$response->ajax_report_exception($ex, true, true);
        }
    }

    protected function form_load_assets()
    {
        $phpr_url = '/' . Phpr::$config->get('PHPR_URL', 'system');
        $this->_controller->add_javascript($phpr_url.'/modules/db/behaviors/db_formbehavior/resources/javascript/tags.js');
    }
}