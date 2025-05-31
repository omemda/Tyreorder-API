<?php
defined('ABSPATH') || exit;

/**
 * Renders the CSV Product Import admin page with import controls.
 */
if (!function_exists('tyreorder_import_page')) :
function tyreorder_import_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('Access denied', 'tyreorder-api'));
    }

    if (!class_exists('WooCommerce')) {
        echo '<div class="notice notice-error"><p>' . esc_html__('WooCommerce is not active. Please activate it to use the import.', 'tyreorder-api') . '</p></div>';
        return;
    }

    $message = '';

    if (
        isset($_POST['import_single_product']) &&
        check_admin_referer('tyreorder_update_products')
    ) {
        $message = tyreorder_update_products_from_csv(true);
    } elseif (
        isset($_POST['import_all_products']) &&
        check_admin_referer('tyreorder_update_products')
    ) {
        $message = tyreorder_update_products_from_csv(false);
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('CSV Product Import', 'tyreorder-api'); ?></h1>
        <p><?php esc_html_e('Import or update products in stock from cached Tyreorder CSV.', 'tyreorder-api'); ?></p>

        <form method="post">
            <?php wp_nonce_field('tyreorder_update_products'); ?>
            <input type="submit" class="button button-secondary" name="import_single_product" value="<?php esc_attr_e('Import One Product', 'tyreorder-api'); ?>" />
            <input type="submit" class="button button-primary" name="import_all_products" value="<?php esc_attr_e('Import All Products', 'tyreorder-api'); ?>" />
        </form>

        <?php if ($message) : ?>
            <div class="notice notice-success is-dismissible" style="margin-top: 15px;">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
endif;

/**
 * Import or update WooCommerce products from cached CSV data.
 *
 * @param bool $single If true, import only the first in-stock product found.
 * @return string Summary message of the import result.
 */
if (!function_exists('tyreorder_update_products_from_csv')) :
function tyreorder_update_products_from_csv($single = false)
{
    if (!class_exists('WooCommerce')) {
        return __('WooCommerce plugin is not active.', 'tyreorder-api');
    }

    $file = tyreorder_csv_cache_file();

    if (!file_exists($file)) {
        return __('CSV cache file not found. Please redownload the CSV first.', 'tyreorder-api');
    }

    $handle = fopen($file, 'r');
    if (!$handle) {
        return __('Could not open cached CSV file.', 'tyreorder-api');
    }

    $header = null;
    $created = 0;
    $updated = 0;
    $skipped = 0;
    $tyre_cat_id = tyreorder_get_tyre_category_id();

    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        if (!$header) {
            $header = $row;
            continue;
        }

        $data = array_combine($header, $row);
        if (!$data) {
            $skipped++;
            continue;
        }

        $sku = trim($data['original code'] ?? '');
        $stock = intval(($data['storage main'] ?? 0)) + intval(($data['storage manufacturer'] ?? 0));

        if (empty($sku) || $stock < 1) {
            $skipped++;
            continue;
        }

        $product_id = wc_get_product_id_by_sku($sku);
        $title_pieces = array_filter([
            $data['company'] ?? '',
            $data['pattern'] ?? '',
            $data['measure'] ?? ''
        ]);
        $title = trim(implode(' ', $title_pieces));
        if (empty($title)) {
            $title = 'Tyre ' . $sku;
        }

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
            if (!$product) {
                $skipped++;
                continue;
            }
            $product->set_name($title);
            $product->set_regular_price($regular_price);
            $product->set_price($regular_price);
            $product->set_stock_quantity($stock);
            $product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
            if ($tyre_cat_id) {
                $product->set_category_ids([$tyre_cat_id]);
            }
            if (!empty($image_url)) {
                $attach_id = tyreorder_media_sideload_image($image_url, $product_id);
                if ($attach_id) {
                    $product->set_image_id($attach_id);
                }
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
            if (!$new_id || is_wp_error($new_id)) {
                $skipped++;
                continue;
            }
            $product = wc_get_product($new_id);
            if ($product && !empty($image_url)) {
                $attach_id = tyreorder_media_sideload_image($image_url, $new_id);
                if ($attach_id) {
                    set_post_thumbnail($new_id, $attach_id);
                }
                $product->save();
            }
            $created++;
        }

        if ($single) {
            break;
        }
    }

    fclose($handle);

    return sprintf(
        /* translators: 1:number created 2:number updated 3:number skipped */
        __('Products created: %1$d, updated: %2$d, skipped: %3$d.', 'tyreorder-api'),
        $created,
        $updated,
        $skipped
    );
}
endif;

/**
 * Sideload image by URL to Media Library.
 *
 * @param string $url Image URL.
 * @param int    $post_id Post ID to attach image to.
 * @param string|null $desc Optional image description.
 * @return int|false Attachment ID or false on failure.
 */
if (!function_exists('tyreorder_media_sideload_image')) :
function tyreorder_media_sideload_image($url, $post_id = 0, $desc = null)
{
    if (empty($url)) {
        return false;
    }

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
endif;

/**
 * Get the Tyre product category ID by slug.
 *
 * @return int|0 Category ID or 0 if not found.
 */
if (!function_exists('tyreorder_get_tyre_category_id')) :
function tyreorder_get_tyre_category_id() {
    $term = get_term_by('slug', 'tyre', 'product_cat');
    if ($term && !is_wp_error($term)) {
        return (int) $term->term_id;
    }
    return 0;
}
endif;
