<?php namespace Phpr;

use Phpr\SystemException;

/**
 * Base class for extension validation
 * @see Phpr\Extension
 */

class Validate_Extension extends Extension
{

	public function _execute_validation($method, $name, $value)
	{
		if (method_exists($this, $method))
			return $this->$method($name, $value);

		throw new SystemException('Validation method '.$method.' not found in '.get_class($this));
	}

}
