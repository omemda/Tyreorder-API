jQuery(document).ready(function($) {
    var wiping = false;
    var stopRequested = false;

    function wipeBatch(onlyOutOfStock) {
        if (stopRequested) {
            $('#wipe-progress').text('Wipe stopped by user.');
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
                $('#wipe-progress').text('Error: ' + response.data);
                wiping = false;
                $('#stop-wipe').hide();
                $('#start-wipe-outstock, #start-wipe-all').prop('disabled', false);
                return;
            }
            var data = response.data;
            $('#wipe-progress').text('Deleted ' + data.deleted + ' products in this batch.');

            if (data.remaining > 0) {
                setTimeout(function() {
                    wipeBatch(onlyOutOfStock);
                }, 200);
            } else {
                $('#wipe-progress').text('Wipe complete! All requested products deleted.');
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
        $('#wipe-progress').text('Stopping wipe... please wait');
        $(this).prop('disabled', true);
    });
});