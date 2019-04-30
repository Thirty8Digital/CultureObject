<div class="wrap">
  <?php if (get_transient('cos_message')) { ?>
    <div class="updated">
      <p><?php echo get_transient('cos_message'); ?></p>
    </div>
    <?php delete_transient('cos_message'); ?>
  <?php } ?>

  <?php $plugin_data = get_plugin_data(__DIR__.'/../CultureObject.php'); ?>

  <h2><?php _e('Culture Object Settings','culture-object'); ?> <small><?php printf( /* Translators: 1 Version Number */ __('Version %s','culture-object'), $plugin_data['Version']); ?> by <a href="http://www.thirty8.co.uk">Thirty8 Digital</a>.</small></h2>

  <?php
  $key = get_option('cos_core_sync_key');
  if (empty($key)) {
    $key = md5(time().rand(0,100000));
    update_option('cos_core_sync_key',$key);
  }
  ?>

  
  <?php if (isset($provider_info) && $provider_info && (!isset($provider_info['cron']) || !$provider_info['cron'])) { ?>
    <p><strong>
      <?php
        printf(
            __('Your current provider, %s, doesn\'t support automated import by cron. You do not need to create a cronjob for this provider.', 'culture-object'),
            $provider_info['name']
        );
        echo '<br />';
        _e('Use the provider settings page to process an import.', 'culture-object');
      ?>
    </strong></p>
    <?php } else { ?>
  
    <p><?php _e('The Culture Object plugin you\'re using requires you to set up a manual cronjob to the frequency you wish to check for updates for your chosen sync provider.', 'culture-object'); ?></p>
    <p><?php _e('You shouldn\'t do this too frequently to avoid causing problems with your provider.', 'culture-object'); ?></p>
    <p>
        <?php 
        printf(
            /* Translators 1: URL to perform cron sync */
            __('You should load the following URL: %s', 'culture-object'),
            '<a target="_blank" href="'.get_site_url().'?perform_culture_object_sync=true&key='.get_option('cos_core_sync_key').'">'.get_site_url().'?perform_culture_object_sync=true&key='.get_option('cos_core_sync_key').'</a>'
        );
        ?>
    </p>
    <p>
        <?php
        printf(
            /* Translators 1: <pre> wrapped cli path */
            __('Alternatively, you can call the following script from your root path to bypass any web timeouts that may exist with your provider. %s', 'culture-object'),
            '<pre>php wp-content/plugins/[culture-object-sync-folder-name]/cron.php</pre>'
        );
        ?>
    </p>

  <?php } ?>

  <form method="POST" action="options.php">
    <?php 
      settings_fields('cos_settings');
      do_settings_sections('cos_settings');
      submit_button();
    ?>
  </form>
  
</div>