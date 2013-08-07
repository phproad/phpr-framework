<?php namespace Db;

use Phpr;
use Phpr\Controller_Behavior;
use Phpr\User_Parameters;
use Phpr\Pagination;
use Phpr\SystemException;
use Phpr\ApplicationException;
use Db\Helper as Db_Helper;
use File\Upload;
use File\Csv;

class List_Behavior extends Controller_Behavior
{
	public $list_model_class = null;
	public $list_name = null;
	public $list_items_per_page = 20;
	public $list_no_data_message = 'There are no items in this view';
	public $list_load_indicator = null;
	public $list_record_url = null;
	public $list_record_onclick = null;
	public $list_handle_row_click = true;
	public $list_display_as_tree = false;
	public $list_display_as_sliding_list = false;
	public $list_root_level_label = 'Root';
	public $list_sorting_column = null;
	public $list_sorting_direction = null;
	public $list_default_sorting_column = null;
	public $list_default_sorting_direction = null;
	public $list_no_sorting = false;
	public $list_data_context = null;
	public $list_reuse_model = true;
	public $list_node_expanded_default = true;

	public $list_csv_import_url = null;
	public $list_csv_cancel_url = null;
	public $list_csv_template_url = null;

	public $list_search_enabled = false;
	public $list_min_search_query_length = 0;
	public $list_search_show_empty_query = true;
	public $list_search_prompt = null;
	public $list_search_fields = array();
	public $list_search_custom_func = null;

	public $list_no_interaction = false;
	public $list_no_js_declarations = false;
	public $list_no_form = false;
	public $list_no_setup_link = false;
	public $list_no_pagination = false;
	public $list_scrollable = false;

	public $list_custom_body_cells = null;
	public $list_custom_head_cells = null;
	public $list_cell_partial = false;
	public $list_custom_partial = null;
	public $list_cell_individual_partial = array();
	public $list_top_partial = null;
	public $list_control_panel = null;
	public $list_sidebar_panel = null;

	public $list_render_filters = false;

	public $list_columns = array();
	public $list_options = array();

	protected $_model_object = null;
	protected $_total_item_number = null;
	protected $_list_settings = null;
	protected $_list_columns = null;
	protected $_list_column_number = null;
	protected $_list_sorting_column = null;
	protected $_children_number_cache = null;
	protected $_model_primary_key_name = null;

	public function __construct($controller)
	{
		parent::__construct($controller);
	}

	public function init_extension()
	{
		if (!$this->_controller)
			return;

		$this->list_load_resources();

		$this->hide_action('list_prepare_data');
		$this->add_event_handler('on_list_column_click');
		$this->add_event_handler('on_load_list_setup');
		$this->add_event_handler('on_apply_list_settings');

		$this->add_event_handler('on_list_prev_page');
		$this->add_event_handler('on_list_next_page');
		$this->add_event_handler('on_list_set_page');
		$this->add_event_handler('on_list_toggle_node');
		$this->add_event_handler('on_list_reload');
		$this->add_event_handler('on_list_search');
		$this->add_event_handler('on_list_search_cancel');
		$this->add_event_handler('on_list_goto_node');
	}

	//
	// Asset management
	// 

	protected function list_load_resources()
	{
		$phpr_url = Phpr::$config->get('PHPR_URL', 'phpr');

		if (!$this->list_load_indicator)
			$this->list_load_indicator = $phpr_url.'/assets/images/loading_50.gif';
	}

	//
	// Public methods - available to call in views
	// 

	public function list_render($options = array(), $partial = null)
	{
		$this->_model_object = null;
		$this->_total_item_number = null;
		$this->_list_settings = null;
		$this->_list_columns = null;
		$this->_list_column_number = null;
		$this->_list_sorting_column = null;

		$this->apply_options($options);

		$this->prepare_render_data();

		if (!$partial)
			$this->display_partial('list_container');
		else
			$this->display_partial($partial);
	}

