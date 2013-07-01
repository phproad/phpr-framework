<?php namespace Phpr;

use DateTimeZone;

use Phpr;
use Phpr\DateTime;
use Phpr\SystemException;
use Db\ActiveRecord;

/**
 * Validation rule class.
 *
 * Phpr\Validation_Rules represents a set of validation rules applied to a POST element.
 */
class Validation_Rules
{
	const rule_type = 'type';
	const obj_name = 'name';
	const type_function = 'function';
	const type_method = 'method';
	const type_internal = 'internal';
	const params = 'params';
	const message = 'message';

	/**
	 * @ignore
	 * Contains a list of validation rules.
	 * @var array
	 */
	public $rules;

	/**
	 * @ignore
	 * Contains a field name.
	 * @var string
	 */
	public $field_name;

	/**
	 * @ignore
	 * Determines whether the field is focusable.
	 * @var string
	 */
	public $focusable;
	
	/**
	 * @ignore
	 * An element that should be focused in case of error
	 * @var string
	 */
	public $focus_id;
	
	public $required;

	protected $validation;

	/**
	 * Creates a new Phpr\Validation_Rules instance. Do not instantiate this class directly - 
	 * the controller Validation property: $this->validation->addRule("FirstName").
	 * @param Phpr_Validation $validation Specifies the validation class instance.
	 * @param bool $focusable Specifies whether the field is focusable.
	 * @param string $field_name Specifies a field name.
	 */
	public function __construct($validation, $field_name, $focusable)
	{
		$this->rules = array();
		$this->validation = $validation;
		$this->field_name = $field_name;
		$this->focusable = $focusable;
	}

	/**
	 * Adds a rule that validates a value using a PHP function.
	 * The function must accept one parameter - the value 
	 * and return a string or boolean value.
	 * @param string $name Specifies a PHP function name.
	 * @return Phpr\Validation_Rules
	 */
	public function fn($name)
	{
		$this->rules[] = array(self::rule_type=>self::type_function, self::obj_name=>$name);
		return $this;
	}
	
	/**
	 * Sets an identifier of a element that should be focused in case of error
	 * @param string $id Specifies an element identifier
	 * @return Phpr\Validation_Rules
	 */
	public function focus_id($id)
	{
		$this->focus_id = $id;
		return $this;
	}

	/**
	 * Adds a rule that validates a value using a owner class method.
	 * The owner class must be inherited from the Phpr\Validate_Extension class.
	 * The method must accept two parameters - the field name and value
	 * and return a string or boolean value.
	 * @param string $name Specifies a controller method name.
	 * @return Phpr\Validation_Rules
	 */
	public function method($name)
	{
		$this->rules[] = array(self::rule_type=>self::type_method, self::obj_name=>$name);
		return $this;
	}

	/**
	 * @ignore
	 * Evaluates the internal validation rule.
	 * This method is used by the Phpr_Validation class internally.
	 * @param string $rule Specifies the rule name
	 * @param string $name Specifies a field name
	 * @param string $value Specifies a value to validate
	 * @param array &$params A list of the rule parameters.
	 * @return mixed
	 */
	public function eval_internal($rule, $name, $value, &$params, $custom_message, &$data_src, $deferred_session_key)
	{
		$method_name = "eval_".$rule;
		if (!method_exists($this, $method_name))
			throw new SystemException("Unknown validation rule: ".$rule);
			
		$params['deferred_session_key'] = $deferred_session_key;

		return $this->$method_name($name, $value, $params, $custom_message, $data_src);
	}

	/**
	 * Registers an internal validation rule.
	 * @param string $method Specifies the rule method name.
	 * @param array $params A list of the rule parameters.
	 * @param string $custom_message Custom error message
	 */
	protected function register_internal($method, $params = array(), $custom_message = null)
	{
		if (($pos = strpos($method, '::')) !== false)
			$method = substr($method, $pos+2);

		$this->rules[] = array(self::rule_type=>self::type_internal, self::obj_name=>$method, self::params=>$params, self::message=>$custom_message);
	}

