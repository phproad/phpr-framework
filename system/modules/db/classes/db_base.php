<?php

class Db_Base extends Phpr_Validate_Extension
{
    /**
     * Wraps supplied value(s) with quotes.
     * @param mixed $value Single or array of values to wrap
     * @return string
     */
    public function quote($value) 
    {
        if (is_array($value)) 
        {
            foreach ($value as &$item)
            {
                $item = $this->quote($item);
            }

            return implode(', ', $value);
        }
        
        if ($value instanceof Phpr_DateTime)
            $value = $value->toSqlDateTime();
            
        if (!strlen($value))
            return 'null';

        $result = str_replace("\\", '\\\\', $value);
        $result = str_replace("'", "\'", $result);
        
        return "'" . $result . "'";
    }

    /**
     * Passes parameters to an SQL query. Parameters use a colon character 
     * before the name. Example :user_id
     * @param string $sql Query with parameters
     * @param array $params Key and values to parse in to query
     * @return string
     */    
    public function prepare($sql, $params = null) 
    {
        // Attempt to build parameters from method arguements
        if (!isset($params) || !is_array($params)) 
        {
            $params = func_get_args();
            array_shift($params);
        }

        // Capture query and parameters
        $split_sql = preg_split(
            '/(\?|\:[a-z_0-9]+)/i',
            $sql,
            -1,
            PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE
        );

        // Parse in parameters
        $index = 0;
        $sql = array();

        foreach ($split_sql as $value) 
        {
            if ($value[0] == ':') 
            {
                // Key/Value parse
                if (array_key_exists(substr($value, 1), $params)) 
                {
                    $value = $params[substr($value, 1)];
                    $value = $this->quote($value);
                }
            } 
            elseif ($value[0] == '?') 
            {
                // Iteration parse
                if (array_key_exists($index, $params)) 
                {
                    $value = $params[$index];
                    $value = $this->quote($value);
                    $index++;
                }
            }
            
            $sql[] = $value;
        }

        return implode('', $sql);
    }
}