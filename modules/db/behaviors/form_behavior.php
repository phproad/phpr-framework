<?php namespace Db;

use Phpr;
use Phpr\Util;
use Phpr\Controller_Behavior;
use Phpr\User_Parameters;
use Phpr\Inflector;
use Phpr\Date;
use Phpr\DateTime;
use Phpr\ApplicationException;
use Phpr\SystemException;
use File\Upload;

class Form_Behavior extends Controller_Behavior
{
	public $form_create_title = 'Create';
	public $form_edit_title = 'Edit';
	public $form_preview_title = 'Preview';
	public $form_not_found_message = 'Record not found';

	public $form_model_class = null;
	public $form_redirect = null;
	public $form_create_save_redirect = null;
	public $form_edit_save_redirect = null;
	public $form_delete_redirect = null;
	public $form_edit_save_flash = null;
	public $form_edit_delete_flash = null;
	public $form_create_save_flash = null;
	public $form_edit_save_auto_timestamp = false;
	public $form_create_context_name = null;
	public $form_flash_id = null;
	public $form_no_flash = false;
	public $form_disable_ace = false;

	public $form_preview_mode = false;
	public $form_report_layout_mode = false;
	public $form_unique_prefix = null;
	public $form_file_model_class = 'Db\File';

	public $enable_concurrency_locking = false;

	/**
	 * Specifies a tab type. Supported values are: tabs, sliding.
	 * @var string
	 */
	public $form_tabs_type = 'tabs';

	protected $_model = null;
	protected $_edit_session_key = null;
	protected $_context = null;
	protected $_widgets = array();

	public function __construct($controller)
	{
		parent::__construct($controller);
	}

	public function init_extension()
	{
		if (!$this->_controller)
			return;

		$this->form_load_resources();

		$this->hide_action('create_on_save');
		$this->hide_action('edit_on_save');
		$this->hide_action('create_on_cancel');
		$this->hide_action('edit_on_cancel');
		
		$this->hide_action('edit_form_before_display');
		$this->hide_action('create_form_before_display');
		$this->hide_action('preview_form_before_display');
		$this->hide_action('form_before_create_save');
		$this->hide_action('form_before_edit_save');
		$this->hide_action('form_before_save');
		$this->hide_action('form_after_edit_save');
		$this->hide_action('form_after_delete');

		$this->add_event_handler('on_update_file_list');
		$this->add_event_handler('on_delete_file');
		$this->add_event_handler('on_preview_popup');
		$this->add_event_handler('on_find_form_record');
		$this->add_event_handler('on_set_record_finder_record');
		$this->add_event_handler('on_set_form_files_order');
		$this->add_event_handler('on_show_file_description_form');
		$this->add_event_handler('on_save_form_file_description');
		$this->add_event_handler('on_form_toggle_collapsable_area');
		$this->add_event_handler('on_form_widget_event');
		$this->add_event_handler('on_dropdown_create_form_load');
		$this->add_event_handler('on_dropdown_create_form_create');

		$this->_controller->add_public_action('form_file_upload');

		if (post('record_finder_flag'))
			$this->form_prepare_record_finder_list();
	}

	//
	// Asset management
	// 

	protected function form_load_resources()
	{
		if (Phpr::$request->is_remote_event())
			return;
		
		$phpr_url = '/' . Phpr::$config->get('PHPR_URL', 'phpr');
		
		$this->_controller->add_javascript($phpr_url.'/vendor/redactor/jquery.redactor.js');
		$this->_controller->add_css($phpr_url.'/vendor/redactor/redactor.css?'.module_build('core'));

		if (!$this->_controller->form_disable_ace) {
			$this->_controller->add_javascript($phpr_url.'/vendor/ace/ace.js?'.module_build('core'));
			$this->_controller->add_javascript($phpr_url.'/modules/db/behaviors/form_behavior/assets/scripts/js/ace_wrapper.js?'.module_build('core'));
		}

		$this->_controller->add_javascript($phpr_url.'/modules/db/behaviors/form_behavior/assets/extras/tag-it/js/tag-it.js?'.module_build('core'));
	}

	//
	// Controller Actions
	// 

	public function create($context = null)
	{
		try
		{
			$this->_context = !strlen($context) ? $this->_controller->form_create_context_name : $context;

			$this->_controller->app_page_title = $this->_controller->form_create_title;
			$this->_controller->view_data['form_model'] = $this->view_data['form_model'] = $this->_controller->form_create_model_object();

			if ($this->controller_method_exists('create_form_before_display'))
				$this->_controller->create_form_before_display($this->view_data['form_model']);
		}
		catch (exception $ex)
		{
			$this->_controller->handle_page_error($ex);
		}
	}

	public function edit($record_id, $context = null)
	{
		try
		{
			$this->_context = $context;

			$this->_controller->view_data['form_record_id'] = $record_id;
			$this->_controller->app_page_title = $this->_controller->form_edit_title;
			$this->_controller->view_data['form_model'] = $this->view_data['form_model'] = $this->_controller->form_find_model_object($record_id);

			if ($this->controller_method_exists('edit_form_before_display'))
				$this->_controller->edit_form_before_display($this->view_data['form_model']);
		}
		catch (exception $ex)
		{
			$this->_controller->handle_page_error($ex);
		}
	}

	public function preview($record_id, $context = null)
	{
		try
		{
			$this->_context = $context ? $context : 'preview';

			$this->_controller->view_data['form_record_id'] = $record_id;
			$this->_controller->app_page_title = $this->_controller->form_preview_title;
			$this->_controller->view_data['form_model'] = $this->view_data['form_model'] = $this->_controller->form_find_model_object($record_id);

			if ($this->controller_method_exists('preview_form_before_display'))
				$this->_controller->preview_form_before_display($this->view_data['form_model']);
		}
		catch (exception $ex)
		{
			$this->_controller->handle_page_error($ex);
		}
	}