	public function list_cell_class($column_definition)
	{
		$list_display_path_column = isset($this->view_data['list_display_path_column']) && $this->view_data['list_display_path_column'];
		$result = $column_definition->type;
		$result .= (!$list_display_path_column && $column_definition->index == $this->_list_column_number-1) ? ' last' : null;
		
		$sorting_column = $this->_controller->list_override_sorting_column($this->eval_sorting_column());
		if ($sorting_column->field == $column_definition->db_name)
		{
			$result .= ' active ';
			$result .= $sorting_column->direction == 'asc' ? 'order-asc' : 'order-desc';
		}

		return $result;
	}


	public function list_apply_options($options)
	{
		$this->apply_options($options);
	}

	public function list_get_name()
	{
		if ($this->_controller->list_name !== null)
			return $this->_controller->list_name;

		return get_class($this->_controller).'_'.Phpr::$router->action.'_list';
	}
	
	public function list_get_form_id()
	{
		return 'listform'.$this->list_get_name();
	}
	
	public function list_get_popup_form_id()
	{
		return 'listform_popup'.$this->list_get_name();
	}

	public function list_get_container_id()
	{
		return 'list'.$this->list_get_name();
	}

	public function list_get_element_id($element)
	{
		return $element.$this->list_get_name();
	}
	
	public function list_render_partial($view, $params=array(), $throw_not_found=true)
	{
		$model = $this->create_model_object();
		
		$this->display_controller_partial($model->native_controller, $view, $params, false, $throw_not_found);
	}

	public function list_eval_total_item_number()
	{
		if ($this->_total_item_number !== null)
			return $this->_total_item_number;
			
		$model = $this->load_data();
		
		if ($this->_controller->list_display_as_sliding_list)
			$this->configure_sliding_list_data($model);

		return $this->_total_item_number = $this->_controller->list_get_total_item_number($model);
	}
	
	public function list_get_record_children_count($record)
	{
		if ($this->_children_number_cache === null)
		{
			$model = $this->create_model_object();
			$this->_model_primary_key_name = $model->primary_key;
			$parent_id = $record->{$model->act_as_tree_parent_key};
			
			$query = 'select c1.{primary_key_field} as id, count(c2.{primary_key_field}) as cnt
				from {table_name} c1
				left join {table_name} c2 on c2.{parent_field}=c1.{primary_key_field}
				where %s
				group by c1.{primary_key_field}';
			
			if (strlen($parent_id))
				$query = sprintf($query, 'c1.{parent_field} = :parent_id');
			else
				$query = sprintf($query, 'c1.{parent_field} is null');
			
			$cache_data = Db_Helper::query_array(strtr($query , array(
				'{primary_key_field}' => $model->primary_key,
				'{table_name}' => $model->table_name,
				'{parent_field}' => $model->act_as_tree_parent_key
			)), array('parent_id' => $parent_id));

			$this->_children_number_cache = array();
			foreach ($cache_data as $cache_item)
			{
				$this->_children_number_cache[$cache_item['id']] = $cache_item['cnt'];
			}
		}
		
		$record_pk = $record->{$this->_model_primary_key_name};
		
		if (!array_key_exists($record_pk, $this->_children_number_cache))
			return 0;
			
		return $this->_children_number_cache[$record_pk];
	}
	
	public function list_get_prev_level_parent_id($model, $current_parent_id)
	{
		return Db_Helper::scalar(strtr('select {parent_field} from {table_name} where {primary_key_field}=:parent_id', array(
			'{table_name}' => $model->table_name,
			'{parent_field}' => $model->act_as_tree_parent_key,
			'{primary_key_field}' => $model->primary_key
		)), array('parent_id' => $current_parent_id));
	}
	
