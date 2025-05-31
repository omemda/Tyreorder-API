<?php
/*
Plugin Name: Tyreorder API Integracija
Description: Integrates Tyreorder API with WooCommerce. Per-user credentials, daily CSV cache, product import/update, batch tools.
Version: 1.4
Author: Stormas
Text Domain: tyreorder-api
*/

defined('ABSPATH') || exit;

// Load core modules in proper order
require_once plugin_dir_path(__FILE__) . 'inc/credentials.php';
require_once plugin_dir_path(__FILE__) . 'inc/api.php';
require_once plugin_dir_path(__FILE__) . 'inc/csv.php';
require_once plugin_dir_path(__FILE__) . 'inc/import.php';
require_once plugin_dir_path(__FILE__) . 'inc/tools.php';
require_once plugin_dir_path(__FILE__) . 'inc/admin-menu.php';

// Activation and deactivation hooks for cron scheduling
register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('tyreorder_daily_csv_cache_event')) {
        wp_schedule_event(strtotime('tomorrow'), 'daily', 'tyreorder_daily_csv_cache_event');
    }
});
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('tyreorder_daily_csv_cache_event');
});
add_action('tyreorder_daily_csv_cache_event', 'tyreorder_fetch_csv_and_cache_cron');

// Cron callback fetches CSV with first admin’s credentials
function tyreorder_fetch_csv_and_cache_cron() {
    $users = get_users(['role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC']);
    if (!$users) return;
    $user_id = $users[0]->ID;
    tyreorder_fetch_csv_and_cache(true, $user_id);
}