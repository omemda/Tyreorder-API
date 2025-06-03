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
            <?php wp_nonce_field( 'tyreorder_wipe_all_action', 'tyreorder_wipe_all_nonce' ); ?>
            <button type="button" id="tyreorder-wipe-all-button" class="button button-danger">
                <?php esc_html_e( 'Wipe All Tyre Images', 'tyreorder-api' ); ?>
            </button>
        </form>

        <div id="tyreorder-wipe-all-progress" style="margin-top:10px; font-weight:bold;"></div>
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

    <div id="wipe-progress" style="margin-top:15px; font-weight:bold;"></div>
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
    $batch_size       = 100;

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
        if (wp_delete_post($product_id, true)) {
            $deleted++;
        }
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
        '1.0',
        true
    );

    wp_localize_script('tyreorder-wipe-batch-js', 'tyreorder_ajax', [
        'nonce'   => wp_create_nonce('tyreorder-delete-batch'),
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
});


/**
 * Remove all media library attachments whose file path or URL contains 'muster_terve'.
 *
 * @return int Number of deleted attachments.
 */
add_action('wp_ajax_tyreorder_wipe_all_images', function() {
    // Verify nonce for security (expecting nonce sent as 'security' POST field)
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'tyreorder-wipe-images-nonce')) {
        wp_send_json_error('Invalid nonce');
        wp_die();
    }

    // Check permissions
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
        wp_die();
    }

    // Batch size per AJAX call to avoid timeout; adjust as needed
    $batch_size = 100;
    
    // Query attachments attached to WooCommerce products only
    $attachments_query = [
        'post_type'      => 'attachment',
        'posts_per_page' => $batch_size,
        'post_status'    => 'inherit',
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => '_wp_attachment_context',
                'value'   => 'woocommerce',
                'compare' => 'LIKE',
            ]
        ],
        // Alternatively, query by post_parent of products, or by mime type
        'meta_key'       => '_wp_attached_to_product',
        // You may refine further to only product images linked via featured image or gallery
    ];

    // Attempt to get product attachment IDs
    $attachments = get_posts($attachments_query);

    if (empty($attachments)) {
        wp_send_json_success(['deleted' => 0, 'remaining' => 0, 'message' => 'No product images found to delete.']);
        wp_die();
    }

    $deleted = 0;
    foreach ($attachments as $attachment_id) {
        if (wp_delete_attachment($attachment_id, true)) {
            $deleted++;
        }
    }

    // Check if any attachments remain (approximate)
    $remaining_attachments = get_posts($attachments_query);
    $remaining = count($remaining_attachments);

    wp_send_json_success([
        'deleted'   => $deleted,
        'remaining' => $remaining,
        'message'   => sprintf('Deleted %d product images in this batch. %d remaining.', $deleted, $remaining),
    ]);
    wp_die();
});