	//
	// ====================== Numeric rule ======================
	//

	/**
	 * Adds a rule that determines whether a value is numeric.
	 * @return Phpr\Validation_Rules
	 */
	public function numeric($custom_message = null)
	{
		$this->register_internal(__METHOD__, array(), $custom_message);
		return $this;
	}

	/**
	 * Determines whether a value is numeric.
	 * @param string $name Specifies a field name
	 * @param $value Specifies a value to validate.
	 * @return boolean.
	 */
	protected function eval_numeric($name, $value, &$params, $custom_message)
	{
		if (!strlen($value))
			return true;

		$result = preg_match("/^\-?[0-9]+$/", $value) ? true : false;
		
		$message = strlen($custom_message) 
			? $custom_message 
			: sprintf(Phpr::$locale->get_string('phpr.validation', 'numeric'), $this->field_name);

		if (!$result)
			$this->validation->set_error($message, $name);

		return $result;
	}

	//
	// ====================== Float rule ======================
	//

	/**
	 * Adds a rule that determines whether a value is a valid float number.
	 * This function uses a number format specified in user language settings.
	 * @see Phpr_Language
	 * @return Phpr\Validation_Rules
	 */
	public function float($custom_message = null)
	{
		$this->register_internal(__METHOD__, array(), $custom_message);
		return $this;
	}

	/**
	 * Determines whether a value is a valid float number.
	 * @param string $name Specifies a field name
	 * @param $value Specifies a value to validate.
	 * @return boolean.
	 */
	protected function eval_float($name, $value, &$params, $custom_message)
	{
		if (!strlen($value))
			return true;
		
		// $result = Phpr::$locale->strToNum($value);

		if (!preg_match('/^(\-?[0-9]*\.[0-9]+|\-?[0-9]+)$/', $value))
		{
			$message = strlen($custom_message) 
				? $custom_message 
				: sprintf(Phpr::$locale->get_string('phpr.validation', 'float'), $this->field_name);
			
			$this->validation->set_error($message, $name);
			return false;
		}
		
		$value = trim($value);
		if (strlen($value))
		{
			$first_char = substr($value, 0, 1);
			if ($first_char == '.')
				$value = (float)('0'.$value);
			elseif ($first_char == '-')
			{
				if (substr($value, 1, 1) == '.')
					$value = (float)('-0'.substr($value, 1));
			}
		}

		return $value;
	}

	//
	// ====================== Min length rule ======================
	//

	/**
	 * Adds a rule that determines whether a value is not shorter than a specified length.
	 * @param int $length Specifies a minimum field length
	 * @return Phpr\Validation_Rules
	 */
	public function min_length($length, $custom_message = null)
	{
		$this->register_internal(__METHOD__, array($length), $custom_message);
		return $this;
	}

	/**
	 * Determines whether a value is not shorter than a specified length.
	 * @param string $name Specifies a field name
	 * @param $value Specifies a value to validate.
	 * @param array &$params A list of parameters passed to the MinLength method.
	 * @return boolean.
	 */
	protected function eval_min_length($name, $value, &$params, $custom_message)
	{
		$result = mb_strlen($value) >= $params[0] ? true : false;

		if (!$result)
		{
			$message = strlen($custom_message) 
				? $custom_message 
				: sprintf(Phpr::$locale->get_string('phpr.validation', 'minlen'), $this->field_name, $params[0]);


			$this->validation->set_error($message, $name);
		}

		return $result;
	}

	//
	// ====================== Max length rule ======================
	//

	/**
	 * Adds a rule that determines whether a value is not longer than a specified length.
	 * @param int $length Specifies a maximum field length
	 * @return Phpr\Validation_Rules
	 */
	public function max_length($length, $custom_message = null)
	{
		$this->register_internal(__METHOD__, array($length), $custom_message);
		return $this;
	}

