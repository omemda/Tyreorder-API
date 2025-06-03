<?php
defined('ABSPATH') || exit;

/**
 * Render the batch product wipe admin page.
 * Can be removed if you embed wipe buttons elsewhere.
 */
function tyreorder_product_wipe_page()
{
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('Access denied', 'tyreorder-api'));
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Tyreorder Product Wipe', 'tyreorder-api'); ?></h1>
        <p><?php esc_html_e('Safely delete WooCommerce products in batches to avoid server overload. Choose to wipe all products or only out-of-stock ones.', 'tyreorder-api'); ?></p>

        <?php tyreorder_render_batch_wipe_buttons(); ?>
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
        <?php esc_html_e('Wipe Out-of-Stock Products (Batched)', 'tyreorder-api'); ?>
    </button>

    <button id="start-wipe-all" class="button button-danger"
            style="color:#fff; background:#d63638; border-color:#d63638;">
        <?php esc_html_e('Wipe ALL Products (Batched)', 'tyreorder-api'); ?>
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
    if (strpos($hook, 'tyreorder-admin') === false && strpos($hook, 'tyreorder-import') === false) {
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
