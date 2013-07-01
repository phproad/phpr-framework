<?php namespace Db;

use Phpr;
use Phpr\Inflector;
use Phpr\DateTime;
use Phpr\Date;
use Phpr\Html;
use Phpr\SystemException;

/**
 * Column definition class. 
 * Objects of this class are used for defining presentation field properties in models.
 * List behavior and form behavior are use data from these objects to output correct 
 * field display names and format field data.
 *
 * Important note about date and datetime fields.
 * Date fields are NOT converted to GMT during saving to the database 
 * and display_value method always returns the field value as is.
 *
 * Datetime fields are CONVERTED to GMT during saving and display_value returns value converted
 * back to a time zone specified in the configuration file.
 */
class Column_Definition
{
	public $db_name;
	public $display_name;
	public $default_order = null;
	public $type;
	public $is_calculated;
	public $is_custom;
	public $is_reference;
	public $reference_type = null;
	public $reference_value_expr;
	public $relation_name;
	public $reference_foreign_key;
	public $reference_class_name;
	public $visible = true;
	public $default_visible = true;
	public $list_title = null;
	public $list_no_title = false;
	public $no_log = false;
	public $log = false;
	public $date_as_is = false;
	public $currency = false;
	public $no_sorting = false;
	
	private $_model;
	private $_column_info;
	private $_calculated_column_name;
	private $_validation_obj = null;
	
	private static $_relation_joins = array();
	private static $_cached_models = array();
	private static $_cached_class_instances = array();
	
	public $index;

	/**
	 * Date/time display format
	 * @var string
	 */
	private $_date_format = '%x';
	private $_datetime_format = '%x %X';
	private $_time_format = '%X';

	/**
	 * Floating point numbers display precision.
	 * @var int
	 */
	private $_precision = 2;
	
	/**
	 * Text display length
	 */
	private $_length = null;

	public function __construct($model, $db_name, $display_name, $type=null, $relation_name=null, $value_expression=null)
	{
		$this->db_name = $db_name;
		$this->display_name = $display_name;
		$this->_model = $model;
		$this->is_reference = strlen($relation_name);
		$this->relation_name = $relation_name;

		if (!$this->is_reference)
		{
			$this->_column_info = $this->_model->column($db_name);
			if ($this->_column_info)
				$this->type = $this->_column_info->type;

			if ($this->_column_info)
			{
				$this->is_calculated = $this->_column_info->calculated;
				$this->is_custom = $this->_column_info->custom;
			}
		} 
		else
		{
			$this->type = $type;
			
			if (strlen($value_expression))
			{
				$this->reference_value_expr = $value_expression;
				$this->define_reference_column();
			}
		}
		
		if ($this->type == db_date || $this->type == db_datetime)
			$this->validation();
	}
	
	public function extend_model($model)
	{
		$this->set_context($model);

		if ($this->is_reference && strlen($this->reference_value_expr))
			$this->define_reference_column();
			
		return $this;
	}

	/**
	 * Common column properties
	 */

	public function type($type_name)
	{
		$valid_types = array(db_varchar, db_number, db_float, db_bool, db_datetime, db_date, db_time, db_text);
		if (!in_array($type_name, $valid_types))
			throw new SystemException('Invalid database type: '.$type_name);
			
		$this->type = $type_name;
		$this->_column_info = null;
		
		return $this;
	}
	
	public function date_format($display_format)
	{
		if ($this->type == db_datetime || $this->type == db_date || $this->type == db_time)
			$this->_date_format = $display_format;
		else 
			throw new SystemException('Error in column definition for: '.$this->db_name.' column. Method "date_format" is applicable only for date or time fields.');
		$this->validation(null, true);
			
		return $this;
	}

	public function time_format($display_format)
	{
		if ($this->type == db_datetime || $this->type == db_time)
			$this->_time_format = $display_format;
		else
			throw new SystemException('Error in column definition for: '.$this->db_name.' column. Method "time_format" is applicable only for datetime or time fields.');
		$this->validation(null, true);
		
		return $this;
	}
	
	public function datetime_format($display_format)
	{
		if ($this->type == db_datetime)
			$this->_datetime_format = $display_format;
		else
			throw new SystemException('Error in column definition for: '.$this->db_name.' column. Method "datetime_format" is applicable only for datetime fields.');
		
		return $this;
	}

	public function date_as_is($value = true)
	{
		$this->date_as_is = $value;
		$this->validation(null, true);
		return $this;
	}
	
	public function precision($precision)
	{
		if ($this->type == db_float)
			$this->_precision = $precision;
		else 
			throw new SystemException('Error in column definition for: '.$this->db_name.' column. Method "precision" is applicable only for floating point number fields.');
			
		return $this;
	}
	
