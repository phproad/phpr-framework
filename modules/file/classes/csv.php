<?php

class File_Csv
{
	public static function determine_csv_delimeter($path)
	{
		$delimiters = array(';', ',', "\t");
		$max_count = 0;
		$detected = null;

		foreach ($delimiters as $index=>$delimiter)
		{
			$handle = fopen($path, "r");
			$data = fgetcsv($handle, 3000, $delimiter);

			if ($data)
			{
				$count = count($data);
				if ($max_count < $count)
				{
					$max_count = $count;
					$detected = $delimiter;
				}
			}
				
			fclose($handle);
		}

		return $detected;
	}
	
	public static function output_csv_row($row, $separator = ';', $return_data = false)
	{
		$str='';
		$quot = '"';
		$fd = $separator;

		foreach ($row as $cell)
		{
			$cell = str_replace($quot, $quot.$quot, $cell);
				$str .= $quot.$cell.$quot.$fd;
		}

		if (!$return_data)
			print substr($str, 0, -1)."\n";
		else 
			return substr($str, 0, -1)."\n";
	}
	
	public static function convert_csv_encoding(&$data)
	{
		$data_found = false;
		foreach ($data as &$value)
		{
			$value = trim(mb_convert_encoding($value, 'UTF-8', Phpr::$config->get('FILESYSTEM_CODEPAGE')));
			if (strlen($value))
				$data_found = true;
		}
		
		return $data_found;
	}
	
	public static function get_csv_field(&$row, $index, $default = null)
	{
		if (array_key_exists($index, $row))
			return $row[$index];

		return $default;
	}
	
	public static function csv_row_is_empty(&$row)
	{
		foreach ($row as $column_data)
		{
			if (strlen(trim($column_data)))
				return false;
		}

		return true;
	}
}