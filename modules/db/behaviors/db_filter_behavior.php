<?php

class Db_Filter_Behavior extends Phpr_Controller_Behavior
{
	public $filter_list_title = 'Filters';
	public $filter_filters = array();
	public $filter_switchers = array();
	public $filter_ignore_filter = null;
	public $filter_prompt = 'Please select something to filter.';
	public $filter_desc_max_len = 100;
	public $filter_on_apply = null;
	public $filter_on_remove = null;

	public function __construct($controller)
	{
		parent::__construct($controller);
	}

	public function init_extension()
	{
		if (!$this->_controller)
			return;

		$this->filter_load_resources();

		$this->add_event_handler('on_filter_load_form');
		$this->add_event_handler('on_filter_apply');
		$this->add_event_handler('on_filter_remove');
		$this->add_event_handler('on_filter_reset');
		$this->add_event_handler('on_filter_apply_switchers');

		if (post('filter_id_value'))
		{
			$filter_id = post('filter_id_value');
			$filter_obj = $this->get_filter_object($filter_id);
			$model_obj = $this->create_model_object($filter_obj);
			$filter_columns = Phpr_Util::splat($filter_obj->list_columns);

			$search_fields = $filter_columns;
			foreach ($search_fields as $index => &$field) {
				$field = "@".$field;
			}

			$this->_controller->list_custom_body_cells = PATH_SYSTEM.'/modules/db/behaviors/db_filter_behavior/partials/_filter_body_control.htm';
			$this->_controller->list_custom_head_cells = PATH_SYSTEM.'/modules/db/behaviors/db_filter_behavior/partials/_filter_head_control.htm';

			$is_tree = $model_obj->is_extended_with('Db_Act_As_Tree');

			$this->_controller->list_options['list_model_class'] = get_class($model_obj);
			$this->_controller->list_options['list_no_setup_link'] = true;
			$this->_controller->list_options['list_columns'] = $filter_columns;
			$this->_controller->list_options['list_display_as_tree'] = $is_tree;
			$this->_controller->list_options['list_custom_prepare_func'] = 'filter_prepare_data';
			$this->_controller->list_options['list_custom_body_cells'] = PATH_SYSTEM.'/modules/db/behaviors/db_filter_behavior/partials/_filter_body_control.htm';
			$this->_controller->list_options['list_custom_head_cells'] = PATH_SYSTEM.'/modules/db/behaviors/db_filter_behavior/partials/_filter_head_control.htm';
			$this->_controller->list_options['list_search_fields'] = $search_fields;
			$this->_controller->list_options['list_search_prompt'] = 'search';
			$this->_controller->list_options['list_no_form'] = true;
			$this->_controller->list_options['list_record_url'] = null;
			$this->_controller->list_options['list_items_per_page'] = 6;
			$this->_controller->list_options['list_search_enabled'] = !$is_tree;
			$this->_controller->list_options['list_name'] = $this->filter_get_list_name($model_obj);
			$this->_controller->list_options['filter_id'] = $filter_id;
			$this->_controller->list_options['list_reuse_model'] = false;
			$this->_controller->list_options['list_no_js_declarations'] = true;
			$this->_controller->list_options['list_scrollable'] = $is_tree;
			$this->_controller->list_name = $this->filter_get_list_name($model_obj);
			$this->_controller->list_record_url = null;

			$this->_controller->list_apply_options($this->_controller->list_options);
		}
	}

	//
	// Asset management
	// 

	protected function filter_load_resources()
	{
		$phpr_url = '/' . Phpr::$config->get('PHPR_URL', 'phpr');
		$this->_controller->add_css($phpr_url.'/modules/db/behaviors/db_filter_behavior/assets/stylesheets/css/filters.css?'.module_build('core'));
		$this->_controller->add_javascript($phpr_url.'/modules/db/behaviors/db_filter_behavior/assets/scripts/js/filters.js?'.module_build('core'));
	}

	//
	// Public methods
	// 

