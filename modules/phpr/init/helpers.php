<?php

// General helpers
// 

if (!function_exists('root_url'))
{
	function root_url($value = '/', $add_host_name_and_protocol = false, $protocol = null)
	{
		return Phpr_Url::root_url($value, $add_host_name_and_protocol, $protocol);
	}
}

if (!function_exists('site_url'))
{
	function site_url($resource = null, $suppress_protocol = false)
	{
		return Phpr_Url::site_url($resource, $suppress_protocol);
	}
}

if (!function_exists('post'))
{
	function post($name, $default = null)
	{
		return Phpr::$request->post_field($name, $default);
	}
}

if (!function_exists('post_array'))
{
	function post_array($array_name, $name, $default = null)
	{
		return Phpr::$request->post_array($array_name, $name, $default);
	}
}

if (!function_exists('h'))
{
	function h($string)
	{
		return Phpr_Html::encode($string);
	}
}

if (!function_exists('trace_log'))
{
	function trace_log($str, $listener = 'DEBUG')
	{
		if (Phpr::$trace_log)
			Phpr::$trace_log->write($str, $listener);
	}
}

if (!function_exists('module_build'))
{
	function module_build($module_id)
	{
		return Phpr_Version::get_module_build_cached($module_id);
	}
}

if (!function_exists('module_exists'))
{
	function module_exists($module_name)
	{
		return Phpr_Module_Manager::module_exists($module_name);
	}
}

if (!function_exists('mailto_encode'))
{
	function mailto_encode($email, $title = '', $params = '')
	{
		return Phpr_Email::mailto_encode($email, $title, $params);
	}
}

// Form helpers
// 

if (!function_exists('form_open'))
{
	function form_open($attributes = array())
	{
		$attributes = array_merge(array(
			'id'      => null,
			'onsubmit'=> null,
			'enctype' => 'multipart/form-data'
		), $attributes);

		$result = Phpr_Form::open_tag($attributes);
		$session_key = post('session_key', uniqid('phpr'));
		$result .= '<input type="hidden" name="session_key" value="'.Phpr_Html::encode($session_key).'" />';

		return $result;
	}
}

if (!function_exists('form_widget'))
{
	function form_widget($model, $field, $options = array()) 
	{
		return Phpr_Form::widget($model, $field, $options);
	}
}

if (!function_exists('form_close'))
{
	function form_close() 
	{
		return Phpr_Form::close_tag();
	}
}

if (!function_exists('form_input'))
{
	function form_input($name = '', $value = '', $extra = '') 
	{
		return Phpr_Form::form_input($name, $value, $extra);
	}
}

if (!function_exists('form_hidden'))
{
	function form_hidden($name = '', $value = '', $extra = '') 
	{
		return Phpr_Form::form_hidden($name, $value, $extra);
	}
}

if (!function_exists('form_file'))
{
	function form_file($name = '', $value = '', $extra = '') 
	{
		return Phpr_Form::form_file($name, $value, $extra);
	}
}

if (!function_exists('form_password'))
{
	function form_password($name = '', $value = '', $extra = '') 
	{
		return Phpr_Form::form_password($name, $value, $extra);
	}
}

if (!function_exists('form_textarea'))
{
	function form_textarea($data = '', $value = '', $extra = '') 
	{
		return Phpr_Form::form_textarea($data, $value, $extra);
	}
}

if (!function_exists('form_dropdown'))
{
	function form_dropdown($name = '', $options = array(), $selected = array(), $extra = '', $empty_option = false) 
	{
		return Phpr_Form::form_dropdown($name, $options, $selected, $extra, $empty_option);
	}
}

if (!function_exists('form_checkbox'))
{
	function form_checkbox($name = '', $value = '', $checked = false, $extra = '') 
	{
		return Phpr_Form::form_checkbox($name, $value, $checked, $extra);
	}
}

if (!function_exists('form_radio'))
{
	function form_radio($name = '', $value = '', $checked = false, $extra = '') 
	{
		return Phpr_Form::form_radio($name, $value, $checked, $extra);
	}
}

if (!function_exists('form_submit'))
{
	function form_submit($name = '', $value = '', $extra = '') 
	{
		return Phpr_Form::form_submit($name, $value, $extra);
	}
}

if (!function_exists('form_button'))
{
	function form_button($name = '', $value = '', $extra = '') 
	{
		return Phpr_Form::form_button($name, $value, $extra);
	}
}

if (!function_exists('form_label'))
{
	function form_label($text = '', $id = '', $extra = '') 
	{
		return Phpr_Form::form_label($text, $id, $extra);
	}
}

if (!function_exists('radio_state'))
{
	function radio_state($value1, $value2) 
	{
		return Phpr_Form::radio_state($value1, $value2);
	}
}

if (!function_exists('checkbox_state'))
{
	function checkbox_state($value) 
	{
		return Phpr_Form::checkbox_state($value);
	}
}

if (!function_exists('option_state'))
{
	function option_state($value1, $value2) 
	{
		return Phpr_Form::option_state($value1, $value2);
	}
}

if (!function_exists('multi_option_state'))
{
	function multi_option_state($items, $name, $value)
	{
		return Phpr_Form::multi_option_state($items, $name, $value);
	}
}

if (!function_exists('form_value'))
{
	function form_value($object, $value, $default = null) 
	{
		$return = (isset($object->$value)) ? $object->$value : $default;
		return trim($return);
	}
}

if (!function_exists('form_value_boolean'))
{
	function form_value_boolean($object, $value, $role, $default = null) 
	{
		if (isset($object->$value))
		{
			if ($object->$value)
				return $role;
			else
				return !$role;
		}
		else
			return $default;
	}
}
