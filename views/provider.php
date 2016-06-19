<div class="wrap">
    <?php if (get_transient('cos_message')) { ?>
        <div class="updated">
            <p><?php echo get_transient('cos_message'); ?></p>
        </div>
        <?php delete_transient('cos_message'); ?>
    <?php } ?>
    
    <?php $plugin_data = get_plugin_data(__DIR__.'/../CultureObject.php'); ?>
    
    <h2><?php _e('Culture Object Provider Settings', 'culture-object'); ?> <small><?php printf( /* Translators: %s: Version Number */ __('Version %s','culture-object'), $plugin_data['Version']); ?> by <a href="http://www.thirty8.co.uk">Thirty8 Digital</a>.</small></h2>
    
    
    <?php
        $show_settings = true;
        if (isset($provider_class)) {
            $info = $provider_class->get_provider_information();
            if (isset($info['no_options']) && $info['no_options']) $show_settings = false;
        }
    ?>
    <?php if ($show_settings) { ?>
        <form method="POST" action="options.php">
            <?php 
            settings_fields('cos_provider_settings');
            do_settings_sections('cos_provider_settings');
            submit_button();
            ?>
        </form>
    <?php } ?>
    
    <?php if (isset($provider_class) && method_exists($provider_class, 'generate_settings_outside_form_html')) $provider_class->generate_settings_outside_form_html(); ?>
    
    <?php if (false && isset($provider_info) && $provider_info && (isset($provider_info['ajax']) && $provider_info['ajax'])) { //Disable this while we figure out how AJAX will work. ?>
    
        <p>Once you've completed the settings above and saved, click perform sync to initiate the sync</p>
        <input type="submit" name="perform_ajax_sync" id="perform_ajax_sync" data-sync-key="<?php echo get_option('cos_core_sync_key'); ?>" class="button button-primary" value="<?php _e('Perform Sync','culture-object'); ?>">
        
        <div id="ajax_output">
            
        </div>
    
    <?php } ?>

</div>