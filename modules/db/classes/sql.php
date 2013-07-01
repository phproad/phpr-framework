<?php namespace Db;

use Phpr;
use Phpr\Util;

class Sql extends Where
{
	const default_driver = 'Db\MySQL_Driver';

	protected $_driver = null;

	public $use_straight_join = false;
	
	public $parts = array(
		'calc_rows' => false,
		'from' => array(),
		'fields' => array(),
		'order' => array()
	);

	public static function create() 
	{
		return new self();
	}

	public function __toString() 
	{
		return $this->build_sql();
	}
		
	public function reset() 
	{
		parent::reset();
		$this->parts = array(
			'calc_rows' => false,
			'from' => array(),
			'fields' => array(),
			'order' => array()
		);
	}

	public function select($fields = '*', $table_fields = '', $replace_columns = false) 
	{
		if (func_num_args() > 1) 
		{
			// SELECT(table, fields), append table. to fields
			// 
			$table = $fields;
			$fields = explode(',', $table_fields);
			foreach ($fields as &$field) {
				if (strstr($field, '.') === false) {
					$field = $table . '.' . $field;
				}
			}
		} 
		else 
		{
			// SELECT(fields)
			// 
			$fields = explode(',', $fields);
		}

		// Merge fields
		// 
		if (!$replace_columns) 
		{
			$this->parts['fields'] = array_merge($this->parts['fields'], $fields);
		} 
		else 
		{
			$this->parts['fields'] = $fields;
		}
		return $this;
	}
	
	/**
	 * @param string $table
	 * @param string|string[] $columns
	 * @return SQL
	 */
	public function from($table, $columns = '*', $replace_columns = false) 
	{
		$this->set_part('from', $table);
		
		if ((strpos($columns, ',') === false) && (strpos($columns, '.') === false) && (strpos($columns, '(') === false))
			$columns = $table . '.' . $columns;

		if (!$replace_columns)
			$this->set_part('fields', $columns);
		else
			$this->parts['fields'] = Util::splat($columns);

		return $this;
	}
	
	/**
	 * Adds column(s) to the query field list
	 * @param string $column Column definition
	 * @return SQL
	 */
	public function add_column($column)
	{
		$this->select($column);
		return $this;
	}

	public function join($table_name, $cond, $columns = '', $type = 'left') 
	{
		if (!in_array(strtolower($type), array('left', 'inner', 'left outer', 'full outer')))
			$type = null;
		
		if ($columns == '*')
			$columns = $table_name . '.*';

		$this->parts['join'][] = array(
			'type' => $type,
			'name' => $table_name,
			'cond' => $cond
		);
		
		if (trim($columns) != '')
			$this->set_part('fields', $columns);
		
		return $this;
	}

	public function group($spec) 
	{
		if (is_string($spec)) {
			if ($spec == '') return $this;
			$spec = explode(',', $spec);
		} else
			settype($spec, 'array');

		foreach ($spec as $val) 
		{
			if (strpos($val, '.') === false)
				$val = ':__table_name__.' . $val;

			$this->set_part('group', trim($val));
		}
		
		return $this;
	}
	
	public function having($spec)
	{
		if (is_string($spec)) {
			if ($spec == '') return $this;
			$spec = explode(',', $spec);
		} else
			settype($spec, 'array');

		foreach ($spec as $val) 
			$this->set_part('having', trim($val));
		
		return $this;
	}
	
	public function order($spec) 
	{
		if (is_null($spec)) 
			return $this;
			
		if (is_string($spec)) 
		{
			if ($spec == '') return $this;
			$spec = explode(',', $spec);
		} else
			settype($spec, 'array');

		foreach ($spec as $val) 
		{
			$asc = (strtoupper(substr($val, -4)) == ' ASC');
			$desc = (strtoupper(substr($val, -5)) == ' DESC');
				
			$val = trim($val);

			$this->set_part('order', trim($val));
		}
		
		return $this;
	}
	
	public function reset_order()
	{
		$this->reset_part('order');
	}
	
	public function reset_joins()
	{
		$this->reset_part('join');
	}
	
	protected function has_order() 
	{
		return isset($this->parts['order']) && (count($this->parts['order']) != 0);
	}

	protected function has_group() 
	{
		return isset($this->parts['group']) && (count($this->parts['group']) != 0);
	}

