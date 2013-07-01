<?php namespace Phpr;

use Phpr\SystemException;
use Phpr\ValidationException;
use Db\ActiveRecord;
use Db\Data_Collection;

/**
 * PHPR Validation Class
 *
 * Phpr_Validation class assists in validating form data.
 */
class Validation
{
	private $_owner;

	/**
	 * @ignore
	 * Contains a list of fields validation rules
	 * @var array
	 */
	public $_fields;

	/**
	 * Indicates whether all validation rules are valid.
	 * A value of this field is set by the Validate method.
	 * @var boolean
	 */
	public $valid;

	/**
	 * Contains a list of invalid field names.
	 * @var array
	 */
	public $error_fields;

	/**
	 * Contains a list of fields error messages.
	 */
	public $field_errors;

	/**
	 * Keeps a common error message.
	 * @var string
	 */
	public $error_message;

	/**
	 * @ignore
	 * Contains an evaluated field values.
	 * @var array
	 */
	public $field_values;
	
	/**
	 * Specifies a prefix to add to field identifiers in focusField method call
	 */
	public $focus_prefix = null;
	
	private $_form_id;
	private $_widget_data = array();

	/**
	 * Creates a new validation class
	 * @param object $owner Specifies an optional owner object
	 * @param string $form_id Specifies an optional HTML form identifier
	 * The owner object class must be inherited from the Phpr\Validate_Extension class
	 * if you are going to use the Method rule.
	 */
	public function __construct($owner = null, $form_id = 'form-element')
	{
		$this->_owner = $owner;
		$this->_form_id = $form_id;
		$this->_fields = array();
		$this->error_fields = array();
		$this->valid = false;
		$this->error_message = null;
		$this->field_errors = array();
		$this->field_values = array();
	}
	
	/**
	 * Sets a form element identifier
	 */
	public function set_form_id($form_id)
	{
		$this->_form_id = $form_id;
	}

	/**
	 * Attaches a validation rule set to a form field.
	 * @param string $field Specifies a form field name (firstname)
	 * @param string $field_name Specifies a field name (First Name). 
	 * You may omit this field if the field name matches the form field.
	 * @param bool $focusable Specifies whether the field is focusable.
	 * @return Phpr_Validation_Rules
	 */
	public function add($field, $field_name = null, $focusable = true)
	{
		if ($field_name === null)
			$field_name = $field;

		return $this->_fields[$field] = new Validation_Rules($this, $field_name, $focusable);
	}
	
	/**
	 * Sets a common or field error message.
	 * @param string $message Specifies the error message
	 * @param string $field Specifies the field name. If this parameter is omitted, the common message will be set.
	 * @param bool Throw exception
	 * @return Phpr_Validation
	 */
	public function set_error($message, $field = null, $throw = false)
	{
		$this->valid = false;

		if ($field !== null)
		{
			$this->field_errors[$field] = $message;
			$this->error_fields[] = $field;
		}
		else
			$this->error_message = $message;

		if ($throw)
			$this->throw_exception();

		return $this;
	}

	/**
	 * Determines whether a field with the name specified is invalid.
	 * @param string $field Specifies the field name.
	 * @return boolean
	 */
	public function is_error($field)
	{
		return in_array($field, $this->error_fields);
	}

	/**
	 * Returns an error message for the specified field.
	 * @param string $field Specifies the field name.
	 * @param boolean $html Indicates whether the message must be prepared to the HTML output.
	 * @return string
	 */
	public function get_error($field, $html = true)
	{
		if (!isset($this->field_errors[$field]))
			return null;

		$message = $this->field_errors[$field];
		return $html ? Html::encode($message) : $message;
	}

