<?php
defined('ABSPATH') || exit;

/**
 * Retrieve Tyreorder API credentials for a given user.
 *
 * @param int|null $user_id Optional. User ID defaults to current user.
 * @return array Associative array with 'username' and 'password' keys (empty strings if not set).
 */
if (!function_exists('tyreorder_get_api_credentials')) :
function tyreorder_get_api_credentials($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    $username = get_user_meta($user_id, 'tyreorder_username', true);
    $password = get_user_meta($user_id, 'tyreorder_password', true);

    return [
        'username' => $username ? sanitize_text_field($username) : '',
        'password' => $password ? sanitize_text_field($password) : '',
    ];
}
endif;

/**
 * Save Tyreorder API credentials for a given user.
 *
 * @param string   $username API username.
 * @param string   $password API password.
 * @param int|null $user_id  Optional. User ID defaults to current user.
 */
if (!function_exists('tyreorder_save_api_credentials')) :
function tyreorder_save_api_credentials($username, $password, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    update_user_meta($user_id, 'tyreorder_username', sanitize_text_field($username));
    update_user_meta($user_id, 'tyreorder_password', sanitize_text_field($password));
}
endif;
