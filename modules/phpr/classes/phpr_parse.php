<?php

/**
 * PHPR Parse class
 *
 * This helpful class allows text to have data parsed in.
 */
class Phpr_Parse
{
    const key_open = '{';
    const key_close = '}';

    protected $options;

    // Instance
    // 

    public function __construct($params = array('encode_html' => false)) 
    {
        if (isset($params['encode_html']) && $params['encode_html'])
            $this->set_html_encode();
    }

    public static function create($params = array('encode_html' => false)) 
    {
        return new self($params);
    }

    // Options
    // 

    public function set_html_encode($use_encoding = true)
    {
        $this->options['encode_html'] = $use_encoding;
        return $this;
    }

    // Services
    // 

    public function parse_file($file_path, $data)
    {
        $string = file_get_contents($file_path);
        return $this->process_string($string, $data);
    }

    public function parse_text($string, $data)
    {
        return $this->process_string($string, $data);
    }    

    // Internal string parse
    private function process_string($string, $data)
    {
        if (!is_string($string) || !strlen(trim($string)))
            return false;

        foreach ($data as $key => $value)
        {
            if (is_array($value))
                $string = $this->process_loop($key, $value, $string);
            else
                $string = $this->process_key($key, $value, $string);
        }

        return $string;
    }

    // Process a single key
    private function process_key($key, $value, $string)
    {
        if (isset($this->options['encode_html']) && $this->options['encode_html'])
            $value = Phpr_Html::encode($value);

        $return_string = str_replace(self::key_open.$key.self::key_close, $value, $string);

        return $return_string;
    }

    // Search for open/close keys and process them in a nested fashion
    private function process_loop($key, $data, $string)
    {
        $return_string = '';
        $match = $this->process_loop_regex($string, $key);

        if (!$match)
            return $string;

        foreach ($data as $row)
        {
            $matched_text = $match[1];
            foreach ($row as $key => $value)
            {
                if (is_array($value))
                    $matched_text = $this->process_loop($key, $value, $matched_text);
                else
                    $matched_text = $this->process_key($key, $value, $matched_text);
            }

            $return_string .= $matched_text;
        }

        return str_replace($match[0], $return_string, $string);
    }

    private function process_loop_regex($string, $key)
    {
        $open = preg_quote(self::key_open);
        $close = preg_quote(self::key_close);

        $regex = '|';
        $regex .= $open.$key.$close; // Open
        $regex .= '(.+?)'; // Content
        $regex .= $open.'/'.$key.$close; // Close
        $regex .='|s';

        preg_match($regex, $string, $match);
        return ($match) ? $match : false;
    }

}