	/**
	 * Sets a limit count and offset to the query.
	 * @param int $count The number of rows to return.
	 * @param int $offset Start returning after this many rows.
	 * @return void
	 */
	public function limit($count = null, $offset = null) 
	{
		if (is_null($count) && is_null($offset)) 
			return $this;
			
		$this->parts['limit_count'] = (int)$count;
		$this->parts['limit_offset'] = (int)$offset;

		return $this;
	}
	
	/**
	 * Sets the limit and count by page number.
	 * @param int $page Limit results to this page number.
	 * @param int $row_count Use this many rows per page.
	 * @return void
	 */
	public function limit_page($page, $row_count) 
	{
		if (is_null($page) && is_null($row_count)) 
			return $this;
			
		$page = ($page > 0) ? $page : 1;
		
		$row_count = ($row_count > 0) ? $row_count : 1;
		$this->parts['limit_count'] = (int)$row_count;
		$this->parts['limit_offset'] = (int)$row_count * ($page - 1);

		return $this;
	}
	
	public function use_calc_rows() 
	{
		$this->parts['calc_rows'] = true;
	}

	public function build_sql() 
	{
		$sql = array();

		// Select only sepcified fields
		// 
		$sql[] = "SELECT";
		if ($this->parts['calc_rows'])
			$sql[] = 'SQL_CALC_FOUND_ROWS';
		else 
		{
			if ($this->use_straight_join && Phpr::$config->get('ALLOW_STRAIGHT_JOIN'))
				$sql[] = 'STRAIGHT_JOIN';
		}

		// Determine sql fields versus custom fields
		// 
		$fields = $this->parts['fields'];
		if (count($fields) == 0)
			$fields = array(':__table_name__.*');

		$sql[] = implode(', ', $fields);
		$sql[] = 'FROM :__table_name__';

		// Joins
		// 
		if (isset($this->parts['join'])) 
		{
			$list = array();
			foreach ($this->parts['join'] as $join) 
			{
				$tmp = '';
				// add the type (LEFT, INNER, etc)
				if (!empty($join['type']))
					$tmp .= strtoupper($join['type']) . ' ';

				// add the table name and condition
				$tmp .= 'JOIN ' . $join['name'];
				$tmp .= ' ON ' . $join['cond'];
				// add to the list
				$list[] = $tmp . ' ';
			}
			
			// Add the list of all joins
			// 
			$sql[] = implode("\n", $list) . "\n";
		}
		
		// Add where
		// 
		$where = $this->build_where();
		if (trim($where) != '')
			$sql[] = "WHERE\n\t" . $where;
			
		// Grouped by these columns
		// 
		if (isset($this->parts['group']) && count($this->parts['group']))
			$sql[] = "GROUP BY\n\t" . implode('', $this->parts['group']);

		// Having these conditions
		// 
		if (isset($this->parts['having']) && count($this->parts['having']))
			$sql[] = "HAVING\n\t" . implode('', $this->parts['having']);

		// Add order
		// 
		if (count($this->parts['order']))
			$sql[] = "ORDER BY\n\t" . implode(', ', $this->parts['order']);

		// Add limit
		// 
		$count = !empty($this->parts['limit_count']) ? (int)$this->parts['limit_count'] : 0;
		$offset = !empty($this->parts['limit_offset']) ? (int)$this->parts['limit_offset'] : 0;
		
		if ($count > 0) 
		{
			$offset = ($offset > 0) ? $offset : 0;
			$sql[] = ' ' . $this->driver()->limit($offset, $count);
		}
		
		$sql = implode(' ', $sql);
		return $this->prepare_tablename($sql);
	}
	
	private function prepare_tablename($sql, $tablename = '') 
	{
		return str_replace(':__table_name__', ($tablename == '') ? $this->parts['from'][0] : $tablename, $sql);
	}

	/* Insert/Update/Delete */

