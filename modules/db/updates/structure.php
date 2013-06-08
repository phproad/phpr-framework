<?php

$table = Db_Structure::table('db_deferred_bindings');
	$table->primary_key('id');
	$table->column('master_class_name', db_varchar, 100);
	$table->column('detail_class_name', db_varchar, 100);
	$table->column('master_relation_name', db_varchar, 100);
	$table->column('is_bind', db_number);
	$table->column('detail_key_value', db_number);
	$table->column('created_at', db_datetime);
	$table->column('session_key', db_varchar);

$table = Db_Structure::table('db_files');
	$table->primary_key('id');
	$table->column('name', db_varchar)->index();
	$table->column('title', db_varchar);
	$table->column('description', db_varchar);
	$table->column('disk_name', db_varchar);
	$table->column('mime_type', db_varchar);
	$table->column('size', db_number);
	$table->column('field', db_varchar, 100)->index();
	$table->column('master_object_class', db_varchar);
	$table->column('master_object_id', db_number);
	$table->column('is_public', db_bool);
	$table->column('sort_order', db_number);
	$table->column('created_at', db_datetime);
	$table->column('created_user_id', db_number)->index();
	$table->add_key('master_index', array('master_object_class', 'master_object_id'));

$table = Db_Structure::table('db_record_locks');
	$table->primary_key('id');
	$table->column('record_id', db_number);
	$table->column('record_class', db_varchar, 100);
	$table->column('non_db_hash', db_varchar, 100)->index();
	$table->column('last_ping', db_datetime);
	$table->column('created_at', db_datetime);
	$table->column('created_user_id', db_number);
	$table->add_key('record_index', array('record_id', 'record_class'));

$table = Db_Structure::table('db_session_data');
	$table->primary_key('id');
	$table->column('client_ip', db_varchar, 15);
	$table->column('session_id', db_varchar, 100);
	$table->column('session_data', db_text);
	$table->column('created_at', db_datetime);
	$table->add_key('session_id', array('session_id', 'client_ip'));

$table = Db_Structure::table('db_saved_tickets');
	$table->primary_key('ticket_id', db_varchar, 50);
	$table->column('ticket_data', db_text);
	$table->column('created_at', db_datetime);

$table = Db_Structure::table('db_model_logs');
	$table->primary_key('id');
	$table->column('master_object_class', db_varchar);
	$table->column('master_object_id', db_number)->index();
	$table->column('type', 'char', 20)->index();
	$table->column('param_data', db_text);
	$table->column('record_datetime', db_datetime);