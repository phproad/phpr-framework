<?php

define('frm_text', 'text');
define('frm_password', 'password');
define('frm_dropdown', 'dropdown');
define('frm_autocomplete', 'autocomplete');
define('frm_radio', 'radio');
define('frm_checkbox', 'checkbox');
define('frm_checkboxlist', 'checkboxlist');

define('frm_textarea', 'textarea');
define('frm_html', 'html');
define('frm_code_editor', 'code_editor');

define('frm_datetime', 'datetime');
define('frm_date', 'date');
define('frm_time', 'time');

define('frm_onoffswitcher', 'on_off_switcher');
define('frm_record_finder', 'recordfinder');

define('frm_file_attachments', 'file_attachments');
define('frm_widget', 'widget');

define('frm_tags', 'tags');
define('frm_dropdown_create', 'dropdown_create');

class Db_Form_Field_Definition extends Db_Form_Element
{
	public $db_name;
	public $form_side;
	public $display_mode = null;
	public $comment;
	public $comment_position;
	public $comment_html = false;
	public $preview_comment = null;
	public $size;
	public $empty_option = null;
	public $no_options = null;
	public $options_method = null;
	public $option_state_method = null;
	public $reference_filter = null;
	public $reference_sort = null;
	public $reference_description_field = null;
	public $checkbox_on_state = 1;
	public $add_attachment_label = 'Add file';
	public $no_attachments_label = 'No files';
	public $image_thumb_size = 100;
	public $preview_no_relation = false;
	public $relation_preview_no_options = 'No options were assigned';
	public $options_html_encode = true;
	public $disabled = false;
	public $textareaServices = null;
	public $css_classes = null;
	public $css_class_name = null;
	public $display_files_as = 'file_list';
	public $language = 'html';
	public $no_label = false;
	public $hide_content = false;
	public $file_download_base_url = null;
	public $html_plugins = "paste,searchreplace,advlink,inlinepopups";
	public $html_buttons1 = "cut,copy,paste,pastetext,pasteword,separator,undo,redo,separator,link,unlink,separator,image,separator,bold,italic,underline,separator,formatselect,separator,bullist,numlist,separator,code";
	public $html_buttons2 = null;
	public $html_buttons3 = null;
	public $html_content_css = null;
	public $htmlBlockFormats = 'p,address,pre,h1,h2,h3,h4,h5,h6';
	public $htmlCustomStyles = null;
	public $htmlFontSizes = null;
	public $htmlFontColors = null;
	public $htmlBackgroundColors = null;
	public $htmlAllowMoreColors = true;
	public $html_valid_elements = null;
	public $html_valid_child_elements = null;
	public $nl2br = false;
	public $title_partial = null;
	public $form_element_partial = null;
	public $preview_help = null;
	public $comment_tooltip = null;
	public $html_full_width = false;
	public $render_options = array();
	public $preview_link = null;

	public $save_callback = null;

	public $placeholder = array();

	private $_model;
	private $_column_definition;

	public function __construct($model, $db_name, $side)
	{
		$model_class = get_class($model);

		$column_definitions = $model->get_column_definitions();
		if (!array_key_exists($db_name, $column_definitions))
			throw new Phpr_SystemException("Column ".$model_class.".".$db_name." cannot be added to a form because it is not defined with define_column method call.");

		$this->_column_definition = $column_definitions[$db_name];

		if ($this->_column_definition->is_reference && !in_array($this->_column_definition->reference_type, array('belongs_to', 'has_many', 'has_and_belongs_to_many')))
			throw new Phpr_SystemException("Error adding form field ".$db_name.". Form fields can only be defined for the belongs_to, has_and_belongs_to_many and has_many relations. ".$this->_column_definition->reference_type." associations are not supported.");

		$this->db_name = $db_name;
		$this->form_side = $side;
		$this->_model = $model;

		$phpr_url = Phpr::$config->get('PHPR_URL', 'phpr');
		if (!$this->html_content_css)
			$this->html_content_css = '/'.$phpr_url.'/assets/css/htmlcontent.css';
	}

	/**
	 * Sets a side of the field on a form.
	 * @param $side Specifies a side. Possible values: left, right, full
	 */
	public function side($side = 'full')
	{
		$this->form_side = $side;
		return $this;
	}

