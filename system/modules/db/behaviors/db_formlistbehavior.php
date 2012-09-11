<?php

/**
 * Form list behavior combines Db_FormBehavior and Db_ListBehavior to allow
 * lists to be used inside forms.
 * 
 * Usage: Add to controller
 *
 * public $list_name = null;
 * public $list_custom_prepare_func = null;
 * public $list_custom_body_cells = null;
 * public $list_custom_head_cells = null;
 * public $list_custom_partial = null;
 * public $list_search_prompt = null;
 *
 * public $form_lists = array(
 *     'pages' => array(
 *         'class_name' => 'Cms_Page',
 *         'columns' => array('name'),
 *         'search_fields' => array('@name'),
 *         'search_prompt' => 'find pages by name',
 *         'no_data_message' => 'This template has no pages',
 *         'record_url' => '/cms/pages/edit/%s',
 *         'control_panel' => 'page_control_panel'
 *      )
 *  );
 *
 * public function preview_formBeforeRender($model)
 * {
 *     $this->form_list_model_define($model);
 * }
 *
 * // Optional
 * public function form_list_prepare_pages($parent_id)
 * {
 *     return Cms_Page::create();
 * }
 *
 *  Notes:
 *
 *  Make sure the parent constructor call appears
 *  after all the definitions (not before)
 *
 *  Make sure the preview partial does not contain
 *  form tags
 *
 */

class Db_FormListBehavior extends Phpr_ControllerBehavior
{
    public $form_lists = array();
    public $form_list_active = null;

    public $form_list_options = array();
    public $form_list_default_options = array(
        'form_tab' => null,
        'add_form_field' => true,
        'columns' => null,
        'search_fields' => null,
        'search_enabled' => false,
        'search_prompt' => 'search...',
        'no_data_message' => 'No results',
        'record_url' => null,
        'control_panel' => false,
        'record_onclick' => null,
        'conditions' => null,
        'show_checkboxes' => false,
        'custom_body_cells' => null,
        'custom_head_cells' => null,
        'custom_partial' => null,
        'no_pagination' => false,
        'items_per_page' => 10,
        'manage_popup' => false,
        'manage_popup_title' => 'Object',

        'manage_popup_context' => null,       // Optional
        'manage_popup_foreign_key' => null,   // Optional
        'manage_popup_relation_type' => null, // Optional
        'manage_popup_relation_name' => null, // Optional
    );

    public function __construct($controller)
    {
        parent::__construct($controller);

        $controller->form_register_view_path(PATH_SYSTEM.'/modules/db/behaviors/db_formlistbehavior/partials');

        $this->add_event_handler('on_form_list_manage_form');
        $this->add_event_handler('on_form_list_manage_update');
        $this->add_event_handler('on_form_list_manage_delete');

        $this->form_list_apply_defaults();
        $this->form_list_set_relations();

        if (post('form_list_name'))
        {
            $this->form_list_init(post('form_list_name'));
        }
    }

    // Get definition and apply defaults
    private function form_list_apply_defaults()
    {
        $this->form_lists = isset($this->_controller->form_lists) ? $this->_controller->form_lists : $this->form_lists;
        foreach ($this->form_lists as $name=>$list)
        {
            $this->form_lists[$name] = array_merge($this->form_list_default_options, $this->form_lists[$name]);
            if ($this->form_lists[$name]['show_checkboxes'])
            {
                $this->form_lists[$name]['custom_body_cells'] = PATH_SYSTEM.'/modules/db/behaviors/db_listbehavior/partials/_list_body_cb.htm';
                $this->form_lists[$name]['custom_head_cells'] = PATH_SYSTEM.'/modules/db/behaviors/db_listbehavior/partials/_list_head_cb.htm';
            }

            if ($this->form_lists[$name]['manage_popup'])
            {
                $this->form_lists[$name]['record_onclick'] = "new PopupForm('".$this->_controller->get_event_handler('on_form_list_manage_form')."', { ajaxFields: {primary_id: '%s', form_list_name: '".$name."' } }); return false;";
            }
        }
    }

