$ = jQuery.noConflict();
$( document ).ready(
	function() {
		$( '#mds_perform_ajax_import' ).click(
			function(e) {
				$( '#mds_perform_ajax_import' ).unbind( 'click' );
				$( "#mds_perform_ajax_import" ).val( strings.importing_please_wait );
				$( "#mds_perform_ajax_import" ).addClass( "button-disabled" );

				$( "#hide-on-import" ).css( 'display','none' );

				perform_cleanup = $( '#perform_cleanup' ).is( ':checked' );

				$( "#mds_import_progressbar .progress-label" ).css( 'display', 'inline-block' );
				$( "#mds_import_progressbar" ).progressbar( {value: 0} );

				importChunk( 'start', $( '#mds_perform_ajax_import' ).data( 'import-id' ), $( '#mds_perform_ajax_import' ).data( 'sync-key' ), $( '#mds_perform_ajax_import' ).data( 'starting-nonce' ), perform_cleanup );
			}
		);
	}
)


function importChunk(resume, import_id, sync_key, nonce, perform_cleanup) {
	$.post(
		ajaxurl,
		{
			'action': 'cos_sync',
			'key': sync_key,
			'resume': resume,
			'import_id': import_id,
			'perform_cleanup': perform_cleanup,
			'nonce': nonce
		},
		function(response) {
			if (response.state == "error") {
				alert( response.message + '\r\n\r\n' + response.detail );
			} else {
				if (response.complete) {
					$( "#mds_import_progressbar" ).progressbar( {value: 100} );
					$( "#mds_import_progressbar .progress-label" ).html( strings.import_complete + ' ' + response.imported_count + ' ' + strings.objects_imported );
					$( "#mds_import_detail" ).prepend( response.import_status.join( "<br />" ) + "<br />" );
					if (perform_cleanup) {
						$( "#mds_import_detail" ).prepend( strings.performing_cleanup + "<br />" );
						performCleanup( import_id, sync_key, response.next_nonce );
					}
				} else {
					$( "#mds_import_progressbar" ).progressbar( {value: response.percentage} );
					$( "#mds_import_progressbar .progress-label" ).html( strings.imported + ' ' + (response.imported_count) + '/' + response.total_objects + ' ' + strings.objects );
					$( "#mds_import_detail" ).prepend( response.import_status.join( "<br />" ) + "<br />" );
					importChunk( response.next_resume, import_id, sync_key, response.next_nonce, perform_cleanup );
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
			'resume': 'cleanup',
			'nonce': nonce
		},
		function(response) {

			if (response.state == "error") {
				alert( response.message + '\r\n\r\n' + response.detail );
			} else {
				$( "#mds_import_detail" ).prepend( response.deleted_status.join( "<br />" ) + "<br />" );
				$( "#mds_import_detail" ).prepend( response.deleted_count + ' ' + strings.objects_deleted + "<br />" );
				$( "#mds_import_detail" ).prepend( strings.import_complete + '<br />' );
			}
		},
		'json'
	);
}