	public function list_get_navigation_parents($model, $current_parent_id)
	{
		if (!$current_parent_id)
			return array();
		
		$sql = 'select {primary_key_field} as id, {parent_field} as parent_id, {title_field} as title from {table_name} where {primary_key_field}=:id';
		$sql = strtr($sql, array(
			'{table_name}' => $model->table_name,
			'{parent_field}' => $model->act_as_tree_parent_key,
			'{primary_key_field}' => $model->primary_key,
			'{title_field}' => $model->act_as_tree_name_field
		));
		
		$result = array();
		while ($current_parent_id)
		{
			$obj = Db_Helper::object($sql, array('id' => $current_parent_id));
			if (!$obj)
				break;
			$result[] = $obj;
			$current_parent_id = $obj->parent_id;
		}
		
		return array_reverse($result);
	}

	public function list_display_table()
	{
		$this->display_table();
	}
	
	public function list_render_csv_import()
	{
		$completed = false;
		$errors = array();
		$success = 0;

		if (post('postback'))
		{
			try
			{
				Upload::validate_uploaded_file($_FILES['file']);
				$file_info = $_FILES['file'];

				$path_info = pathinfo($file_info['name']);
				if (!isset($path_info['extension']) || strtolower($path_info['extension']) != 'csv')
					throw new ApplicationException('Imported file is not a CSV file.');

				$file_path = null;
				try
				{
					if (!is_writable(PATH_APP.'/temp/'))
						throw new SystemException('There is no writing permissions for the directory: '.PATH_APP.'/temp');

					$file_path = PATH_APP.'/temp/'.uniqid('csv');
					if (!move_uploaded_file($file_info['tmp_name'], $file_path))
						throw new SystemException('Unable to copy the uploaded file to '.$file_path);
						
					$model_class = $this->_controller->list_model_class;
					$model_object = new $model_class();

					if (!$model_object->is_extended_with('Db_Model_Csv'))
						throw new SystemException("The model class ".$model_class." should be extended with the Db_Model_Csv extension.");

					$delimeter = Csv::determine_csv_delimeter($file_path);
					if (!$delimeter)
						throw new SystemException('Unable to detect the file type');
						
					$handle = @fopen($file_path, "r");
					if (!$handle)
						throw new ApplicationException('Unable to open the uploaded file');
					
					$skip_first_row = post('first_row_titles');
					$completed = true;
					$counter = 0;

					while (($data = fgetcsv($handle, 10000, $delimeter)) !== false) 
					{
						if (Csv::csv_row_is_empty($data))
							continue;

						if ($skip_first_row)
						{
							$skip_first_row = false;
							continue;
						}

						$counter++;

						$model_object = new $model_class();
						try
						{
							$model_object->csv_import_record(null, $data);
							$success++;
						} 
						catch (Exception $ex)
						{
							$errors[$counter] = $ex->getMessage();
						}
					}

					@fclose($handle);
					@unlink($file_path);
				}
				catch (Exception $ex)
				{
					if (isset($handle) && $handle)
						@fclose($handle);

					if (strlen($file_path) && @file_exists($file_path))
						@unlink($file_path);

					throw $ex;
				}
			}
			catch (Exception $ex)
			{
				$this->view_data['form_error'] = $ex->getMessage();
			}
		}
		$this->view_data['errors'] = $errors;
		$this->view_data['success'] = $success;
		$this->view_data['completed'] = $completed;
		$this->display_partial('list_import_csv');
	}