	/**
	 * Inserts a table row with specified data.
	 *
	 * @param string $table The table to insert data into.
	 * @param array $bind Column-value pairs.
	 * @return int The number of affected rows.
	 */
	public function sql_insert($table, $values, $pairs = null) 
	{
		// Column names come from the array keys
		// 
		if (is_null($pairs)) 
		{
			$cols = array_keys($values);
			
			// Build statement
			// 
			$sql = 'INSERT INTO ' . $table
				. '(' . implode(', ', $cols) . ') '
				. 'VALUES (:' . implode(', :', $cols) . ')';

			// Execute the statement and return the number of affected rows
			// 
			$this->query($this->prepare_tablename($this->prepare($sql, $values), $table));
		} 
		else 
		{
			$cols = $values;
			$values = array();
			foreach ($pairs as $pair)
				$values[] = $this->prepare('(?, ?)', $pair[0], $pair[1]);

			// Build statement
			// 
			$sql = 'INSERT INTO ' . $table
				. '(' . implode(', ', $cols) . ') '
				. 'VALUES' . implode(',', $values);
			
			// Execute the statement and return the number of affected rows
			// 
			$this->query($this->prepare_tablename($sql, $table));
		}

		return $this->row_count();
	}
	
	/**
	 * Updates table rows with specified data based on a WHERE clause.
	 *
	 * @param string $table The table to udpate.
	 * @param array $bind Column-value pairs.
	 * @param WhereBase|string $where UPDATE WHERE clause.
	 * @param string $order UPDATE ORDER BY clause.
	 * @return int The number of affected rows.
	 */
	public function sql_update($table, $bind, $where, $order = '') 
	{
		// Check if $where is a WhereBase object
		// 
		if ($where instanceof WhereBase)
			$where = $where->build_where();

		if (is_array($bind)) 
		{
			// Build "col = :col" pairs for the statement
			// 
			$set = array();
			foreach ($bind as $col => $val)
			{
				$set[] = "$col = :$col";
			}

			$record = implode(', ', $set);
		} 
		else if (is_string($bind))
			$record = $bind;
		else 
			return -1;
			
		$sql = 'UPDATE ' . $table
			. ' SET ' . $record
			. (($where) ? " WHERE $where" : '')
			. (($order != '') ? " ORDER BY $order" : '');
		
		// Execute the statement and return the number of affected rows
		// 
		$this->query($this->prepare_tablename($this->prepare($sql, $bind), $table));
		return $this->row_count();
	}
	
	/**
	 * Deletes table rows based on a WHERE clause.
	 *
	 * @param string $table The table to udpate.
	 * @param WhereBase|string $where DELETE WHERE clause.
	 * @return int The number of affected rows.
	 */
	public function sql_delete($table, $where) 
	{
		// Check if $where is a WhereBase object
		// 
		if ($where instanceof WhereBase)
			$where = $where->build_where();

		// Build statement
		// 
		$sql = 'DELETE FROM ' . $table . (($where) ? " WHERE $where" : '');
		
		// Execute the statement and return the number of affected rows
		// 
		$this->query($this->prepare_tablename($sql, $table));

		return $this->row_count();
	}
	
	/* SQL execute */

	public function query($sql) 
	{
		return $this->execute($sql);
	}
	
	/* Utility routines */

	public function row_count() 
	{
		return $this->driver()->row_count();
	}
	
	/**
	 * Gets the last inserted ID.
	 * @param string $tableName table or sequence name needed for some PDO drivers
	 * @param string $primaryKey primary key in $tableName need for some PDO drivers
	 * @return integer
	 */
	public function last_insert_id($tableName = null, $primaryKey = null) 
	{
		return $this->driver()->last_insert_id($tableName, $primaryKey);
	}

	/**
	 * Returns the column descriptions for a table.
	 * @return array
	 */
	public function describe_table($table) 
	{
		return $this->driver()->describe_table($table);
	}

	/**
	 * Returns the index descriptions for a table.
	 * @return array
	 */
	public function describe_index($table)
	{
		return $this->driver()->describe_index($table);
	}
	
	/* Fetch methods */
	
	protected function _fetch_all($result, $col = null) 
	{
		$data = array();
		while ($row = $this->driver()->fetch($result, $col)) {
			$data[] = $row;
		}
		
		return $data;
	}
	
	/**
	 * Fetches all SQL result rows as a sequential array.
	 * @param string $sql An SQL SELECT statement.
	 * @param array $bind Data to bind into SELECT placeholders.
	 * @return array
	 */
	public function fetch_all($sql, $bind = null) 
	{
		$result = Phpr::$events->fire_event(array('name' => 'db:on_before_database_fetch', 'type' => 'filter'), array(
			'sql' => $sql,
			'fetch' => null
		));
		
		extract($result);
		
		if (isset($fetch))
			return $fetch;
	
		$result = $this->query($this->prepare($sql, $bind));
		$fetch = $this->_fetch_all($result);
		$this->driver()->free_query_result($result);

		Phpr::$events->fire_event('db:on_after_database_fetch', $sql, $fetch);
				
		return $fetch;
	}
	
