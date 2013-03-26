<?php

define('frm_tags', 'tags');
define('frm_dropdown_create', 'dropdown_create');
define('frm_multi_textarea', 'multi_textarea');

class Db_FormFieldDefinition extends Db_FormElement
{
    public $db_name;
    public $empty_option = null;
}