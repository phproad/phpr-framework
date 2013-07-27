<?php namespace Db;

use Phpr;
use Phpr\DateTime;
use Db\Helper as Db_Helper;

class Record_Lock extends ActiveRecord 
{
	const lock_timeout = 20;

	public $table_name = 'db_record_locks';
	public $implement = 'Db\AutoFootprints';
	
	public static function create($values = null) 
	{
		return new self($values);
	}

	public static function lock_exists($obj)
	{
		$user = Phpr::$security->get_user();
		if (!$user)
			return null;

		if (is_object($obj))
		{
			$record_id = $obj->get_primary_key_value();
			$record_class = get_class($obj);
			$obj = self::create()->where('record_id=?', $record_id)->where('record_class=?', $record_class);
		}
		else
			$obj = self::create()->where('non_db_hash=?', $obj);

		$obj->where('created_user_id <> ?', $user->id);
		$obj->where('date_add(last_ping, interval '.(self::lock_timeout).' second) > ?', DateTime::now());

		return $obj->find();
	}
	
	public function get_age_str()
	{
		return DateTime::now()->substract_datetime($this->created_at)->interval_as_string().' ago';
	}
	
	public static function ping($lock_id)
	{
		Db_Helper::query('update db_record_locks set last_ping=:last_ping where id=:id',
		array(
			'id'=>$lock_id,
			'last_ping'=>DateTime::now()
		));
	}

	public function get_age_mins()
	{
		return DateTime::now()->substract_datetime($this->created_at)->get_minutes_total();
	}
	
	public static function lock($obj)
	{
		self::unlock_record($obj);
		
		$lock = new self();
		if (is_object($obj))
		{
			$record_id = $obj->get_primary_key_value();
			$record_class = get_class($obj);

			$lock->record_id = $record_id;
			$lock->record_class = $record_class;
		} 
		else
			$lock->non_db_hash = $obj;

		$lock->last_ping = DateTime::now();
		$lock->save();

		return $lock;
	}

	public static function unlock_record($obj)
	{
		if (is_object($obj))
		{
			$record_id = $obj->get_primary_key_value();
			$record_class = get_class($obj);

			Db_Helper::query('delete from db_record_locks where record_id=:record_id and record_class=:record_class',
			array(
				'record_id'=>$record_id,
				'record_class'=>$record_class
			));
		} 
		else
			Db_Helper::query('delete from db_record_locks where non_db_hash=:non_db_hash', array('non_db_hash'=>$obj));
	}

	public static function unlock($lock_id)
	{
		if (!strlen($lock_id))
			return;
			
		$obj = self::create()->find($lock_id);
		if ($obj)
		{
			if (!strlen($obj->non_db_hash))
			{
				Db_Helper::query('delete from db_record_locks where record_id=:record_id and record_class=:record_class',
				array(
					'record_id'=>$obj->record_id,
					'record_class'=>$obj->record_class
				));
			} 
			else
				Db_Helper::query('delete from db_record_locks where non_db_hash=:non_db_hash', array('non_db_hash'=>$obj->non_db_hash));
		}
	}
	
	public static function clean_up()
	{
		try
		{
			Db_Helper::query(
				'delete from db_record_locks where date_add(last_ping, interval '.(self::lock_timeout*2).' second) < :now',
				array('now'=>DateTime::now())
			);
		} 
		catch (\Exception $ex) 
		{
			// Do nothing
		}
	}
}
