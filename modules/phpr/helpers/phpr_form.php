<?php

/**
 * PHPR Form helper
 *
 * This class contains functions for working with HTML forms.
 */
class Phpr_Form
{
    /**
     * Returns the opening form tag.
     * @param array $attributes Optional list of the opening tag attributes.
     * @return string
     */
    public static function open_tag($attributes = array()) 
    {
        $default_url = Phpr_Html::encode(rawurldecode(strip_tags(root_url(Phpr::$request->get_current_uri()))));

        if (($pos = mb_strpos($default_url, '|')) !== false)
            $default_url = mb_substr($default_url, 0, $pos);

        $default_attributes = array(
            'action' => $default_url, 
            'method' => 'post', 
            'id' => 'FormElement', 
            'onsubmit' => 'return false;'
        );

        $result = "<form ";
        $result .= Phpr_Html::format_attributes($attributes, $default_attributes);
        $result .= ">\n";

        return $result;
    }

	/**
	 * Returns the closing form tag.
	 * @return string
	 */
	public static function close_tag() 
	{
		$result = "</form>";
		return $result;
	}

	/**
	 * Returns the checked="checked" string if the $Value is true.
	 * Use this helper to set a checkbox state.
	 * @param boolean $value Specifies the checbox state value
	 * @return string
	 */
	public static function checkbox_state($value)
	{
		return $value ? 'checked="checked"' : '';
	}

	/**
	 * Returns the checked="checked" string if the $Value1 equals $Value2
	 * Use this helper to set a radiobutton state.
	 * @param boolean $value1 Specifies the first value
	 * @param boolean $value2 Specifies the second value
	 * @return string
	 */
	public static function radio_state($value1, $value2)
	{
		return $value1 == $value2 ? "checked=\"checked\"" : "";
	}

	/**
	 * Returns the selected="selected" string if the $SelectedState = $CurrentState
	 * Use this helper to set a select option state.
	 * @param boolean $selected_state Specifies the select value that is currently selected
	 * @param boolean $current_state Specifies the current option value
	 * @return string
	 */
	public static function option_state($selected_state, $current_state)
	{
		return $selected_state == $current_state ? 'selected="selected"' : null;
	}

	/**
	 * Form widget
	 */
	
	public static function widget($model, $field_name, $options=array())
	{
		if (!isset($options['class']))
			throw new Phpr_ApplicationException("Missing widget class from Phpr_Form::widget(), please define 'class' in the third parameter");

		extract($options);

		if (is_string($model))
			$model = new $model();

		if (!$model)
			$model = new User(); // Gotta use something!

		$controller = new Phpr_Controller();
		
		$widget = new $class($controller, $model, $field_name, $options);
		$widget->render();
	}

	/**
	 * Generic input
	 *
	 * @param  string $type    Input type
	 * @param  string $name    Input name
	 * @param  string $value   Input value
	 * @param  string $extra   Extra attributes to include
	 * @return string
	 */
	private static function _form_input($type = 'text', $name = '', $value = '', $extra = '', $attributes = array())
	{
		if (!is_array($attributes))
			$attributes = array();

		$attributes['name'] = $name;
		$attributes['type'] = $type;
		$attributes['value'] = $value;

		if (is_array($extra)) 
		{
			$attributes = array_merge($extra, $attributes);
			$extra = '';
		}
		return "<input ".Phpr_Html::format_attributes($attributes)." ".$extra." />";
	}

	/**
	 * Form text input
	 *
	 * @param  string $name    Input name
	 * @param  string $value   Input value
	 * @param  string $extra   Extra attributes to include
	 * @return string
	 */
	public static function form_input($name = '', $value = '', $extra = '')
	{
		return self::_form_input('text', $name, $value, $extra);
	}

	/**
	 * Form hidden input
	 *
	 * @param  string $name    Input name
	 * @param  string $value   Input value
	 * @param  string $extra   Extra attributes to include
	 * @return string
	 */
	public static function form_hidden($name = '', $value = '', $extra = '')
	{
		return self::_form_input('hidden', $name, $value, $extra);
	}

	/**
	 * Form file input
	 *
	 * @param  string $name    Input name
	 * @param  string $value   Input value
	 * @param  string $extra   Extra attributes to include
	 * @return string
	 */
	public static function form_file($name = '', $value = '', $extra = '')
	{
		return self::_form_input('file', $name, $value, $extra);
	}

	/**
	 * Form password input
	 *
	 * @param  string $name    Input name
	 * @param  string $value   Input value
	 * @param  string $extra   Extra attributes to include
	 * @return string
	 */
	public static function form_password($name = '', $value = '', $extra = '')
	{
		return self::_form_input('password', $name, $value, $extra);
	}

	/**
	 * Form checkbox input
	 *
	 * @param  string $name    Input name
	 * @param  string $value   Input value
	 * @param  bool   $checked Is checkbox checked?
	 * @param  string $extra   Extra attributes to include
	 * @return string
	 */
	public static function form_checkbox($name = '', $value = '', $checked = false, $extra = '')
	{
		if ($value===false)
			$value = 0;

		$attributes = array();

		if ($checked)
			$attributes['checked'] = 'checked';

		return self::_form_input('checkbox', $name, $value, $extra, $attributes);
	}

