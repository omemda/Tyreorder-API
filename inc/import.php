<?php
defined('ABSPATH') || exit;

/**
 * Renders the CSV Product Import admin page with AJAX batch import controls.
 */
function tyreorder_import_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('Access denied', 'tyreorder-api'));
    }

    if (!class_exists('WooCommerce')) {
        echo '<div class="notice notice-error"><p>' . esc_html__('WooCommerce is not active. Please activate it to use the import.', 'tyreorder-api') . '</p></div>';
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('CSV Product Import', 'tyreorder-api'); ?></h1>
        <p><?php esc_html_e('Import or update products in stock from cached Tyreorder CSV.', 'tyreorder-api'); ?></p>

        <button type="button" id="tyreorder-import-batch-btn" class="button button-primary">
            <?php esc_html_e('Import All Products', 'tyreorder-api'); ?>
        </button>
        <div id="tyreorder-import-progress" style="margin-top:15px;"></div>
    </div>
    <?php
}

/**
 * Import or update WooCommerce products from cached CSV data in batches.
 *
 * @param int $offset Start row (excluding header).
 * @param int $batch_size Number of products to process.
 * @param bool $single If true, import only the first in-stock product found.
 * @return array|false Array with results or false on error.
 */
function tyreorder_import_products_from_csv_batch($offset = 0, $batch_size = 20, $single = false) {
    if (!class_exists('WooCommerce')) {
        return false;
    }

    $file = tyreorder_csv_cache_file();
    if (!file_exists($file)) {
        return false;
    }

    $handle = fopen($file, 'r');
    if (!$handle) {
        return false;
    }

    $header = null;
    $created = 0;
    $updated = 0;
    $skipped = 0;
    $tyre_cat_id = tyreorder_get_or_create_tyres_category_id();
    $row_num = 0;
    $processed = 0;

    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        if (!$header) {
            $header = $row;
            continue;
        }
        $row_num++;
        if ($row_num <= $offset) continue;
        if ($processed >= $batch_size) break;

        $data = array_combine($header, $row);
        if (!$data) { $skipped++; $processed++; continue; }

        $sku = trim($data['original code'] ?? '');
        $stock = intval(($data['storage main'] ?? 0)) + intval(($data['storage manufacturer'] ?? 0));
        if (empty($sku) || $stock < 1) { $skipped++; $processed++; continue; }

        $product_id = wc_get_product_id_by_sku($sku);
        $title_pieces = array_filter([$data['company'] ?? '', $data['pattern'] ?? '', $data['measure'] ?? '']);
        $title = trim(implode(' ', $title_pieces));
        if (empty($title)) $title = 'Tyre ' . $sku;
        $regular_price = floatval($data['retail price'] ?? 0);
        $image_url = $data['image'] ?? '';

        $meta_input = [
            '_sku'           => $sku,
            '_regular_price' => $regular_price,
            '_price'         => $regular_price,
            '_stock'         => $stock,
            '_stock_status'  => $stock > 0 ? 'instock' : 'outofstock',
            '_manage_stock'  => 'yes',
        ];

        if ($product_id) {
            $product = wc_get_product($product_id);
            if (!$product) { $skipped++; $processed++; continue; }
            $product->set_name($title);
            $product->set_regular_price($regular_price);
            $product->set_price($regular_price);
            $product->set_stock_quantity($stock);
            $product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
            if ($tyre_cat_id) $product->set_category_ids([$tyre_cat_id]);
            if (!empty($image_url)) {
                $attach_id = tyreorder_media_sideload_image($image_url, $product_id);
                if ($attach_id) $product->set_image_id($attach_id);
            }
            $product->save();
            $updated++;
        } else {
            $post_args = [
                'post_title'  => $title,
                'post_status' => 'publish',
                'post_type'   => 'product',
                'meta_input'  => $meta_input,
                'tax_input'   => $tyre_cat_id ? ['product_cat' => [$tyre_cat_id]] : [],
            ];
            $new_id = wp_insert_post($post_args);
            if (!$new_id || is_wp_error($new_id)) { $skipped++; $processed++; continue; }
            $product = wc_get_product($new_id);
            if ($product && !empty($image_url)) {
                $attach_id = tyreorder_media_sideload_image($image_url, $new_id);
                if ($attach_id) set_post_thumbnail($new_id, $attach_id);
                $product->save();
            }
            $created++;
        }
        $processed++;

        if ($single) break;
    }
    fclose($handle);

    // Count total rows (excluding header)
    $total = 0;
    if (($handle2 = fopen($file, 'r')) !== false) {
        $header2 = fgetcsv($handle2, 0, ';');
        while (fgetcsv($handle2, 0, ';') !== false) $total++;
        fclose($handle2);
    }

    return [
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
        'processed' => $processed,
        'total' => $total,
        'next_offset' => $offset + $processed,
        'done' => ($offset + $processed) >= $total,
    ];
}

