<?php
defined('ABSPATH') || exit;

if (!function_exists('tyreorder_csv_cache_file')) {
    function tyreorder_csv_cache_file() {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['basedir']) . 'tyreorder-csv-cache.csv';
    }
}

if (!function_exists('tyreorder_fetch_csv_and_cache')) {
    function tyreorder_fetch_csv_and_cache($force = false, $user_id = null) {
        $csv_file = tyreorder_csv_cache_file();

        if ($force || !file_exists($csv_file)) {
            $creds = tyreorder_get_api_credentials($user_id);
            $username = $creds['username'];
            $password = $creds['password'];
            if (!$username || !$password) return false;

            $url = 'https://www.tyreorder.com/en/xml/export/csv-v2';
            $context = stream_context_create([
                'http' => [
                    'header'  => 'Authorization: Basic ' . base64_encode("$username:$password"),
                    'timeout' => 60,
                ],
            ]);

            $csv_data = @file_get_contents($url, false, $context);
            if ($csv_data === false) return false;

            file_put_contents($csv_file, $csv_data);
        }

        return $csv_file;
    }
}

if (!function_exists('tyreorder_csv_preview')) {
    function tyreorder_csv_preview() {
        if (isset($_POST['tyreorder_redownload_csv']) && check_admin_referer('tyreorder_redownload_csv')) {
            $file = tyreorder_fetch_csv_and_cache(true);
            if ($file === false) {
                return '<div class="notice notice-error">Could not redownload CSV.</div>';
            }
        } else {
            $file = tyreorder_fetch_csv_and_cache();
            if ($file === false) {
                return '<div class="notice notice-error">Could not load cached or fetch fresh CSV.</div>';
            }
        }

        $searched_code = isset($_POST['tyreorder_code']) ? trim(sanitize_text_field($_POST['tyreorder_code'])) : '';
        if (empty($searched_code)) {
            return '<em>Enter a tyre code above and submit to preview corresponding CSV row.</em>'
                 . tyreorder_csv_redownload_button();
        }

        $handle = @fopen($file, 'r');
        if (!$handle) {
            return '<div class="notice notice-error">Failed to open cached CSV file.</div>'
                 . tyreorder_csv_redownload_button();
        }

        $header = null;
        $row_found = null;
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (!$header) {
                $header = $row;
                continue;
            }
            if ($row[0] == $searched_code) {
                $row_found = $row;
                break;
            }
        }
        fclose($handle);

        if (!$row_found) {
            return '<div class="notice notice-warning"><p>No matching row found for code: <strong>' . esc_html($searched_code) . '</strong></p></div>'
                 . tyreorder_csv_redownload_button();
        }

        ob_start();
        echo '<table class="widefat striped" style="margin-top: 15px;">';
        echo '<thead><tr><th>Field</th><th>Value</th></tr></thead><tbody>';
        foreach ($header as $index => $field_name) {
            $value = isset($row_found[$index]) ? $row_found[$index] : '';
            echo '<tr><td>' . esc_html($field_name) . '</td><td>' . esc_html($value) . '</td></tr>';
        }
        echo '</tbody></table>';

        echo tyreorder_csv_redownload_button();
        return ob_get_clean();
    }
}

if (!function_exists('tyreorder_csv_redownload_button')) {
    function tyreorder_csv_redownload_button() {
        $button  = '<form method="post" style="margin-top: 10px;">';
        $button .= wp_nonce_field('tyreorder_redownload_csv', '_wpnonce', true, false);
        $button .= '<input type="submit" class="button" name="tyreorder_redownload_csv" value="Redownload CSV" />';
        $button .= '</form>';
        return $button;
    }
}