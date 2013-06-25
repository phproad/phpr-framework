<?php

/**
 * Base class for form widgets. Form widgets can render custom form controls,
 * and provide their life cycle operations.
 */
class Db_Form_Widget_Base
{
	public $unique_id;
	public $column_name;
	public $model;
	public $model_class;
	public $widget_class;

	private static $loaded_widgets = array();

	protected $controller;
	protected $view_path;
	protected $resources_path;
	protected $configuration;
	protected $view_data = array();
	
	public function __construct($controller, $model, $column_name, $configuration)
	{
		$this->controller = $controller;
		$this->model = $model;
		$this->model_class = get_class($model);
		$this->widget_class = get_class($this);
		$this->column_name = $column_name;
		$this->unique_id = $column_name;
		$this->configuration = $configuration;
		$this->view_data['widget'] = $this;

		$ref_object = new ReflectionObject($this);
		$widget_root_dir = dirname($ref_object->getFileName()).DS.strtolower($this->widget_class);
		$this->view_path = $widget_root_dir.DS.'partials';

		$this->resources_path = File_Path::get_public_path($widget_root_dir.DS.'assets');

		foreach ($configuration as $name => $value) {
			$this->{$name} = $value;
		}
		
		$this->load_resources();
		
		if (!Phpr::$request->is_remote_event())
			self::$loaded_widgets[$this->widget_class] = true;
	}
	
	//
	// Services
	// 

	public function handle_event($event, $model, $field)
	{
		if (substr($event, 0, 2) != 'on')
			throw new Phpr_SystemException('Invalid widget event name: '.$event);
			
		if (!method_exists($this, $event))
			throw new Phpr_SystemException(sprintf('Event handler %s not found in widget %s.', $event, $this->widget_class));
			
		$this->$event($field, $model);
	}
	
	//
	// Resource management
	// 

	/**
	 * Adds widget specific resource files. Use $this->controller->add_javascript() and $this->controller->add_css()
	 * to register new resources.
	 */
	protected function load_resources()
	{
	}

	/**
	 * Outputs <link> and <script> tags to load widget specific resource files. This method has an inbuilt safe guard
	 * to only include the widget resources once.
	 */
	public function include_resources($force = false)
	{
		if (array_key_exists($this->widget_class, self::$loaded_widgets) && !$force)
			return;

		self::$loaded_widgets[$this->widget_class] = true;

		return $this->controller->load_resources();
	}
	
	//
	// Partials
	// 

	/**
	 * Renders a controller partial (1) or widget partial (2) with the same name.
	 * @param string $view_name Specifies a view name
	 * @param array $params A list of parameters to pass to the partial file
	 * @param bool $override_controller Indicates that the controller partial should be overridden by the widget partial.
	 * @param bool $throw Indicates that an exception should be thrown in case if the partial does not exist
	 * @return bool
	 */
	public function display_partial($view_name, $params = array(), $override_controller = false, $throw = true)
	{
		$this->display_partial_file($this->controller->get_views_path(), $view_name, $params, $override_controller, $throw);
	}
	
	private function display_partial_file($controller_view_path, $view_name, $params = array(), $override_controller = false, $throw = true)
	{
		$this->controller->view_data = $this->view_data + $this->controller->view_data;
		$controller_view_path = $controller_view_path.'/_'.$view_name.'.htm';

		if (!$override_controller && file_exists($controller_view_path)) {
			$this->controller->display_partial($controller_view_path, $params, true, true);
		} else {

			//
			// Absolute reference
			//   NB: In Phpr_Controller_Base this expression is determined by the presence of a forwardslash (/).
			//   
			if (strpos($view_name, PATH_APP) !== false)
				$view_path = $view_name;
			else
				$view_path = $this->view_path.'/_'.$view_name.'.htm';

			if (!$throw && !file_exists($view_path))
				return;

			$this->controller->display_partial($view_path, $params, true, true);
		}
	}

	public function render()
	{
	}

	//
	// Getters
	//
	
	/**
	 * Returns full relative path to a resource file situated in the widget's resources directory.
	 * @param string $path Specifies the relative resource file name, for example '/assets/javascript/widget.js'
	 * @return string Returns full relative path, suitable for passing to the controller's add_css() or add_javascript() method.
	 */
	protected function get_public_asset_path($path)
	{
		if (substr($path, 0, 1) != '/')
			$path = '/'.$path;
			
		return $this->resources_path.$path;
	}

	public function get_form_id()
	{
		return $this->get_element_id('form');
	}

	public function get_id($identifier=null)
	{
		if ($identifier === null)
			$identifier = $this->unique_id;

		return $this->widget_class.$this->model_class."_".$identifier;
	}

	public function get_element_id($element)
	{
		return $this->get_id().'_'.$element;
	}

	public function get_event_handler_data($handler)
	{
		return "phpr_event_field: '".$this->column_name."', phpr_custom_event_name: '".$handler."'";
	}
}
