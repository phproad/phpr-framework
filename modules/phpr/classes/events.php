<?php

/*
 * Events extension
 * 
 * Firing: $this->fire_event('on_event_name', $_param1);
 * Handling: 
 * Function handler: $tester->add_event('on_after_show', 'handler_function');
 * Class method handler: $tester->add_event('on_after_show', $reciever, 'on_show_message');
 */

class Phpr_Events extends Phpr_Extension
{
	public $events = array();
	
	public function add_event($options = array()) 
	{
		if (is_string($options)) 
		{
			// First param being the event name
			// 			
			$options = array(
				'name' => $options
			);
		}
		else 
		{
			$options = array_merge(array(
				'name' => null
			), $options);
		}
		
		extract($options);
		
		$args = func_get_args();
		$handler = $this->extract_function_arg($args, 1);
		
		if (count($args) > 0)
			$priority = (int)$args[0];
		else
			$priority = 500;

		if (!isset($this->events[$name]))
			$this->events[$name] = array();

		$this->events[$name][] = array('handler' => $handler, 'priority' => $priority);
	}

	public function fire_event($options = array()) 
	{
		if (is_string($options)) {
			// First param being the event name
			// 
			$options = array(
				'name' => $options, 
				'type' => 'combine'
			);
		}
		else {
			$options = array_merge(array(
				'name' => null, 
				'type' => 'combine'
			), $options);
		}
	
		extract($options);

		$params = func_get_args();
		
		array_shift($params);
		
		if (!isset($this->events[$name])) {
			if ($type == 'combine')
				return array(); // Backwards compatability
			else if ($type == 'filter')
				return count($params) > 0 ? $params[0] : null;
		}
		
		uasort($this->events[$name], array($this, 'sort_by_priority'));
				
		if ($type == 'combine') {
			$result = array();
			
			foreach ($this->events[$name] as $event) {
				$result[] = call_user_func_array($event['handler'], $params);
			}
		}
		else if ($type == 'filter') {
			$result = count($params) > 0 ? $params[0] : null;
			
			foreach ($this->events[$name] as $event) {
				$result = call_user_func_array($event['handler'], array($result));
			}
		}
		
		return $result;
	}
	
	public function remove_event_by_handler_substring($handler_substring)
	{
		$events = array();
		foreach ($this->events as $event_name=>$handlers)
		{
			$event_handlers = array();
			foreach ($handlers as $handler_data)
			{
				if (!isset($handler_data['handler'][1]) || strpos($handler_data['handler'][1], $handler_substring) === false)
					$event_handlers[] = $handler_data;
			}
			
			$events[$event_name] = $event_handlers;
		}
		
		$this->events = $events;
	}
	
	public function listeners_exist() 
	{
		$listeners = func_get_args();
		foreach ($listeners as $name)
		{
			if (array_key_exists($name, $this->events))
				return true;
		}
				
		return false;
	}
	
	private function sort_by_priority($a, $b) 
	{
		if ($a['priority'] == $b['priority']) 
			return 0;
		
		return ($a['priority'] < $b['priority']) ? 1 : -1;
	}

	private function extract_function_arg(&$args, $offset = 0)
	{
		$count = count($args) - $offset;

		if ($count == 0) 
			return null;

		if (is_string($args[$offset]) || 
			is_array($args[$offset]) || 
			(is_object($args[$offset]) && $args[$offset] instanceof Phpr_Closure))
		{
			for ($i = 0; $i <= $offset; $i++) {
				$last_obj = array_shift($args);
			}

			return $last_obj;
		}

		if ($count > 1 && is_object($args[$offset]) && is_string($args[$offset+1])) {
			$last_obj = array($args[$offset], $args[$offset+1]);

			$new_args = array();
			
			for ($i = $offset+2; $i < count($args); $i++) {
				$new_args[] = $args[$i];
			}

			$args = $new_args;
			return $last_obj;
		}

		return null;
	}

}