	/**
	 * Determines whether a value is not longer than a specified length.
	 * @param string $name Specifies a field name
	 * @param $value Specifies a value to validate.
	 * @param array &$params A list of parameters passed to the MaxLength method.
	 * @return boolean.
	 */
	protected function eval_max_length($name, $value, &$params, $custom_message)
	{
		$result = mb_strlen($value) <= $params[0] ? true : false;

		if (!$result)
		{
			$message = strlen($custom_message) 
				? $custom_message 
				: sprintf(Phpr::$locale->get_string('phpr.validation', 'maxlen'), $this->field_name, $params[0]);

			$this->validation->set_error($message, $name);
		}

		return $result;
	}

	//
	// ====================== Length rule ======================
	//

	/**
	 * Adds a rule that determines whether a value length matches a specified value.
	 * @param int $length Specifies a field length
	 * @return Phpr\Validation_Rules
	 */
	public function length($length, $custom_message = null)
	{
		$this->register_internal(__METHOD__, array($length), $custom_message);
		return $this;
	}

	/**
	 * Determines whether a value length matches a specified value.
	 * @param string $name Specifies a field name
	 * @param $value Specifies a value to validate.
	 * @param array &$params A list of parameters passed to the Length method.
	 * @return boolean.
	 */
	protected function eval_length($name, $value, &$params, $custom_message)
	{
		$result = mb_strlen($value) == $params[0] ? true : false;

		if (!$result)
		{
			$message = strlen($custom_message) 
				? $custom_message 
				: sprintf(Phpr::$locale->get_string('phpr.validation', 'length'), $this->field_name, $params[0]);

			$this->validation->set_error($message, $name);
		}

		return $result;
	}
	
	//
	// ====================== Unique rule ======================
	//
	
	/**
	 * Adds a rule that determines whether a value is unique in the database table. 
	 * @return Phpr\Validation_Rules
	 */
	public function unique($custom_message = null, $checker_filter_callback = null)
	{
		$this->register_internal(__METHOD__, array('filter_callback' => $checker_filter_callback), $custom_message);
		return $this;
	}

	/**
	 * Determines whether a value length matches a specified value.
	 * @param string $name Specifies a field name
	 * @param $value Specifies a value to validate.
	 * @param array &$params A list of parameters passed to the Length method.
	 * @return boolean.
	 */
	protected function eval_unique($name, $value, &$params, $custom_message, &$obj)
	{
		if (!($obj instanceof ActiveRecord) || !strlen($value))
			return true;

		$model_class_name = get_class($obj);

		$checker = new $model_class_name();
		$checker->where($name." = ?", $value);
		if (!$obj->is_new_record())
			$checker->where($obj->primary_key." != ?", $obj->get_primary_key_value());
			
		if ($params['filter_callback'])
			call_user_func($params['filter_callback'], $checker, $obj, $params['deferred_session_key']);
			
		if ($checker->find())
		{
			$message = strlen($custom_message) 
				? sprintf($custom_message, $value) 
				: sprintf(Phpr::$locale->get_string('phpr.validation', 'unique'), $this->field_name);

			$this->validation->set_error($message, $name);
			return false;
		}

		return true;
	}

	//
	// ====================== Required rule ======================
	//

	/**
	 * Adds a rule that determines whether a value is not empty.
	 * @return Phpr\Validation_Rules
	 */
	public function required($custom_message = null)
	{
		$this->register_internal(__METHOD__, array(), $custom_message);
		$this->required = true;
		return $this;
	}

	/**
	 * Determines whether a value is not empty.
	 * @param string $name Specifies a field name
	 * @param $value Specifies a value to validate.
	 * @return boolean.
	 */
	protected function eval_required($name, $value, &$params, $custom_message)
	{
		if (!is_array($value) && !($value instanceof \Db\Data_Collection))
			$result = trim($value) != '' ? true : false;
		elseif ($value instanceof \Db\Data_Collection)
			$result = $value->count() ? true : false;
		else
			$result = count($value) ? true : false;

		if (!$result) {
			$message = strlen($custom_message) 
				? $custom_message 
				: sprintf(Phpr::$locale->get_string('phpr.validation', 'required'), $this->field_name);

			$this->validation->set_error($message, $name);
		}

		return $result;
	}
	
