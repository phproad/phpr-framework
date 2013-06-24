<?php

/*
// Usage

$this->add_form_field('quotes')->display_as(frm_widget, array(
	'class'=>'Db_ListWidget', 
	'class_name' => 'Service_Quote',
	'columns' => array('first_name', 'last_name', 'email', 'placement'),
	'search_fields' => array('@first_name', '@last_name', '@email'),
	'search_enabled' => true,
	'search_prompt' => 'find quotes by name',
	'no_data_message' => 'This request has no quotes',
	'record_url' => null,
	'control_panel' => 'quote_control_panel',
	'is_editable' => true,
	'form_title' => 'Quote',
	'form_context' => 'create',
	'form_foreign_key' => 'request_id',
	'conditions' => 'request_id=:id',
	'show_checkboxes' => false,
	'show_reorder' => false, // Requires model is extended with Db_Model_Sortable
))->tab('Quotes');

*/

class Db_List_Widget extends Db_Form_Widget_Base
{
	protected $child_model = null;
	public $class_name = null;
	public $conditions;

	// List	
	public $items_per_page = 20; 
	public $no_data_message = 'There are no items in this view';
	public $load_indicator = null;
	public $record_url = null;
	public $record_onclick = null;
	public $handle_row_click = true;
	public $root_level_label = 'Root';
	public $sorting_column = null;
	public $sorting_direction = null;
	public $default_sorting_column = null;
	public $default_sorting_direction = null;
	public $no_sorting = false;
	public $data_context = null;
	public $node_expanded_default = true;

	public $csv_import_url = null;
	public $csv_cancel_url = null;
	public $csv_template_url = null;

	public $search_enabled = false;
	public $min_search_query_length = 0;
	public $search_show_empty_query = true;
	public $search_prompt = null;
	public $search_fields = array();
	public $search_custom_func = null;

	public $no_interaction = false;
	public $no_js_declarations = false;
	public $no_form = true;
	public $no_pagination = false;

	public $custom_body_cells = null;
	public $custom_head_cells = null;
	public $cell_partial = false;
	public $custom_partial = null;
	public $cell_individual_partial = array();
	public $top_partial = null;
	public $control_panel = null;

	public $columns = array();
	public $column_order = null;
	public $sorting = null;

	public $finder_mode = false;
	public $show_checkboxes = false;
	public $show_reorder = false;
	public $show_delete_icon = false;

	// Form
	public $is_editable = false;
	public $form_title = false;
	public $form_context = null;       // Optional
	public $form_foreign_key = null;   // Optional
	public $form_relation_type = null; // Optional
	public $form_relation_name = null; // Optional

	protected $_columns = null;
	protected $_sorting_column = null;
	protected $_settings = null;

	protected function load_resources()
	{

	}

	public function render()
	{
		$this->apply_defaults();
		$this->load_relations();
		$this->prepare_render_data();
		$this->display_partial('list_container');
	}

	// Used to locate has_and_belongs_to_many relatives
	public function render_finder()
	{
		$this->finder_mode = true;
		$this->load_relations();
		$this->prepare_render_data();
		$this->display_partial('list_container');
	}

	protected function display_table()
	{
		$this->prepare_render_data();

		if (!$this->custom_partial)
			$this->display_partial('list');
		else
			$this->display_partial($this->custom_partial);
	}