/**
 * Get existing attachment ID by image URL.
 *
 * @param string $image_url
 * @return int|false Attachment ID if found, or false
 */
function tyreorder_get_existing_attachment_id_by_url($image_url) {
    global $wpdb;

    // Extract filename from URL
    $filename = basename( parse_url( $image_url, PHP_URL_PATH ) );

    if ( empty( $filename ) ) {
        return false;
    }

    // Query attachment posts with meta _wp_attached_file that contains the filename
    $attachment_id = $wpdb->get_var( $wpdb->prepare(
        "
        SELECT post_id FROM {$wpdb->postmeta} 
        WHERE meta_key = '_wp_attached_file' 
        AND meta_value LIKE %s
        LIMIT 1
        ",
        '%' . $wpdb->esc_like( $filename )
    ));

    if ( $attachment_id ) {
        return (int) $attachment_id;
    }

    return false;
}

/**
 * Enhanced media sideload function reusing existing images when found.
 *
 * @param string $url Image URL.
 * @param int $post_id Parent post ID.
 * @param string|null $desc Optional description.
 * @return int|false Attachment ID or false on failure.
 */
function tyreorder_media_sideload_image($url, $post_id = 0, $desc = null) {
    if (empty($url)) {
        return false;
    }

    // Try to find existing image attachment by this URL first
    $existing_id = tyreorder_get_existing_attachment_id_by_url($url);
    if ($existing_id) {
        return $existing_id;
    }

    // No existing attachment found; sideload new image
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $tmp = download_url($url);
    if (is_wp_error($tmp)) {
        return false;
    }

    $file_array = [
        'name'     => basename(parse_url($url, PHP_URL_PATH)),
        'tmp_name' => $tmp,
    ];

    $id = media_handle_sideload($file_array, $post_id, $desc);

    if (is_wp_error($id)) {
        @unlink($tmp);
        return false;
    }

    return $id;
}

/**
 * Get the Tyres product category ID by slug.
 *
 * @return int|0 Category ID or 0 if not found.
 */
function tyreorder_get_or_create_tyres_category_id() {
    $slug = 'tyres'; // change to your category slug
    $term = get_term_by('slug', $slug, 'product_cat');
    if ($term && !is_wp_error($term)) {
        return (int) $term->term_id;
    }

    $new_term = wp_insert_term('Tyres', 'product_cat', [
        'slug' => $slug,
        'description' => 'Tyre products',
        'parent' => 0,
    ]);

    if (is_wp_error($new_term)) {
        // Failed to create category, return 0 to indicate failure
        return 0;
    }

    return (int) $new_term['term_id'];
}

$category_name = get_option('tyreorder_category_name', 'Tyres');

/**
 * AJAX handler for batch importing products from cached CSV.
 * Processes a batch of products and returns progress.
 */
add_action('wp_ajax_tyreorder_import_products_batch', function() {
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'tyreorder_update_products')) {
        wp_send_json_error('Invalid nonce');
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $batch_size = intval(get_option('tyreorder_product_import_batch', 20));

    $result = tyreorder_import_products_from_csv_batch($offset, $batch_size, false);
    if ($result === false) {
        wp_send_json_error('Import failed or CSV not found.');
    }

    wp_send_json_success([
        'created' => $result['created'],
        'updated' => $result['updated'],
        'skipped' => $result['skipped'],
        'offset'  => $result['next_offset'],
        'total'   => $result['total'],
        'done'    => $result['done'],
        'message' => sprintf(
            __('Batch: created %d, updated %d, skipped %d. (%d/%d)', 'tyreorder-api'),
            $result['created'], $result['updated'], $result['skipped'], $result['next_offset'], $result['total']
        )
    ]);
});

/**
 * Enqueue scripts for the import page.
 */
add_action('admin_enqueue_scripts', function($hook){
    if (strpos($hook, 'tyreorder-import') === false) return;
    wp_enqueue_script(
        'tyreorder-import-batch-js',
        plugin_dir_url(__DIR__) . 'inc/js/import-batch.js',
        ['jquery'],
        '1.1',
        true
    );
    wp_localize_script('tyreorder-import-batch-js', 'tyreorder_import_ajax', [
        'nonce' => wp_create_nonce('tyreorder_update_products'),
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
});