	/**
	 * Form radio input
	 *
	 * @param  string $name    Input name
	 * @param  string $value   Input value
	 * @param  bool   $checked Is radio selected
	 * @param  string $extra   Extra attributes to include
	 * @return string
	 */
	public static function form_radio($name = '', $value = '', $checked = false, $extra = '')
	{
		if ($value===false)
			$value = 0;

		$attributes = array();

		if ($checked)
			$attributes['checked'] = 'checked';

		return self::_form_input('radio', $name, $value, $extra, $attributes);
	}

	/**
	 * Form submit button
	 *
	 * @param  string $name  Button name
	 * @param  string $value Button text
	 * @param  string $extra Extra attributes to include
	 * @return string
	 */
	public static function form_submit($name = '', $value = '', $extra = '')
	{
		return self::_form_input('submit', $name, $value, $extra);
	}

	/**
	 * Form reset button
	 *
	 * @param  string $name  Button name
	 * @param  string $value Button text
	 * @param  string $extra Extra attributes to include
	 * @return string
	 */
	public static function form_reset($name = '', $value = '', $extra = '')
	{
		return self::_form_input('reset', $name, $value, $extra);
	}

	/**
	 * Form textarea
	 *
	 * @param  mixed  $data  Textarea name (string) or data (array) to define name, cols and rows.
	 * @param  string $value Textarea value
	 * @param  string $extra Extra attributes to include
	 * @return string
	 */
	public static function form_textarea($data = '', $value = '', $extra = '')
	{
		$attributes = array('name' => ((!is_array($data)) ? $data : ''), 'cols' => '35', 'rows' => '12');

		if (is_array($extra)) 
		{
			$attributes = array_merge($extra, $attributes);
			$extra = '';
		}

		return "<textarea ".Phpr_Html::format_attributes($attributes)." ".$extra.">".$value."</textarea>";
	}

	/**
	 * Form dropdown select input
	 *
	 * @param  string $name     Select input name
	 * @param  Array  $options  Dropdown options
	 * @param  Array  $selected Dropdown selected options
	 * @param  string $extra    Extra attributes to include
	 * @return string
	 */
	public static function form_dropdown($name = '', $options = array(), $selected = array(), $extra = '', $empty_option = false)
	{
		if (!is_array($selected))
			$selected = array($selected);

		if (count($selected) === 0 && isset($_POST[$name]))
			$selected = array($_POST[$name]);

		if (is_array($extra)) 
			$extra = Phpr_Html::format_attributes($extra);
		else if ($extra != '')
			$extra = ' '.$extra;

		$multiple = (count($selected) > 1 && strpos($extra, 'multiple') === false) ? ' multiple="multiple"' : '';
		$return = '<select name="'.$name.'"'.$extra.$multiple.">".PHP_EOL;

        if ($empty_option !== false)
            $return .= '<option value="">'.h($empty_option).'</option>'.PHP_EOL;

		foreach ($options as $key => $value)
		{
			$key = (string)$key;
			if (is_array($value))
			{
				$return .= '<optgroup label="'.$key.'">'.PHP_EOL;
				foreach ($value as $optgroup_key => $optgroup_val)
				{
					$selected_string = (in_array($optgroup_key, $selected)) ? ' selected="selected"' : '';
					$return .= '<option value="'.$optgroup_key.'"'.$selected_string.'>'.(string)$optgroup_val."</option>".PHP_EOL;
				}
				$return .= '</optgroup>'.PHP_EOL;
			}
			else
			{
				$selected_string = (in_array($key, $selected)) ? ' selected="selected"' : '';
				$return .= '<option value="'.$key.'"'.$selected_string.'>'.(string)$value."</option>".PHP_EOL;
			}
		}
		$return .= '</select>';
		return $return;
	}

	/**
	 * Form button
	 *
	 * @param  string $name    Button name
	 * @param  string $text    Button text
	 * @param  string $extra   Extra attributes to include
	 * @return string
	 */
	public static function form_button($name = '', $text = '', $extra = '')
	{
		$attributes = array('name' => $name, 'type' => 'button');

		if (is_array($extra)) 
		{
			$attributes = array_merge($extra, $attributes);
			$extra = '';
		}

		return "<button ".Phpr_Html::format_attributes($attributes)." ".$extra.">".$text."</button>";
	}

	/**
	 * Form label
	 *
	 * @param  string $text  Text value for the label
	 * @param  string $id    Assosiated input ID
	 * @param  string $extra Extra attributes to include
	 * @return string
	 */
	public static function form_label($text = '', $id = '', $extra = '')
	{
		$for = ($id != '') ? ' for="'.$id.'" ' : '';

		if (is_array($extra)) 
			$extra = Phpr_Html::format_attributes($extra);
		else if ($extra != '')
			$extra = ' '.$extra;

		return '<label'.$for.$extra.'>'.$text.'</label>';
	}

	/**
	 * @deprecated
	 */
	public static function openTag($attributes = array()) 
	{
		return self::open_tag($attributes);
	}

	public static function checkboxState($value) 
	{
		return self::checkbox_state($value);
	}

	public static function radioState($value1, $value2) 
	{
		return self::radio_state($value1, $value2);
	}

	public static function optionState($selected_state, $current_state) 
	{
		return self::option_state($selected_state, $current_state);
	}
}
