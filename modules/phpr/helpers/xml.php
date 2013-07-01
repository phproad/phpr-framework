<?php

/**
 * PHPR XML helper
 *
 * This class contains functions for working with XML
 */

class Phpr_Xml
{
	public static function create_node($document, $parent=null, $field, $value = null, $use_cdata = false)
	{
		$cdata_value = $value;
		if ($use_cdata)
			$value = null;

		if ($parent === null)
			$parent = $document;

		if ($document instanceof SimpleXMLElement)
		{
			$element = $parent->addChild($field, $value);
		}
		else
		{
			$element = $document->createElement($field, $value);
			$parent->appendChild($element);
		}

		if ($use_cdata)
			return self::create_cdata($document, $element, $cdata_value);
		
		return $element;
	}

	// Wrap a node's value with a CDATA block
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
	
	// Plain array is a single dimension array
	public static function from_plain_array($params = array(), $root_node='data', $use_cdata = false)
	{
		if (!is_array($params))
			return null;
				
		$xml_string = '<'.$root_node.'></'.$root_node.'>';
		$document = new SimpleXMLElement($xml_string);

		if (!is_array($params))
			return $document;

		foreach ($params as $field=>$value)
		{
			self::create_node($document, null, $field, $value, $use_cdata);
		}

		return Phpr_Xml::beautify_xml($document);
	}

	// Plain array is a single dimension array
	public static function to_plain_array($xml_string, $use_parent_keys = false)
	{
		$document = new DOMDocument();
		$document->loadXML($xml_string);

		$result = array();
		self::node_to_array($document, $result, '', $use_parent_keys);
		return $result;
	}


	// Multi dimension array to xml
	public static function from_array($params = array(), $root_node='data', $use_cdata = false, &$document = null) 
	{
		if (!is_array($params))
			return null;

		if ($document === null) {
			$xml_string = '<'.$root_node.'></'.$root_node.'>';
			$document = new SimpleXMLElement($xml_string);
		}

		foreach ($params as $key => $value) {
			if (!strlen($key))
				continue;

			if (is_array($value)) {
				if (!is_numeric($key)){
					$subnode = $document->addChild($key);
					self::from_array($value, $root_node, $use_cdata, $subnode);
				}
				else {
					self::from_array($value, $root_node, $use_cdata, $document);
				}
			}
			else {
				if (is_integer($key))
					$key = db_number.$key;

				$document->addChild($key, $value);
			}
		}

		return Phpr_Xml::beautify_xml($document);
	}

	public static function to_array($xml_data) 
	{
		$array = (array)simplexml_load_string($xml_data);
		$array = json_decode(json_encode($array),1);
		return $array;
	}

	// Takes an XML object and makes it purrty
	public static function beautify_xml($xml) 
	{       
		$level = 4;  
		$indent = 0;
		$pretty = array();  

		if ($xml instanceof SimpleXMLElement)
			$xml = $xml->asXML();

		if (!is_string($xml))
			return null;

		$xml = explode("\n", preg_replace('/>\s*</', ">\n<", $xml));  

		if (count($xml) && preg_match('/^<\?\s*xml/', $xml[0])) 
			$pretty[] = array_shift($xml);  

		foreach ($xml as $el) 
		{
			if (preg_match('/^<([\w])+[^>\/]*>$/U', $el)) 
			{  
				$pretty[] = str_repeat(' ', $indent) . $el;  
				$indent += $level;  
			} 
			else 
			{  
				if (preg_match('/^<\/.+>$/', $el)) 
					$indent -= $level;

				if ($indent < 0) 
					$indent += $level;  

				$pretty[] = str_repeat(' ', $indent) . $el;  
			}  
		}

		$xml_string = implode("\n", $pretty);     
		return $xml_string;  
	}    

	// Internals
	// 

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
			{
				$result[$node_path] = $child->wholeText;
			}
			else if ($child instanceof DOMText)
			{
				if (!($child->parentNode->childNodes->length > 1))
					$result[$node_path] = $child->wholeText;
			}
			else
				self::node_to_array($child, $result, $node_path, $use_parent_keys);
		}
	}

}