	/*
	* @param array $extend_csv_callback: array with two possible elements, both arrays where
	* the key is 'header_callback' or 'row_callback' and value an array with valid callback
	* example: array('header_callback' => array('Shop_Order', 'export_orders_and_products_header'));
	*/
	public function list_export_csv($filename, $options = array(), $filter_callback = null, $no_column_info_init = false, $extend_csv_callback = array())
	{
		Phpr::$events->fire_event('db:on_before_list_export', $this->_controller);

		$this->apply_options($options);

		$data_model = $this->load_data();
		$column_defintions = $data_model->get_column_definitions($this->_controller->list_data_context);
		$sorting_column = $this->_controller->list_override_sorting_column($this->eval_sorting_column());
		$sorting_field = $column_defintions[$sorting_column->field]->get_sorting_column_name();

		$list_sort_column = $sorting_field.' '.$sorting_column->direction;
		$data_model->reset_order();
		$data_model->order($list_sort_column);

		$list_columns = $this->eval_list_columns();

		$data_model->apply_calculated_columns();
		$query = $data_model->build_sql();
		
		header("Expires: 0");
		header("Content-Type: Content-type: text/csv");
		header("Content-Description: File Transfer");
		header("Cache-control: private");
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Cache-Control: pre-check=0, post-check=0, max-age=0');
		header("Content-disposition: attachment; filename=$filename");

		$this->_controller->suppress_view();

		$header = array();
		foreach ($list_columns as $column)
			$header[] = strlen($column->list_title) ? $column->list_title : $column->display_name;
			
		$iwork = array_key_exists('iwork', $options) ? $options['iwork'] : false;
		$separator = $iwork ? ',' : ';';
		
		if (array_key_exists('header_callback', $extend_csv_callback))
			$header = call_user_func($extend_csv_callback['header_callback'], $header);

		Csv::output_csv_row($header, $separator);

		$list_data = Db_Helper::query_array($query);
		foreach ($list_data as $row_data)
		{
			$row = $data_model;
			$row->fill($row_data);
			
			if ($filter_callback)
			{
				if (!call_user_func($filter_callback, $row))
					continue;
			}
			
			$row_data = array();
			foreach ($list_columns as $index => $column)
				$row_data[] = $row->display_field($column->db_name, 'list');
			
			if (array_key_exists('row_callback', $extend_csv_callback))
				call_user_func($extend_csv_callback['row_callback'], $row, $row_data, $separator);
			else
				Csv::output_csv_row($row_data, $separator);
		}
	}

	public function list_cancel_search()
	{
		Phpr::$session->set($this->list_get_name().'_search', '');
	}
	
	public function list_reset_cache()
	{
		$this->_model_object = null;
		$this->_total_item_number = null;
		$this->_list_settings = null;
		$this->_list_columns = null;
		$this->_list_column_number = null;
		$this->_list_sorting_column = null;
	}
	
	// Common methods - available to override in controller
	// 

	/**
	 * Returns a configured model object.
	 * @return Db\ActiveRecord
	 */
	public function list_prepare_data()
	{
		$obj = $this->create_model_object();
		return $obj;
	}

	public function list_extend_model_object($model)
	{
		return $model;
	}

	/**
	 * Returns a total number of items, not limited by a current page.
	 * @return int
	 */
	public function list_get_total_item_number($model)
	{
		return $model->get_row_count();
	}

	public function list_format_record_url($model)
	{
		$record_url = $this->_controller->list_record_url;
		
		if (!strlen($record_url))
		{
			if (!strlen($this->_controller->list_record_onclick))
				return null;
				
			return "#";
		}

		if (strpos($record_url, '%s'))
			return sprintf($record_url, $model->id);
		else
			return $record_url.$model->id;
	}

	public function list_format_record_on_click($model)
	{
		$onclick = $this->_controller->list_record_onclick;
		
		if (!strlen($onclick))
			return null;

		if (strpos($onclick, '%s'))
			return 'onclick="'.sprintf($onclick, $model->id).'"';
		else
			return 'onclick="'.$onclick.'"';
	}
	
	public function list_format_cell_on_click($model)
	{
		$onclick = $this->_controller->list_record_onclick;
		
		if (!strlen($onclick))
			return null;

		if (strpos($onclick, '%s'))
			return sprintf($onclick, $model->id);

		return $onclick;
	}

	public function list_node_is_expanded($node)
	{
		return User_Parameters::get($this->list_get_name().'_treenodestatus_'.$node->id, null, $this->_controller->list_node_expanded_default);
	}
	
	public function list_reset_page()
	{
		Phpr::$session->set($this->list_get_name().'_page', 0);
	}

	public function list_get_row_class($model)
	{
		return null;
	}

	public function list_before_display_record($model)
	{
	}

	public function list_override_sorting_column($sorting_column)
	{
		return $sorting_column;
	}

	// Event handlers
	//
	