	//
	// ====================== Optional rule ======================
	//

	/**
	 * Makes a field optional.
	 */
	public function optional()
	{
		$this->required = false;
		
		$required_index = null;
		foreach ($this->rules as $index=>$rule)
		{
			if ($rule['name'] == 'required')
			{
				$required_index = $index;
				break;
			}
		}
		
		if ($required_index !== null)
			unset($this->rules[$required_index]);

		return $this;
	}
	
	/**
	 * Changes the field to become non-unique.
	 */
	public function not_unique()
	{
		foreach ($this->rules as $key => $rule)
		{
			// there could be multiple unique() calls, so keep going!
			if ($rule['name'] === 'unique')
				unset($this->rules[$key]); 
		}
		
		return $this;
	}
	
	//
	// ====================== Alpha rule ======================
	//

	/**
	 * Adds a rule that determines whether a value contains only alphabetical characters.
	 * @return Phpr\Validation_Rules
	 */
	public function alpha($custom_message = null)
	{
		$this->register_internal(__METHOD__, array(), $custom_message);
		return $this;
	}

	/**
	 * Determines whether a value contains only alphabetical characters.
	 * @param string $name Specifies a field name
	 * @param $value Specifies a value to validate.
	 * @return boolean.
	 */
	protected function eval_alpha($name, $value, &$params, $custom_message)
	{
		$result = preg_match("/^([-a-z])+$/i", $value) ? true : false;

		if (!$result)
		{
			$message = strlen($custom_message) 
				? $custom_message 
				: sprintf(Phpr::$locale->get_string('phpr.validation', 'alpha'), $this->field_name);

			$this->validation->set_error($message, $name);
		}

		return $result;
	}

	//
	// ====================== Alphanumeric rule ======================
	//

	/**
	 * Adds a rule that determines whether a value contains only alpha-numeric characters.
	 * @return Phpr\Validation_Rules
	 */
	public function alphanum($custom_message = null)
	{
		$this->register_internal(__METHOD__, array(), $custom_message);
		return $this;
	}

	/**
	 * Determines whether a value contains only alpha-numeric characters.
	 * @param string $name Specifies a field name
	 * @param $value Specifies a value to validate.
	 * @return boolean.
	 */
	protected function eval_alphanum($name, $value, &$params, $custom_message)
	{
		$result = preg_match("/^([-a-z0-9])+$/i", $value) ? true : false;

		if (!$result)
		{
			$message = strlen($custom_message) 
				? $custom_message 
				: sprintf(Phpr::$locale->get_string('phpr.validation', 'alphanum'), $this->field_name);

			$this->validation->set_error($message, $name);
		}

		return $result;
	}

	//
	// ====================== Email rule ======================
	//

	/**
	 * Adds a rule that determines whether a value is a valid email address.
	 * @param bool @AllowEmpty Determines whether empty string is allowed
	 * @return Phpr\Validation_Rules
	 */
	public function email($allow_empty = false, $custom_message = null)
	{
		$this->register_internal(__METHOD__, array($allow_empty), $custom_message);
		return $this;
	}

	/**
	 * Determines whether a value is a valid email address.
	 * @param string $name Specifies a field name
	 * @param $value Specifies a value to validate.
	 * @param array &$params A list of parameters passed to the Regexp method.
	 * @return boolean.
	 */
	protected function eval_email($name, $value, &$params, $custom_message)
	{
		if (!strlen($value) && $params[0])
			return true;

		$result = preg_match("/^[_a-z0-9-\.\=\+]+@[_a-z0-9-\.\=\+]+$/", mb_strtolower($value)) ? true : false;

		if (!$result)
		{
			$message = strlen($custom_message) 
				? $custom_message 
				: sprintf(Phpr::$locale->get_string('phpr.validation', 'email'), $this->field_name);

			$this->validation->set_error($message, $name);
		}

		return $result;
	}