	//
	// Form rendering
	// 

	public function form_render_field($db_name)
	{
		$obj = $this->view_data['form_model'];
		$field_definition = $obj->find_form_field($db_name);
		
		if (!$field_definition)
			throw new SystemException("Field ".$db_name." is not found in the model form field definition list.");

		$this->view_data['form_field'] = $field_definition;

		$field_partial = ($this->form_preview_mode) 
			? 'form_field_preview_'.$db_name 
			: 'form_field_'.$db_name;

		if ($this->controller_partial_exists($field_partial))
			$this->display_partial($field_partial);
		else
			$this->display_partial('form_field');
	}

	public function form_render_field_partial($form_model, $form_field)
	{
		if ($form_field->form_element_partial) {
			$this->_controller->display_partial($form_field->form_element_partial);
		}
		else {
			$controller_field_partial = $this->_controller->form_preview_mode ? 'form_field_element_preview_'.$form_field->db_name : 'form_field_element_'.$form_field->db_name;

			if ($this->controller_partial_exists($controller_field_partial))
				$this->display_partial($controller_field_partial);
			else
				$this->form_render_field_element_partial($form_model, $form_field);
		}
	}

	public function form_render_field_element_partial($form_model, $form_field)
	{
	 	$display_mode = $this->form_get_field_render_mode($form_field->db_name);
		
		$partial_name = ($this->_controller->form_preview_mode) 
			? 'form_field_preview_'.$display_mode 
			: 'form_field_'.$display_mode;

		$this->form_render_partial($partial_name, array(
			'form_field' => $form_field,
			'form_model_class' => get_class($form_model))
		);
	}

	public function form_render_field_container($obj, $db_name)
	{
		$this->view_data['form_model'] = $obj;
		$field_definition = $obj->find_form_field($db_name);
		
		if (!$field_definition)
			throw new SystemException("Field $db_name is not found in the model form field definition list.");

		$display_mode = $this->form_get_field_render_mode($field_definition->db_name);
		$partial_name = 'form_field_'.$display_mode;

		$this->form_render_partial($partial_name, array(
			'form_field' => $field_definition,
			'form_model_class' => get_class($obj))
		);
	}

	/**
	 * Renders a set of fields
	 * @param mixed $fields Could be an array containing field names
	 * or 2 parameters - fromDbName and toDbName
	 */
	public function form_render_fields($param1, $param2 = null)
	{
	}

	/**
	 * Allows to register alternative view paths. Please use application root relative path.
	 */
	public function form_register_view_path($path)
	{
		$this->register_view_path($path);
	}

	public function form_render($obj = null)
	{
		try {
			if ($obj !== null) {
				$this->_controller->view_data['form_model'] = $this->view_data['form_model'] = $obj;
				$this->_controller->view_data['form_record_id'] = $obj->get_primary_key_value();
			}

			$lock = null;
			if (($obj = $this->view_data['form_model']) && !$this->form_preview_mode) {
				if ($this->_controller->enable_concurrency_locking && !$obj->is_new_record())
					$lock = Record_Lock::lock($this->view_data['form_model']);
			}

			$this->view_data['form_has_tabs'] = $this->has_tabs();
			$this->view_data['form_elements'] = $this->form_split_to_tabs();
			$this->view_data['form_tabs_type'] = $this->_controller->form_tabs_type;
			$this->view_data['form_session_key'] = $this->form_get_edit_session_key();
			$this->view_data['form_lock_object'] = $lock;

			Phpr::$events->fire_event('db:on_before_' . Inflector::pascalize(get_class($obj)) . '_form_render', $this->_controller, $obj);

			$this->display_partial('form_form');
		}
		catch (exception $ex)
		{
			Phpr::$response->report_exception($ex, true, true);
		}
	}

	public function form_add_lock_code()
	{
		$this->display_partial('form_lock');
	}

	public function form_render_preview($obj = null)
	{
		if ($obj)
			$this->_controller->form_model_class = get_class($obj);

		$this->form_preview_mode = true;

		Phpr::$events->fire_event('db:on_before_' . Inflector::pascalize($this->_controller->form_model_class) . '_form_preview', $this->_controller, $obj);

		$this->form_render($obj);
	}

	public function form_render_report_preview($obj = null)
	{
		$this->form_report_layout_mode = true;
		$this->form_render_preview($obj);
	}

	public function form_render_partial($view, $params = array())
	{
		$this->display_partial($view, $params);
	}

	// Session keys
	// 

	public function form_get_edit_session_key()
	{
		if ($this->_edit_session_key)
			return $this->_edit_session_key;

		if (post('edit_session_key'))
			return $this->_edit_session_key = post('edit_session_key');

		return $this->_edit_session_key = uniqid($this->_controller->form_model_class, true);
	}

	public function reset_form_edit_session_key()
	{
		return $this->_edit_session_key = uniqid($this->_controller->form_model_class, true);
	}

	// Getters
	// 

	public function form_get_field_render_mode($db_name, $obj = null)
	{
		$obj = $obj ? $obj : $this->view_data['form_model'];
		$field_definition = $obj->find_form_field($db_name);
		if (!$field_definition)
			throw new SystemException("Field ".$db_name." is not found in the model form field definition list.");

		$column_definition = $field_definition->get_col_definition();

		if ($field_definition->display_mode === null)
		{
			if ($column_definition->is_reference)
			{
				switch ($column_definition->reference_type)
				{
					case 'belongs_to' : return frm_dropdown;
				 	case 'has_and_belongs_to_many' : return frm_checkboxlist;
				}
			}

			switch ($column_definition->type)
			{
				case db_float:
				case db_number:
				case db_varchar: return frm_text;
				case db_bool: return frm_checkbox;
				case db_text: return frm_textarea;
				case db_datetime: return frm_datetime;
				case db_date: return frm_date;
				case db_time: return frm_time;
				default:
					throw new SystemException("Render mode is unknown for $db_name field.");
			}
		}

		return $field_definition->display_mode;
	}