	public function on_list_column_click()
	{
		$column = post('column_name');
		if (strlen($column))
		{
			$sorting_column = $this->_controller->list_override_sorting_column($this->eval_sorting_column());
			if ($sorting_column->field == $column)
				$sorting_column->direction = $sorting_column->direction == 'asc' ? 'desc' : 'asc';
			else
			{
				$sorting_column->field = $column;
				$sorting_column->direction = 'asc';
			}
			
			$sorting_column = $this->_controller->list_override_sorting_column($sorting_column);
			
			$this->save_sorting_column($sorting_column);
			$this->display_table();
		}
	}
	
	public function on_list_next_page()
	{
		try
		{
			$page = $this->eval_page_number() + 1;
			$this->set_page_number($page);
		
			$this->display_table();
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}
	
	public function on_list_prev_page()
	{
		try
		{
			$page = $this->eval_page_number() - 1;
			$this->set_page_number($page);

			$this->display_table();
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}
	
	public function on_list_set_page()
	{
		try
		{
			$this->set_page_number(post('pageIndex'));

			$this->display_table();
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajax_report_exception($ex, true, true);
		}
	}

	public function on_load_list_setup()
	{
		$list_settings = $this->load_list_settings();
		$this->view_data['columns'] = $this->eval_list_columns(false);
		$this->view_data['visible_columns'] = $list_settings['visible_list'];
		$this->view_data['invisible_columns'] = $list_settings['invisible_list'];
		$this->view_data['list_load_indicator'] = $this->_controller->list_load_indicator;
		$this->view_data['records_per_page'] = $list_settings['records_per_page'];

		$this->display_partial('list_settings_form');
	}
	
	public function on_apply_list_settings()
	{
		$model = $this->create_model_object();
		$list_settings = $this->load_list_settings();

		/*
		 * Apply visible columns
		 */
		$visible_columns = array_keys(post('list_visible_colums', array()));
		$list_settings['visible_list'] = $visible_columns;
		
		/*
		 * Apply invisible columns
		 */
		$invisible_columns = array();
		$definitions = $model->get_column_definitions($this->_controller->list_data_context);
		foreach ($definitions as $dn_name => $definition)
		{
			if (!in_array($dn_name, $visible_columns))
				$invisible_columns[] = $dn_name;
		}
		$list_settings['invisible_list'] = $invisible_columns;

		/*
		 * Apply column order columns
		 */
		$list_settings['column_order'] = post('ordered_list', array());

		/*
		 * Apply records per page
		 */
		$list_settings['records_per_page'] = post('records_per_page', $this->_controller->list_items_per_page);

		$this->save_list_settings($list_settings);
		$this->display_table();
	}

	public function on_list_toggle_node()
	{
		User_Parameters::set($this->list_get_name().'_treenodestatus_'.post('node_id'), post('status') ? 0 : 1);
		$this->display_table();
	}
	
	public function on_list_goto_node()
	{
		$this->set_current_parent_id(post('node_id'));
		$this->set_page_number(0);
		$this->display_table();
	}
	
	public function on_list_reload()
	{
		$this->display_table();
	}
	
	public function on_list_search()
	{
		$search_string = trim(post('search_string'));
		if ($this->_controller->list_min_search_query_length > 0 && mb_strlen($search_string) < $this->_controller->list_min_search_query_length)
			throw new ApplicationException(sprintf('The entered search query is too short. Please enter at least %s symbols', $this->_controller->list_min_search_query_length));
		
		Phpr::$session->set($this->list_get_name().'_search', $search_string);

		$this->display_table();
	}
	
	public function on_list_search_cancel()
	{
		Phpr::$session->set($this->list_get_name().'_search', '');
		$this->display_table();
	}

	// Internals
	// 

	/**
	 * Returns a list of list columns in correct order
	 */
	protected function eval_list_columns($only_visible = true)
	{
		if ($this->_list_columns !== null && $only_visible)
			return $this->_list_columns;

		$model = $this->create_model_object();
		$model->init_columns('list_settings');
		$list_settings = $this->load_list_settings();

		$definitions = $model->get_column_definitions($this->_controller->list_data_context);
		if (!count($definitions))
			throw new ApplicationException('Error rendering list: model columns are not defined.');

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
			throw new ApplicationException('Error rendering list: there are no visible columns defined in the model.');

		if (count($this->_controller->list_columns))
			$ordered_list = $this->_controller->list_columns;
		else
		{
			$column_list = array();

			// Add visible columns
			//
			foreach ($list_settings['visible_list'] as $column_name)
			{
				if (array_key_exists($column_name, $definitions) && $definitions[$column_name]->visible)
					$column_list[] = $column_name;
			}

			// Add remaining columns if they are not invisible
			//
			foreach ($definitions as $column_name => $definition)
			{
				if (!in_array($column_name, $column_list) && (!in_array($column_name, $list_settings['invisible_list']) || !$only_visible) && $definition->visible
				&& (($only_visible && $definitions[$column_name]->default_visible) || !$only_visible))
					$column_list[] = $column_name ;
			}
		
			// Apply column order
			//
			$ordered_list = array();
			if (!count($list_settings['column_order']))
				$list_settings['column_order'] = array_keys($definitions);
			
			foreach ($list_settings['column_order'] as $column_name)
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
		foreach ($ordered_list as $index => $column_name)
		{
			$definition_obj = $definitions[$column_name];
			$definition_obj->index = $index;
			$result[] = $definition_obj;
		}
		
		$this->_list_column_number = count($result);
		if ($only_visible)
			$this->_list_columns = $result;
			
		return $result;
	}

	protected function eval_sorting_column()
	{
		if (strlen($this->_controller->list_sorting_column))
		{
			$column = $this->_controller->list_sorting_column;

			$direction = $this->_controller->list_sorting_direction;
			
			if (strtoupper($direction) != 'ASC' && strtoupper($direction) != 'DESC')
				$direction = 'asc';

			return (object)(array('field' => $column, 'direction' => $direction));
		}
		
		if ($this->_list_sorting_column !== null)
			return $this->_list_sorting_column;
			
		$list_columns = $this->eval_list_columns();
		$model = $this->create_model_object();
		$list_settings = $this->load_list_settings();
		$definitions = $model->get_column_definitions();

		if (strlen($list_settings['sorting']->field) && array_key_exists($list_settings['sorting']->field, $definitions) )
			return $list_settings['sorting'];

		if (strlen($this->_controller->list_default_sorting_column))
		{
			$column = $this->_controller->list_default_sorting_column;
			$direction = $this->_controller->list_sorting_direction;
			
			if (strtoupper($direction) != 'ASC' && strtoupper($direction) != 'DESC')
				$direction = 'asc';

			$this->_list_sorting_column = (object)(array('field' => $column, 'direction' => $direction));
			return $this->_list_sorting_column;
		}

		foreach ($definitions as $column_name=>$definition)
		{
			if ($definition->default_order !== null)
				return (object)(array('field' => $column_name, 'direction' => $definition->default_order));
		}

		if (!count($list_columns))
			return null;

		$column_names = array_keys($list_columns);
		$first_column = $column_names[0];

		$this->_list_sorting_column = (object)(array('field' => $list_columns[$first_column]->db_name, 'direction'=>'asc'));
		return $this->_list_sorting_column;
	}
	
	protected function eval_page_number()
	{
		return Phpr::$session->get($this->list_get_name().'_page', 0);
	}
	
	protected function set_page_number($page)
	{
		Phpr::$session->set($this->list_get_name().'_page', $page);
	}
	
	protected function get_current_parent_id()
	{
		return Phpr::$session->get($this->list_get_name().'_parent_id', null);
	}
	
	protected function set_current_parent_id($parent_id)
	{
		Phpr::$session->set($this->list_get_name().'_parent_id', $parent_id);
	}
	
	protected function save_sorting_column($sorting_obj)
	{
		$list_settings = $this->load_list_settings();
		$list_settings['sorting'] = $sorting_obj;
		$this->save_list_settings($list_settings);
	}

	protected function prepare_render_data($no_pagination = false, $no_column_info_init = false)
	{
		$form_context = $this->_controller->list_data_context;
		
		$this->view_data['list_columns'] = $list_columns = $this->eval_list_columns();
		$this->view_data['list_sorting_column'] = $sorting_column = $this->_controller->list_override_sorting_column($this->eval_sorting_column());
		$this->view_data['list_column_definitions'] = $this->create_model_object()->get_column_definitions();

		$model = $this->load_data();

		if ($this->_controller->list_display_as_sliding_list)
		{
			$current_parent_id = $this->view_data['list_current_parent_id'] = $this->configure_sliding_list_data($model);
			$this->view_data['list_upper_level_parent_id'] = $this->list_get_prev_level_parent_id($model, $current_parent_id);
			$this->view_data['list_navigation_parents'] = $this->list_get_navigation_parents($model, $current_parent_id);
		}
		
		$column_defintions = $model->get_column_definitions($form_context);
		$total_row_count = $this->list_eval_total_item_number();
		
		if (!$no_pagination && 
			!$this->_controller->list_display_as_tree  && 
			!$this->_controller->list_no_interaction && 
			!$this->_controller->list_no_pagination)
		{
			$list_settings = $this->load_list_settings();
			
			$pagination = new Pagination($list_settings['records_per_page']);
			$pagination->set_row_count($total_row_count);

			$pagination->set_current_page_index($this->eval_page_number());
			$pagination->limit_active_record($model);

			$this->view_data['list_pagination'] = $pagination;
		}

		$sorting_field = $column_defintions[$sorting_column->field]->get_sorting_column_name();

		$list_sort_column = $sorting_field.' '.$sorting_column->direction;
		$model->order($list_sort_column);

		$this->view_data['list_model_class'] = get_class($model);
		$this->view_data['list_total_row_count'] = $total_row_count;

		if ($no_column_info_init)
		{
			global $activerecord_no_columns_info;
			$activerecord_no_columns_info = true;
		}
		
		if (!$this->_controller->list_display_as_tree)
		{
			if (!$this->_controller->list_reuse_model)
				$this->view_data['list_data'] = $model->find_all(null, array(), $form_context);
			else
			{
				$model->apply_calculated_columns();
				$query = $model->build_sql();
				$this->view_data['list_data'] = Db_Helper::query_array($query);
				$this->view_data['reusable_model'] = $model;
			}
		} else
		{
			$this->_controller->list_reuse_model = false;
				$this->view_data['list_data'] = $model->list_root_children($list_sort_column);
		}
		
		if ($no_column_info_init)
			$activerecord_no_columns_info = false;

		$this->view_data['list_no_data_message'] = $this->_controller->list_no_data_message;
		$this->view_data['list_sort_column'] = $list_sort_column;

		$this->view_data['list_column_count'] = count($list_columns);
		$this->view_data['list_load_indicator'] = $this->_controller->list_load_indicator;
		$this->view_data['list_tree_level'] = 0;
		$this->view_data['list_search_string'] = Phpr::$session->get($this->list_get_name().'_search');
	}
	
	protected function configure_sliding_list_data($model)
	{
		$current_parent_id = $this->get_current_parent_id();
		if ($current_parent_id === null || !strlen($current_parent_id))
		{
			$model->where($model->act_as_tree_parent_key.' is null');
			return null;
		}
		else
		{
			$parent_exists = Db_Helper::scalar('select count(*) from `'.$model->table_name.'` where `'.$model->primary_key.'`=:id', array('id' => $current_parent_id));

			if (!$parent_exists)
			{
				$model->where($model->act_as_tree_parent_key.' is null');
				return null;
			}
			else
			{
				$model->where($model->act_as_tree_parent_key.'=?', $current_parent_id);
				return $current_parent_id;
			}
		}
	}

	protected function display_table()
	{
		$this->prepare_render_data();

		if (!$this->_controller->list_custom_partial)
			$this->display_partial('list_list');
		else
			$this->display_partial($this->_controller->list_custom_partial);
	}
	
	protected function load_list_settings()
	{
		if ($this->_list_settings === null)
		{
			$this->_list_settings = User_Parameters::get($this->list_get_name().'_settings');

			if (!is_array($this->_list_settings))
				$this->_list_settings = array();
				
			if (!array_key_exists('visible_list', $this->_list_settings))
				$this->_list_settings['visible_list'] = array();
				
			if (!array_key_exists('invisible_list', $this->_list_settings))
				$this->_list_settings['invisible_list'] = array();
				
			if (!array_key_exists('column_order', $this->_list_settings))
				$this->_list_settings['column_order'] = array();
				
			if (!array_key_exists('sorting', $this->_list_settings))
				$this->_list_settings['sorting'] = (object)array('field'=>null, 'direction'=>null);
				
			if (!array_key_exists('records_per_page', $this->_list_settings))
				$this->_list_settings['records_per_page'] = $this->_controller->list_items_per_page;
		}
		
		return $this->_list_settings;
	}
	
	protected function save_list_settings($settings)
	{
		$this->_list_settings = $settings;
		User_Parameters::set($this->list_get_name().'_settings', $settings);
	}

	protected function create_model_object()
	{
		if ($this->_model_object !== null)
			return $this->_model_object;

		if (!strlen($this->_controller->list_model_class))
			throw new SystemException('Data model class is not specified for List Behavior. Use the list_model_class public field to set it.');
			
		$model_class = $this->_controller->list_model_class;
		$result = $this->_model_object = new $model_class();
		
		$result = $this->_controller->list_extend_model_object($result);
		
		return $result;
	}

	protected function apply_options($options)
	{
		$this->_controller->list_options = $options;
		foreach ($options as $key=>$value)
		{
			$this->_controller->$key = $value;
		}
	}

	protected function load_data()
	{
		$model = null;

		if (strlen($this->_controller->list_custom_prepare_func))
		{
			$func = $this->_controller->list_custom_prepare_func;
			$model = $this->_controller->$func($this->create_model_object(), $this->_controller->list_options);
		} 
		else
		{
			$model = $this->_controller->list_prepare_data();
		}
		
		// Apply search
		//
		$search_string = Phpr::$session->get($this->list_get_name().'_search');
		if ($this->_controller->list_search_enabled)
		{
			if (!$this->_controller->list_search_fields)
				throw new ApplicationException('List search is enabled, but search fields are not specified in the list settings. Please use $list_search_fields public controller field to define an array of fields to search in.');
			
			if (!strlen($search_string) && !$this->_controller->list_search_show_empty_query)
			{
				$first_field = $this->_controller->list_search_fields[0];
				$model->where($first_field.' != '.$first_field);
			} 
			else if (strlen($search_string))
			{
				$this->_controller->list_display_as_tree = false;
				
				if ($this->_controller->list_display_as_sliding_list)
				{
					$this->view_data['list_display_path_column'] = true;
					$this->view_data['list_model_parent_field'] = $model->act_as_tree_parent_key;
				}
				
				$this->_controller->list_display_as_sliding_list = false;
				
				if (strlen($this->_controller->list_search_custom_func))
				{
					$func = $this->_controller->list_search_custom_func;
					$this->_controller->$func($model, $search_string);
				} 
				else
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
					foreach ($this->_controller->list_search_fields as $field)
					{
						$field_name = $field;
						
						$field = str_replace('@', $model->table_name.'.', $field);

						if ($field_name == 'id' || $field_name == '@id')
							$field_queries[] = '('.sprintf(implode(' and ', $word_queries_int), $field, '%').')';
						else
							$field_queries[] = '('.sprintf(implode(' and ', $word_queries), $field, '%').')';
					}

					$query = '('.implode(' or ', $field_queries).')';
					$model->where($query);
				}
			}
		}
		
		return $model;
	}
}
