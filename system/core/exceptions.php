<?php

/**
 * PHPR Exception handling base class
 */

class Phpr_Exception extends Exception
{
}

class Phpr_SystemException extends Phpr_Exception
{
}

class Phpr_ApplicationException extends Phpr_Exception
{
}

class Phpr_DatabaseException extends Phpr_Exception
{
}

class Phpr_DeprecateException extends Phpr_Exception
{
}