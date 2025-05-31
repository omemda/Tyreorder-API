<?php
defined('ABSPATH') || exit;

/**
 * Performs a Tyreorder XML API call to check if a tyre is in stock.
 *
 * @param string $code The tyre code to check (usually the "original code").
 * @return SimpleXMLElement|false Parsed XML object on success, false on error.
 */
if ( ! function_exists( 'tyreorder_api_test' ) ) :
function tyreorder_api_test( $code ) {
    $creds = tyreorder_get_api_credentials();
    $username = $creds['username'];
    $password = $creds['password'];

    if ( ! $username || ! $password ) {
        error_log( 'Tyreorder API: Credentials missing for user ' . get_current_user_id() );
        return false;
    }

    $endpoint = 'https://www.tyreorder.com/en/xml/api/is_tyre_in_storage';
    $xml_request = '<?xml version="1.0" encoding="UTF-8"?><root><code>' . esc_xml( $code ) . '</code></root>';

    $response = wp_remote_post( $endpoint, [
        'method'  => 'POST',
        'body'    => $xml_request,
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode( "$username:$password" ),
            'Content-Type'  => 'application/xml',
        ],
        'timeout' => 20,
        // Optionally add SSL verify or other args here
    ] );

    if ( is_wp_error( $response ) ) {
        error_log( 'Tyreorder API error: ' . $response->get_error_message() );
        return false;
    }

    $body = wp_remote_retrieve_body( $response );

    if ( empty( $body ) ) {
        error_log( 'Tyreorder API: Empty response body.' );
        return false;
    }

    $xml = @simplexml_load_string( $body );

    if ( ! $xml ) {
        error_log( 'Tyreorder API: Failed to parse XML response. Raw body: ' . $body );
        return false;
    }

    return $xml;
}
endif;

/**
 * Returns the 'original code' value from the cached CSV for a given CSV ID.
 *
 * @param string $search_id The CSV 'id' column value to find.
 * @return string|false The original code string on success, false on failure or not found.
 */
if ( ! function_exists( 'tyreorder_get_original_code_by_csv_id' ) ) :
function tyreorder_get_original_code_by_csv_id( $search_id ) {
    $file = tyreorder_csv_cache_file();

    if ( ! file_exists( $file ) ) {
        error_log( 'Tyreorder API: CSV cache file not found.' );
        return false;
    }

    $handle = fopen( $file, 'r' );
    if ( ! $handle ) {
        error_log( 'Tyreorder API: Could not open CSV cache file.' );
        return false;
    }

    $header = null;
    $original_code = false;

    while ( ( $row = fgetcsv( $handle, 0, ';' ) ) !== false ) {
        if ( ! $header ) {
            $header = $row;
            continue;
        }

        if ( $row[0] === $search_id ) {
            $index = array_search( 'original code', $header );
            if ( false !== $index && isset( $row[ $index ] ) ) {
                $original_code = $row[ $index ];
            }
            break;
        }
    }

    fclose( $handle );
    return $original_code;
}
endif;
