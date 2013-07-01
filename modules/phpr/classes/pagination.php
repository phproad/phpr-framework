<?php namespace Phpr;

use Phpr;
use Phpr\ApplicationException;
use Db\ActiveRecord;

/**
 * PHPR Pagination Class
 */
class Pagination
{
	private $_current_page_index;
	private $_page_size;
	private $_row_count;
	private $_page_count;

	/**
	 * Creates a new Phpr_Pagination instance
	 * @param integer $page_size Specifies a page size.
	 * @return Phpr_Pagination
	 */
	public function __construct($page_size = 20)
	{
		$this->_current_page_index = 0;
		$this->_page_size = $page_size;
		$this->_row_count = 0;
		$this->_page_count = 1;
	}

	/**
	 * Applies the limitation rules to an active record object.
	 * @param Db\ActiveRecord $obj Specifies the Active Record object to limit.
	 */
	public function limit_active_record(ActiveRecord $obj)
	{
		$obj->limit($this->get_page_size(), $this->get_first_page_row_index());
	}

	/**
	 * Restores a pagination object from session or creates a new object.
	 * @param string $name Specifies a name of the object in the session.
	 * @param integer $page_size Specifies a page size.
	 */
	public static function from_session($name, $page_size = 20)
	{
		if (!Phpr::$session->has($name))
			Phpr::$session[$name] = new Pagination($page_size);

		return Phpr::$session[$name];
	}

	/**
	 * Evaluates the number of pages for the page size and row count specified in the object properties.
	 * @return integer
	 */
	private function evaluate_page_count($page_size, $row_count)
	{
		$result = ceil($row_count / $page_size);

		if ($result == 0)
			$result = 1;

		return $result;
	}

	/**
	 * Re-evaluates the current page index value.
	 * @param integer $CurrentPage Specifies the current page value
	 * @param integer $page_count Specifies the count value
	 * @return integer
	 */
	private function fix_current_page_index($current_page_index, $page_count)
	{
		$last_page_index = $page_count - 1;

		if ($current_page_index > $last_page_index)
			$current_page_index = $last_page_index;

		return $current_page_index;
	}

	/**
	 * Sets the index of the current page.
	 * @param integer $value Specifies the value to set.
	 */
	public function set_current_page_index($value)
	{
		$last_page_index = $this->_page_count - 1;

		if ($value < 0)
			$value = 0;

		if ($value > $last_page_index)
			$value = $last_page_index;

		$this->_current_page_index = $value;

		return $value;
	}

	/**
	 * Returns the index of the current page.
	 * @return integer
	 */
	public function get_current_page_index()
	{
		return $this->_current_page_index;
	}

	/**
	 * Sets the number of rows on a single page.
	 * @param integer $value Specifies the value to set.
	 */
	public function set_page_size($value)
	{
		if ($value <= 0)
			throw new ApplicationException("Page size is out of range");

		$this->_page_size = $value;

		$this->_page_count = $this->evaluate_page_count($value, $this->_row_count);
		$this->_current_page_index = $this->fix_current_page_index($this->_current_page_index, $this->_page_count);
	}

	/**
	 * Returns the number of rows on a single page.
	 * @return integer
	 */
	public function get_page_size()
	{
		return $this->_page_size;
	}

	/**
	 * Sets the total number of rows.
	 * @param integer $row_count Specifies the value to set.
	 */
	public function set_row_count($value)
	{
		if ($value < 0)
			throw new ApplicationException("Row count is out of range");

		$this->_page_count = $this->evaluate_page_count($this->_page_size, $value);
		$this->_current_page_index = $this->fix_current_page_index($this->_current_page_index, $this->_page_count);
		$this->_row_count = $value;
	}

	/**
	 * Returns the total number of rows.
	 * @return integer
	 */
	public function get_row_count()
	{
		return $this->_row_count;
	}

	/**
	 * Returns the index of the first row on the current page.
	 * @return integer
	 */
	public function get_first_page_row_index()
	{
		return $this->_page_size * $this->_current_page_index;
	}
	
	public function get_last_page_row_index()
	{
		$index = $this->get_first_page_row_index();
		$index += $this->_page_size-1;

		if ($index > $this->_row_count-1)
			$index = $this->_row_count-1;
			
		return $index;
	}

	/**
	 * Returns the total number of pages.
	 * @return integer
	 */
	public function get_page_count()
	{
		return $this->_page_count;
	}

	/**
	 * Returns the current query string abscent of the first parameter key
	 * @return string
	 */
	public static function get_query_string($page_param_name = 'page') 
	{
		parse_str(urldecode($_SERVER['QUERY_STRING']), $query);

		if(isset($query['q']))
			unset($query['q']);

		if(isset($query[$page_param_name]))
			unset($query[$page_param_name]);

		$query = http_build_query($query);

		$query = preg_replace('/%5B[0-9]+%5D/simU', '%5B%5D', $query);

		return $query;
	}

	/**
	 * @deprecated
	 */

	public function getCurrentPageIndex() { return $this->get_current_page_index(); }
	public function getPageCount() { return $this->get_page_count(); }
	public function getFirstPageRowIndex() { return $this->get_first_page_row_index(); }
	public function getLastPageRowIndex() { return $this->get_last_page_row_index(); }
	public function getRowCount() { return $this->get_row_count(); }
}
