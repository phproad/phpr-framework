<?php namespace Db;

use Phpr\Inflector;
use Phpr\DateTime;
use Db\Helper as Db_Helper;

class Deferred_Binding extends ActiveRecord 
{
	public $table_name = 'db_deferred_bindings';
	public $simple_caching = true;

	public static function create($values = null) 
	{
		return new self($values);
	}
	
	public function before_validation_on_create($deferred_session_key = null)
	{
		if ($this->is_bind)
		{
			// Skip repeating bindings 
			$obj = $this->find_binding_record(1);
			if ($obj)
				return false;
		} 
		else 
		{
			// Remove add-delete pairs
			$obj = $this->find_binding_record(1);
			if ($obj)
			{
				$obj->delete_cancel();
				return false;
			}
		}
	}
	
	protected function find_binding_record($is_bind)
	{
		$obj = self::create()
			->where('master_class_name=?', $this->master_class_name)
			->where('detail_class_name=?', $this->detail_class_name)
			->where('master_relation_name=?', $this->master_relation_name)
			->where('is_bind=?', $is_bind)
			->where('detail_key_value=?', $this->detail_key_value)
			->where('session_key=?', $this->session_key);
		
		return $obj->find();
	}

	public static function cancel_deferred_actions($master_class_name, $session_key)
	{
		$records = self::create()
			->where('master_class_name=?', $master_class_name)
			->where('session_key=?', $session_key)
			->find_all();
			
		foreach ($records as $record)
		{
			$record->delete_cancel();
		}
	}
	
	public static function cancel_deferred_actions_sub($master_class_name, $session_key)
	{
		$len = strlen($session_key);
		
		$records = self::create()
			->where('master_class_name=?', $master_class_name)
			->where('substring(session_key, 1, '.$len.')=? ', $session_key)
			->find_all();
			
		foreach ($records as $record)
		{
			$record->delete_cancel();
		}
	}
	
	public function delete_cancel()
	{
		$this->delete_detail_record();
		$this->delete();
	}
	
	public static function clean_up($days = 5)
	{
		$now = DateTime::now();
		
		$records = self::create()->where('ADDDATE(created_at, INTERVAL :days DAY) < :now', array('days'=>$days, 'now'=>$now))->find_all();
		foreach ($records as $record)
		{
			$record->delete_cancel();
		}
	}
	
	protected function delete_detail_record()
	{
		// Try to delete unbound has_one records from the details table
		try
		{
			if (!$this->is_bind)
				return;

			$master_class_name = $this->master_class_name;
			$master_object = new $master_class_name();
			$master_object->define_columns();

			if (!array_key_exists($this->master_relation_name, $master_object->has_models))
				return;

			if (($type = $master_object->has_models[$this->master_relation_name]) !== 'has_many')
				return;

			$related = $master_object->related($this->master_relation_name);
			$related_obj  = $related->find($this->detail_key_value);
			if (!$related_obj)
				return;

			$has_primary_key = false;
			$has_foreign_key = false;
			$options = $master_object->get_relation_options($type, $this->master_relation_name, $has_primary_key, $has_foreign_key);

			if (!array_key_exists('delete', $options) || !$options['delete'])
				return;
			
			if (!$has_foreign_key)
				$options['foreign_key'] = Inflector::foreign_key($master_object->table_name, $related_obj->primary_key);

			if (!$related_obj->{$options['foreign_key']})
				$related_obj->delete();
		}
		catch (exception $ex)
		{
			// Do nothing
		}
	}
	
	public static function reset_object_field_bindings($master, $detail, $relation_name, $session_key)
	{
		$master_class_name = get_class($master);
		$detail_class_name = get_class($detail);
		$detail_key_value = $detail->get_primary_key_value();
		
		$bind = array(
			'master_class_name'=>$master_class_name,
			'detail_class_name'=>$detail_class_name,
			'master_relation_name'=>$relation_name,
			'detail_key_value'=>$detail_key_value,
			'session_key'=>$session_key
		);

		Db_Helper::query(
			'delete 
				from db_deferred_bindings 
			where
				master_class_name=:master_class_name and
				detail_class_name=:detail_class_name and
				master_relation_name=:master_relation_name and
				detail_key_value=:detail_key_value and
				session_key=:session_key
			', $bind);
	}
}
