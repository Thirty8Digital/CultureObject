$ = jQuery.noConflict();
$( document ).ready(
	function() {
		$( "#csv_import_submit" ).click(
			function(e) {
				$( "#csv_import_submit" ).unbind( 'click' );
				$( "#csv_import_submit" ).val( strings.uploading_please_wait );
				$( "#csv_import_submit" ).addClass( "button-disabled" );
				window.setTimeout( '$("#csv_import_form").submit();',100 );
			}
		);

		$( '#delete_uploaded_csv' ).click(
			function(e) {
				$( '#delete_uploaded_csv_form' ).submit();
			}
		);

		$( '#csv_perform_ajax_import' ).click(
			function(e) {
				$( '#csv_perform_ajax_import' ).unbind( 'click' );
				$( "#csv_perform_ajax_import" ).val( strings.importing_please_wait );
				$( "#csv_perform_ajax_import" ).addClass( "button-disabled" );

				$( "#hide-on-import" ).css( 'display','none' );

				perform_cleanup = $( '#perform_cleanup' ).is( ':checked' );
				since           = $( '#since' ).val().trim();

				if (since) {
					perform_cleanup = false;
				} else {
					since = false;
				}

				$( "#csv_import_progressbar .progress-label" ).css( 'display', 'inline-block' );
				$( "#csv_import_progressbar" ).progressbar( {value: 0} );

				importChunk( 1, $( '#csv_perform_ajax_import' ).data( 'import-id' ), $( '#csv_perform_ajax_import' ).data( 'sync-key' ), $( '#csv_perform_ajax_import' ).data( 'starting-nonce' ), perform_cleanup, since );
			}
		);
	}
)


function importChunk(start, import_id, sync_key, nonce, perform_cleanup, since) {
	$.post(
		ajaxurl,
		{
			'action': 'cos_sync',
			'key': sync_key,
			'start': start,
			'import_id': import_id,
			'perform_cleanup': perform_cleanup,
			'since': since,
			'nonce': nonce
		},
		function(response) {
			if (response.state == "error") {
				alert( response.message + '\r\n\r\n' + response.detail );
			} else {
				if (response.complete) {
					$( "#csv_import_progressbar" ).progressbar( {value: 100} );
					$( "#csv_import_progressbar .progress-label" ).html( strings.import_complete + ' ' + response.imported_count + ' ' + strings.objects_imported );
					$( "#csv_import_detail" ).prepend( response.import_status.join( "<br />" ) + "<br />" );
					if (perform_cleanup) {
						$( "#csv_import_detail" ).prepend( strings.performing_cleanup + "<br />" );
						performCleanup( import_id, sync_key, response.next_nonce );
					}
				} else {
					$( "#csv_import_progressbar" ).progressbar( {value: response.percentage} );
					$( "#csv_import_progressbar .progress-label" ).html( strings.imported + ' ' + (response.imported_count) + '/' + response.total_objects + ' ' + strings.objects );
					$( "#csv_import_detail" ).prepend( response.import_status.join( "<br />" ) + "<br />" );
					importChunk( response.next_start, import_id, sync_key, response.next_nonce, perform_cleanup, since );
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
			'start': 'cleanup',
			'nonce': nonce
		},
		function(response) {

			if (response.state == "error") {
				alert( response.message + '\r\n\r\n' + response.detail );
			} else {
				$( "#csv_import_detail" ).prepend( response.deleted_status.join( "<br />" ) + "<br />" );
				$( "#csv_import_detail" ).prepend( response.deleted_count + ' ' + strings.objects_deleted + "<br />" );
				$( "#csv_import_detail" ).prepend( strings.import_complete + '<br />' );
			}
		},
		'json'
	);
}
