<?php
defined('ABSPATH') || exit;

if (!function_exists('tyreorder_get_api_credentials')) {
    function tyreorder_get_api_credentials($user_id = null) {
        if (!$user_id) $user_id = get_current_user_id();
        return [
            'username' => get_user_meta($user_id, 'tyreorder_username', true),
            'password' => get_user_meta($user_id, 'tyreorder_password', true),
        ];
    }
}

if (!function_exists('tyreorder_save_api_credentials')) {
    function tyreorder_save_api_credentials($username, $password, $user_id = null) {
        if (!$user_id) $user_id = get_current_user_id();
        update_user_meta($user_id, 'tyreorder_username', sanitize_text_field($username));
        update_user_meta($user_id, 'tyreorder_password', sanitize_text_field($password));
    }
}