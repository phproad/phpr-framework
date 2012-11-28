<?php

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