	//
	// ====================== IP rule ======================
	//

	/**
	 * Adds a rule that determines whether a value is a valid IP address.
	 * @return Phpr\Validation_Rules
	 */
	public function ip($custom_message = null)
	{
		$this->register_internal(__METHOD__, array(), $custom_message);
		return $this;
	}

	/**
	 * Determines whether a value is a valid IP address.
	 * @param string $name Specifies a field name
	 * @param $value Specifies a value to validate.
	 * @return boolean.
	 */
	protected function eval_ip($name, $value, &$params, $custom_message)
	{
		$result = preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/", $value) ? true : false;

		if (!$result)
		{
			$message = strlen($custom_message) 
				? $custom_message 
				: sprintf(Phpr::$locale->get_string('phpr.validation', 'ip'), $this->field_name);

			$this->validation->set_error($message, $name);
		}

		return $result;
	}

	//
	// ====================== Matches rule ======================
	//

	/**
	 * Adds a rule that determines whether a value matches another field value.
	 * @param string $field Specifies a name of field this field value must match
	 * @return Phpr\Validation_Rules
	 */
	public function matches($field, $custom_message = null)
	{
		$this->register_internal(__METHOD__, array($field, $custom_message));
		return $this;
	}

	/**
	 * Determines whether a value matches another field value.
	 * @param string $name Specifies a field name
	 * @param $value Specifies a value to validate.
	 * @param array &$params A list of parameters passed to the Matches method.
	 * @return boolean.
	 */
	protected function eval_matches($name, $value, &$params)
	{
		$field_to_match = $params[0];
		$error_message = $params[1];
		if (!isset($this->validation->_fields[$field_to_match]))
			throw new SystemException("Unknown validation field: ".$field_to_match);

		$value_to_match = isset($this->validation->field_values[$field_to_match]) 
			? $this->validation->field_values[$field_to_match] 
			: Phpr::$request->post_field($field_to_match);

		$result = $value == $value_to_match ? true : false;

		if (!$result)
		{
			if (!strlen($error_message))
			{
				$field_to_matchName = $this->validation->_fields[$field_to_match]->field_name;
				$this->validation->set_error(sprintf(Phpr::$locale->get_string('phpr.validation', 'matches'), $this->field_name, $field_to_matchName), $name);
			} 
			else
			{
				$this->validation->set_error($error_message, $name);
			}
		}

		return $result;
	}

	//
	// ====================== Regexp rule ======================
	//

	/**
	 * Adds a rule that determines whether a value matches a specified regular expression pattern.
	 * @param string $pattern Specifies a Perl-compatible regular expression pattern.
	 * @param string $error_message Optional error message.
	 * @param bool @AllowEmpty Determines whether empty string is allowed
	 * @return Phpr\Validation_Rules
	 */
	public function regexp($pattern, $custom_message = null, $allow_empty = false)
	{
		$this->register_internal(__METHOD__, array($pattern, $custom_message, $allow_empty));
		return $this;
	}

	/**
	 * Determines whether a value matches a specified regular expression pattern.
	 * @param string $name Specifies a field name
	 * @param $value Specifies a value to validate.
	 * @param array &$params A list of parameters passed to the Regexp method.
	 * @return boolean.
	 */
	protected function eval_regexp($name, $value, &$params)
	{
		if (!strlen($value) && $params[2])
			return true;

		$result = preg_match($params[0], $value) ? true : false;

		if (!$result) 
		{
			$error_message = ($params[1] !== null) 
				? $params[1] 
				: sprintf(Phpr::$locale->get_string('phpr.validation', 'regexp'), $this->field_name);

			$this->validation->set_error($error_message, $name);
		}

		return $result;
	}

	//
	// ====================== DateTime rule ======================
	//

