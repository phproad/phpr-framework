<?php
/**
 * PHPR Flash Class
 *
 * The flash provides a way to pass temporary objects between actions.
 *
 * The instance of this class is available in the Session object: Phpr::$session->flash
 *
 * @see Phpr
 */
class Phpr_Flash implements ArrayAccess, IteratorAggregate, Countable
{
	const flash_key = '__flash';

	public $flash = array();

	/**
	 * Creates a new Phpr_Flash instance
	 */
	public function __construct()
	{
		if (!Phpr::$session->has(self::flash_key))
			return;

		$this->flash = Phpr::$session->get(self::flash_key);
		$this->now();
	}

	/**
	 * Removes an object with a specified key or erases the flash data.
	 * @param string $key Specifies a key to remove, optional
	 */
	public function discard($key = null)
	{
		if ($key === null)
			$this->flash = array();
		else
			unset($this->flash[$key]);
	}

	/**
	 * Stores the flash data to the session.
	 * @param string $key Specifies a key to store, optional
	 */
	public function store($key = null)
	{
		if ($key === null)
			Phpr::$session->set(self::flash_key, $this->flash);
		else
			Phpr::$session->set(self::flash_key, array($key=>$this->flash[$key]));
	}

	/*
	 * Removes the flash data from the session.
	 */
	public function now()
	{
		Phpr::$session->remove(self::flash_key);
	}

	/**
	* Iterator implementation
	*/
	
	function offsetExists($offset)
	{
		return isset($this->flash[$offset]);
	}
	
	function offsetGet($offset)
	{
		if ($this->offsetExists($offset))
			return $this->flash[$offset];
		else
			return (false);
	}
	
	function offsetSet($offset, $value)
	{
		if ($offset)
			$this->flash[$offset] = $value;
		else
			$this->glash[] = $value;
		
		$this->store();
	}
	
	function offsetUnset($offset)
	{
		unset($this->flash[$offset]);
		$this->store();
	}
	
	function getIterator()
	{
		return new ArrayIterator($this->flash);
	}

	/**
	* Returns a number of flash items
	* @return integer
	*/
	public function count()
	{
		return count($this->flash);
	}
}

