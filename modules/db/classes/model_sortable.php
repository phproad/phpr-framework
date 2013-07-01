<?php

/*
 * Sortable model extension
 */

/*
 * Usage:
 *
 * Model table must have sort_order table column.
 * In the model class definition: 
 *
 *   public $implement = 'Db_Model_Sortable';
 *
 * To set orders: 
 *
 *   $obj->set_item_orders($item_ids, $item_orders);
 *
 * You can change the sort field used by declaring:
 *
 *   public $sortable_model_field = 'my_sort_order';
 *
 */

class Db_Model_Sortable extends Phpr_Extension
{
	protected $_model;
	protected $_field_name = "sort_order";
	
	public function __construct($model)
	{
		parent::__construct();
		
		$this->_model = $model;

		if (isset($model->sortable_model_field))
			$this->_field_name = $model->sortable_model_field;

		$model->add_event('db:on_after_create', $this, 'set_order_id');
	}
	
	public function set_order_id()
	{
		$new_id = mysql_insert_id();
		Db_Helper::query('update `'.$this->_model->table_name.'` set '.$this->_field_name.'=:new_id where id=:new_id', array(
			'new_id'=>$new_id
		));
	}
	
	public function set_item_orders($item_ids, $item_orders)
	{
		if (is_string($item_ids))
			$item_ids = explode(',', $item_ids);
			
		if (is_string($item_orders))
			$item_orders = explode(',', $item_orders);

		if (count($item_ids) != count($item_orders))
			throw new Phpr_ApplicationException('Invalid set_item_orders call - count of item_ids does not match a count of item_orders');

		foreach ($item_ids as $index=>$id)
		{
			$order = $item_orders[$index];
			Db_Helper::query('update `'.$this->_model->table_name.'` set '.$this->_field_name.'=:sort_order where id=:id', array(
				'sort_order'=>$order,
				'id'=>$id
			));
		}
	}
}