	public function length($length)
	{
		if ($this->type == db_varchar || $this->type == db_text)
			$this->length = $length;
		else 
			throw new SystemException('Error in column definition for: '.$this->db_name.' column. Method "length" is applicable only for varchar or text fields.');

		return $this;
	}
	
	/**
	 * Hides the column from lists. 
	 */
	public function invisible()
	{
		$this->visible = false;
		return $this;
	}
	
	/**
	 * Hides the column from lists with default list settings
	 */
	public function default_invisible()
	{
		$this->default_visible = false;
		return $this;
	}
	
	/**
	 * Sets a title to display in list columns.
	 */
	public function list_title($title)
	{
		$this->list_title = $title;
		return $this;
	}

	/**
	 * Allows to hide the column list title.
	 */
	public function list_no_title($value = true)
	{
		$this->list_no_title = $value;
		return $this;
	}

	/**
	 * Do not log changes of the column.
	 */
	public function no_log()
	{
		$this->no_log = true;
		return $this;
	}
	
	/**
	 * Disables or enables sorting for the column.
	 */
	public function no_sorting($value = true)
	{
		$this->no_sorting = true;
		return $this;
	}
	
	/**
	 * Log changes of the column. By default changes are not logged for calculated and custom columns.
	 */
	public function log()
	{
		$this->log = true;
		return $this;
	}
	
	/**
	 * Indicates that the column should be used for sorting the list 
	 * in case if a user have not selected other sorting column.
	 * @param string $direction Specifies an order direction - 'asc' or 'desc'
	 */
	public function order($directon = 'asc')
	{
		$this->default_order = $directon;
		
		return $this;
	}
	
	/**
	 * Indicates that the column value should be formatted as currency
	 * in case if a user have not selected other sorting column.
	 * @param string $value Pass the TRUE value if the currency formatting should be applied
	 * @param boolean $readd This parameter is for internal use.
	 */
	public function currency($value)
	{
		$this->currency = $value;
		return $this;
	}

	public function validation($custom_format_message = null, $readd = false)
	{
		if (!strlen($this->type))
			throw new SystemException('Error applying validation to '.$this->db_name.' column. Column type is unknown. Probably this is a calculated column. Please call the "type" method to set the column type.');
			
		if ($this->_validation_obj && !$readd)
			return $this->_validation_obj;

		$db_name = $this->is_reference ? $this->reference_foreign_key : $this->db_name;

		$rule = $this->_model->validation->add($db_name, $this->display_name);
		if ($this->type == db_date)
			$rule->date($this->_date_format, $custom_format_message);
		elseif ($this->type == db_datetime)
			$rule->datetime($this->_date_format.' '.$this->_time_format, $custom_format_message, $this->date_as_is);
		elseif ($this->type == db_float)
			$rule->float($custom_format_message);
		elseif ($this->type == db_number)
			$rule->numeric($custom_format_message);

		return $this->_validation_obj = $rule;
	}
	
	/**
	 * Internal methods - used by the framework
	 */
	
	public function get_column_info()
	{
		return $this->_column_info;
	}

	public function get_date_format()
	{
		return $this->_date_format;
	}
	
	public function get_time_format()
	{
		return $this->_time_format;
	}

	public function display_value($media)
	{
		$db_name = $this->db_name;

		if (!$this->is_reference)
			$value = $this->_model->$db_name;
		else
		{
			$colum_name = $this->_calculated_column_name;
			$value = $this->_model->$colum_name;
		}

		switch ($this->type)
		{
			case db_varchar:
			case db_text:
				if ($media == 'form' || $this->_length === null)
					return $value;
				
				return Html::str_trim($value, $this->_length);
			case db_number:
			case db_bool:
				return $value;
			case db_float:
				if ($media != 'form')
				{
					if ($this->currency)
						return Phpr::$locale->get_currency($value);

					return Phpr::$locale->get_number($value, $this->_precision);
				}
				else
					return $value;
			case db_date:
				if (gettype($value) == 'string' && strlen($value))
				{
					$value = new DateTime($value.' 00:00:00');
				}
				return $value ? $value->format($this->_date_format) : null;
			case db_datetime:
				if (gettype($value) == 'string' && strlen($value))
				{
					if (strlen($value) == 10) 
						$value.=' 00:00:00';
					
					$value = new DateTime($value);
				}
				if (!$this->date_as_is)
				{
					if ($media == 'time')
						return $value ? Date::display($value, $this->_time_format) : null;
					elseif ($media == 'date')
						return $value ? Date::display($value, $this->_date_format) : null;
					else
						return $value ? Date::display($value, $this->_datetime_format) : null;
				}
				else
				{
					if ($media == 'time')
						return $value ? $value->format($this->_time_format) : null;
					elseif ($media == 'date')
						return $value ? $value->format($this->_date_format) : null;
					else
						return $value ? $value->format($this->_datetime_format) : null;
				}
			case db_time:
				return Date::display($value, $this->_time_format);
			default:
				return $value;
		}
	}
	
