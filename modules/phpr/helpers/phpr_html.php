<?php

/**
 * PHPR HTML helper
 *
 * This class contains functions for working with HTML
 */

class Phpr_Html
{

    /**
     * Formats a list of attributes to use in a HTML tag.
     * @param array $attributes Specifies a list of attributes.
     * @param array $defaults Specifies a list of default attribute values.
     * @return string
     */
    public static function format_attributes($attributes, $defaults = array())
    {
        foreach ($defaults as $attr_name=>$attr_value)
        {
            if (!isset($attributes[$attr_name]))
                $attributes[$attr_name] = $defaults[$attr_name];
        }

        $result = array();
        foreach ($attributes as $attr_name=>$attr_value)
        {
            if (strlen($attr_value))
                $result[] = $attr_name.'="'.$attr_value.'"';
        }

        return implode(" ", $result);
    }

}