	// Renders filter UI
	public function filter_render()
	{
		$this->load_filter_settings();
		$this->display_partial('filter_settings');
	}

	public function filter_render_partial($view, $params = array())
	{
		$this->display_partial($view, $params);
	}

	public function filter_prepare_data($model, $options)
	{
		$filter_obj = $this->get_filter_object($options['filter_id']);
		return $this->create_model_object($filter_obj);
	}

	//
	// Filtering methods (called from controller)
	//

	public function filter_apply_to_model($model, $context = null)
	{
		$filters = Phpr_User_Parameters::get($this->get_filters_name(), null, array());
		
		foreach ($filters as $filter_id => $filter_set) {
			if (array_key_exists($filter_id, $this->_controller->filter_filters))
				$this->get_filter_object($filter_id)->apply_to_model($model, array_keys($filter_set), $context);
		}

		$swicher_values = array();
		$enabled_switchers = Phpr_User_Parameters::get($this->get_filters_name('switchers'), null, array());
		
		foreach ($this->_controller->filter_switchers as $switcher_id=>$switcher_info) {
		
			$switcher_obj = $this->get_switcher_object($switcher_id);

			if (in_array($switcher_id, $enabled_switchers))
				$switcher_obj->apply_to_model($model, true, $context);
			else
				$switcher_obj->apply_to_model($model, false, $context);

		}

		return $model;
	}

	public function filter_as_string($context = null)
	{
		$filters = Phpr_User_Parameters::get($this->get_filters_name(), null, array());
		$result = null;
		foreach ($filters as $filter_id => $filter_set) {
			$result .= ' '.$this->get_filter_object($filter_id)->as_string(array_keys($filter_set), $context);
		}

		$enabled_switchers = Phpr_User_Parameters::get($this->get_filters_name('switchers'), null, array());
		foreach ($this->_controller->filter_switchers as $switcher_id => $switcher_info) {

			$switcher_obj = $this->get_switcher_object($switcher_id);
			
			if (in_array($switcher_id, $enabled_switchers))
				$result .= ' '.$switcher_obj->as_string(true, $context);
			else
				$result .= ' '.$switcher_obj->as_string(false, $context);
		}

		return $result;
	}

	public function filter_reset()
	{
		Phpr_User_Parameters::set($this->get_filters_name(), array());
	}

	public function filter_get_keys($filter_id)
	{
		$filters = Phpr_User_Parameters::get($this->get_filters_name(), null, array());
		if (!array_key_exists($filter_id, $filters))
			return array();

		return array_keys($filters[$filter_id]);
	}

	public function filter_get_list_name($model)
	{
		return get_class($this->_controller).'_filterlist_'.get_class($model);
	}

	//
	// Event handlers
	//

