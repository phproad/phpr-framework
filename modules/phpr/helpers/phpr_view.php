<?php

/**
 * PHPR View helper
 *
 * This class contains functions for working with views.
 */
class Phpr_View
{
	private static $block_stack = array();
	private static $blocks = array();

	/**
	 * Returns the JavaScript inclusion tag for the PHPR script file.
	 * The PHPR java script files are situated in the PHPR javascript folder.
	 * By default this function creates a link to the application bootstrap file 
	 * that outputs the requested script. You may speed up the resource request
	 * by providing a direct URL to the PHPR javascript folder in the 
	 * configuration file: 
	 * $CONFIG['JAVASCRIPT_URL'] = 'www.my_company_com/phpr/javascript';
	 * @param mixed $name Specifies a name of the script file to include.
	 * Use the 'defaults' name to include the minimal required PHPR script set.
	 * If this parameter is omitted the 'defaults' value is used.
	 * Also you may specify a list of script names as array.
	 * @return string
	 */
	public static function include_javascript($name = 'defaults', $version_mark = null)
	{
		if (!is_array($name))
			$name = array($name);

		$javascript_url = Phpr::$config->get('JAVASCRIPT_URL', 'framework/assets/scripts/js');

		$result = null;
		foreach ($name as $script_name)
		{
			$script_name = urlencode($script_name);

			if ($script_name == 'defaults') {
				foreach (Phpr_Response::$default_js_scripts as $default_script) {
					$result .= '<script type="text/javascript" src="'.$javascript_url.'/'.$default_script.'?'.$version_mark.'"></script>'.PHP_EOL;
				}
			} else {
				$result .= '<script type="text/javascript" src="'.$javascript_url.'/'.$script_name.'"></script>'.PHP_EOL;
			}
		}

		return $result;
	}

	/**
	 * Begins the layout block.
	 * @param string $name Specifies the block name.
	 */
	public static function begin_block($name) 
	{
		array_push(self::$block_stack, $name);
		ob_start();
	}
	
	
	/**
	 * Closes the layout block.
	 * @param boolean $append Indicates that the new content should be appended to the existing block content.
	 */
	public static function end_block($append = false) 
	{
		if (!count(self::$block_stack))
			throw new Phpr_SystemException("Invalid layout blocks nesting");

		$name = array_pop(self::$block_stack);
		$contents = ob_get_clean();

		if (!isset(self::$blocks[$name]))
			self::$blocks[$name] = $contents;
		else 
			if ($append)
				self::$blocks[$name] .= $contents;

		if (!count(self::$block_stack) && (ob_get_length() > 0))
			ob_end_clean();
	}
	 

	/**
	 * Sets a content of the layout block.
	 * @param string $name Specifies the block name.
	 * @param string $content Specifies the block content.
	 * 
	 */
	public static function set_block($name, $content)
	{
		self::begin_block($name);
		echo $content;
		self::end_block();
	}

	/**
	 * Appends a content of the layout block.
	 * @param string $name Specifies the block name.
	 * @param string $content Specifies the block content.
	 * 
	 */
	public static function append_block($name, $content)
	{
		if (!isset(self::$blocks[$name]))
			self::$blocks[$name] = null;

		self::$blocks[$name] .= $content;
	}

	/**
	 * Returns the layout block contents and deletes the block from memory.
	 * @param string $name Specifies the block name.
	 * @param string $default Specifies a default block value to use if the block requested is not exists.
	 * @return string
	 */
	public static function block($name, $default = null)
	{
		$result = self::get_block($name, $default);

		unset(self::$blocks[$name]);

		return $result;
	}

	/**
	 * Returns the layout block contents but not deletes the block from memory.
	 * @param string $name Specifies the block name.
	 * @param string $default Specifies a default block value to use if the block requested is not exists.
	 * @return string
	 */
	public static function get_block($name, $default = null)
	{
		if (!isset(self::$blocks[$name]))
			return  $default;

		$result = self::$blocks[$name];

		return $result;
	}

	/**
	 * Returns an error message.
	 * @param string $message Specifies the error message. If this parameter is omitted, the common 
	 * validation message will be returned.
	 * @return string
	 */
	public static function show_error($message = null)
	{
		if ($message === null)
		{
			$controller = self::get_controller();
			if (is_null($controller))
				return null;

			$message = Phpr_Html::encode($controller->validation->error_message);
		}

		if (strlen($message))
			return $message;
	}

	/**
	 * Returns a current controller
	 * @return Phpr_Controller_Base
	 */
	private static function get_controller()
	{
		if (Phpr_Controller::$current !== null)
			return Phpr_Controller::$current;
	}

	/**
	 * @deprecated
	 */ 

	public static function includeJavaScript($name = 'defaults', $version_mark = null) { Phpr::$deprecate->set_function('includeJavaScript', 'include_javascript'); return self::include_javascript($name, $version_mark); }
	public static function setBlock($name, $content) { Phpr::$deprecate->set_function('setBlock', 'set_block'); return self::set_block($name, $content); }
	public static function appendBlock($name, $content) { Phpr::$deprecate->set_function('appendBlock', 'append_block'); return self::append_block($name, $content); }
	public static function getBlock($name, $default = null) { Phpr::$deprecate->set_function('getBlock', 'get_block'); return self::get_block($name, $default); }
	public static function beginBlock($name) { Phpr::$deprecate->set_function('beginBlock', 'begin_block'); return self::begin_block($name); }
	public static function endBlock($append = false) { Phpr::$deprecate->set_function('endBlock', 'end_block'); return self::end_block($append); }
	public static function showError($message = null) { Phpr::$deprecate->set_function('showError', 'show_error'); return self::show_error($message); }
}