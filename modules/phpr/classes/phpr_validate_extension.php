<?php

/**
 * Base class for extension validation
 * @see Phpr_Extension
 */

class Phpr_Validate_Extension extends Phpr_Extension
{

	public function _execute_validation($method, $name, $value)
	{
		if (method_exists($this, $method))
			return $this->$method($name, $value);

		throw new Phpr_SystemException('Validation method '.$method.' not found in '.get_class($this));
	}

}
