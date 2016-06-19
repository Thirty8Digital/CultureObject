$ = jQuery.noConflict();
$(document).ready(function() {
    $('#perform_ajax_sync').click(function() {
        $('#ajax_output').css('display','block');
        $('#ajax_output').html('Initialising Import');
        $.post(
            ajaxurl, 
            {
                'action': 'cos_sync',
                'key': $(this).data('sync-key'),
                'phase': 'init'
            }, 
            function(response){
                alert('The server responded: ' + response);
            },
            'json'
        ); 
    });
});