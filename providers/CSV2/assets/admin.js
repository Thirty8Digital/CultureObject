$ = jQuery.noConflict();
$(document).ready(function() {
    $("#csv_import_submit").click(function(e) {
        $("#csv_import_submit").unbind('click');
        $("#csv_import_submit").val(strings.uploading_please_wait);
        $("#csv_import_submit").addClass("button-disabled");
        window.setTimeout('$("#csv_import_form").submit();',100);
    });
    
    $('#delete_uploaded_csv').click(function(e) {
        $('#delete_uploaded_csv_form').submit(); 
    });
    
    $('#csv_perform_ajax_import').click(function(e) {
        $('#csv_perform_ajax_import').unbind('click');
        $("#csv_perform_ajax_import").val(strings.importing_please_wait);
        $("#csv_perform_ajax_import").addClass("button-disabled");
        
        var id_field = $('#id_field').val();
        var title_field = $('#title_field').val();
        
        $("#csv_import_progressbar .progress-label").css('display', 'inline-block');
        $("#csv_import_progressbar").progressbar({value: 0});
        
        importChunk(1, $('#csv_perform_ajax_import').data('sync-key'), $('#csv_perform_ajax_import').data('starting-nonce'), id_field, title_field);
    });
})


function importChunk(start, sync_key, nonce, id_field, title_field) {
    $.post(
        ajaxurl, 
        {
            'action': 'cos_sync',
            'key': sync_key,
            'start': start,
            'id_field': id_field,
            'title_field': title_field,
            'nonce': nonce
        }, 
        function(response) {
            if (response.complete) {
                $("#csv_import_progressbar").progressbar({value: 100});
                $("#csv_import_progressbar .progress-label").html('Imported Complete. '+response.total_rows+' objects imported');
                $("#csv_import_detail").prepend(response.import_status.join("<br />")+"<br />");
            } else {
                $("#csv_import_progressbar").progressbar({value: response.percentage});
                $("#csv_import_progressbar .progress-label").html('Imported '+(response.next_start-1)+'/'+response.total_rows+' objects');
                $("#csv_import_detail").prepend(response.import_status.join("<br />")+"<br />");
                importChunk(response.next_start, sync_key, response.next_nonce, id_field, title_field);
            }
        },
        'json'
    ); 
}