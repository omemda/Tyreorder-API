<?php
defined('ABSPATH') || exit;

/**
 * Render the Product Wipe admin buttons.
 */
function tyreorder_render_batch_wipe_buttons() {
    ?>
    <h2><?php esc_html_e('Product Wipe Controls', 'tyreorder-api'); ?></h2>
    <p>Automatic cleaning for your Wordpress installation. Backup before proceeding! 100 per batch.</p>
    <button id="start-wipe-outstock" class="button" data-out-stock="1" style="margin-right: 10px;">
        <?php esc_html_e('Wipe Out-of-Stock Products', 'tyreorder-api'); ?>
    </button>
    <button id="start-wipe-all" class="button button-danger" style="color:#fff; background:#d63638; border-color:#d63638;">
        <?php esc_html_e('Wipe ALL Products', 'tyreorder-api'); ?>
    </button>
    <button id="stop-wipe" class="button" style="margin-left: 20px; display: none;">
        <?php esc_html_e('Stop Wipe', 'tyreorder-api'); ?>
    </button>
    <div id="wipe-progress" style="margin-top: 20px; font-weight: bold;"></div>
    <?php
}

/**
 * AJAX handler for batch deleting products.
 */
add_action('wp_ajax_tyreorder_wipe_products_batch', 'tyreorder_wipe_products_batch_handler');
function tyreorder_wipe_products_batch_handler()
{
    check_ajax_referer('tyreorder-delete-batch', 'security');

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
        wp_die();
    }

    $only_out_of_stock = isset($_POST['only_out_of_stock']) && $_POST['only_out_of_stock'] === '1';
    $batch_size = 100;

    $args = [
        'post_type'      => 'product',
        'post_status'    => 'any',
        'posts_per_page' => $batch_size,
        'fields'         => 'ids',
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ];

    if ($only_out_of_stock) {
        $args['meta_query'] = [
            [
                'key'     => '_stock_status',
                'value'   => 'outofstock',
                'compare' => '=',
            ]
        ];
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

    // Because we just deleted a batch, check if more products remain
    $remaining_args = $args;
    $remaining_args['posts_per_page'] = 1;
    $remaining_posts = get_posts($remaining_args);
    $remaining = count($remaining_posts) > 0 ? 1 : 0;

    wp_send_json_success([
        'deleted'   => $deleted,
        'remaining' => $remaining,
    ]);
}

/**
 * Enqueue Admin JS for batch wiping with stop support.
 */
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'tyreorder-import') === false && strpos($hook, 'tyreorder-admin') === false) {
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
        'nonce' => wp_create_nonce('tyreorder-delete-batch'),
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
});