    // Try to determine foreign key, relation name and relation type automatically
    private function form_list_set_relations()
    {
        $model = $this->_controller->formCreateModelObject();
        foreach ($this->form_lists as $name=>$list) {

            if (!$this->form_lists[$name]['manage_popup_relation_name'])
                $this->form_lists[$name]['manage_popup_relation_name'] = $name;

            if ($this->form_lists[$name]['manage_popup_relation_type'])
                continue;

            $relation_name = $this->form_lists[$name]['manage_popup_relation_name'];

            if (!isset($model->has_models[$relation_name]))
                continue;

            $relation_type = $model->has_models[$relation_name];
            $this->form_lists[$name]['manage_popup_relation_type'] = $relation_type;

            if ($relation_type == "has_many")
            {
                if ($this->form_lists[$name]['manage_popup_foreign_key'])
                    continue;

                if (isset($model->has_many[$relation_name]['foreign_key']))
                    $foreign_key = $model->has_many[$relation_name]['foreign_key'];
                else
                    $foreign_key = Phpr_Inflector::singularize($model->table_name) . "_" . $model->primary_key;

                $this->form_lists[$name]['manage_popup_foreign_key'] = $foreign_key;
            }
        }
    }

    public function form_list_render($name)
    {
        $this->render_partial('form_list_container', array(
            'form_list_name'=>$name
        ));
    }

    public function form_list_get_form_id($name)
    {
        return "form_list_form_".$name;
    }

    public function form_list_get_id($name)
    {
        return "form_list_".$name;
    }

    public function form_list_get_options($name)
    {
        $list = (object)$this->form_lists[$name];
        return $this->form_list_options = array(
            'form_list_name' => $name,
            //'form_list_manage_popup' => $list->manage_popup,
            'list_name' => $this->form_list_get_id($name),
            'list_model_class' => $list->class_name,
            'list_columns' => $list->columns,
            'list_search_enabled' => $list->search_enabled,
            'list_search_fields' => $list->search_fields,
            'list_search_prompt' => $list->search_prompt,
            'list_control_panel' => null,
            'list_custom_prepare_func' => 'form_list_prepare',
            'list_no_setup_link' => true,
            'list_no_data_message' => $list->no_data_message,
            'list_no_pagination' => $list->no_pagination,
            'list_no_form' => true,
            'list_items_per_page' => $list->items_per_page,
            'list_record_url' => ($list->record_url) ? url($list->record_url) : null,
            'list_record_onclick' => $list->record_onclick,
            'list_custom_body_cells' => $list->custom_body_cells,
            'list_custom_head_cells' => $list->custom_head_cells,
            'list_custom_partial' => $list->custom_partial,
            'list_control_panel' => $list->control_panel,
        );
    }

    public function form_list_init($name)
    {
        if (!isset($this->form_lists[$name]))
            throw new Phpr_SystemException('Missing definition for '.$name);

        $list_options = $this->form_list_get_options($name);

        foreach ($list_options as $option=>$value)
        {
            $this->_controller->$option = $value;
        }
    }

    public function form_list_prepare($model, $options)
    {
        if (!isset($options['form_list_name']) && !post('form_list_name'))
            return $model;

        $id = Phpr::$router->param('param1');
        $name = (post('form_list_name')) ? post('form_list_name') : $options['form_list_name'];

        if ($this->controllerMethodExists('form_list_prepare_'.$name))
        {
            $model = $this->_controller->{'form_list_prepare_'.$name}($id);
        }
        else
        {
            $list = (object)$this->form_lists[$name];
            if ($list->conditions)
                $model->where($list->conditions, array('id' => $id));
        }

        return $model;
    }

    public function form_list_model_define($model)
    {
        foreach ($this->form_lists as $list_name=>$list)
        {
            $add_field = $this->form_lists[$list_name]['add_form_field'];
            if (!$add_field)
                continue;

            $list = (object)$list;
            $field = $model->add_form_custom_area('form_list');
            if ($list->form_tab)
                $field->tab($list->form_tab);
        }
    }

    public function form_list_get_active()
    {
        $list_keys = array_keys($this->form_lists);

        if ($this->form_list_active===null)
            $this->form_list_active = 0;
        else
            $this->form_list_active++;

        $list_name = $list_keys[$this->form_list_active];

        // Recurse if we dont add a form field
        $add_field = $this->form_lists[$list_name]['add_form_field'];
        if (!$add_field)
            return $this->form_list_get_active();

        return $list_name;
    }

