<?php
defined('ABSPATH') || exit;

/**
 * Returns the full path to the cached CSV file in uploads directory.
 *
 * @return string Absolute file path.
 */
if (!function_exists('tyreorder_csv_cache_file')) :
function tyreorder_csv_cache_file() {
    $upload_dir = wp_upload_dir();
    return trailingslashit($upload_dir['basedir']) . 'tyreorder-csv-cache.csv';
}
endif;

/**
 * Fetches the CSV from Tyreorder via HTTP Basic Auth, caches it locally.
 *
 * @param bool     $force   If true, forces redownload even if cache exists.
 * @param int|null $user_id Optional user ID to get API credentials for.
 * @return string|false Absolute path to cached file, or false on failure.
 */
if (!function_exists('tyreorder_fetch_csv_and_cache')) :
function tyreorder_fetch_csv_and_cache($force = false, $user_id = null) {
    $csv_file = tyreorder_csv_cache_file();

    if (!$force && file_exists($csv_file)) {
        // Cache exists and we do not force redownload
        return $csv_file;
    }

    $creds = tyreorder_get_api_credentials($user_id);
    $username = $creds['username'];
    $password = $creds['password'];

    if (!$username || !$password) {
        return false; // Credentials missing
    }

    $url = 'https://www.tyreorder.com/en/xml/export/csv-v2';

    $context = stream_context_create([
        'http' => [
            'header'  => 'Authorization: Basic ' . base64_encode("$username:$password"),
            'timeout' => 60,
        ],
    ]);

    $csv_data = @file_get_contents($url, false, $context);

    if ($csv_data === false) {
        return false;
    }

    if (false === file_put_contents($csv_file, $csv_data)) {
        return false;
    }

    return $csv_file;
}
endif;

/**
 * Output a preview table of the CSV row matching the searched code.
 *
 * @return string HTML or notices.
 */
if (!function_exists('tyreorder_csv_preview')) :
function tyreorder_csv_preview() {
    // Handle manual CSV redownload request
    if (isset($_POST['tyreorder_redownload_csv']) && check_admin_referer('tyreorder_redownload_csv')) {
        $file = tyreorder_fetch_csv_and_cache(true);
        if ($file === false) {
            return '<div class="notice notice-error">' . esc_html__('Could not redownload CSV.', 'tyreorder-api') . '</div>';
        }
    } else {
        $file = tyreorder_fetch_csv_and_cache();
        if ($file === false) {
            return '<div class="notice notice-error">' . esc_html__('Could not load cached or fetch fresh CSV.', 'tyreorder-api') . '</div>';
        }
    }

    $searched_code = isset($_POST['tyreorder_code']) ? trim(sanitize_text_field($_POST['tyreorder_code'])) : '';

    if (empty($searched_code)) {
        return '<em>' . esc_html__('Enter a tyre code above and submit to preview corresponding CSV row.', 'tyreorder-api') . '</em>'
             . tyreorder_csv_redownload_button();
    }

    $handle = @fopen($file, 'r');
    if (!$handle) {
        return '<div class="notice notice-error">' . esc_html__('Failed to open cached CSV file.', 'tyreorder-api') . '</div>'
             . tyreorder_csv_redownload_button();
    }

    $header = null;
    $row_found = null;

    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        if (!$header) {
            $header = $row;
            continue;
        }
        if ($row[0] === $searched_code) {
            $row_found = $row;
            break;
        }
    }

    fclose($handle);

    if (!$row_found) {
        return '<div class="notice notice-warning"><p>' .
            sprintf(
                esc_html__('No matching row found for code: %s', 'tyreorder-api'),
                esc_html($searched_code)
            ) .
            '</p></div>' . tyreorder_csv_redownload_button();
    }

    ob_start();

    echo '<table class="widefat striped" style="margin-top: 15px;">';
    echo '<thead><tr><th>' . esc_html__('Field', 'tyreorder-api') . '</th><th>' . esc_html__('Value', 'tyreorder-api') . '</th></tr></thead><tbody>';

    foreach ($header as $index => $field_name) {
        $value = isset($row_found[$index]) ? $row_found[$index] : '';
        echo '<tr>';
        echo '<td>' . esc_html($field_name) . '</td>';
        echo '<td>' . esc_html($value) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    echo tyreorder_csv_redownload_button();

    return ob_get_clean();
}
endif;

/**
 * Render CSV redownload button in admin.
 *
 * @return string HTML form.
 */
if (!function_exists('tyreorder_csv_redownload_button')) :
function tyreorder_csv_redownload_button() {
    ob_start();
    ?>
    <form method="post" style="margin-top: 10px;">
        <?php wp_nonce_field('tyreorder_redownload_csv', '_wpnonce'); ?>
        <input type="submit" class="button" name="tyreorder_redownload_csv" value="<?php esc_attr_e('Redownload CSV', 'tyreorder-api'); ?>" />
    </form>
    <?php
    return ob_get_clean();
}
endif;