	/**
	 * Fetches the first column of all SQL result rows as an array.
	 * The first column in each row is used as the array key.
	 * @param string $sql An SQL SELECT statement.
	 * @param array $bind Data to bind into SELECT placeholders.
	 * @return array
	 */
	public function fetch_col($sql, $bind = null) 
	{
		$result = Phpr::$events->fire_event(array('name' => 'db:on_before_database_fetch', 'type' => 'filter'), array(
			'sql' => $sql,
			'fetch' => null
		));
		
		extract($result);
		
		if (isset($fetch))
			return $fetch;
	
		$result = $this->query($this->prepare($sql, $bind));
		$fetch = $this->_fetch_all($result, 0);
		$this->driver()->free_query_result($result);

		Phpr::$events->fire_event('db:on_after_database_fetch', $sql, $fetch);
				
		return $fetch;
	}
	
	/**
	 * Fetches the first column of the first row of the SQL result.
	 * @param string $sql An SQL SELECT statement.
	 * @param array $bind Data to bind into SELECT placeholders.
	 * @return string
	 */
	public function fetch_one($sql, $bind = null) 
	{
		$result = Phpr::$events->fire_event(array('name' => 'db:on_before_database_fetch', 'type' => 'filter'), array(
			'sql' => $sql,
			'fetch' => null
		));
		
		extract($result);
		
		if (isset($fetch))
			return $fetch;
	
		$result = $this->query($this->prepare($sql, $bind));
		$fetch = $this->driver()->fetch($result, 0);
		$this->driver()->free_query_result($result);

		Phpr::$events->fire_event('db:on_after_database_fetch', $sql, $fetch);
				
		return $fetch;
	}
	
	/**
	 * Fetches the first row of the SQL result.
	 * @param string $sql An SQL SELECT statement.
	 * @param array $bind Data to bind into SELECT placeholders.
	 * @return array
	 */
	public function fetch_row($sql, $bind = null) 
	{
		$result = Phpr::$events->fire_event(array('name' => 'db:on_before_database_fetch', 'type' => 'filter'), array(
			'sql' => $sql,
			'fetch' => null
		));
		
		extract($result);
		
		if (isset($fetch))
			return $fetch;
	
		$result = $this->query($this->prepare($sql, $bind));
		$fetch = $this->driver()->fetch($result);
		$this->driver()->free_query_result($result);

		Phpr::$events->fire_event('db:on_after_database_fetch', $sql, $fetch);
				
		return $fetch;
	}
	
	// Common methods
	// 

	public function execute($sql) 
	{
		if (Phpr::$trace_log)
			Phpr::$trace_log->write($sql, 'SQL');

		if (Phpr::$config && Phpr::$config->get('ENABLE_DEVELOPER_TOOLS') && Phpr::$events)
			Phpr::$events->fire_event('phpr:on_before_database_query', $sql);

		$result = $this->driver()->execute($sql);
		
		if (Phpr::$config && Phpr::$config->get('ENABLE_DEVELOPER_TOOLS') && Phpr::$events)
			Phpr::$events->fire_event('phpr:on_after_database_query', $sql, $result);
		
		return $result;
	}

	// Service methods
	// 
	
	public function driver() 
	{
		if ($this->_driver === null) 
		{
			if (Phpr::$config && Phpr::$config->get('DB_DRIVER'))
				$driver = Phpr::$config->get('DB_DRIVER') . '_Driver';
			else
				$driver = self::default_driver;

			$this->_driver = new $driver();
		}

		return $this->_driver;
	}

	protected function set_part($name, $value) 
	{
		if (!isset($this->parts[$name]))
			$this->parts[$name] = array();

		$this->parts[$name][] = $value;
	}

	protected function reset_part($name) 
	{
		$this->parts[$name] = array();
	}
	
	protected function get_limit() 
	{
		return (isset($this->parts['limit_count']) ? $this->parts['limit_count'] : 0);
	}    
}
