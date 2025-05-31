<?php
defined('ABSPATH') || exit;

/**
 * Register the main plugin menu and submenus.
 */
add_action('admin_menu', function () {
    // Main menu page: Tyreorder Dashboard
    add_menu_page(
        __('Tyreorder API', 'tyreorder-api'),   // page title
        __('Tyreorder', 'tyreorder-api'),       // menu title
        'manage_options',                        // capability
        'tyreorder-admin',                      // menu slug
        'tyreorder_admin_dashboard_page',       // callback function
        'dashicons-update',
        56
    );

    // Submenu: API Login under the Tyreorder menu
    add_submenu_page(
        'tyreorder-admin',
        __('Tyreorder Login', 'tyreorder-api'),
        __('API Login', 'tyreorder-api'),
        'manage_options',
        'tyreorder-login',
        'tyreorder_login_page'
    );

    // Submenu: CSV Product Import page
    add_submenu_page(
        'tyreorder-admin',
        __('CSV Product Import', 'tyreorder-api'),
        __('Product Import', 'tyreorder-api'),
        'manage_options',
        'tyreorder-import',
        'tyreorder_import_page'
    );

    // Submenu: Product Wipe page (optional if you want a dedicated page)
    add_submenu_page(
        'tyreorder-admin',
        __('Product Wipe', 'tyreorder-api'),
        __('Product Wipe', 'tyreorder-api'),
        'manage_options',
        'tyreorder-product-wipe',
        'tyreorder_product_wipe_page'
    );
});

/**
 * Dashboard page for stock checking using Tyreorder API and CSV preview.
 */
function tyreorder_admin_dashboard_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $current_user_creds = tyreorder_get_api_credentials();
    $has_creds = !empty($current_user_creds['username']) && !empty($current_user_creds['password']);

    $result = null;
    $tyre_code_posted = isset($_POST['tyreorder_code']) ? sanitize_text_field($_POST['tyreorder_code']) : '';

    if ($tyre_code_posted && check_admin_referer('tyreorder_check_stock')) {
        // Get original code from CSV to call API correctly
        $original_code = tyreorder_get_original_code_by_csv_id($tyre_code_posted);
        if ($original_code) {
            $result = tyreorder_api_test($original_code);
        } else {
            $result = false; // Code not found
        }
    }
    ?>
    <div class="wrap">
        <?php
        if (function_exists('tyreorder_render_batch_wipe_buttons')) {
            tyreorder_render_batch_wipe_buttons();
        }
        ?>

        <h1><?php esc_html_e('Tyreorder API Dashboard', 'tyreorder-api'); ?></h1>

        <?php if (!$has_creds): ?>
            <div class="notice notice-warning">
                <p><?php esc_html_e('Please enter your Tyreorder API credentials in the "API Login" tab before using the dashboard.', 'tyreorder-api'); ?></p>
            </div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('tyreorder_check_stock'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="tyreorder_code"><?php esc_html_e('Tyre ID', 'tyreorder-api'); ?></label></th>
                    <td>
                        <input type="text" name="tyreorder_code" id="tyreorder_code" class="regular-text" required value="<?php echo esc_attr($tyre_code_posted); ?>" />
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Check Stock', 'tyreorder-api')); ?>

            <?php
            if ($tyre_code_posted && $result !== false && isset($result->actual_amount)) {
                echo '<div style="margin-top:20px;"><strong>' . esc_html($tyre_code_posted) . ' – in stock: ' . intval($result->actual_amount) . '</strong></div>';
            } elseif ($tyre_code_posted && $result === false) {
                echo '<div class="notice notice-error" style="margin-top:20px;">' . esc_html__('Could not fetch stock info. Check credentials or code.', 'tyreorder-api') . '</div>';
            }
            ?>
        </form>

        <hr />

        <h2><?php esc_html_e('CSV Info', 'tyreorder-api'); ?></h2>
        <?php
        if (function_exists('tyreorder_csv_preview')) {
            echo tyreorder_csv_preview();
        }
        ?>
    </div>
    <?php
}

/**
 * API credentials login page form callback.
 */
function tyreorder_login_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('Access denied', 'tyreorder-api'));
    }

    $msg = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['tyreorder_login_nonce']) &&
        wp_verify_nonce($_POST['tyreorder_login_nonce'], 'tyreorder_save_settings')
    ) {
        tyreorder_save_api_credentials(
            sanitize_text_field($_POST['tyreorder_username']),
            sanitize_text_field($_POST['tyreorder_password'])
        );
        $msg = '<div class="updated notice"><p>' . esc_html__('Settings saved!', 'tyreorder-api') . '</p></div>';
    }

    $creds = tyreorder_get_api_credentials();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Tyreorder API Login (User-specific)', 'tyreorder-api'); ?></h1>
        <?php echo $msg; ?>
        <form method="post">
            <?php wp_nonce_field('tyreorder_save_settings', 'tyreorder_login_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="tyreorder_username"><?php esc_html_e('API Username', 'tyreorder-api'); ?></label></th>
                    <td><input type="text" name="tyreorder_username" id="tyreorder_username" value="<?php echo esc_attr($creds['username']); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tyreorder_password"><?php esc_html_e('API Password', 'tyreorder-api'); ?></label></th>
                    <td><input type="password" name="tyreorder_password" id="tyreorder_password" value="" class="regular-text" autocomplete="new-password" required></td>
                </tr>
            </table>
            <?php submit_button(__('Save Credentials', 'tyreorder-api')); ?>
        </form>
        <p><em><?php esc_html_e('Credentials are only visible and saved for your user account.', 'tyreorder-api'); ?></em></p>
    </div>
    <?php
}
