jQuery(document).ready(function($){
    $('#tyreorder-import-batch-btn').on('click', function(e){
        e.preventDefault();
        if (!confirm('Import all products from CSV in batches?')) return;
        $('#tyreorder-import-batch-btn').prop('disabled', true);
        $('#tyreorder-import-progress').text('Starting import...');
        importBatch(0, 0, 0, 0);
    });

    function importBatch(offset, totalCreated, totalUpdated, totalSkipped) {
        $.post(tyreorder_import_ajax.ajaxurl, {
            action: 'tyreorder_import_products_batch',
            security: tyreorder_import_ajax.nonce,
            offset: offset
        }, function(response){
            if (!response.success) {
                $('#tyreorder-import-progress').text('Error: ' + response.data);
                $('#tyreorder-import-batch-btn').prop('disabled', false);
                return;
            }
            var data = response.data;
            totalCreated += data.created;
            totalUpdated += data.updated;
            totalSkipped += data.skipped;
            $('#tyreorder-import-progress').text(data.message);

            if (!data.done) {
                setTimeout(function(){
                    importBatch(data.offset, totalCreated, totalUpdated, totalSkipped);
                }, 200);
            } else {
                $('#tyreorder-import-progress').text(
                    'Import complete! Created: ' + totalCreated + ', Updated: ' + totalUpdated + ', Skipped: ' + totalSkipped + '.'
                );
                $('#tyreorder-import-batch-btn').prop('disabled', false);
            }
        });
    }
});