<?php
/**
 * Plugin Name: Tyreorder API Integration
 * Plugin URI: https://github.com/omemda/Tyreorder-API
 * Description: Integrates the Tyreorder API with WooCommerce, including per-user credentials, CSV caching, product import, and batch wipe tools.
 * Version: 1.4
 * Author: Stormas
 * Author URI: https://stormas.lt/
 * Text Domain: tyreorder-api
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package TyreorderAPI
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

// Prevent direct access for security.
defined( 'ABSPATH' ) || exit;

// ------------------------------------------
// Load plugin modular components
// ------------------------------------------

// User credential management (per user storage, sanitation, retrieval)
require_once plugin_dir_path( __FILE__ ) . 'inc/credentials.php';

// Tyreorder XML API request helpers (stock checks, XML parsing)
require_once plugin_dir_path( __FILE__ ) . 'inc/api.php';

// CSV fetch, cache, row searching, and preview rendering
require_once plugin_dir_path( __FILE__ ) . 'inc/csv.php';

// WooCommerce product import/update logic and media handling
require_once plugin_dir_path( __FILE__ ) . 'inc/import.php';

// Admin tools: batch product wipes and related JS enqueue
require_once plugin_dir_path( __FILE__ ) . 'inc/tools.php';

// Admin menus and page callbacks rendering
require_once plugin_dir_path( __FILE__ ) . 'inc/admin-menu.php';

// ------------------------------------------
// Scheduled events for daily CSV caching
// ------------------------------------------

// Activation hook: schedule daily CSV fetch event at next midnight
register_activation_hook( __FILE__, function() {
    if ( ! wp_next_scheduled( 'tyreorder_daily_csv_cache_event' ) ) {
        wp_schedule_event( strtotime('tomorrow midnight'), 'daily', 'tyreorder_daily_csv_cache_event' );
    }
});

// Deactivation hook: clear scheduled event to avoid orphaned cron job
register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'tyreorder_daily_csv_cache_event' );
});

// Callback hooked to the scheduled event to refresh CSV cache.
// Uses credentials of the first administrator on the site.
add_action( 'tyreorder_daily_csv_cache_event', 'tyreorder_fetch_csv_and_cache_cron' );

function tyreorder_fetch_csv_and_cache_cron() {
    $admins = get_users( [
        'role'    => 'administrator',
        'orderby' => 'ID',
        'order'   => 'ASC',
        'number'  => 1
    ] );
    if ( empty( $admins ) ) {
        error_log( 'Tyreorder API: No administrator users found to fetch CSV.' );
        return;
    }
    $user_id = $admins[0]->ID;
    tyreorder_fetch_csv_and_cache( true, $user_id );
}
