<?php

class File_Csv_Import extends Db_ActiveRecord
{
	public $table_name = 'db_files';

	public $has_many = array(
		'csv_file'=>array('class_name'=>'Db_File', 'foreign_key'=>'id', 'conditions'=>"master_object_class='File_Csv_Import'", 'order'=>'id', 'delete'=>true)
	);

	public function define_columns($context = null)
	{
		$this->define_multi_relation_column('csv_file', 'csv_file', 'CSV file ', '@name')->invisible();
	}

	public function define_form_fields($context = null)
	{
		$this->add_form_field('csv_file', 'left')
			->display_as(frm_file_attachments)
			->display_files_as('single_file')
			->add_document_label('Upload a file')
			->file_download_base_url(url('admin/files/get/'))
			->no_attachments_label('');
	}

}