	private function apply_defaults()
	{
		if (!$this->column_order)
			$this->column_order = array();
		
		$this->sorting = $this->load_preference('sorting');

		if (!$this->sorting)
			$this->sorting = (object)array('field'=>null, 'direction'=>null);

		if ($this->is_editable)
		{
			$this->record_onclick = "new PopupForm('".$this->controller->get_event_handler('on_form_widget_event')."', { ajaxFields: { 
				".$this->get_event_handler_data('on_form_popup').", 
				primary_id: '%s', 
				edit_session_key: '".$this->controller->form_get_edit_session_key()."',
				form_context: '".$this->controller->form_get_context()."'
			} }); return false;";
		}

		if ($this->show_reorder)
			$this->no_sorting = true;

		if ($this->show_checkboxes||$this->show_reorder) {
			$this->custom_body_cells = 'list_body_custom';
			$this->custom_head_cells = 'list_head_custom';
		}
	}

	protected function load_relations()
	{
		$this->autoload_child_properties();
		$this->autoload_child_class_name();

		if (post('finder_mode_flag', $this->finder_mode)) 
			$this->autoload_finder_properties();
	}

	protected function prepare_render_data($no_pagination = false)
	{
		$form_context = $this->data_context;
		$model = $this->load_data();

		$this->view_data['list_columns'] = $list_columns = $this->eval_list_columns();
		$this->view_data['list_sorting_column'] = $sorting_column = $this->eval_sorting_column();
		$this->view_data['list_column_definitions'] = $this->create_child_model_object()->get_column_definitions();

		// Pagination
		$total_row_count = $model->get_row_count();
		if (!$no_pagination && !$this->no_pagination)
		{
			$pagination = new Phpr_Pagination($this->items_per_page);
			$pagination->set_row_count($total_row_count);

			$pagination->set_current_page_index($this->load_preference('page'));
			$pagination->limit_active_record($model);

			$this->view_data['list_pagination'] = $pagination;
		}

		$this->view_data['list_total_row_count'] = $total_row_count;

		// Sort order
		$column_defintions = $model->get_column_definitions($form_context);
		$sorting_field = $column_defintions[$sorting_column->field]->get_sorting_column_name();

		$list_sort_column = $sorting_field.' '.$sorting_column->direction;
		$model->order($list_sort_column);

		// Execute data load
		$this->view_data['data'] = $model->find_all(null, array(), $form_context);
		$this->view_data['list_column_count'] = count($list_columns);
		$this->view_data['list_model_class'] = get_class($model);
		$this->view_data['controller'] = $this->controller;
		$this->view_data['search_string'] = Phpr::$session->get($this->get_id().'_search');
	}

	// Form Event Handlers
	//

	// Try to determine foreign key, relation name and relation type automatically
	private function autoload_child_properties()
	{
		$model = $this->model;

		if (!$this->form_relation_name)
			$this->form_relation_name = $this->column_name;

		if ($this->form_relation_type)
			return;

		$relation_name = $this->form_relation_name;

		if (!isset($model->has_models[$relation_name]))
			return;

		$relation_type = $model->has_models[$relation_name];
		$this->form_relation_type = $relation_type;

		if ($relation_type == "has_many")
		{
			if ($this->form_foreign_key)
				return;

			if (isset($model->has_many[$relation_name]['foreign_key']))
				$foreign_key = $model->has_many[$relation_name]['foreign_key'];
			else
				$foreign_key = Phpr_Inflector::singularize($model->table_name) . "_" . $model->primary_key;

			$this->form_foreign_key = $foreign_key;
		}
	}

	private function autoload_child_class_name()
	{
		if (!$this->form_relation_name && !$this->form_relation_type)
			return;

		if (!$this->class_name)
			return $this->class_name = $this->model->{$this->form_relation_type}[$this->form_relation_name]['class_name'];
	}
	
	private function autoload_finder_properties()
	{
		$this->finder_mode = true;
		$this->control_panel = null;
		$this->no_form = true;
		$this->is_editable = false;
		$this->show_delete_icon = false;
		$this->form_relation_type = null;
		$this->show_checkboxes = true;
		$this->unique_id = $this->unique_id .= "_finder";
	}

	public function cp_popup_button($icon='plus', $options = array())
	{
		if (!isset($options['data']['form_popup_mode']))
			$options['data']['form_popup_mode'] = 'create';

		$ajax_fields_arr = array();
		foreach ($options['data'] as $key=>$value) {
			$ajax_fields_arr[] = "'".$key."': '".$value."'";
		}

		$ajax_fields = implode(','.PHP_EOL,  $ajax_fields_arr);

		$attributes = (isset($options['attributes'])) ? $options['attributes'] : array();
		$attributes['onclick'] = "new PopupForm('".$this->controller->get_event_handler('on_form_widget_event')."', { 
				ajaxFields: {
					".$ajax_fields.", 
					".$this->get_event_handler_data('on_form_popup').",
					form_context: '".$this->controller->form_get_context()."', 
					edit_session_key: '".$this->controller->form_get_edit_session_key()."'
				} 
			}); return false";

		$label = (isset($options['label'])) ? $options['label'] : 'Add '.$this->form_title;

		return cp_button($label, $icon, $attributes);
	}

	public function cp_add_button($icon='plus', $options = array())
	{
		$options['data']['form_popup_mode'] = 'add';
		return $this->cp_popup_button($icon, $options);
	}

	public function cp_create_button($icon='plus', $options = array())
	{
		$options['data']['form_popup_mode'] = 'create';
		return $this->cp_popup_button($icon, $options);
	}

	public function cp_delete_button($icon='minus', $options = array())
	{		
		$ajax_fields = '';
		if (isset($options['data'])) {
			$ajax_fields_arr = array();
			foreach ($options['data'] as $key=>$value) {
				$ajax_fields_arr[] = "'".$key."': '".$value."'";
			}

			$ajax_fields = implode(','.PHP_EOL,  $ajax_fields_arr) . ',';
		}

		$attributes = (isset($options['attributes'])) ? $options['attributes'] : array();
		$attributes['onclick'] = "return $('".$this->get_id()."').phpr().post(
		'".$this->controller->get_event_handler('on_form_widget_event')."',
		{
			data: {
				".$ajax_fields."
				".$this->get_event_handler_data('on_list_delete_selected')."
			},
			update: '#".$this->get_id()."',
			loadIndicator: {
				show: true,
				element: '#".$this->get_id()."',
				hideOnSuccess: true
			},
			confirm: 'Do you really want to remove these ".strtolower(Phpr_Inflector::pluralize($this->form_title))."?',
			afterUpdate: ".$this->get_id()."_init
		}).send()";

		$label = (isset($options['label'])) ? $options['label'] : 'Delete '.Phpr_Inflector::pluralize($this->form_title);

		return cp_button($label, $icon, $attributes);
	}

	public function on_form_popup($field, $model)
	{
		$this->load_relations();
		try
		{
			$model_id = post('primary_id', null);
			$form_context = post('form_context');
			$mode = post('form_popup_mode', 'create');
			
			// Deferred sessions not needed
			if ($form_context == "preview")
				$this->controller->reset_form_edit_session_key();

			$model_context = $this->form_context;
			$model_class = $this->class_name;
			$model = call_user_func(array($model_class, 'create'));
			if ($model_id)
				$model = $model->find($model_id);

			$model->init_form_fields($model_context);

			if (method_exists($this->controller, 'listwidget_before_form_popup_'.$this->column_name))
				$this->controller->{'listwidget_before_form_popup_'.$this->column_name}($model);

			$this->view_data['model'] = $model;
			$this->view_data['new_record_flag'] = !($model_id);
			$this->view_data['form_title'] = $this->form_title;

			$this->display_partial('list_popup_'.$mode);
		}
		catch (Exception $ex)
		{
			$this->controller->handle_page_error($ex);
		}
	}

	public function on_form_update($field, $model)
	{
		$this->load_relations();
		try
		{
			$master_object_id = $this->model->id;
			$master_object_class = get_class($this->model);
			$master_object = $this->model;

			$session_key = post('edit_session_key');
			$form_context = post('form_context');

			// Do not use deferred sessions
			if ($form_context == "preview")
				$session_key = null;

			$model_id = post('primary_id');

			$model_class = $this->class_name;
			$model = call_user_func(array($model_class, 'create'));

			$foreign_key = $this->form_foreign_key;
			$relation_type = $this->form_relation_type;
			$relation_name = $this->form_relation_name;

			if ($model_id) {
				$model = $model->find($model_id);
			}
			
			if ($foreign_key !== null && $relation_type == "has_many")
				$model->{$foreign_key} = $master_object_id;

			$data = post($model_class, array());

			$model->init_columns();
			$model->init_form_fields();

			if (method_exists($this->controller, 'listwidget_before_form_update_'.$this->column_name))
				$this->controller->{'listwidget_before_form_update_'.$this->column_name}($model, $data, $master_object);

			$model->save($data, $session_key);

			// Is new record (!$model_id)
			if (!$model_id && ($relation_type == "has_and_belongs_to_many" || $relation_type == "has_many"))
			{
				// Prevents duplicate
				if ($relation_type == "has_and_belongs_to_many")
					$master_object->{$relation_name}->delete($model, $session_key);

				$master_object->{$relation_name}->add($model, $session_key);
				
				// Preview lists should autosave
				if (!$session_key)
					$master_object->save();
			}

			$this->display_table();
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}

	public function on_form_add($field, $model)
	{
		unset($_POST['finder_mode_flag']);

		$this->load_relations();
		try
		{
			$master_object_id = $this->model->id;
			$master_object_class = get_class($this->model);
			$master_object = $this->model;

			$session_key = post('edit_session_key');
			$form_context = post('form_context');

			// Do not use deferred sessions
			if ($form_context == "preview")
				$session_key = null;

			$foreign_key = $this->form_foreign_key;
			$relation_type = $this->form_relation_type;
			$relation_name = $this->form_relation_name;

			$model_ids = post('list_ids', array());
			if (!count($model_ids))
				throw new Phpr_ApplicationException('Please select '.$this->form_title.'(s) to add.');

			$model_class = $this->class_name;
			$models = call_user_func(array($model_class, 'create'));
			$models = $models->where('id in (?)', array($model_ids))->find_all();

			foreach ($models as $model) {
				$master_object->{$relation_name}->delete($model, $session_key);
				$master_object->{$relation_name}->add($model, $session_key);

				// Preview lists should autosave
				if (!$session_key)
					$master_object->save();
			}

			$this->display_table();

		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}

	public function on_form_delete($field, $model)
	{
		$this->load_relations();
		try
		{
			$master_object_id = $this->model->id;
			$master_object_class = get_class($this->model);
			$master_object = $this->model;

			if (!$master_object)
				throw new Exception("Could not find master object");

			$model_id = post('primary_id', null);

			if (!$model_id)
				throw new Phpr_ApplicationException("Missing item or item has already been deleted");

			$relation_type = $this->form_relation_type;
			$relation_name = $this->form_relation_name;

			$model_class = $this->class_name;
			$model = call_user_func(array($model_class, 'create'));
			$model = $model->find($model_id);

			if ($relation_type == "has_and_belongs_to_many")
			{
				$master_object->{$relation_name}->delete($model);
				$master_object->save();
			}
			else
				$model->delete();

			$this->display_table();
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}

	// List Event Handlers
	//

	public function on_list_column_click()
	{
		$this->load_relations();
		$column = post('column_name');
		if (strlen($column))
		{
			$sorting_column = $this->eval_sorting_column();
			if ($sorting_column->field == $column)
				$sorting_column->direction = $sorting_column->direction == 'asc' ? 'desc' : 'asc';
			else
			{
				$sorting_column->field = $column;
				$sorting_column->direction = 'asc';
			}
			
			$this->save_preference('sorting', $sorting_column);
			$this->display_table();
		}
	}
	
	public function on_list_next_page()
	{
		$this->load_relations();
		try
		{
			$page = $this->load_preference('page') + 1;
			$this->save_preference('page', $page);
			$this->display_table();
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}
	
	public function on_list_prev_page()
	{
		$this->load_relations();
		try
		{
			$page = $this->load_preference('page') - 1;
			$this->save_preference('page', $page);
			$this->display_table();
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}
	
	public function on_list_set_page()
	{
		$this->load_relations();
		try
		{
			$this->save_preference('page', post('pageIndex'));
			$this->display_table();
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}
	
	public function on_list_reload()
	{
		$this->load_relations();
		$this->display_table();
	}
	
	public function on_list_search()
	{
		$this->load_relations();
		$search_string = trim(post('search_string'));
		if ($this->min_search_query_length > 0 && mb_strlen($search_string) < $this->min_search_query_length)
			throw new Phpr_ApplicationException(sprintf('The entered search query is too short. Please enter at least %s symbols', $this->min_search_query_length));
		
		$this->save_preference('search', $search_string);
		$this->display_table();
	}
	
	public function on_list_search_cancel()
	{
		$this->load_relations();
		$this->save_preference('search', '');
		$this->display_table();
	}

	public function on_list_set_order()
	{
		$this->load_relations();
		$child_model = $this->create_child_model_object();
		$child_model->set_item_orders(post('item_ids'), post('sort_orders'));
	}

	protected function on_list_delete_selected($id = null)
	{
		$this->load_relations();
		try
		{
			$master_object_id = $this->model->id;
			$master_object_class = get_class($this->model);
			$master_object = $this->model;

			$child_model = $this->create_child_model_object();

			$session_key = post('edit_session_key');
			$form_context = post('form_context');

			// Do not use deferred sessions
			if ($form_context == "preview")
				$session_key = null;

			$relation_name = $this->form_relation_name;			
			
			$model_ids = post('list_ids', array());
			if (!count($model_ids))
				throw new Phpr_ApplicationException('Please select '.$this->form_title.'(s) to delete.');

			$models = $child_model->where('id in (?)', array($model_ids))->find_all();

			foreach ($models as $model) {
				$master_object->{$relation_name}->delete($model, $session_key);

				// Preview lists should autosave
				if (!$session_key)
					$master_object->save();
			}

			$this->display_table();
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}

	// Preferences
	//

	protected function save_preference($name, $value)
	{
		Phpr::$session->set($this->get_id().'_'.$name, $value);
	}

	protected function load_preference($name)
	{
		return Phpr::$session->get($this->get_id().'_'.$name);
	}

	// Internals
	//

	public function list_cell_class($column_definition)
	{
		$result = $column_definition->type;
		
		$sorting_column = $this->eval_sorting_column();
		if ($sorting_column->field == $column_definition->db_name)
		{
			$result .= ' active ';
			$result .= $sorting_column->direction == 'asc' ? 'order-asc' : 'order-desc';
		}

		return $result;
	}

	public function list_format_record_url($model)
	{
		$record_url = $this->record_url;
		
		if (!strlen($record_url))
		{
			if (!strlen($this->record_onclick))
				return null;
				
			return "#";
		}

		if (strpos($record_url, '%s'))
			return sprintf($record_url, $model->id);
		else
			return $record_url.$model->id;
	}

	public function list_format_record_onclick($model)
	{
		$onclick = $this->record_onclick;
		
		if (!strlen($onclick))
			return null;

		if (strpos($onclick, '%s'))
			return 'onclick="'.sprintf($onclick, $model->id).'"';
		else
			return 'onclick="'.$onclick.'"';
	}
	
	public function list_format_cell_onclick($model)
	{
		$onclick = $this->record_onclick;
		
		if (!strlen($onclick))
			return null;

		if (strpos($onclick, '%s'))
			return sprintf($onclick, $model->id);

		return $onclick;
	}

	protected function create_child_model_object()
	{
		if ($this->child_model !== null)
			return $this->child_model;

		if (!strlen($this->class_name))
			throw new Phpr_SystemException('Data model class is not specified for List Widget. Use the class_name option to set it');
			
		$model_class = $this->class_name;
		$result = $this->child_model = new $model_class();
		
		return $result;
	}

	protected function load_data()
	{
		$child_model = $this->create_child_model_object();

		if ($this->form_relation_type == "has_and_belongs_to_many" || $this->form_relation_type == "has_many")
		{
			$child_model = $this->model->get_deferred($this->form_relation_name, $this->controller->form_get_edit_session_key());
		}

		// Apply conditions
		if ($this->conditions)
			$child_model->where($this->conditions, array('id' => $this->model->id));

		// Apply search
		$search_string = $this->load_preference('search');
		if ($this->search_enabled)
		{
			if (!$this->search_fields)
				throw new Phpr_ApplicationException('List search is enabled, but search fields are not specified in the list settings. Please use $search_fields option to define an array of fields to search in');
			
			if (!strlen($search_string) && !$this->search_show_empty_query)
			{
				$first_field = $this->search_fields[0];
				$child_model->where($first_field.' <> '.$first_field);
			} 
			elseif (strlen($search_string))
			{
				$words = explode(' ', $search_string);
				$word_queries = array();
				$word_queries_int = array();
				foreach ($words as $word)
				{
					if (!strlen($word))
						continue;

					$word = trim(mb_strtolower($word));
					$word_queries[] = '%1$s like \'%2$s'.mysql_real_escape_string($word).'%2$s\'';
					$word_queries_int[] = '%1$s = ((\''.mysql_real_escape_string($word).'\')+0)';
				}

				$field_queries = array();
				foreach ($this->search_fields as $field)
				{
					$field_name = $field;
					
					$field = str_replace('@', $child_model->table_name.'.', $field);

					if ($field_name == 'id' || $field_name == '@id')
						$field_queries[] = '('.sprintf(implode(' and ', $word_queries_int), $field, '%').')';
					else
						$field_queries[] = '('.sprintf(implode(' and ', $word_queries), $field, '%').')';
				}

				$query = '('.implode(' or ', $field_queries).')';
				$child_model->where($query);
			}
		}
		
		if (method_exists($this->controller, 'listwidget_before_fetch_'.$this->column_name))
			$this->controller->{'listwidget_before_fetch_'.$this->column_name}($child_model, $this->model);
		
		return $child_model;
	}

	protected function eval_list_columns($only_visible = true)
	{
		if ($this->_columns !== null && $only_visible)
			return $this->_columns;

		$model = $this->create_child_model_object();
		$model->init_columns('list_widget');
		$this->apply_defaults();

		$definitions = $model->get_column_definitions($this->data_context);
		if (!count($definitions))
			throw new Phpr_ApplicationException('Error rendering list: model columns are not defined.');

		$visible_found = false;
		foreach ($definitions as $definition)
		{
			if ($definition->visible)
			{
				$visible_found = true;
				break;
			}
		}
		if (!$visible_found)
			throw new Phpr_ApplicationException('Error rendering list: there are no visible columns defined in the model.');

		if (count($this->columns))
			$ordered_list = $this->columns;
		else
		{
			$column_list = array();

			// Add columns
			foreach ($definitions as $column_name=>$definition)
			{
				if (!in_array($column_name, $column_list) && $definition->visible
				&& (($only_visible && $definitions[$column_name]->default_visible) || !$only_visible))
					$column_list[] = $column_name ;
			}
		
			// Apply column order
			$ordered_list = array();
			if (!count($this->column_order))
				$this->column_order = array_keys($definitions);
			
			foreach ($this->column_order as $column_name)
			{
				if (in_array($column_name, $column_list))
					$ordered_list[] = $column_name;
			}
		
			foreach ($column_list as $column_name)
			{
				if (!in_array($column_name, $ordered_list))
					$ordered_list[] = $column_name;
			}
		}

		$result = array();
		foreach ($ordered_list as $index=>$column_name)
		{
			$definition_obj = $definitions[$column_name];
			$definition_obj->index = $index;
			$result[] = $definition_obj;
		}
		
		$this->_list_column_number = count($result);
		if ($only_visible)
			$this->_columns = $result;
			
		return $result;
	}

	protected function eval_sorting_column()
	{
		if ($this->sorting_column)
		{
			$column = $this->sorting_column;

			$direction = $this->sorting_direction;
			if (strtoupper($direction) != 'ASC' && strtoupper($direction) != 'DESC')
				$direction = 'asc';
			return (object)(array('field'=>$column, 'direction'=>$direction));
		}
		
		if ($this->_sorting_column !== null)
			return $this->_sorting_column;
			
		$list_columns = $this->eval_list_columns();
		$this->apply_defaults();
		$model = $this->create_child_model_object();
		$definitions = $model->get_column_definitions();

		if (isset($this->sorting->field) && array_key_exists($this->sorting->field, $definitions) )
			return $this->sorting;

		if (strlen($this->default_sorting_column))
		{
			$column = $this->default_sorting_column;
			$direction = $this->sorting_direction;
			if (strtoupper($direction) != 'ASC' && strtoupper($direction) != 'DESC')
				$direction = 'asc';
			$this->_sorting_column = (object)(array('field'=>$column, 'direction'=>$direction));
			return $this->_sorting_column;
		}

		foreach ($definitions as $column_name=>$definition)
		{
			if ($definition->default_order !== null)
				return (object)(array('field'=>$column_name, 'direction'=>$definition->default_order));
		}

		if (!count($list_columns))
			return null;

		$column_names = array_keys($list_columns);
		$first_column = $column_names[0];

		$this->_sorting_column = (object)(array('field'=>$list_columns[$first_column]->db_name, 'direction'=>'asc'));
		return $this->_sorting_column;
	}

}