	/**
	 * Adds a rule that determines whether a value represents a date/time value, according the specified format.
	 * Some formats (like %x and %X) depends on the current user language date format. 
	 * This rule sets the field value to a valid SQL date format converted to GMT.
	 * @param string $format Specifies an expected format. 
	 * By default the short date format (%x) used (11/6/2006 - for en_US).
	 * @param string $error_message Optional error message.
	 * @return Phpr\Validation_Rules
	 */
	public function datetime($format = "%x %X", $error_message = null, $date_as_is = false)
	{
		$this->register_internal(__METHOD__, array($format, $error_message, $date_as_is));
		return $this;
	}

	/**
	 * Determines whether a value is a valid data and time string
	 * @param string $name Specifies a field name
	 * @param $value Specifies a value to validate.
	 * @param array &$params A list of parameters passed to the Regexp method.
	 * @return boolean.
	 */
	protected function eval_datetime($name, $value, &$params)
	{
		if (is_object($value))
			return true;

		if (!strlen($value))
			return null;

		$timezone = Phpr::$config->get('TIMEZONE');
		try
		{
			$timezone_obj = new DateTimeZone($timezone);
		}
		catch (Exception $ex)
		{
			throw new SystemException('Invalid time zone specified in config.php: '.$timezone.'. Please refer this document for the list of correct time zones: http://docs.php.net/timezones.');
		}
		
		// Check against defined format
		$result = DateTime::parse($value, $params[0], $timezone_obj);

		// Also check against SqlDateTime format
		if (!$result)
			$result = DateTime::parse($value, null, $timezone_obj);

		if (!$result) 
		{
			$error_message = $params[1] !== null 
				? $params[1] 
				: sprintf(Phpr::$locale->get_string('phpr.validation', 'datetime'), $this->field_name, DateTime::now()->format($params[0]));
				
			$this->validation->set_error($error_message, $name);
		} 
		else
		{
			if (!$params[2])
			{
				$timezone_obj = new DateTimeZone('GMT');
				$result->set_timezone($timezone_obj);
				unset($timezone_obj);
			}
			
			$result = $result->to_sql_datetime();
		}

		return $result;
	}

	/**
	 * Adds a rule that determines whether a value represents a date/time value, according the specified format.
	 * Some formats (like %x and %X) depends on the current user language date format. 
	 * This rule sets the field value to a valid SQL date format.
	 * @param string $format Specifies an expected format. 
	 * By default the short date format (%x) used (11/6/2006 - for en_US).
	 * @param string $error_message Optional error message.
	 * @return Phpr\Validation_Rules
	 */
	public function date($format = "%x", $custom_message = null)
	{
		$this->register_internal(__METHOD__, array($format, $custom_message));
		return $this;
	}

	/**
	 * Determines whether a value is a valid data and time string
	 * @param string $name Specifies a field name
	 * @param $value Specifies a value to validate.
	 * @param array &$params A list of parameters passed to the Regexp method.
	 * @return string.
	 */
	protected function eval_date($name, $value, &$params)
	{
		if (is_object($value))
			return true;

		if (!strlen($value))
			return null;
		
		$result = DateTime::parse($value, $params[0]);

		if (!$result) 
		{
			$error_message = ($params[1] !== null) 
				? $params[1] 
				: sprintf(Phpr::$locale->get_string('phpr.validation', 'datetime'), $this->field_name, DateTime::now()->format($params[0]));
				
			$this->validation->set_error($error_message, $name);
		} 
		else
			$result = $result->to_sql_date();

		return $result;
	}

	/**
	 * Cleans HTML preventing XSS code.
	 * @param string $value Specifies a controller method name.
	 * @return Phpr\Validation_Rules
	 */
	public function clean_html()
	{
		$this->register_internal(__METHOD__, array());
		return $this;
	}

	protected function eval_clean_html($name, $value, &$params)
	{
		return Html::clean_xss($value);
	}
}
