<?php

/**
 * PHPR Debug Helper Class
 */
class Phpr_Debug
{
	protected static $start_times = array();
	protected static $incremental = array();
	
	public static $listener = 'DEBUG';
	
	public static function start_timing($name)
	{
		self::$start_times[$name] = microtime(true);
	}
	
	public static function end_timing($name, $message = null, $add_memory_usage = false, $reset_timer = true)
	{
		$time_end = microtime(true);
		
		$message = $message ? $name.' - '.$message : $name;
		$time = $time_end - self::$start_times[$name];
		
		if ($add_memory_usage)
			$message .= ' Peak memory usage: '.File::size_from_bytes(memory_get_peak_usage());
		
		if ($reset_timer)
			self::start_timing($name);

		Phpr::$trace_log->write('['.$time.'] '.$message, self::$listener);
	}
	
	public static function increment($name)
	{
		$time_end = microtime(true);
		$time = $time_end - self::$start_times[$name];
		if (!array_key_exists($name, self::$incremental))
			self::$incremental[$name] = 0;

		self::$incremental[$name] += $time;
	}
	
	public static function end_incremental_timing($name, $message = null)
	{
		$message = $message ? $message : $name;
		$time = self::$incremental[$name];

		Phpr::$trace_log->write('['.$time.'] '.$message, self::$listener);
	}
	
	public static function backtrace()
	{
		$trace = debug_backtrace();
		$data = array();
		foreach ($trace as $trace_step)
		{
			if (isset($trace_step['file']))
				$data[] = basename($trace_step['file']).' #'.$trace_step['line'].' '.$trace_step['function'].'()';
			else
				$data[] = $trace_step['function'].'()';
		}

		Phpr::$trace_log->write(implode("\n", $data), self::$listener);
	}
}

Phpr_Debug::start_timing('Application');