	public function form_is_field_required($db_name)
	{
		$real_db_name = $this->form_get_field_db_name($db_name, $this->view_data['form_model']);

		$obj = $this->view_data['form_model'];
		$rules = $obj->validation->get_rule($real_db_name);
		if (!$rules)
			return false;

		return $rules->required;
	}

	public function form_get_element_id($prefix, $form_class = null)
	{
		return $this->form_get_unique_prefix().$prefix.$form_class;
	}

	public function form_get_unique_prefix()
	{
		$prefix = post('form_unique_prefix');
		if (!strlen($prefix))
			return $this->_controller->form_unique_prefix;
		else
			return $prefix;
	}

	public function form_get_context()
	{
		return post('form_context', $this->_context);
	}
	
	public function form_get_field_db_name($db_name, $obj)
	{
		$field_definition = $obj->find_form_field($db_name);
		if (!$field_definition)
			throw new SystemException("Field ".$db_name." is not found in the model form field definition list.");

		$column_definition = $field_definition->get_col_definition();
		if (!$column_definition->is_reference)
			return $db_name;

		return $column_definition->reference_foreign_key;
	}

	/**
	 * Returns a list of available options for fields rendered as dropdown, autocomplete and radio controls.
	 * You may override dynamic version of this method in the model like this:
	 * public function get_customerId_options()
	 * @param string $db_name Specifies a field database name
	 * @param mixed $obj Specifies a model object
	 * @param mixed $key_value Optional value of a key to find a specific option. If this parameter is not null,
	 * the method should return exactly one value as array: [caption=>description] or as scalar: caption
	 * @return mixed For dropdowns: [id=>caption], for others: [id=>[caption=>description]]
	 */
	public function form_field_get_options($db_name, $obj, $key_value = -1)
	{
		/*
		 * Try to load data from a custom model method
		 */
		$method_name = 'get_'.$db_name.'_options';
		if (method_exists($obj, $method_name))
			return $obj->$method_name($key_value);

		$field_definition = $obj->find_form_field($db_name);
		if (!$field_definition)
			throw new SystemException("Field ".$db_name." is not found in the model form field definition list.");

		$options_method = $field_definition->options_method;
		if (strlen($options_method)) {
			if (!method_exists($obj, $options_method))
				throw new SystemException("Method ".$options_method." is not found in the model class ".get_class($obj));

			$result = $obj->$options_method($db_name, $key_value);
			if ($result !== false)
				return $result;
		}

		/*
		 * Load options from reference table
		 */
		$column_definition = $field_definition->get_col_definition();
		if (!$column_definition->is_reference)
			throw new SystemException("Error loading options for ".$db_name." field. Please define method ".$method_name." in the model.");

		$has_primary_key = $has_foreign_key = false;
		$relation_type = $obj->has_models[$column_definition->relation_name];
		$options = $obj->get_relation_options($relation_type, $column_definition->relation_name, $has_primary_key, $has_foreign_key);

		if ($relation_type == 'belongs_to' || $relation_type == 'has_and_belongs_to_many') {
			$class_name = $options['class_name'];
			$object = new $class_name();

			$name_expr = str_replace('@', $object->table_name.'.', $column_definition->reference_value_expr);

			$object->calculated_columns['_name_calc_column'] = array('sql'=>$name_expr);

			if ($field_definition->reference_description_field !== null)
				$object->calculated_columns['_description_calc_column'] = array('sql'=>str_replace('@', $object->table_name.'.', $field_definition->reference_description_field));

			if ($field_definition->reference_filter !== null)
				$object->where($field_definition->reference_filter);

			$sorting_field = $field_definition->reference_sort !== null ? $field_definition->reference_sort : '1 asc';
			$object->order($sorting_field);

			$object->where($options['conditions']);

			$result = array();

			if ($key_value !== -1) {
				$assigned_values = array();
				
				if ($key_value instanceof Data_Collection) {
					if (!$key_value->count())
						return array();

					foreach ($key_value as $assigned_record) {
						$assigned_values[] = $assigned_record->get_primary_key_value();
					}

					$assigned_values = array($assigned_values);
				} else
					$assigned_values[] = $key_value;

				$records = $object->find_all($assigned_values);
			}
			else {
				if (!$object->is_extended_with('Db\Act_As_Tree')) {
					$records = $object->find_all();
				}
				else {
					$records = array();
					$this->fetch_tree_items($object, $records, $sorting_field, 0);
				}
			}

			$is_tree = $object->is_extended_with('Db\Act_As_Tree');
			foreach ($records as $record) {

				$primary_key_value = $record->get_primary_key_value();
				
				if ($field_definition->reference_description_field === null) {
					
					if ($is_tree) {
						$result[$primary_key_value] = array(
							$record->_name_calc_column, 
							null, 
							$record->act_as_tree_level, 
							'level'=>$record->act_as_tree_level
						);
					} else {
						$result[$primary_key_value] = $record->_name_calc_column;
					}
					
				}
				else {
					$option = array();
					$option[$record->_name_calc_column] = $record->_description_calc_column;
					if ($is_tree)
						$option['level'] = $record->act_as_tree_level;

					$result[$primary_key_value] = $option;
				}
			}

			if ($key_value !== -1 && count($result) && $relation_type == 'belongs_to') {
				$keys = array_keys($result);
				return $result[$keys[0]];
			}

			return $result;
		}

		return array();
	}

