<?php

// General helpers
// 

function root_url($value = '/', $add_host_name_and_protocol = false, $protocol = null)
{
    return Phpr_Url::root_url($value, $add_host_name_and_protocol, $protocol);
}

function post($name, $default = null)
{
    return Phpr::$request->post($name, $default);
}

function post_array($array_name, $name, $default = null)
{
    return Phpr::$request->post_array($array_name, $name, $default);
}

function h($string)
{
    return Phpr_Html::encode($string);
}

function trace_log($str, $listener = 'DEBUG')
{
    if (Phpr::$trace_log)
        Phpr::$trace_log->write($str, $listener);
}

function module_build($module_id)
{
    return Phpr_Version::get_module_build_cached($module_id);
}

// Form helpers
// 

function form_open($attributes = array())
{
    $attributes = array_merge(array(
        'id'=>null,
        'onsubmit'=>null,
        'enctype'=>'multipart/form-data'
    ), $attributes);

    $result = Phpr_Form::open_tag($attributes);
    $session_key = post('secure_token', uniqid());
    $result .= "\n".'<input type="hidden" name="secure_token" value="'.Phpr_Html::encode($session_key).'"/>';

    return $result;
}

function form_widget($model, $field, $options = array()) {
    return Phpr_Form::widget($model, $field, $options);
}

function form_close() {
    return Phpr_Form::close_tag();
}

function form_input($name = '', $value = '', $extra = '') {
    return Phpr_Form::form_input($name, $value, $extra);
}

function form_hidden($name = '', $value = '', $extra = '') {
    return Phpr_Form::form_hidden($name, $value, $extra);
}

function form_file($name = '', $value = '', $extra = '') {
    return Phpr_Form::form_file($name, $value, $extra);
}

function form_password($name = '', $value = '', $extra = '') {
    return Phpr_Form::form_password($name, $value, $extra);
}

function form_textarea($data = '', $value = '', $extra = '') {
    return Phpr_Form::form_textarea($data, $value, $extra);
}

function form_dropdown($name = '', $options = array(), $selected = array(), $extra = '', $empty_option = false) {
    return Phpr_Form::form_dropdown($name, $options, $selected, $extra, $empty_option);
}

function form_checkbox($name = '', $value = '', $checked = false, $extra = '') {
    return Phpr_Form::form_checkbox($name, $value, $checked, $extra);
}

function form_radio($name = '', $value = '', $checked = false, $extra = '') {
    return Phpr_Form::form_radio($name, $value, $checked, $extra);
}

function form_submit($name = '', $value = '', $extra = '') {
    return Phpr_Form::form_submit($name, $value, $extra);
}

function form_button($name = '', $value = '', $extra = '') {
    return Phpr_Form::form_button($name, $value, $extra);
}

function form_label($text = '', $id = '', $extra = '') {
    return Phpr_Form::form_label($text, $id, $extra);
}

function form_value($object, $value, $default = null) {
    return (isset($object->$value)) ? $object->$value : $default;
}

function form_value_boolean($object, $value, $role, $default = null) {
    if (isset($object->$value))
    {
        if ($object->$value)
            return $role;
        else
            return !$role;
    }
    else
        return $default;
}