	/**
	 * Runs the validation rules and determines whether the data is valid.
	 * @param mixed $data Specifies a data object. It may be array or object. 
	 * If this parameter is omitted, the POST variables will be used.
	 * @param string $deferred_session_key An edit session key for deferred bindings. 
	 * Use it for validating Active Record objects with deferred bindings.
	 * @return boolean Indicates whether all validation rules are passed.
	 */
	public function validate($data = null, $deferred_session_key = null)
	{
		$error_found = false;
		
		if ($data === null)
			$src_arr = $_POST;
		elseif (is_object($data))
			$src_arr = (array)$data;
		elseif (is_array($data))
			$src_arr = $data;
		else
			throw new SystemException('Invalid validation data object');

		foreach ($this->_fields as $param_name => $rule_set)
		{
			if (!is_object($data))
				$field_value = isset($src_arr[$param_name]) ? $src_arr[$param_name] : null;
			else if ($data instanceof ActiveRecord)
				$field_value = $data->get_deferred_value($param_name, $deferred_session_key);		
			else
				$field_value = $data->{$param_name};

			if ($field_value instanceof Data_Collection)
				$field_value = $field_value->as_array('id');

			foreach ($rule_set->rules as $rule)
			{
				$rule_obj = $rule[Validation_Rules::obj_name];

				switch ($rule[Validation_Rules::rule_type])
				{
					case Validation_Rules::type_internal: 
						$rule_result = $rule_set->eval_internal($rule_obj, $param_name, $field_value, $rule[Validation_Rules::params], $rule[Validation_Rules::message], $data, $deferred_session_key);
						break;

					case Validation_Rules::type_function:
						if (!function_exists($rule_obj))
							throw new SystemException('Unknown validation function: '.$rule_obj);

						$rule_result = $rule_obj($field_value);
						break;

					case Validation_Rules::type_method:
						if ($this->_owner === null)
							throw new SystemException('Can not execute the method-type rule '.$rule_obj.' without an owner object');
						
						if (is_string($rule_obj))
							$rule_result = $this->_owner->_execute_validation($rule_obj, $param_name, $field_value);
						elseif (is_callable($rule_obj))
							$rule_result = call_user_func($rule_obj, $param_name, $field_value, $this, $this->_owner);
						break;
				}

				if ($rule_result === false)
				{
					$this->error_fields[] = $param_name;
					$error_found = true;
					continue 2;
				}

				if ($rule_result === true)
					continue;

				$field_value = $rule_result;
			}

			$this->field_values[$param_name] = $field_value;
		}

		$this->valid = !$error_found;

		if ($this->valid)
		{
			foreach ($this->field_values as $field_name=>$field_value)
			{
				if ($data === null)
					$_POST[$field_name] = $field_value;
				elseif (is_object($data))
				{
					if (!($data instanceof ActiveRecord))
						$data->{$field_name} = $field_value;
					else
						$data->set_deferred_value($field_name, $field_value, $deferred_session_key);
				}
			}
		}

		return $this->valid;
	}

	/**
	 * Sets focus to a first error field.
	 * If there are no error fields, sets focus to a first form field.
	 * You may also specify explicitly with the optional parameter.
	 * @param string $field_id Optional identifier of a field to focus. 
	 * @param boolean $force Optional. Determines whether the field specified 
	 * in the first parameter must be focused even in case of errors.
	 */
	public function focus($field_id = null, $force = false)
	{
		$has_errors = count($this->error_fields);

		$form_id = $this->_form_id === null ? 'document.forms[0]' : $this->_form_id;

		if ($field_id !== null && (!$has_errors || ($has_errors && $force)))
			return "PHPR.form('#".$form_id."').focusField('#".$field_id."').focus();";

		if ($has_errors)
		{
			$field = $this->error_fields[0];
			if (isset($this->_fields[$field]) && !$this->_fields[$field]->focusable)
				return null;

			return "PHPR.form('#".$form_id."').focusField('#".$this->error_fields[0]."').focus();";
		}

		return "PHPR.form('#".$form_id."').focusField('input:first').focus();";
	}

	/**
	 * Generates a Java Script code for focusing an error field
	 * @param boolean $add_script_node Indicates whether the script node must be generated
	 * @return string
	 */
	public function get_focus_error_script($add_script_node = true)
	{
		if (!count($this->error_fields))
			return null;

		$field = $this->error_fields[0];
		if (isset($this->_fields[$field]) && !$this->_fields[$field]->focusable)
			return null;

		$result = null;
		if ($add_script_node)
			$result .= "<script type='text/javascript'>";

		$form_id = $this->_form_id === null ? 'document.forms[0]' : $this->_form_id;
		$focus_id = strlen($this->_fields[$field]->focus_id) ? $this->_fields[$field]->focus_id : $field;

		if ($this->focus_prefix)
			$focus_id = $this->focus_prefix.$focus_id;

		$result .= "PHPR.form().focusField('#".$focus_id."');";
		$result .= "window.phprErrorField = '".$focus_id."';";
		if ($widget_data = $this->get_widget_data())
		{
			$result .= 'phpr_dispatch_widget_response_data('.json_encode($widget_data).');';
		}

		if ($add_script_node)
			$result .= "</script>";

		return $result;
	}

	public function set_widget_data($data)
	{
		$this->_widget_data[] = $data;
	}
	
	public function get_widget_data()
	{
		return $this->_widget_data;
	}

	/**
	 * Throws the Validation Exception in case if data is not valid.
	 */
	public function throw_exception()
	{
		throw new ValidationException($this);
	}
	
	public function has_rule_for($field)
	{
		return array_key_exists($field, $this->_fields);
	}
	
	public function get_rule($field)
	{
		if ($this->has_rule_for($field))
			return $this->_fields[$field];
			
		return null;
	}
}
