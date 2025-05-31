<?php
defined('ABSPATH') || exit;

/**
 * Retrieve Tyreorder API credentials for a given user.
 *
 * Returns raw values as stored. Output escaping should be handled by caller.
 *
 * @param int|null $user_id Optional. User ID defaults to current user.
 * @return array Associative array with 'username' and 'password' keys (empty strings if not set).
 */
if (!function_exists('tyreorder_get_api_credentials')) :
function tyreorder_get_api_credentials(?int $user_id = null): array {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    $username = get_user_meta($user_id, 'tyreorder_username', true);
    $password = get_user_meta($user_id, 'tyreorder_password', true);

    return [
        'username' => $username ?: '',
        'password' => $password ?: '',
    ];
}
endif;

/**
 * Save Tyreorder API credentials for a given user.
 *
 * Sanitizes input before storage.
 *
 * @param string   $username API username.
 * @param string   $password API password.
 * @param int|null $user_id  Optional. User ID defaults to current user.
 * @return bool True on success, false on failure.
 */
if (!function_exists('tyreorder_save_api_credentials')) :
function tyreorder_save_api_credentials(string $username, string $password, ?int $user_id = null): bool {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    $username_sanitized = sanitize_text_field($username);
    $password_sanitized = sanitize_text_field($password);

    $user_updated = update_user_meta($user_id, 'tyreorder_username', $username_sanitized);
    $pass_updated = update_user_meta($user_id, 'tyreorder_password', $password_sanitized);

    return ($user_updated !== false && $pass_updated !== false);
}
endif;
