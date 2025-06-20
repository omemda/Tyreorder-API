jQuery(document).ready(function($){
    var stopRequestedImport = false;

    $('#tyreorder-import-batch-btn').on('click', function(e){
        e.preventDefault();
        if (!confirm('Import all products from CSV in batches?')) return;
        $('#tyreorder-import-batch-btn').prop('disabled', true);
        $('#tyreorder-import-cancel-btn').show();
        $('#tyreorder-import-spinner').show();
        showNotice($('#tyreorder-import-progress'), 'info', 'Starting import...');
        stopRequestedImport = false;
        setTimeout(function() {
            importBatch(0, 0, 0, 0);
        }, 100); // slight delay for UI update
    });

    $('#tyreorder-import-cancel-btn').on('click', function(e){
        e.preventDefault();
        stopRequestedImport = true;
        showNotice($('#tyreorder-import-progress'), 'warning', 'Stopping import... Please wait.');
        $('#tyreorder-import-spinner').show();
    });

    function importBatch(offset, totalCreated, totalUpdated, totalSkipped) {
        if (stopRequestedImport) {
            showNotice($('#tyreorder-import-progress'), 'warning', 'Import stopped by user.');
            $('#tyreorder-import-cancel-btn').hide();
            $('#tyreorder-import-batch-btn').prop('disabled', false);
            $('#tyreorder-import-spinner').hide();
            stopRequestedImport = false;
            return;
        }
        showNotice($('#tyreorder-import-progress'), 'info', 'Processing batch...'); // Show immediately before AJAX
        $.post(tyreorder_import_ajax.ajaxurl, {
            action: 'tyreorder_import_products_batch',
            security: tyreorder_import_ajax.nonce,
            offset: offset
        }, function(response){
            if (!response.success) {
                showNotice($('#tyreorder-import-progress'), 'error', 'Error: ' + response.data);
                $('#tyreorder-import-cancel-btn').hide();
                $('#tyreorder-import-batch-btn').prop('disabled', false);
                $('#tyreorder-import-spinner').hide();
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
                $('#tyreorder-import-cancel-btn').hide();
                $('#tyreorder-import-batch-btn').prop('disabled', false);
                $('#tyreorder-import-spinner').hide();
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