	/**
	 * Specifies a field control rendering mode. Supported modes are:
	 * - frm_text - creates a text field. Default for varchar column types.
	 * - frm_textarea - creates a textarea control. Default for text column types.
	 * - frm_html - creates an HTML WYSIWYG control.
	 * - frm_dropdown - creates a drop-down list. Default for reference-based columns.
	 * - frm_autocomplete - creates an autocomplete field.
	 * - frm_radio - creates a set of radio buttons.
	 * - frm_checkbox - creates a single checkbox.
	 * @param string $display_mode Specifies a render mode as described above
	 * @param array $options A list of render mode specific options.
	 */
	public function display_as($display_mode, $options = array())
	{
		$this->display_mode = $display_mode;
		$this->render_options = $options;
		return $this;
	}

	/**
	 * Specifies a language for code editor fields syntax highlighting.
	 * @param string $language Specifies a language name. Examples: html, css, php, perl, ruby, sql, xlml
	 */
	public function language($language)
	{
		$this->language = $language;
		return $this;
	}

	/**
	 * Specifies a callback function name to be called when user clicks Save button on the text editor toolbar
	 * @param string $callbacl A JavaScript function name
	 */
	public function save_callback($callback)
	{
		$this->save_callback = $callback;
		return $this;
	}

	/**
	 * Adds a text comment above or below the field.
	 * @param string $text Specifies a comment text.
	 * @param string $position Specifies a comment position.
	 * @param bool $comment_html Set to true if you use HTML formatting in the comment
	 * Supported values are 'below' and 'above'
	 */
	public function comment($text, $position = 'below', $comment_html = false)
	{
		$this->comment = $text;
		$this->comment_position = $position;
		$this->comment_html = $comment_html;

		return $this;
	}

	/**
	 * Alternative comment text for the preview mode
	 */
	public function preview_comment($text)
	{
		$this->preview_comment = $text;
		return $this;
	}

	/**
	 * Sets a vertical size for textareas
	 * @param string $size Specifies a size selector. Supported values are 'tiny', 'small', 'large'.
	 */
	public function size($size)
	{
		$this->size = $size;
		return $this;
	}

	/**
	 * Sets a textarea text services. Currently supports 'auto_close_brackets'
	 * @param string $services Specifies a list of services, separated with comma
	 */
	public function text_services($services)
	{
		$services = explode(',', $services);
		foreach ($services as &$service)
			$service = "'".trim($service)."'";

		$this->textareaServices = implode(',', $services);

		return $this;
	}

	/**
	 * Specifies CSS classes to apply to the field container element
	 */
	public function css_classes($classes)
	{
		$this->css_classes = $classes;
		return $this;
	}

	/**
	 * Specifies CSS class name to apply to the field LI element
	 */
	public function css_class_name($className)
	{
		$this->css_class_name = $className;
		return $this;
	}

	/**
	 * Specifies a select element option text to display before other options.
	 * Use this method for options like "<please select color>"
	 */
	public function empty_option($text)
	{
		$this->empty_option = $text;
		return $this;
	}

	/**
	 * Specifies a text to display in multi-relation fields if there are no options available
	 */
	public function no_options($text)
	{
		$this->no_options = $text;
		return $this;
	}

	/*
	 * Specifies a method name in the model class, responsible for returning
	 * a list of options for drop-down and radio fields. The method should be defined like this:
	 * public method method_name($db_name, $current_key_value = -1);
	 * The parameter passed to the method is a database field name
	 * The method must return an array of record values: array(33=>'Red', 34=>'Blue')
	 */
	public function options_method($name)
	{
		$this->options_method = $name;
		return $this;
	}

	/*
	 * Specifies a method name in the model class, responsible for returning
	 * a state of a checkbox in the checknox list. The method should be defined like this:
	 * public method method_name($db_name, $current_key_value = -1);
	 * The method must return a boolean value
	 */
	public function option_state_method($name)
	{
		$this->option_state_method = $name;
		return $this;
	}

	/**
	 * Adds a filter SQL expression for reference-type fields.
	 * @param string $expr Specifies an SQL expression. Example: 'status is not null and status = 1'
	 */
	public function reference_filter($expr)
	{
		$this->reference_filter = $expr;
		return $this;
	}

	/**
	 * Adds an SQL expression to evaluate option descriptions for reference-type fields.
	 * Option descriptions are supported by the radio button fields.
	 * @param string $expr Specifies an SQL expression. Example 'concat(login_name, ' (', first_name, ' ', last_name, ')')'
	 */
	public function reference_description_field($expr)
	{
		$this->reference_description_field = $expr;
		return $this;
	}

