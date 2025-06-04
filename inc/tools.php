<?php
defined('ABSPATH') || exit;

/**
 * Render the batch product wipe admin page.
 * Can be removed if you embed wipe buttons elsewhere.
 */
function tyreorder_product_wipe_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( __( 'Access denied', 'tyreorder-api' ) );
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Tyreorder Product Wipe', 'tyreorder-api' ); ?></h1>
        <p><?php esc_html_e( 'Safely delete WooCommerce products in batches to avoid server overload. Choose to wipe all products or only out-of-stock ones.', 'tyreorder-api' ); ?></p>

        <?php
        // Render your batch wipe buttons (defined elsewhere)
        if ( function_exists( 'tyreorder_render_batch_wipe_buttons' ) ) {
            tyreorder_render_batch_wipe_buttons();
        }
        ?>

        <hr />

        <h2><?php esc_html_e( 'Tyreorder Product Image Wipe', 'tyreorder-api' ); ?></h2>
        <p><?php esc_html_e( 'Back up your website and database before using this function. Use at your own risk.', 'tyreorder-api' ); ?></p>

        <form method="post" id="tyreorder-wipe-all-form" style="display:inline-block; margin-right:10px;">
            <button type="button" id="tyreorder-wipe-all-button" class="button button-danger">
                <?php esc_html_e( 'Wipe All Tyre Images', 'tyreorder-api' ); ?>
            </button>
        </form>
        <button type="button" id="tyreorder-wipe-all-cancel" class="button" style="display:none; margin-left:10px;">
            <?php esc_html_e('Cancel Wipe', 'tyreorder-api'); ?>
        </button>
        <div id="tyreorder-wipe-all-progress" style="margin-top:10px;"></div>
        <span id="wipe-spinner" class="spinner" style="float:none;display:none;vertical-align:middle;"></span>
    </div>
    <?php
}

/**
 * Render batch wipe buttons UI with progress and stop placeholders.
 * You can call this anywhere you want to show these controls.
 */
function tyreorder_render_batch_wipe_buttons()
{
    ?>
    <button id="start-wipe-outstock" class="button" data-out-stock="1" style="margin-right:10px;">
        <?php esc_html_e('Wipe Out-of-Stock Products', 'tyreorder-api'); ?>
    </button>

    <button id="start-wipe-all" class="button button-danger"
            style="color:#fff; background:#d63638; border-color:#d63638;">
        <?php esc_html_e('Wipe ALL Products', 'tyreorder-api'); ?>
    </button>

    <button id="stop-wipe" class="button" style="margin-left:20px; display:none;">
        <?php esc_html_e('Stop Wipe', 'tyreorder-api'); ?>
    </button>

    <div id="wipe-progress" style="margin-top:15px;"></div>
    <?php
}

/**
 * AJAX handler for batch deleting WooCommerce products.
 */
add_action('wp_ajax_tyreorder_wipe_products_batch', 'tyreorder_wipe_products_batch_handler');

function tyreorder_wipe_products_batch_handler()
{
    check_ajax_referer('tyreorder-delete-batch', 'security');

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
        wp_die();
    }

    $only_out_of_stock = !empty($_POST['only_out_of_stock']) && $_POST['only_out_of_stock'] === '1';
    $batch_size       = intval(get_option('tyreorder_product_wipe_batch', 100));

    $args = [
        'post_type'      => 'product',
        'post_status'    => 'any',
        'posts_per_page' => $batch_size,
        'fields'         => 'ids',
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ];

    if ($only_out_of_stock) {
        $args['meta_query'] = [[
            'key'     => '_stock_status',
            'value'   => 'outofstock',
            'compare' => '=',
        ]];
    }

    $products = get_posts($args);
    if (empty($products)) {
        wp_send_json_success([
            'deleted'   => 0,
            'remaining' => 0,
            'message'   => __('No more products to delete.', 'tyreorder-api'),
        ]);
        wp_die();
    }

    $deleted = 0;
    foreach ($products as $product_id) {
        // Delete featured image
        $thumbnail_id = get_post_thumbnail_id($product_id);
        if ($thumbnail_id) {
            wp_delete_attachment($thumbnail_id, true);
        }

        // Delete gallery images
        $gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);
        if ($gallery_ids) {
            $gallery_ids = explode(',', $gallery_ids);
            foreach ($gallery_ids as $gallery_id) {
                wp_delete_attachment($gallery_id, true);
            }
        }

        // Now delete the product
        wp_delete_post($product_id, true);
        $deleted++;
    }

    // Check if more products remain after deleting current batch
    $remaining_args = $args;
    $remaining_args['posts_per_page'] = 1;
    $remaining_posts = get_posts($remaining_args);
    $remaining = count($remaining_posts) > 0 ? 1 : 0; // Approximate

    wp_send_json_success([
        'deleted'   => $deleted,
        'remaining' => $remaining,
    ]);
}

/**
 * Remove all media library attachments whose file path or URL contains 'muster_terve'.
 *
 * @return int Number of deleted attachments.
 */
add_action('wp_ajax_tyreorder_wipe_all_images', 'tyreorder_wipe_all_images_callback');

function tyreorder_wipe_all_images_callback() {
    // Verify nonce for security
    if (empty($_POST['security']) || !wp_verify_nonce($_POST['security'], 'tyreorder-wipe-images-nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    // Capability check
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
    }

    global $wpdb;
    $batch_size = 100;
    $like = '%' . $wpdb->esc_like('muster_terve') . '%';

    // Fetch batch of attachment IDs matching the pattern
    $attachment_ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE p.post_type = 'attachment'
           AND pm.meta_key = '_wp_attached_file'
           AND pm.meta_value LIKE %s
         LIMIT %d",
        $like,
        $batch_size
    ));

    if (empty($attachment_ids)) {
        wp_send_json_success([
            'deleted'   => 0,
            'remaining' => 0,
            'message'   => 'No matching images found to delete.',
        ]);
    }

    $deleted = 0;

    foreach ($attachment_ids as $attachment_id) {
        if (wp_delete_attachment($attachment_id, true)) {
            $deleted++;
        }
    }

    // Get count of remaining attachments matching pattern
    $remaining = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE p.post_type = 'attachment'
           AND pm.meta_key = '_wp_attached_file'
           AND pm.meta_value LIKE %s",
        $like
    ));

    wp_send_json_success([
        'deleted'   => $deleted,
        'remaining' => $remaining,
        'message'   => sprintf('Deleted %d images in this batch. %d remaining.', $deleted, $remaining),
    ]);
}

/**
 * Enqueue JavaScript for batch wiping with progress and stop controls.
 */
add_action('admin_enqueue_scripts', function ($hook) {
    // Load on your plugin's admin pages only—to avoid unnecessary load elsewhere
    if (strpos($hook, 'tyreorder-admin') === false && strpos($hook, 'tyreorder-product-wipe') === false) {
        return;
    }

    wp_enqueue_script(
        'tyreorder-wipe-batch-js',
        plugin_dir_url(__DIR__) . 'inc/js/wipe-batch.js',
        ['jquery'],
        '1.5.2',
        true
    );

    wp_localize_script('tyreorder-wipe-batch-js', 'tyreorder_ajax', [
        'nonce'   => wp_create_nonce('tyreorder-delete-batch'),
        'image_wipe_nonce' => wp_create_nonce('tyreorder-wipe-images-nonce'),
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
});