<?php
defined('ABSPATH') || exit;

// Register admin menus
add_action('admin_menu', function () {
    add_menu_page(
        __('Tyreorder API', 'tyreorder-api'),
        __('Tyreorder', 'tyreorder-api'),
        'manage_options',
        'tyreorder-admin',
        'tyreorder_admin_dashboard_page',
        'dashicons-update',
        56
    );
    add_submenu_page(
        'tyreorder-admin',
        __('Tyreorder Login', 'tyreorder-api'),
        __('API Login', 'tyreorder-api'),
        'manage_options',
        'tyreorder-login',
        'tyreorder_login_page'
    );
    add_submenu_page(
        'tyreorder-admin',
        __('CSV Product Import', 'tyreorder-api'),
        __('Product Import', 'tyreorder-api'),
        'manage_options',
        'tyreorder-import',
        'tyreorder_import_page' // Assumes defined in import.php
    );

});

// Dashboard page: Tyre stock check and CSV preview
function tyreorder_admin_dashboard_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $current_user_creds = tyreorder_get_api_credentials();
    $has_creds = $current_user_creds['username'] && $current_user_creds['password'];

    $result = null;
    $tyre_code_posted = isset($_POST['tyreorder_code']) ? sanitize_text_field($_POST['tyreorder_code']) : '';

    if (!empty($tyre_code_posted) && check_admin_referer('tyreorder_check_stock')) {
        // Lookup original code from CSV to call API
        $original_code = tyreorder_get_original_code_by_csv_id($tyre_code_posted);
        if ($original_code) {
            $result = tyreorder_api_test($original_code);
        } else {
            $result = false; // or handle "not found" scenario
        }
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Tyreorder API Dashboard', 'tyreorder-api'); ?></h1>

        <?php if (!$has_creds): ?>
            <div class="notice notice-warning">
                <p><?php _e('Please enter your Tyreorder API credentials in the "API Login" tab before using the dashboard.', 'tyreorder-api'); ?></p>
            </div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('tyreorder_check_stock'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="tyreorder_code"><?php _e('Tyre ID', 'tyreorder-api'); ?></label></th>
                    <td>
                        <input type="text" name="tyreorder_code" id="tyreorder_code" class="regular-text" required value="<?php echo esc_attr($tyre_code_posted); ?>" />
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Check Stock', 'tyreorder-api')); ?>

            <?php
            if (!empty($tyre_code_posted) && $result !== false && isset($result->actual_amount)) {
                $amount = esc_html($result->actual_amount);
                echo '<div style="margin-top:20px;"><strong>' . esc_html($tyre_code_posted) . ' – in stock: ' . $amount . '</strong></div>';
            } elseif (!empty($tyre_code_posted) && $result === false) {
                echo '<div class="notice notice-error" style="margin-top:20px;">' . esc_html__('Could not fetch stock info. Check credentials or code.', 'tyreorder-api') . '</div>';
            }
            ?>
        </form>

        <hr />

        <h2><?php esc_html_e('CSV Info', 'tyreorder-api'); ?></h2>
        <?php
        $csv_preview = function_exists('tyreorder_csv_preview') ? tyreorder_csv_preview() : '';
        echo $csv_preview;
        ?>
    </div>
    <?php
}

// API credentials login page
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
        <h1><?php esc_html_e('Tyreorder API Login', 'tyreorder-api'); ?></h1>
        <?php echo $msg; ?>
        <form method="post">
            <?php wp_nonce_field('tyreorder_save_settings', 'tyreorder_login_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="tyreorder_username"><?php _e('API Username', 'tyreorder-api'); ?></label></th>
                    <td><input name="tyreorder_username" type="text" id="tyreorder_username" value="<?php echo esc_attr($creds['username']); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="tyreorder_password"><?php _e('API Password', 'tyreorder-api'); ?></label></th>
                    <td><input name="tyreorder_password" type="password" id="tyreorder_password" value="" class="regular-text" autocomplete="new-password" required></td>
                </tr>
            </table>
            <?php submit_button(__('Save Credentials', 'tyreorder-api')); ?>
        </form>
        <p><em><?php esc_html_e('Credentials are only visible to and saved for your user account.', 'tyreorder-api'); ?></em></p>
    </div>
    <?php
}