<?php namespace Db;

class MultiText_Widget extends Form_Widget_Base
{

	public $field_name = null;
	public $field_id = null;
	public $field_value = null;
	public $css_class = null;
	public $fields = array();

	protected function load_resources()
	{
		$this->controller->add_javascript($this->get_public_asset_path('scripts/js/multitext.js?'.module_build('core')));
		$this->controller->add_css($this->get_public_asset_path('stylesheets/css/multitext.css?'.module_build('core')));
	}

	public function render()
	{
		if (!isset($this->field_name)) 
			$this->field_name = $this->model_class.'['.$this->column_name.']';
		
		if (!isset($this->field_id)) 
			$this->field_id = "id_".strtolower($this->model_class)."_".strtolower($this->column_name);

		$this->display_partial('multitext_container');
	}

}