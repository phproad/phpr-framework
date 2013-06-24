<?php

class Db_Data_Filter_Switcher
{
	public function apply_to_model($model, $enabled, $context = null)
	{
		return $model;
	}
	
	public function as_string($enabled, $context = null)
	{
		return null;
	}
}
