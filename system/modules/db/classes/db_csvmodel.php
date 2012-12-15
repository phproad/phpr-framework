<?php

/*
 * Db_CsvModel extension
 * - Import / Export functions
 */

class Db_CsvModel extends Phpr_Extension_Base
{
    private $_model_class;
    private $_model;
    private $_file_name = 'export.csv';
    private $_columns = null;

    public function __construct($model, $proxy_model_class = null)
    {
        $this->_model_class = $proxy_model_class ? $proxy_model_class : get_class($model);
        $this->_model = $model;
        $this->_file_name = (isset($model->csv_file_name)) ? $model->csv_file_name : $this->_file_name;
        $this->_columns = (isset($model->csv_columns)) ? $model->csv_columns : null;
    }

    // First parameter cannot be an array otherwise it messes up Extensibiilty
    public function csv_import_record($columns_override = null, $row_data)
    {
        $columns = $this->csv_get_columns();

        $save_data = array();
        $counter = 0;

        foreach ($columns as $db_name)
        {
            if (!isset($row_data[$counter]))
                continue;

            $db_name = $this->process_relation_field($db_name);
            $save_data[$db_name] = $row_data[$counter];
            $counter++;
        }

        $this->_model->save($save_data);
    }

    public function csv_export($iwork = false, $columns_override = null)
    {
        set_time_limit(3600);

        header("Expires: 0");
        header("Content-Type: Content-type: text/csv");
        header("Content-Description: File Transfer");
        header("Cache-control: private");
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: pre-check=0, post-check=0, max-age=0');
        header("Content-disposition: attachment; filename=".$this->_file_name);

        $model_class = $this->_model_class;
        $model_columns = $this->_model->get_column_definitions();
        $columns = $this->csv_get_columns();

        if ($columns_override !== null && is_array($columns_override)) 
        {
            $columns_updated = array();
            foreach ($columns_override as $column_name)
            {
                if (!array_key_exists($column_name, $columns))
                    throw new Phpr_ApplicationException(sprintf('Column %s not found in the product column set.', $column_name));
                    
                $columns_updated[$column_name] = $columns[$column_name];
            }
            
            $columns = $columns_updated;
        }

        $header = array();
        foreach ($columns as $db_name)
        {
            if (!isset($model_columns[$db_name]))
                continue;
            
            $column_obj = $model_columns[$db_name];
            $header[] = strlen($column_obj->listTitle) ? $column_obj->listTitle : $column_obj->displayName;
        }

        $separator = $iwork ? ',' : ';';

        Phpr_Files::output_csv_row($header, $separator, false);

        $strings = new $model_class(null, array('no_column_init'=>true, 'no_validation'=>true));
        $query = $strings->build_sql();

        $list_data = Db_Helper::query_array($query);

        foreach ($list_data as $row_data)
        {
            $row = $this->csv_format_row($strings, $row_data, $columns);
            Phpr_Files::output_csv_row($row, $separator, false);
        }
    }

    protected function csv_format_row($string, &$row_data, $columns)
    {
        $string->fill($row_data);

        $row = array();

        foreach ($columns as $db_name)
        {
            // TODO: Use meaningful name with lookup instead of ID
            $field_name = $this->process_relation_field($db_name);
            if ($field_name != $db_name)
                $row[] = $string->$field_name; 
            else
                $row[] = $string->displayField($field_name, 'list');
        }

        return $row;
    }

    protected function csv_get_columns()
    {
        if ($this->_columns)
            return $this->_columns;

        $columns = array();
        
        $model_columns = $this->_model->get_column_definitions();
        foreach ($model_columns as $key => $obj)
        {
            $columns[] = $key;
        }

        return $this->_columns = $columns;
    }

    // TODO: This is not scalable, lookup relations instead
    protected function process_relation_field($db_name)
    {
        if ($db_name == "page" || $db_name == "parent")
            return $db_name.'_id';

        return $db_name;
    }

}