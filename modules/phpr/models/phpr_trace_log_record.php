<?php

/**
 * PHPR trace log record model class.
 */
class Phpr_Trace_Log_Record extends Db_ActiveRecord
{
	public $table_name = "phpr_trace_log";

	/**
	 * Creates a log record
	 * @param string $log Specifies the log name
	 * @param string $message Specifies the message text
	 * @param string $details Specifies the error details string
	 */
	public static function add($log, $message, $details = null)
	{
		$record = new Phpr_Trace_Log_Record();
		
		$record->save(array(
			'log' => $log, 
			'message' => $message, 
			'details' => $details
		));
		
		return $record;
	}

	public function before_validation_on_create($deferred_session_key = null)
	{
		$this->record_datetime = Phpr_DateTime::now();
	}
	
	public function define_columns($context = null)
	{
		$this->define_column('id', 'ID');
		$this->define_column('record_datetime', 'Date and Time')->order('desc')->date_format('%x %X');
		$this->define_column('message', 'Message');
	}

	public function define_form_fields($context = null)
	{
		$this->add_form_field('message');
	}
}