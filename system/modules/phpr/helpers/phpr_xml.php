<?php

/**
 * PHPR XML helper
 *
 * This class contains functions for working with XML
 */
class Phpr_Xml
{
    public static function create_dom_element($document, $parent, $name, $value = null, $cdata = false)
    {
        $cdata_value = $value;
        if ($cdata)
            $value = null;

        if ($document instanceof SimpleXMLElement)
        {
            $element = $parent->addChild($name, $value);
        }
        else
        {
            $element = $document->createElement($name, $value);
            $parent->appendChild($element);
        }

        if ($cdata)
            return self::create_cdata($document, $element, $cdata_value);
        
        return $element;
    }

    public static function create_cdata($document, $parent, $value)
    {
        if ($document instanceof SimpleXMLElement)
        {
            $parent = dom_import_simplexml($parent);
            $document = $parent->ownerDocument;
        }
            
        $element = $document->createCDATASection($value);
        $parent->appendChild($element);
        
        return $element;
    }

    public static function to_plain_array($document, $use_parent_keys = false)
    {
        $result = array();
        self::node_to_array($document, $result, '', $use_parent_keys);
        
        return $result;
    }
    
    protected static function node_to_array($node, &$result, $parent_path, $use_parent_keys)
    {
        foreach ($node->childNodes as $child)
        {
            if (!$use_parent_keys)
            {
                if (!($child instanceof DOMText))
                    $node_path = $orig_path = $parent_path.'_'.$child->nodeName;
                else
                    $node_path = $orig_path = $parent_path;
            } 
            else
            {
                if (!($child instanceof DOMText))
                    $node_path = $orig_path = $child->nodeName;
                else
                    $node_path = $orig_path = $child->parentNode->nodeName;
            }

            $counter = 2;
            while (array_key_exists($node_path, $result))
            {
                $node_path = $orig_path.'_'.$counter;
                $counter++;
            }
            
            if (substr($node_path, 0, 1) == '_')
                $node_path = substr($node_path, 1);
            
            if ($child instanceof DOMCdataSection)
                $result[$node_path] = $child->wholeText;
            elseif ($child instanceof DOMText)
            {
                if (!($child->parentNode->childNodes->length > 1))
                    $result[$node_path] = $child->wholeText;
            }
            else
                self::node_to_array($child, $result, $node_path, $use_parent_keys);
        }
    }
}