	/**
	 * Hides the relation preview button for relation fields.
	 */
	public function preview_no_relation()
	{
		$this->preview_no_relation = true;
		return $this;
	}

	/**
	 * Adds link to a preview field
	 */
	public function preview_link($url)
	{
		$this->preview_link = $url;
		return $this;
	}

	/*
	 * Disables a control
	 */
	public function disabled()
	{
		$this->disabled = true;
		return $this;
	}

	/**
	 * Sets a text to output on form previews for many-to-many relation
	 * fields in case if no options were assigned.
	 */
	public function preview_no_options_message($str)
	{
		$this->relation_preview_no_options = $str;
		return $this;
	}

	/**
	 * Sets an "on" value for checkbox fields. Default value is 1.
	 */
	public function checkbox_on_state($value)
	{
		$this->checkbox_on_state = $value;
		return $this;
	}

	/**
	 * Sets a label for the "Add document" link. This method work only with file attachment fields.
	 */
	public function add_document_label($label)
	{
		$this->add_attachment_label = $label;
		return $this;
	}

	/**
	 * Sets a text to output if there is no files attached. This method work only with file attachment fields.
	 */
	public function no_attachments_label($label)
	{
		$this->no_attachments_label = $label;
		return $this;
	}

	/**
	 * Sets width and height value for image file attachments
	 */
	public function image_thumb_size($size)
	{
		$this->image_thumb_size = $size;
		return $this;
	}

	/**
	 * Adds a sorting expression for reference-type fields.
	 * @param string $expr Specifies an SQL sorting expression. Example: 'name desc'
	 * Notice, that the first model column corresponds the
	 * reference display value field, so you may use expressions like '1 desc'
	 */
	public function reference_sort($expr)
	{
		$this->reference_sort = $expr;
		return $this;
	}

	/**
	 * Determines whether the drop-down option display values should be html-encoded before output.
	 */
	public function options_html_encode($html_encode)
	{
		$this->options_html_encode = $html_encode;
		return $this;
	}

	/**
	 * Sets the file attachments field rendering mode
	 * @param string $display_mode Specifies a render mode value. Possible values: 'file_list', 'image_list'
	 */
	public function display_files_as($display_mode)
	{
		$this->display_files_as = $display_mode;
		return $this;
	}

	/**
	 * Specifies a list of plugins to be loaded into the HTML filed.
	 * Please refer TinyMCE documentation for details about plugins.
	 * @param string $plugins A list of plugins to load.
	 */
	public function html_plugins($plugins)
	{
		if (substr($plugins, 0, 1) != ',')
			$plugins = ', '.$plugins;

		$this->html_plugins .= $plugins;
		return $this;
	}

	/**
	 * Specifies a list of buttons to be displayed in the 1st row of HTML field toolbar.
	 * Please refer TinyMCE documentation for details about buttons.
	 * @param string $buttons A list of buttons to display.
	 */
	public function html_buttons1($buttons)
	{
		$this->html_buttons1 = $buttons;
		return $this;
	}

	/**
	 * Specifies a list of buttons to be displayed in the 2nd row of HTML field toolbar.
	 * Please refer TinyMCE documentation for details about buttons.
	 * @param string $buttons A list of buttons to display.
	 */
	public function html_buttons2($buttons)
	{
		$this->html_buttons2 = $buttons;
		return $this;
	}

	/**
	 * Specifies a list of buttons to be displayed in the 3rd row of HTML field toolbar.
	 * Please refer TinyMCE documentation for details about buttons.
	 * @param string $buttons A list of buttons to display.
	 */
	public function html_buttons3($buttons)
	{
		$this->html_buttons3 = $buttons;
		return $this;
	}

	/**
	 * Specifies a custom CSS file to use within the HTML editor (the editable area)
	 * @param string $url Specifies an URL of CSS file
	 */
	public function html_content_css($url)
	{
		$this->html_content_css = $url;
		return $this;
	}

	/**
	 * Specifies a list of block formats to use in the HTML editor formats drop-down menu
	 * @param string $formats Specifies a comma-separated list of formats
	 */
	public function htmlBlockFormats($formats)
	{
		$this->htmlBlockFormats = $formats;
		return $this;
	}

	/**
	 * Specifies a list of custom styles to use in the HTML editor styles drop-down menu
	 * @param string $styles Specifies a semicolon-separated list of styles
	 */
	public function htmlCustomStyles($styles)
	{
		$this->htmlCustomStyles = $styles;
		return $this;
	}

