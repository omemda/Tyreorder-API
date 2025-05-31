<?php
defined('ABSPATH') || exit;

if (!function_exists('tyreorder_api_test')) {
    function tyreorder_api_test($code) {
        $creds = tyreorder_get_api_credentials();
        $username = $creds['username'];
        $password = $creds['password'];
        if (!$username || !$password) return false;

        $endpoint = 'https://www.tyreorder.com/en/xml/api/is_tyre_in_storage';
        $xml_request = '<?xml version="1.0" encoding="UTF-8"?><root><code>' . esc_xml($code) . '</code></root>';

        $response = wp_remote_post($endpoint, [
            'method'  => 'POST',
            'body'    => $xml_request,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode("$username:$password"),
                'Content-Type'  => 'application/xml',
            ],
            'timeout' => 20,
        ]);
        if (is_wp_error($response)) return false;

        $body = wp_remote_retrieve_body($response);
        $xml = @simplexml_load_string($body);
        if (!$xml) return false;
        return $xml;
    }
}

if (!function_exists('tyreorder_get_original_code_by_csv_id')) {
    // Extract original code field from CSV by csv id
    function tyreorder_get_original_code_by_csv_id($search_id) {
        $file = tyreorder_csv_cache_file();
        if (!file_exists($file)) return false;
        $handle = fopen($file, 'r');
        if (!$handle) return false;

        $header = null;
        $original_code = false;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (!$header) {
                $header = $row; // header row
                continue;
            }
            if ($row[0] == $search_id) {
                $index = array_search('original code', $header);
                if ($index !== false && isset($row[$index])) {
                    $original_code = $row[$index];
                }
                break;
            }
        }
        fclose($handle);
        return $original_code;
    }
}