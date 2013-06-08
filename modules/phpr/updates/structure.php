<?php

$table = Db_Structure::table('phpr_cron_table');
	$table->primary_key('record_code', db_varchar, 50);
	$table->column('updated_at', db_datetime);

$table = Db_Structure::table('phpr_cron_jobs');
	$table->primary_key('id');
	$table->column('handler_name', db_varchar, 100);
	$table->column('param_data', db_text);
	$table->column('created_at', db_datetime);

$table = Db_Structure::table('phpr_user_params');
	$table->primary_key('user_id')->set_default('0');
	$table->primary_key('name', db_varchar, 100);
	$table->column('value', db_text);
	
$table = Db_Structure::table('phpr_module_params');
	$table->primary_key('module_id', db_varchar, 30);
	$table->primary_key('name', db_varchar, 100);
	$table->column('value', db_text);

$table = Db_Structure::table('phpr_trace_log');
	$table->primary_key('id');
	$table->column('log', db_varchar)->index();
	$table->column('message', db_text);
	$table->column('details', 'mediumtext');
	$table->column('record_datetime', db_datetime);

$table = Db_Structure::table('phpr_generic_binds');
	$table->primary_key('id');
	$table->column('primary_id', db_number, 11)->index();
	$table->column('secondary_id', db_number, 11)->index();
	$table->column('field_name', db_varchar, 100)->index();
	$table->column('class_name', db_varchar, 100)->index();