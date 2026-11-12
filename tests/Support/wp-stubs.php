<?php

// Stub-uri minime pentru a incarca pluginul simulator in afara WordPress, doar in testul de caracterizare.
if (! defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir().'/');
}

foreach (['add_action', 'add_filter', 'add_shortcode', 'register_activation_hook', 'register_deactivation_hook'] as $fn) {
    if (! function_exists($fn)) {
        eval("function {$fn}(...\$args) { return true; }");
    }
}

if (! function_exists('is_user_logged_in')) {
    function is_user_logged_in()
    {
        return false;
    }
}
if (! function_exists('get_user_by')) {
    function get_user_by($field, $value)
    {
        return (object) ['ID' => 1];
    }
}
if (! function_exists('get_user_meta')) {
    function get_user_meta($userId, $key = '', $single = false)
    {
        return $key === 'hv_pets' ? \Tests\Unit\SimulatorParityTest::$pets : '';
    }
}
if (! function_exists('get_option')) {
    function get_option($key, $default = false)
    {
        return $default;
    }
}
