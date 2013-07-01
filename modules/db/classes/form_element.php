<?php namespace Db;

/**
 * Base class for all form elements
 */
class Form_Element
{
	public $tab;
	public $no_preview = false;
	public $no_form = false;
	public $sort_order = null;
	public $collapsible = false;

	/**
	 * Specifies a caption of a tab to place the field into
	 * If you decide to use tabs, you should call this method for each form field in the model
	 */
	public function tab($tab_caption)
	{
		$this->tab = $tab_caption;
		return $this;
	}
	
	/**
	 *  Hides the element from form preview
	 */
	public function no_preview()
	{
		$this->no_preview = true;
		return $this;
	}

	/**
	 *  Hides the element from form
	 */
	public function no_form()
	{
		$this->no_form = true;
		return $this;
	}
	
	/**
	 * Sets the element position on the form. For elements without any position 
	 * specified, the position is calculated automatically, basing on the 
	 * add_form_field() method call order. For the first element the sort order
	 * value is 10, for the second element it is 20 and so on.
	 * @param int $value Specifies a form position.
	 */
	public function sort_order($value)
	{
		$this->sort_order = $value;
		return $this;
	}
	
	/**
	 * Places the element to the form or tab collapsible area
	 * @param boolean $value Determines whether the element should be placed to the collapsible area.
	 */
	public function collapsible($value = true)
	{
		$this->collapsible = $value;
		return $this;
	}
}
