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
        
        $("#hide-on-import").css('display','none');
        
        perform_cleanup = $('#perform_cleanup').is(':checked');
        
        $("#csv_import_progressbar .progress-label").css('display', 'inline-block');
        $("#csv_import_progressbar").progressbar({value: 0});
        
        importChunk(0, $('#csv_perform_ajax_import').data('import-id'), $('#csv_perform_ajax_import').data('sync-key'), $('#csv_perform_ajax_import').data('starting-nonce'), perform_cleanup, 1);
    });
})


function importChunk(start, import_id, sync_key, nonce, perform_cleanup, service) {
    $.post(
        ajaxurl, 
        {
            'action': 'cos_sync',
            'key': sync_key,
            'start': start,
            'import_id': import_id,
            'service': service,
            'perform_cleanup': perform_cleanup,
            'nonce': nonce
        }, 
        function(response) {
            if (response.state == "error") {
                alert(response.message+'\r\n\r\n'+response.detail);
            } else {
                if (response.complete) {
                    $("#csv_import_progressbar").progressbar({value: 100});
                    $("#csv_import_progressbar .progress-label").html(strings.import_complete+' '+response.imported_count+' '+strings.objects_imported+' '+strings.service+' '+service);
                    $("#csv_import_detail").prepend(response.import_status.join("<br />")+"<br />");
                    if (response.next_service == "cleanup") {
	                    if (perform_cleanup) {
	                        $("#csv_import_detail").prepend(strings.performing_cleanup+"<br />");
	                        performCleanup(import_id, sync_key, response.next_nonce);
                        }
                    } else {
				        importChunk(0, $('#csv_perform_ajax_import').data('import-id'), $('#csv_perform_ajax_import').data('sync-key'), $('#csv_perform_ajax_import').data('starting-nonce'), perform_cleanup, response.next_service);
                    }
                } else {
                    $("#csv_import_progressbar").progressbar({value: response.percentage});
                    $("#csv_import_progressbar .progress-label").html(strings.imported+' '+response.imported_count+'/'+response.total_objects+' '+strings.objects+' '+strings.service+' '+service);
                    $("#csv_import_detail").prepend(response.import_status.join("<br />")+"<br />");
                    importChunk(response.next_start, import_id, sync_key, response.next_nonce, perform_cleanup, service);
                }
            }
        },
        'json'
    ); 
}

function performCleanup(import_id, sync_key, nonce) {
    $.post(
        ajaxurl, 
        {
            'action': 'cos_sync',
            'key': sync_key,
            'import_id': import_id,
            'service': 0,
            'start': 'cleanup',
            'nonce': nonce
        }, 
        function(response) {
            
            if (response.state == "error") {
                alert(response.message+'\r\n\r\n'+response.detail);
            } else {
                $("#csv_import_detail").prepend(response.deleted_status.join("<br />")+"<br />");
                $("#csv_import_detail").prepend(response.deleted_count+' '+strings.objects_deleted+"<br />");
                $("#csv_import_detail").prepend(strings.import_complete+'<br />');
            }
        },
        'json'
    ); 
}