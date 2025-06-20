// Wipe for Products
jQuery(document).ready(function($) {
    var wiping = false;
    var stopRequested = false;

    // Add a spinner next to the progress area if you want (optional)
    if ($('#wipe-progress').next('.spinner').length === 0) {
        $('#wipe-progress').after('<span id="wipe-spinner" class="spinner" style="float:none;display:none;vertical-align:middle;"></span>');
    }

    function wipeBatch(onlyOutOfStock) {
        if (stopRequested) {
            showNotice($('#wipe-progress'), 'warning', 'Wipe stopped by user.');
            wiping = false;
            stopRequested = false;
            $('#stop-wipe').hide();
            $('#start-wipe-outstock, #start-wipe-all').prop('disabled', false);
            $('#wipe-spinner').hide();
            return;
        }
        wiping = true;
        showNotice($('#wipe-progress'), 'info', 'Processing batch...');
        $('#wipe-spinner').show();

        $.post(tyreorder_ajax.ajaxurl, {
            action: 'tyreorder_wipe_products_batch',
            security: tyreorder_ajax.nonce,
            only_out_of_stock: onlyOutOfStock ? '1' : '0',
        }, function(response) {
            $('#wipe-spinner').hide();
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
        showNotice($('#wipe-progress'), 'info', 'Processing batch...');
        $('#wipe-spinner').show();
        wipeBatch(true);
    });

    $('#start-wipe-all').on('click', function(e) {
        e.preventDefault();
        if (wiping) return;
        if (!confirm('Confirm: Delete ALL WooCommerce products in batches? This action is irreversible!')) return;
        stopRequested = false;
        $('#start-wipe-outstock, #start-wipe-all').prop('disabled', true);
        $('#stop-wipe').show();
        showNotice($('#wipe-progress'), 'info', 'Processing batch...');
        $('#wipe-spinner').show();
        wipeBatch(false);
    });

    $('#stop-wipe').on('click', function(e) {
        e.preventDefault();
        if (!wiping) return;
        stopRequested = true;
        showNotice($('#wipe-progress'), 'warning', 'Stopping wipe... Please wait.');
        $(this).prop('disabled', true);
        $('#wipe-spinner').show();
    });
});

// Wipe for Product Images
jQuery(document).ready(function($){
    var stopRequestedImages = false;

    function wipeImagesBatch() {
        if (stopRequestedImages) {
            showNotice($('#tyreorder-wipe-all-progress'), 'warning', 'Wipe stopped by user.');
            $('#tyreorder-wipe-all-cancel').hide();
            $('#tyreorder-wipe-all-button').prop('disabled', false);
            stopRequestedImages = false;
            return;
        }

        $('#tyreorder-wipe-all-button').prop('disabled', true);
        $('#tyreorder-wipe-all-cancel').show();

        $.post(tyreorder_ajax.ajaxurl, {
            action: 'tyreorder_wipe_all_images',
            security: tyreorder_ajax.image_wipe_nonce
        }, function(response) {
            if (response.success) {
                showNotice($('#tyreorder-wipe-all-progress'), 'success', response.data.message);
                if (response.data.remaining > 0) {
                    setTimeout(wipeImagesBatch, 200); // Continue with next batch
                } else {
                    showNotice($('#tyreorder-wipe-all-progress'), 'success', 'Wipe complete! ' + response.data.message);
                    $('#tyreorder-wipe-all-cancel').hide();
                    $('#tyreorder-wipe-all-button').prop('disabled', false);
                    stopRequestedImages = false;
                }
            } else {
                showNotice($('#tyreorder-wipe-all-progress'), 'error', 'Error: ' + response.data);
                $('#tyreorder-wipe-all-cancel').hide();
                $('#tyreorder-wipe-all-button').prop('disabled', false);
                stopRequestedImages = false;
            }
        });
    }

    $('#tyreorder-wipe-all-button').on('click', function(e){
        e.preventDefault();

        if (!confirm('Are you sure you want to wipe ALL tyre images? This cannot be undone.')) {
            return;
        }

        showNotice($('#tyreorder-wipe-all-progress'), 'info', 'Starting wipe...');
        stopRequestedImages = false;
        wipeImagesBatch();
    });

    $('#tyreorder-wipe-all-cancel').on('click', function(e){
        e.preventDefault();
        stopRequestedImages = true;
        showNotice($('#tyreorder-wipe-all-progress'), 'warning', 'Stopping wipe... Please wait.');
    });
});

function showNotice($el, type, message) {
    var noticeClass = 'notice notice-' + type + ' is-dismissible';
    $el.html('<div class="' + noticeClass + '"><p>' + message + '</p></div>');
    jQuery(document).trigger('wp-updates-notice-added');
}