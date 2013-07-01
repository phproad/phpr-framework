<?php namespace Db;

class DatePicker_Widget extends Form_Widget_Base
{

	public $field_name = null;
	public $field_id = null;
	public $field_value = null;
	public $css_class = null;

	// Javascript to execute when a date is selected
	public $on_select = null;

	public $min_date = null;
	public $max_date = null;
	public $year_range = null;
	public $allow_past_dates = true;
	public $allow_month_change = false;
	public $allow_year_change = false;
	
	public $is_dob = false; // Date of birth

	protected function load_resources()
	{
	}

	public function render()
	{
		if (!isset($this->field_name)) 
			$this->field_name = $this->model_class.'['.$this->column_name.']';
		
		if (!isset($this->field_id)) 
			$this->field_id = "id_".strtolower($this->model_class)."_".strtolower($this->column_name);

		if (!$this->allow_past_dates)
			$this->min_date = 0;

		if ($this->is_dob)
		{
			$this->allow_year_change = true;
			$this->allow_month_change = true;
			$this->year_range = '-100y:c+nn';
			$this->max_date = '-1d';
		}

		$this->display_partial('datepicker_container');
	}

}