	/**
	 * Specifies a list of font sizes to use in the HTML editor font sizes drop-down menu
	 * @param string $sizes Specifies a comma-separated list of sizes
	 */
	public function htmlFontSizes($sizes)
	{
		$this->htmlFontSizes = $sizes;
		return $this;
	}

	/**
	 * Specifies a list of font colors to use in the HTML editor font color palette
	 * @param string $colors Specifies a comma-separated list of colors: "FF00FF,FFFF00,000000"
	 */
	public function htmlFontColors($colors)
	{
		$this->htmlFontColors = $colors;
		return $this;
	}

	/**
	 * Specifies a list of background colors to use in the HTML editor color palette
	 * @param string $colors Specifies a comma-separated list of colors: "FF00FF,FFFF00,000000"
	 */
	public function htmlBackgroundColors($colors)
	{
		$this->htmlBackgroundColors = $colors;
		return $this;
	}

	/**
	 * This option enables you to disable the "more colors" link in the HTML editor
	 * for the text and background color menus.
	 * @param string $allow Indicates whether the more colors link should be enabled
	 */
	public function htmlAllowMoreColors($allow)
	{
		$this->htmlAllowMoreColors = $allow;
		return $this;
	}

	/**
	 * The html_valid_elements option defines which elements will
	 * remain in the edited text when the editor saves.
	 * @param string $value A list of valid elements, as text
	 */
	public function html_valid_elements($value)
	{
		$this->html_valid_elements = $value;
		return $this;
	}

	/**
	 * The html_valid_child_elements This option gives you the ability to specify what elements
	 * are valid inside different parent elements.
	 * @param string $value A list of valid child elements, as text
	 */
	public function html_valid_child_elements($value)
	{
		$this->html_valid_child_elements = $value;
		return $this;
	}

	/**
	 * Makes HTML fields full-width
	 */
	public function html_full_width($value)
	{
		$this->html_full_width = $value;
		return $this;
	}

	/**
	 * Sets file download url for file attachment fields.
	 * @param string $url Specifies an URL
	 */
	public function file_download_base_url($url)
	{
		$this->file_download_base_url = $url;
		return $this;
	}

	public function get_col_definition()
	{
		return $this->_column_definition;
	}

	/**
	 * Hides field label
	 */
	public function no_label()
	{
		$this->no_label = true;
		return $this;
	}

	/**
	 * Suppresses the field value output
	 */
	public function hide_content()
	{
		$this->hide_content = true;
		return $this;
	}

	/**
	 * Convert new lines to <br/> in the preview mode.
	 * This method works only with text areas
	 */
	public function nl2br($value)
	{
		$this->nl2br = $value;
		return $this;
	}

	/**
	 * Sets the field position on the form. For fields without any position
	 * specified, the position is calculated automatically, basing on the
	 * add_form_field() method call order. For the first field the sort order
	 * value is 10, for the second field it is 20 and so on.
	 * @param int $value Specifies a form position.
	 */
	public function sort_order($value)
	{
		$this->sort_order = $value;
		return $this;
	}

	/**
	 * Allows to render a specific partial below the form label
	 */
	public function title_partial($partial_name)
	{
		$this->title_partial = $partial_name;
		return $this;
	}

	/**
	 * Allows to render a specific form element partial instead of the standard field type specific input field.
	 */
	public function form_element_partial($partial_name)
	{
		$this->form_element_partial = $partial_name;
		return $this;
	}

	/**
	 * Adds a help message to the field preview section
	 */
	public function preview_help($string)
	{
		$this->preview_help = $string;
		return $this;
	}

	/**
	 * Adds a help message to the field comment
	 */
	public function comment_tooltip($string)
	{
		$this->comment_tooltip = $string;
		return $this;
	}

	/**
	 * Returns column validation object
	 */
	public function validation()
	{
		return $this->_column_definition->validation();
	}

	/**
	 * Sets placeholder for form field
	 */
	public function placeholder($placeholder, $field_section = null)
	{
		$this->placeholder[$field_section] = $placeholder;
		return $this;
	}

	/**
	 * Gets placeholder for form field
	 */
	public function get_placeholder($field_section = null)
	{
		if (array_key_exists($field_section, $this->placeholder))
			return $this->placeholder[$field_section];
	}	
}
