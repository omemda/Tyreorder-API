jQuery(document).ready(function($){
    $('#tyreorder-import-batch-btn').on('click', function(e){
        e.preventDefault();
        if (!confirm('Import all products from CSV in batches?')) return;
        $('#tyreorder-import-batch-btn').prop('disabled', true);
        showNotice($('#tyreorder-import-progress'), 'info', 'Starting import...');
        importBatch(0, 0, 0, 0);
    });

    function importBatch(offset, totalCreated, totalUpdated, totalSkipped) {
        $.post(tyreorder_import_ajax.ajaxurl, {
            action: 'tyreorder_import_products_batch',
            security: tyreorder_import_ajax.nonce,
            offset: offset
        }, function(response){
            if (!response.success) {
                showNotice($('#tyreorder-import-progress'), 'error', 'Error: ' + response.data);
                $('#tyreorder-import-batch-btn').prop('disabled', false);
                return;
            }
            var data = response.data;
            totalCreated += data.created;
            totalUpdated += data.updated;
            totalSkipped += data.skipped;
            showNotice($('#tyreorder-import-progress'), 'success', data.message);

            if (!data.done) {
                setTimeout(function(){
                    importBatch(data.offset, totalCreated, totalUpdated, totalSkipped);
                }, 200);
            } else {
                showNotice($('#tyreorder-import-progress'), 'success',
                    'Import complete! Created: ' + totalCreated + ', Updated: ' + totalUpdated + ', Skipped: ' + totalSkipped + '.'
                );
                $('#tyreorder-import-batch-btn').prop('disabled', false);
            }
        });
    }
});

// Reuse your showNotice function from wipe-batch.js
function showNotice($el, type, message) {
    var noticeClass = 'notice notice-' + type + ' is-dismissible';
    $el.html('<div class="' + noticeClass + '"><p>' + message + '</p></div>');
    jQuery(document).trigger('wp-updates-notice-added');
}