    // Management popup
    //

    public function on_form_list_manage_form()
    {
        try
        {
            $model_id = post('primary_id', null);
            $list_name = post('form_list_name', null);

            $this->_controller->resetFormEditSessionKey();

            $model_context = $this->form_lists[$list_name]['manage_popup_context'];
            $model_class = $this->form_model_class = $this->form_lists[$list_name]['class_name'];
            $model = call_user_func(array($model_class, 'create'));
            if ($model_id)
                $model = $model->find($model_id);

            $model->define_form_fields($model_context);
            $this->view_data['model'] = $model;
            $this->view_data['form_list_name'] = $list_name;
            $this->view_data['new_record_flag'] = !($model_id);
            $this->view_data['insert_action'] = $this->_controller->get_event_handler('on_form_list_manage_update');
            $this->view_data['delete_action'] = $this->_controller->get_event_handler('on_form_list_manage_delete');
            $this->view_data['manage_popup_title'] = $this->form_lists[$list_name]['manage_popup_title'];
        }
        catch (Exception $ex)
        {
            $this->_controller->handle_page_error($ex);
        }

        $this->render_partial('form_list_popup_form');
    }

    public function on_form_list_manage_update($master_object_id=null)
    {
        try
        {
            $master_object_class = $this->_controller->form_model_class;
            $master_object = new $master_object_class();
            $master_object = $master_object->find($master_object_id);

            if (!$master_object)
                throw new Exception("Could not find master object");

            $model_id = post('primary_id');
            $list_name = post('form_list_name');

            $model_class = $this->form_model_class = $this->form_lists[$list_name]['class_name'];
            $model = call_user_func(array($model_class, 'create'));

            $foreign_key = $this->form_lists[$list_name]['manage_popup_foreign_key'];
            $relation_type = $this->form_lists[$list_name]['manage_popup_relation_type'];
            $relation_name = $this->form_lists[$list_name]['manage_popup_relation_name'];

            if ($model_id)
            {
                $model = $model->find($model_id);
            }
            else if ($foreign_key !== null && $relation_type == "has_many")
            {
                $model->{$foreign_key} = $master_object_id;
            }

            $data = post($model_class, array());

            //$model->master_object_id = $master_object_id;
            //$model->master_object_class = $this->_controller->form_model_class;

            $model->init_columns_info();
            $model->define_form_fields();

            if ($this->controllerMethodExists('form_list_before_save_'.$list_name))
                $this->_controller->{'form_list_before_save_'.$list_name}($model, $data, $master_object);

            $model->save($data);

            // Is new record (!$model_id)
            if (!$model_id && $relation_type == "has_and_belongs_to_many")
            {
                $master_object->{$relation_name}->delete($model);
                $master_object->{$relation_name}->add($model);
                $master_object->save();
            }

            $this->form_list_init($list_name);
            echo $this->form_list_render($list_name);
        }
        catch (Exception $ex)
        {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    public function on_form_list_manage_delete($master_object_id=null)
    {
        try
        {
            $master_object_class = $this->_controller->form_model_class;
            $master_object = new $master_object_class();
            $master_object = $master_object->find($master_object_id);

            if (!$master_object)
                throw new Exception("Could not find master object");

            $model_id = post('primary_id', null);
            $list_name = post('form_list_name', null);

            if (!$model_id)
                throw new Phpr_ApplicationException("Missing item or item has already been deleted");

            $relation_type = $this->form_lists[$list_name]['manage_popup_relation_type'];
            $relation_name = $this->form_lists[$list_name]['manage_popup_relation_name'];

            $model_class = $this->form_model_class = $this->form_lists[$list_name]['class_name'];
            $model = call_user_func(array($model_class, 'create'));
            $model = $model->find($model_id);

            if ($relation_type == "has_and_belongs_to_many")
            {
                $master_object->{$relation_name}->delete($model);
                $master_object->save();
            }
            else
                $model->delete();

            $this->form_list_init($list_name);
            echo $this->form_list_render($list_name);

        }
        catch (Exception $ex)
        {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }
}