	/**
	 * Returns true for options what are exist in many-to-many relation.
	 * For checkbox list many-to-many relations this method returns true if
	 * a checkbox with a specified value should be checked.
	 * You may override dynamic version of this method in the model like this:
	 * public function get_user_rights_option_state($value)
	 * @param string $db_name Specifies a field database name
	 * @param mixed $value Specifies a current checkbox value to check against
	 * @param mixed $obj Specifies a model object
	 * @return bool
	 */
	public function form_option_state($db_name, $value, $obj)
	{
		$field_definition = $obj->find_form_field($db_name);
		if (!$field_definition)
			throw new SystemException("Field ".$db_name." is not found in the model form field definition list.");

		// Try to load data from a dynamic model method
		//
		$method_name = 'get_'.$db_name.'_option_state';
		if (method_exists($obj, $method_name))
			return $obj->$method_name($value);

		$option_state_method = $field_definition->option_state_method;
		if (strlen($option_state_method))
		{
			if (!method_exists($obj, $option_state_method))
				throw new SystemException("Method ".$option_state_method." is not found in the model class ".get_class($obj));

			return $obj->$option_state_method($db_name, $value);
		}

		$column_definition = $field_definition->get_col_definition();
		if (!$column_definition->is_reference || $column_definition->reference_type != 'has_and_belongs_to_many')
			throw new SystemException("Error evaluating option state for ".$db_name." field. Please define method ".$method_name." in the model.");

		foreach ($obj->{$db_name} as $record)
		{
			if ($record instanceof ActiveRecord)
			{
				if ($record->get_primary_key_value() == $value)
					return true;
			}
			elseif ($record == $value)
				return true;
		}

		return false;
	}

	public function form_create_model_object()
	{
		$obj_class = $this->_controller->form_model_class;
		if (!strlen($obj_class))
			throw new SystemException('Form behavior: model class is not specified. Please specify it in the controller class with form_model_class public field.');

		$obj = new $obj_class();
		$obj->init_columns();
		$obj->init_form_fields($this->form_get_context());

		return $obj;
	}

	// Overrides
	// 

	public function form_before_create_save($obj, $session_key)
	{
	}

	public function form_after_create_save($obj, $session_key)
	{
	}

	public function form_after_save($obj, $session_key)
	{
	}

	public function form_before_edit_save($obj, $session_key)
	{
	}

	public function form_before_save($obj, $session_key)
	{
	}

	public function form_after_edit_save($obj, $session_key)
	{
	}

	public function form_after_delete($obj, $session_key)
	{
	}

	public function form_find_model_object($record_id)
	{
		$obj_class = $this->_controller->form_model_class;
		if (!strlen($obj_class))
			throw new SystemException('Form behavior: model class is not specified. Please specify it in the controller class with form_model_class public field.');

		if (!strlen($record_id))
			throw new ApplicationException($this->_controller->form_not_found_message);

		$obj = new $obj_class();

		$obj = $obj->find($record_id);

		if (!$obj || !$obj->count())
			throw new ApplicationException($this->_controller->form_not_found_message);

		$obj->init_form_fields($this->form_get_context());

		return $obj;
	}

	/**
	 * Adds unchecked checkbox values to the $_POST array
	 */
	public function form_recover_checkboxes($obj)
	{
		$obj_class = get_class($obj);
		$post_data = post($obj_class, array());

		foreach ($obj->form_elements as $form_element)
		{
			if (!($form_element instanceof Form_Field_Definition))
				continue;

			$db_name = $form_element->db_name;

			$display_mode = $this->form_get_field_render_mode($db_name, $obj);
			if ($display_mode == frm_checkbox)
				$_POST[$obj_class][$db_name] = array_key_exists($db_name, $post_data) ? $post_data[$db_name] : 0;
			elseif ($display_mode == frm_checkboxlist)
				$_POST[$obj_class][$db_name] = array_key_exists($db_name, $post_data) ? $post_data[$db_name] : array();
		}
	}

	// Events
	//

