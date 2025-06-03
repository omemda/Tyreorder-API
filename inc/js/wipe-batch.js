// Wipe for Products
jQuery(document).ready(function($) {
    var wiping = false;
    var stopRequested = false;

    function wipeBatch(onlyOutOfStock) {
        if (stopRequested) {
            showNotice($('#wipe-progress'), 'warning', 'Wipe stopped by user.');
            wiping = false;
            stopRequested = false;
            $('#stop-wipe').hide();
            $('#start-wipe-outstock, #start-wipe-all').prop('disabled', false);
            return;
        }
        wiping = true;

        $.post(tyreorder_ajax.ajaxurl, {
            action: 'tyreorder_wipe_products_batch',
            security: tyreorder_ajax.nonce,
            only_out_of_stock: onlyOutOfStock ? '1' : '0',
        }, function(response) {
            if (!response.success) {
                showNotice($('#wipe-progress'), 'error', 'Error: ' + response.data);
                wiping = false;
                $('#stop-wipe').hide();
                $('#start-wipe-outstock, #start-wipe-all').prop('disabled', false);
                return;
            }
            var data = response.data;
            showNotice($('#wipe-progress'), 'success', 'Deleted ' + data.deleted + ' products in this batch. ' + data.remaining + ' products remaining.');

            if (data.remaining > 0) {
                setTimeout(function() {
                    wipeBatch(onlyOutOfStock);
                }, 200);
            } else {
                showNotice($('#wipe-progress'), 'success', 'Wipe complete! All requested products deleted.');
                wiping = false;
                $('#stop-wipe').hide();
                $('#start-wipe-outstock, #start-wipe-all').prop('disabled', false);
            }
        });
    }

    $('#start-wipe-outstock').on('click', function(e) {
        e.preventDefault();
        if (wiping) return;
        if (!confirm('Confirm: Delete ALL out-of-stock products in batches? This action is irreversible!')) return;
        stopRequested = false;
        $('#start-wipe-outstock, #start-wipe-all').prop('disabled', true);
        $('#stop-wipe').show();
        wipeBatch(true);
    });

    $('#start-wipe-all').on('click', function(e) {
        e.preventDefault();
        if (wiping) return;
        if (!confirm('Confirm: Delete ALL WooCommerce products in batches? This action is irreversible!')) return;
        stopRequested = false;
        $('#start-wipe-outstock, #start-wipe-all').prop('disabled', true);
        $('#stop-wipe').show();
        wipeBatch(false);
    });

    $('#stop-wipe').on('click', function(e) {
        e.preventDefault();
        if (!wiping) return;
        stopRequested = true;
        showNotice($('#wipe-progress'), 'warning', 'Stopping wipe... please wait');
        $(this).prop('disabled', true);
    });
});

// Wipe for Product Images

jQuery(document).ready(function($){
    function wipeImagesBatch() {
        var $btn = $('#tyreorder-wipe-all-button');
        var $progress = $('#tyreorder-wipe-all-progress');
        $btn.prop('disabled', true);

        $.post(tyreorder_ajax.ajaxurl, {
            action: 'tyreorder_wipe_all_images',
            security: tyreorder_ajax.image_wipe_nonce
        }, function(response) {
            if (response.success) {
                showNotice($progress, 'success', response.data.message);
                if (response.data.remaining > 0) {
                    setTimeout(wipeImagesBatch, 200); // Continue with next batch
                } else {
                    showNotice($progress, 'success', 'Wipe complete! ' + response.data.message);
                    $btn.prop('disabled', false);
                }
            } else {
                showNotice($progress, 'error', 'Error: ' + response.data);
                $btn.prop('disabled', false);
            }
        });
    }

    $('#tyreorder-wipe-all-button').on('click', function(e){
        e.preventDefault();

        if (!confirm('Are you sure you want to wipe ALL tyre images? This cannot be undone.')) {
            return;
        }

        $('#tyreorder-wipe-all-progress').text('Starting wipe...');
        wipeImagesBatch();
    });
});

function showNotice($el, type, message) {
    var noticeClass = 'notice notice-' + type + ' is-dismissible';
    $el.html('<div class="' + noticeClass + '"><p>' + message + '</p></div>');
    // Trigger WP event for dismissible notices
    if (typeof window.wp !== 'undefined' && window.wp && window.wp.hooks) {
        jQuery(document).trigger('wp-updates-notice-added');
    }
}