	public function on_filter_load_form()
	{
		try
		{
			$id = post('id');
			if (!array_key_exists($id, $this->_controller->filter_filters)) {
				$this->view_data['not_found'] = true;
			}
			else {
				$this->view_data['filter_info'] = $filter_info = $this->_controller->filter_filters[$id];
				$this->view_data['filter_id'] = $id;
				$this->view_data['filter_new'] = !post('existing');

				$filter_class = $filter_info['class_name'];
				$obj = new $filter_class();
				$this->view_data['filter_obj'] = $obj;

				$model_class = $obj->model_class_name;
				$model = new $model_class();
				$this->view_data['model'] = $model;

				$settings = $this->load_filter_settings();

				$checked_records = array();
				if (array_key_exists($id, $settings)) {
					$checked_records = array_keys($settings[$id]);
				}

				if ($checked_records) {
					$list_columns = Phpr_Util::splat($obj->list_columns);
					$primary_key = $model->primary_key;
					$this->view_data['filter_checked_records'] = $model->where($primary_key." in (?)", array($checked_records))->order($list_columns[0])->find_all();
				}
			}

			$this->display_partial('filter_form');
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}

	public function on_filter_apply()
	{
		try
		{
			$filter_id = post('filter_id');
			$ids = Phpr_Util::splat(post('filter_ids', array()));

			if (!count($ids)) {
				if (post('filter_existing'))
					throw new Phpr_ApplicationException('Please select at least one record, or click Cancel Filter button to clear the filter.');
				else
					throw new Phpr_ApplicationException('Please select at least one record.');
			}

			$filters = Phpr_User_Parameters::get($this->get_filters_name(), null, array());
			$filter_obj = $this->get_filter_object($filter_id);
			$filter_columns = Phpr_Util::splat($filter_obj->list_columns);

			$model_obj = $this->create_model_object($filter_obj);
			$record_num = $model_obj->get_row_count();

			if ($record_num == count($ids) && $this->filter_cancel_if_all($filter_id)) {

				if (array_key_exists($filter_id, $filters)) {
					unset($filters[$filter_id]);
					Phpr_User_Parameters::set($this->get_filters_name(), $filters);
				}
			} 
			else {
				
				$records = (count($ids)) 
					? $model_obj->where($model_obj->table_name.'.id in (?)', array($ids))->find_all() 
					: array();

				$record_map = array();
				foreach ($records as $record) {
					$record_map[$record->get_primary_key_value()] = $record->{$filter_columns[0]};
				}

				$filter_set = array();
				foreach ($ids as $id) {
					if (array_key_exists($id, $record_map))
						$filter_set[$id] = $record_map[$id];
				}

				$filters[$filter_id] = $filter_set;
				Phpr_User_Parameters::set($this->get_filters_name(), $filters);
			}

			$this->load_filter_settings();
			$this->display_partial('filter_settings_content');
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}

	public function on_filter_remove()
	{
		try
		{
			$filter_id = post('filter_id');
			$filters = Phpr_User_Parameters::get($this->get_filters_name(), null, array());

			if (array_key_exists($filter_id, $filters)) {
				unset($filters[$filter_id]);
				Phpr_User_Parameters::set($this->get_filters_name(), $filters);
			}

			$this->load_filter_settings();
			$this->display_partial('filter_settings_content');
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}

	public function on_filter_reset()
	{
		try
		{
			$this->filter_reset();
			$this->load_filter_settings();
			$this->display_partial('filter_settings_content');
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}		
	}

	public function on_filter_apply_switchers()
	{
		try
		{
			$switchers = array_keys(post('filter_switchers', array()));
			Phpr_User_Parameters::set($this->get_filters_name('switchers'), $switchers);
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}

	//
	// Internals
	// 

	private function load_filter_settings()
	{
		$filters = Phpr_User_Parameters::get($this->get_filters_name(), null, array());
		$switchers = Phpr_User_Parameters::get($this->get_filters_name('switchers'), null, array());
		$this->view_data['filter_checked_switchers'] = $switchers;
		return $this->view_data['filter_settings_info'] = $filters;
	}

	private function create_model_object($filter_obj)
	{
		return $filter_obj->prepare_list_data();
	}

	private function get_filter_object($id)
	{
		if (!array_key_exists($id, $this->_controller->filter_filters))
			throw new Phpr_ApplicationException("Filter '".$id."' not found");

		$class_name = $this->_controller->filter_filters[$id]['class_name'];
		return new $class_name();
	}

	private function get_switcher_object($id)
	{
		if (!array_key_exists($id, $this->_controller->filter_switchers))
			throw new Phpr_ApplicationException("Switcher '".$id."' not found");

		$class_name = $this->_controller->filter_switchers[$id]['class_name'];
		if (class_exists($class_name))
			return new $class_name();

		return null;
	}

	private function filter_cancel_if_all($id)
	{
		if (!array_key_exists($id, $this->_controller->filter_filters))
			throw new Phpr_ApplicationException("Filter '".$id."' not found");

		$filter_info = $this->_controller->filter_filters[$id];
		return isset($filter_info['cancel_if_all']) ? $filter_info['cancel_if_all'] : true;
	}

	private function get_filters_name($property_set = null)
	{
		return get_class($this->_controller).'_filters'.$property_set;
	}
}