	public function create_on_save()
	{
		try
		{
			$obj = $this->_controller->form_create_model_object();
			$this->form_recover_checkboxes($obj);

			$this->_controller->form_before_save($obj, $this->form_get_edit_session_key());
			$this->_controller->form_before_create_save($obj, $this->form_get_edit_session_key());

			Phpr::$events->fire_event('db:on_before_' . Inflector::pascalize(get_class($obj)) . '_form_record_create', $this->_controller, $obj);

			$obj->save(post($this->_controller->form_model_class, array()), $this->form_get_edit_session_key());

			Phpr::$events->fire_event('db:on_after_' . Inflector::pascalize(get_class($obj)) . '_form_record_create', $this->_controller, $obj);

			$this->_controller->form_after_create_save($obj, $this->form_get_edit_session_key());
			$this->_controller->form_after_save($obj, $this->form_get_edit_session_key());

			if ($this->_controller->form_create_save_flash)
				Phpr::$session->flash['success'] = $this->_controller->form_create_save_flash;

			$redirect_url = Util::any($this->_controller->form_create_save_redirect, $this->_controller->form_redirect);

			if ($redirect_url)
			{
				if (strpos($redirect_url, '%s') !== false)
					$redirect_url = sprintf($redirect_url, $obj->get_primary_key_value());

				Phpr::$response->redirect($redirect_url);
			}
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}

	public function edit_on_save($record_id = null)
	{
		try
		{
			$obj = $this->_controller->form_find_model_object($record_id);

			if ($this->_controller->enable_concurrency_locking && ($lock = Record_Lock::lock_exists($obj)))
				throw new ApplicationException(sprintf('User %s is editing this record. The edit session started %s. You cannot save changes.', $lock->created_user_name, $lock->get_age_str()));

			$this->form_recover_checkboxes($obj);

			$this->_controller->form_before_save($obj, $this->form_get_edit_session_key());
			$this->_controller->form_before_edit_save($obj, $this->form_get_edit_session_key());

			Phpr::$events->fire_event('db:on_before_' . Inflector::pascalize(get_class($obj)) . '_form_record_update', $this->_controller, $obj);

			$flash_set = false;
			$obj->save(post($this->_controller->form_model_class, array()), $this->form_get_edit_session_key());

			Phpr::$events->fire_event('db:on_after_' . Inflector::pascalize(get_class($obj)) . '_form_record_update', $this->_controller, $obj);

			$this->_controller->form_after_save($obj, $this->form_get_edit_session_key());

			if ($this->_controller->form_edit_save_flash)
			{
				Phpr::$session->flash['success'] = $this->_controller->form_edit_save_flash;
				$flash_set = true;;
			}

			if (post('redirect', 1))
			{
				$redirect_url = Util::any($this->_controller->form_edit_save_redirect, $this->_controller->form_redirect);

				if (strpos($redirect_url, '%s') !== false)
					$redirect_url = sprintf($redirect_url, $record_id);

				if ($this->_controller->enable_concurrency_locking && !Record_Lock::lock_exists($obj))
					Record_Lock::unlock_record($obj);

				if ($redirect_url)
					Phpr::$response->redirect($redirect_url);
			} else
			{
				if ($flash_set && $this->_controller->form_edit_save_auto_timestamp)
					Phpr::$session->flash['success'] .= ' at '.Date::display(DateTime::now(), '%X');

				if ($this->_controller->form_after_edit_save($obj, $this->form_get_edit_session_key()))
					return;

				$this->display_partial('form_flash');
			}
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}

	public function edit_on_steal_lock()
	{
		$record_id = post('record_id');

		if (strlen($record_id))
		{
			$obj = $this->_controller->form_find_model_object($record_id);
			if ($obj)
				Record_Lock::lock($obj);

			Phpr::$response->redirect(Phpr::$request->get_referer(post('url')));
		}
	}

	public function create_on_cancel()
	{
		$obj = $this->_controller->form_create_model_object();
		$obj->cancel_deferred_bindings($this->form_get_edit_session_key());

		if (strpos($this->_controller->form_create_save_redirect, '%s') === false)
			$redirect_url = Util::any($this->_controller->form_create_save_redirect, $this->_controller->form_redirect);
		else
			$redirect_url = $this->_controller->form_redirect;

		if ($redirect_url)
			Phpr::$response->redirect($redirect_url);
	}

	public function edit_on_cancel($record_id = null)
	{
		$obj = $this->_controller->form_find_model_object($record_id);
		$obj->cancel_deferred_bindings($this->form_get_edit_session_key());

		$redirect_url = Util::any($this->_controller->form_edit_save_redirect, $this->_controller->form_redirect);
		if (strpos($redirect_url, '%s') !== false)
			$redirect_url = sprintf($redirect_url, $record_id);

		if ($redirect_url)
		{
			if ($this->_controller->enable_concurrency_locking && !Record_Lock::lock_exists($obj))
				Record_Lock::unlock_record($obj);

			Phpr::$response->redirect($redirect_url);
		}
	}

	public function edit_on_delete($record_id = null)
	{
		try
		{
			$obj = $this->_controller->form_find_model_object($record_id);
			if ($this->_controller->enable_concurrency_locking && ($lock = Record_Lock::lock_exists($obj)))
				throw new ApplicationException(sprintf('User %s is editing this record. The edit session started %s. The record cannot be deleted.', $lock->created_user_name, $lock->get_age_str()));

			Phpr::$events->fire_event('db:on_before_' . Inflector::pascalize(get_class($obj)) . '_form_record_delete', $this->_controller, $obj);

			$obj->delete();

			if ($this->_controller->form_after_delete($obj, $this->form_get_edit_session_key()))
				return;

			$obj->cancel_deferred_bindings($this->form_get_edit_session_key());

			if ($this->_controller->enable_concurrency_locking && !Record_Lock::lock_exists($obj))
				Record_Lock::unlock_record($obj);

			if ($this->_controller->form_edit_delete_flash)
				Phpr::$session->flash['success'] = $this->_controller->form_edit_delete_flash;

			$redirect_url = Util::any($this->_controller->form_delete_redirect, $this->_controller->form_edit_save_redirect);
			$redirect_url = Util::any($redirect_url, $this->_controller->form_redirect);
			if (strpos($redirect_url, '%s') !== false)
				$redirect_url = sprintf($redirect_url, $record_id);

			if ($redirect_url)
				Phpr::$response->redirect($redirect_url);
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}

	public function on_preview_popup()
	{
		$obj_class = post('model_class');
		if (!strlen($obj_class) || !class_exists($obj_class))
			throw new SystemException("Model class not found: ".$obj_class);

		$obj_obj = new $obj_class();
		$obj_obj = $obj_obj->find(post('model_id'));

		if ($obj_obj)
			$obj_obj->init_form_fields('preview');
		$this->view_data['track_tab'] = 0;
		$this->view_data['popup_level'] = post('popup_level');

		$this->display_partial('form_preview_popup', array('model_obj'=>$obj_obj, 'title'=>post('preview_title')));
	}

	// Uploads
	// 

	public function form_render_file_attachments($db_name, $obj = null)
	{
		$obj = $obj ? $obj : $this->view_data['form_model'];

		$field_definition = $obj->find_form_field($db_name);
		$file_list = $obj->get_all_deferred($db_name, $this->form_get_edit_session_key());

		$this->display_partial('form_attached_file_list', array(
			'form_file_list'=>$file_list,
			'dbName'=>$db_name, // @deprecated
			'db_name'=>$db_name,
			'display_mode'=>$field_definition->display_files_as,
			'form_field'=>$field_definition,
			'form_model'=>$obj));
	}

	public function form_file_upload($ticket, $db_name, $session_key, $record_id = null)
	{
		$this->_controller->suppress_view();

		$result = array();
		try
		{
			// Treat this as an XHR request, otherwise use standard POST method
			$is_xhr = post('phpr_uploader_xhr', false);

			if (!Phpr::$security->validate_ticket($ticket, true))
				throw new ApplicationException('Authorization error. Please try logging out and logging back in.');

			if (!$is_xhr && !array_key_exists('phpr_file', $_FILES))
				throw new ApplicationException('Unable to upload file. Missing parameter.');

			// Only support one file at a time
			if ($_FILES['phpr_file']) {
				$first_first = Upload::extract_multi_file_info($_FILES['phpr_file']);
				$_FILES['phpr_file'] = $first_first[0];
			}

			$obj_class = post('phpr_uploader_model_class');

			if (!$obj_class)
				$obj = strlen($record_id) ? $this->_controller->form_find_model_object($record_id) : $this->_controller->form_create_model_object();
			else
				$obj = $this->create_custom_model($obj_class, post('phpr_uploader_model_id'));

			$field_definition = $obj->find_form_field($db_name);

			if (!$field_definition)
				throw new SystemException('Field '.$db_name.' is not found in the model form field definition list.');

			$file_class = $this->form_file_model_class;
			$file = new $file_class();
			$file->is_public = $field_definition->display_files_as == 'single_image' || $field_definition->display_files_as == 'image_list';

			if ($is_xhr)
				$file->from_xhr($_REQUEST['phpr_file']);
			else
				$file->from_post($_FILES['phpr_file']);

			$file->master_object_class = get_class($obj);
			$file->field = $db_name;
			$file->save();

			if ($field_definition->display_files_as == 'single_image' || $field_definition->display_files_as == 'single_file')
			{
				$files = $obj->get_all_deferred($db_name, $this->form_get_edit_session_key());
				foreach ($files as $existing_file) {
					$obj->{$db_name}->delete($existing_file, $session_key);
				}
			}

			$obj->{$db_name}->add($file, $session_key);

			$result['result'] = 'success';
		}
		catch (Exception $ex)
		{
			$result['result'] = 'failed';
			$result['error'] = $ex->getMessage();
			header("HTTP/1.1 500 " . $result['error']);
		}

		header('Content-type: application/json');
		echo json_encode($result);
	}

	public function form_get_upload_url($db_name, $session_key = null)
	{
		$session_key = $session_key ? $session_key : $this->form_get_edit_session_key();

		$model = $this->view_data['form_model'];
		
		$url = \Admin_Html::controller_url();
		$url = substr($url, 0, -1);
		
		$parts = array(
			$url,
			'form_file_upload',
			Phpr::$security->get_ticket(),
			$db_name,
			$session_key
		);
		
		if (!$model->is_new_record())
			$parts[] = $model->get_primary_key_value();
		
		return implode('/', $parts);
	}

	public function on_update_file_list($record_id = null)
	{
		$obj_class = post('phpr_uploader_model_class');

		if (!$obj_class)
			$obj = strlen($record_id) ? $this->_controller->form_find_model_object($record_id) : $this->_controller->form_create_model_object();
		else
			$obj = $this->create_custom_model($obj_class, post('phpr_uploader_model_id'));

		$this->form_render_file_attachments(post('db_name'), $obj);
	}

	public function on_set_form_files_order($record_id = null)
	{
		$file_class = $this->form_file_model_class;
		call_user_func($file_class.'::set_orders', post('item_ids'), post('sort_orders'));
	}

	public function on_save_form_file_description($record_id)
	{
		try
		{
			$file_class = $this->form_file_model_class;
			$file = new $file_class();
			$file->find(post('file_id'));
			if ($file)
			{
				$file->description = trim(post('description'));
				$file->title = trim(post('title'));
				$file->save();
			}

			$this->display_partial('form_file_description', array('file'=>$file));
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}

	public function on_show_file_description_form($record_id)
	{
		try
		{
			$file_class = $this->form_file_model_class;
			$file = new $file_class();
			$this->view_data['file'] = $file->find(post('file_id'));
		}
		catch (Exception $ex)
		{
			$this->_controller->handle_page_error($ex);
		}

		$this->display_partial('form_file_description_popup');
	}

	public function on_delete_file($record_id = null)
	{
		$obj_class = post('phpr_uploader_model_class');

		if (!$obj_class)
			$obj = strlen($record_id) ? $this->_controller->form_find_model_object($record_id) : $this->_controller->form_create_model_object();
		else
			$obj = $this->create_custom_model($obj_class, post('phpr_uploader_model_id'));

		$db_name = post('db_name');

		$file_class = $this->form_file_model_class;
		$file = new $file_class();

		if ($file = $file->find(post('file_id')))
			$obj->{$db_name}->delete($file, $this->form_get_edit_session_key());

		$this->form_render_file_attachments($db_name, $obj);
	}

	// Record Finder
	// 
	
	public function form_get_record_finder_list_name($obj)
	{
		return get_class($this->_controller).'_rflist_'.get_class($obj);
	}

	public function form_get_record_finder_model($master_model, $field_definition)
	{
		$column_definition = $field_definition->get_col_definition();

		$has_primary_key = $has_foreign_key = false;
		$relation_type = $master_model->has_models[$column_definition->relation_name];
		$options = $master_model->get_relation_options($relation_type, $column_definition->relation_name, $has_primary_key, $has_foreign_key);

		$object = new $options['class_name']();

		if (isset($options['conditions']))
			$object->where($options['conditions']);

		return $object;
	}

	public function form_prepare_record_finder_data()
	{
		$obj = $this->_controller->form_create_model_object();
		$field_definition = null;

		$db_name = post('db_name');
		$field_definition = $obj->find_form_field($db_name);
		if (!$field_definition)
			throw new ApplicationException('Field not found');

		return $this->form_get_record_finder_model($obj, $field_definition);
	}

	public function form_prepare_record_finder_list($obj = null, $field_definition = null)
	{
		if (post('class_name'))
			$this->_controller->form_model_class = post('class_name');

		$obj = $obj ? $obj : $this->form_create_model_object();
		$db_name = post('db_name');
		$field_definition = $field_definition ? $field_definition : $obj->find_form_field($db_name);
		if (!$field_definition)
			throw new ApplicationException('Field not found');

		$list_columns = isset($field_definition->render_options['list_columns']) ? $field_definition->render_options['list_columns'] : 'name';
		$search_columns = isset($field_definition->render_options['search_fields']) ? $field_definition->render_options['search_fields'] : $list_columns;
		$list_columns = Util::splat($list_columns, true);
		$search_fields = Util::splat($search_columns, true);
		$search_prompt = isset($field_definition->render_options['search_prompt']) ? $field_definition->render_options['search_prompt'] : 'search';

		$this->_controller->list_name = $this->form_get_record_finder_list_name($obj);
		$search_model = $this->form_get_record_finder_model($obj, $field_definition);

		$result = array(
			'list_model_class'=>get_class($search_model),
			'list_no_setup_link'=>true,
			'list_columns'=>$list_columns,
			'list_custom_body_cells'=>false,
			'list_custom_head_cells'=>false,
			'list_display_as_tree'=>false,
			'list_scrollable'=>false,
			'list_search_fields'=>$search_fields,
			'list_search_prompt'=>$search_prompt,
			'list_no_form'=>true,
			'list_record_url'=>null,
			'list_items_per_page'=>10,
			'list_search_enabled'=>true,
			'list_render_filters'=>false,
			'list_name'=>$this->form_get_record_finder_list_name($obj),
			'list_custom_prepare_func'=>'form_prepare_record_finder_data',
			'list_top_partial'=>null,
			'list_no_js_declarations'=>true,
			'list_record_onclick'=>'return recordFinderUpdateRecord(%s);'
		);

		$this->_controller->list_options = $result;
		$this->_controller->list_apply_options($this->_controller->list_options);

		return $result;
	}

	public function form_get_record_finder_container_id($obj_class, $db_name)
	{
		return 'recordfinderRecord'.$obj_class.$db_name;
	}

	public function on_find_form_record($record_id)
	{
		if (post('class_name'))
			$this->_controller->form_model_class = post('class_name');

		$title = 'Find Record';
		$obj = $this->_controller->form_create_model_object();
		$field_definition = null;
		$column_name = null;

		try
		{
			$db_name = post('db_name');
			$field_definition = $obj->find_form_field($db_name);
			if (!$field_definition)
				throw new ApplicationException('Field not found');

			$title = isset($field_definition->render_options['form_title']) ? $field_definition->render_options['form_title'] : 'Find Record';

			$column_name = $this->form_get_field_db_name($db_name, $obj);
		}
		catch (Exception $ex)
		{
			$this->_controller->handle_page_error($ex);
		}

		$this->display_partial('record_finder_form', array('db_name'=>$db_name, 'model'=>$obj, 'title'=>$title, 'form_field'=>$field_definition, 'column_name'=>$column_name));
	}

	public function on_set_record_finder_record($record_id)
	{
		if (post('class_name'))
			$this->_controller->form_model_class = post('class_name');

		$obj = $this->_controller->form_create_model_object();

		$db_name = post('db_name');
		$field_definition = $obj->find_form_field($db_name);
		if (!$field_definition)
			throw new ApplicationException('Field not found');

		$field_name = $this->form_get_field_db_name($db_name, $obj);

		$obj->$field_name = post('recordId');

		$this->view_data['form_model'] = $obj;

		$this->display_partial('record_finder_record', array('db_name'=>$db_name, 'form_model'=>$obj, 'form_field'=>$field_definition, 'form_model_class'=>get_class($obj)));
	}

	// Collapsable
	//

	public function form_list_collapsable_elements($elements)
	{
		$result = array();
		foreach ($elements as $element)
		{
			if ($element->collapsible)
				$result[] = $element;
		}

		return $result;
	}

	public function form_list_non_collapsable_elements($elements)
	{
		$result = array();
		foreach ($elements as $element)
		{
			if (!$element->collapsible)
				$result[] = $element;
		}

		return $result;
	}

	protected function get_collapsable_visible_status_var_name($tab_index)
	{
		return get_class($this->_controller).'_'.Phpr::$router->action.'_collapsible_visible_'.$tab_index;
	}

	public function form_is_collapsable_area_visible($tab_index)
	{
		if (Phpr::$router->action == 'create')
			return true;

		$var_name = $this->get_collapsable_visible_status_var_name($tab_index);
		return User_Parameters::get($var_name, null, true);
	}

	public function on_form_toggle_collapsable_area()
	{
		try
		{
			$tab_index = post('collapsible_tab_index');
			$var_name = $this->get_collapsable_visible_status_var_name($tab_index);
			User_Parameters::set($var_name, !post('current_expand_status'), true);
		} 
		catch (exception $ex) {
			throw $ex;
		}
	}

	// Widgets
	// 

	public function form_init_widget($db_name, $model = null)
	{
		if (array_key_exists($db_name, $this->_widgets))
			return $this->_widgets[$db_name];
			
		if (!$model)
			$model = isset($this->view_data['form_model']) ? $this->view_data['form_model'] : null;
			
		if (!$model)
			throw new SystemException('Unable to initialize widget - form model object is not defined');
			
		$form_field = $model->find_form_field($db_name);
		if (!$form_field)
			throw new ApplicationException('Field '.$db_name.' not found in the model '.get_class($model));
			
		$widget_class_name = isset($form_field->render_options['class']) ? $form_field->render_options['class'] : null;
		if (!$widget_class_name)
			throw new SystemException('Widget class name is not specified for the '.$db_name.' field');
			
		$relation_db_name = $this->form_get_field_db_name($db_name, $model);
		return $this->_widgets[$db_name] = new $widget_class_name($this->_controller, $model, $relation_db_name, $form_field->render_options);
	}

	public function form_widget_request($method, $db_name, $param_1 = null, $param_2 = null)
	{
		if (substr($method, 0, 2) != 'on')
			die('Invalid widget method name: '.$method);
		
		try
		{
			$data_model = $this->_controller->form_create_model_object();

			$widget = $this->form_init_widget($db_name, $data_model);
			$widget->$method($param_1, $param_2);
		}
		catch (exception $ex) 
		{
			die ($ex->getMessage());
		}
	}

	public function on_form_widget_event($record_id = null)
	{
		try
		{
			$custom_model_class = post('widget_model_class');

			if (!$custom_model_class)
				$model_class = $this->_controller->form_model_class;
			else
				$model_class = $custom_model_class;
			
			if (!$custom_model_class)
				$data_model = Phpr::$router->action == 'create' ? $this->_controller->form_create_model_object() : $this->_controller->form_find_model_object($record_id);
			else
				$data_model = $this->create_custom_model($model_class, null);
				
			Phpr::$events->fire_event('phpr:on_init_form_widget_model', $this->_controller, $data_model);

			$field = post('phpr_event_field');
			$widget = $this->form_init_widget($field, $data_model);
			$widget->handle_event(post('phpr_custom_event_name'), $data_model, $field);
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}

	// Dropdown create
	// 

	public function on_dropdown_create_form_load()
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
			$model->init_columns('dropdown_create');
			$model->init_form_fields('dropdown_create');

			$this->view_data['form_model'] = $model;
			$parent_model = new $parent_model_class();
			$parent_model->init_columns();
			$parent_model->init_form_fields();
			$this->view_data['parent_model'] = $parent_model;
		}
		catch (Exception $ex)
		{
			$this->_controller->handle_page_error($ex);
		}

		$this->display_partial('form_dropdown_create');
	}

	public function on_dropdown_create_form_create($id = null)
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
			$form_model->init_columns('dropdown_create');
			$form_model->save(post($model_class), $this->_controller->form_get_edit_session_key());

			// Populate the parent object with our new child
			$parent_obj = new $parent_model_class();
			$parent_obj->find($id);

			// Required to render the field container
			$parent_obj->init_columns();
			$parent_obj->init_form_fields();

			$parent_obj->$db_name = $form_model->id;

			// Fill our container
			$field_container_id = "form_field_container_".$db_name.$parent_model_class;
			echo '>>#'.$field_container_id.'<<';

			// Render the field
			$this->_controller->form_render_field_container($parent_obj, $field_name);
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}

	// Internals
	// 

	protected function create_custom_model($class_name, $record_id)
	{
		$obj = null;

		if (strlen($record_id))
		{
			$obj = new $class_name();
			$obj = $obj->find($record_id);

			if (!$obj || !$obj->count())
				throw new ApplicationException($this->_controller->form_not_found_message);

			$obj->init_form_fields($this->form_get_context());
		} else
		{
			$obj = new $class_name();
			$obj->init_columns();
			$obj->init_form_fields($this->form_get_context());
		}

		return $obj;
	}

	protected function has_tabs()
	{
		$tabs_found = 0;
		$fields_found = 0;

		foreach ($this->view_data['form_model']->form_elements as $element)
		{
			if (strlen($element->tab))
				$tabs_found++;

			$fields_found++;
		}

		if ($tabs_found > 0 && ($tabs_found != $fields_found))
			throw new SystemException('Form behavior: error in the model form elements definition. Tabs should be specified either for each form element or for none.');

		return $tabs_found;
	}

	public function form_split_to_tabs($obj = null)
	{
		$tabs = array();

		$obj = $obj ? $obj : $this->view_data['form_model'];

		foreach ($obj->form_elements as $index=>$element)
		{
			if (!$element->sort_order)
				$element->sort_order = ($index+1)*10;
		}

		usort($obj->form_elements, array('Db\Form_Behavior', 'form_sort_form_fields'));

		foreach ($obj->form_elements as $element)
		{
			if (!$this->form_preview_mode && $element->no_form)
				continue;

			$tab_caption = $element->tab ? $element->tab : -1;
			if (!array_key_exists($tab_caption, $tabs))
				$tabs[$tab_caption] = array();

			$tabs[$tab_caption][] = $element;
		}

		return $tabs;
	}

	public static function form_sort_form_fields($element1, $element2)
	{
		if ($element1->sort_order == $element2->sort_order)
			return 0;

		return $element1->sort_order > $element2->sort_order ? 1 : -1;
	}

	public function form_render_form_tab($form_model, $tab_index)
	{
		$form_elements = $this->form_split_to_tabs($form_model);
		$keys = array_keys($form_elements);
		if (!array_key_exists($tab_index, $keys))
			return;

		$this->view_data['form_model'] = $form_model;

		$this->form_render_partial('form_tab', array('form_tab_elements'=>$form_elements[$keys[$tab_index]], 'tab_index'=>$tab_index));
	}

	protected function fetch_tree_items($object, &$records, $sorting_field, $level)
	{
		if ($level == 0)
			$children = $object->list_root_children($sorting_field);
		else
			$children = $object->list_children($sorting_field);

		foreach ($children as $child)
		{
			$child->act_as_tree_level = $level;
			$records[] = $child;

			$this->fetch_tree_items($child, $records, $sorting_field, $level+1);
		}
	}

	/**
	 * @deprecated 
	 */ 
	public function formRender($obj = null) { Phpr::$deprecate->set_function('formRender', 'form_render'); return $this->form_render($obj); }
	public function formRenderPreview($obj = null) { Phpr::$deprecate->set_function('formRenderPreview', 'form_render_preview'); return $this->form_render_preview($obj); }
	public function formRenderReportPreview($obj = null) { Phpr::$deprecate->set_function('formRenderReportPreview', 'form_render_report_preview'); return $this->form_render_report_preview($obj); }
	public function formRenderPartial($view, $params = array()) { Phpr::$deprecate->set_function('formRenderPartial', 'form_render_partial'); return $this->form_render_partial($view, $params); }
	public function formGetEditSessionKey() { Phpr::$deprecate->set_function('formGetEditSessionKey', 'form_get_edit_session_key'); return $this->form_get_edit_session_key(); }
}