	public function get_sorting_column_name()
	{
		if (!$this->is_reference)
			return $this->db_name;

		return $this->_calculated_column_name;
	}

	protected function define_reference_column()
	{
		if (!array_key_exists($this->relation_name, $this->_model->has_models))
			throw new SystemException('Error defining reference "'.$this->relation_name.'". Relation '.$this->relation_name.' is not found in model '.get_class($this->_model));

		$relation_type = $this->_model->has_models[$this->relation_name];

		$has_primary_key = $has_foreign_key = false;
		$options = $this->_model->get_relation_options($relation_type, $this->relation_name, $has_primary_key, $has_foreign_key);

		if (!is_null($options['finder_sql'])) 
			throw new SystemException('Error defining reference "'.$this->relation_name.'". Relation finder_sql option is not supported.');

		$this->reference_type = $relation_type;
		
		$column_name = $this->_calculated_column_name = $this->db_name.'_calculated';
		
		$col_definition = array();
		$col_definition['type'] = $this->type;
		
		$this->reference_class_name = $options['class_name'];

		if (!array_key_exists($options['class_name'], self::$_cached_class_instances))
		{
			$object = new $options['class_name'](null, array('no_column_init'=>true, 'no_validation'=>true));
			self::$_cached_class_instances[$options['class_name']] = $object;
		}
		
		$object = self::$_cached_class_instances[$options['class_name']];
		
		if ($relation_type == 'has_one' || $relation_type == 'belongs_to')
		{
			$objectTableName = $this->relation_name.'_calculated_join';
			$col_definition['sql'] = str_replace('@', $objectTableName.'.', $this->reference_value_expr);

			$join_exists = isset(self::$_relation_joins[$this->_model->object_id][$this->relation_name]);

			if (!$join_exists)
			{
				switch ($relation_type) 
				{
					case 'has_one' : 
						if (!$has_foreign_key)
							$options['foreign_key'] = Inflector::foreign_key($this->_model->table_name, $object->primary_key);

						$this->reference_foreign_key = $options['foreign_key'];
						$condition = "{$objectTableName}.{$options['foreign_key']} = {$this->_model->table_name}.{$options['primary_key']}";
						$col_definition['join'] = array("{$object->table_name} as {$objectTableName}"=>$condition);
					break;
					case 'belongs_to' : 
						$condition = "{$objectTableName}.{$options['primary_key']} = {$this->_model->table_name}.{$options['foreign_key']}";
						$this->reference_foreign_key = $options['foreign_key'];
						$col_definition['join'] = array("{$object->table_name} as {$objectTableName}"=>$condition);

					break;
				}
				self::$_relation_joins[$this->_model->object_id][$this->relation_name] = $this->reference_foreign_key;
			} else
				$this->reference_foreign_key = self::$_relation_joins[$this->_model->object_id][$this->relation_name];
		} 
		else
		{
			$this->reference_foreign_key = $this->relation_name;

			switch ($relation_type) 
			{
				case 'has_many' :
					$valueExpr = str_replace('@', $object->table_name.'.', $this->reference_value_expr);
					$col_definition['sql'] = "select group_concat($valueExpr ORDER BY 1 SEPARATOR ', ') from {$object->table_name} where
						{$object->table_name}.{$options['foreign_key']} = {$this->_model->table_name}.{$options['primary_key']}";
						
					if ($options['conditions'])
						$col_definition['sql'] .= " and ({$options['conditions']})";
						
				break;
				case 'has_and_belongs_to_many':
					$join_table_alias = $this->relation_name.'_relation_table';
					$valueExpr = str_replace('@', $join_table_alias.'.', $this->reference_value_expr);

					if (!isset($options['join_table']))
						$options['join_table'] = $this->_model->get_join_table_name($this->_model->table_name, $object->table_name);

					if (!$has_primary_key)
						$options['primary_key'] = Inflector::foreign_key($this->_model->table_name, $this->_model->primary_key);

					if (!$has_foreign_key)
						$options['foreign_key'] = Inflector::foreign_key($object->table_name, $object->primary_key);

					$col_definition['sql'] = "select group_concat($valueExpr ORDER BY 1 SEPARATOR ', ') from {$object->table_name} as {$join_table_alias}, {$options['join_table']} where
						{$join_table_alias}.{$object->primary_key}={$options['join_table']}.{$options['foreign_key']} and
						{$options['join_table']}.{$options['primary_key']}={$this->_model->table_name}.{$this->_model->primary_key}";
					
					if ($options['conditions'])
						$col_definition['sql'] .= " and ({$options['conditions']})";
				break;
			}
		}

		$this->_model->calculated_columns[$column_name] = $col_definition;
	}
	
	public function set_context($model)
	{
		$this->_model = $model;
		return $this;
	}
}
