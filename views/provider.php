<div class="wrap">
  <?php if (get_transient('cos_message')) { ?>
    <div class="updated">
      <p><?php echo get_transient('cos_message'); ?></p>
    </div>
    <?php delete_transient('cos_message'); ?>
  <?php } ?>

  <?php $plugin_data = get_plugin_data(__DIR__.'/../culture-object.php'); ?>
  <h2>Culture Object Settings <small>Version <?php echo $plugin_data['Version']; ?> by <a href="http://www.thirty8.co.uk">Thirty8 Digital</a>.</small></h2>

  
  <form method="POST" action="options.php">
    <?php 
      settings_fields('cos_provider_settings');
      do_settings_sections('cos_provider_settings');
      submit_button();
    ?>
  </form>
  
  <?php if (isset($provider_class) && method_exists($provider_class, 'generate_settings_outside_form_html')) $provider_class->generate_settings_outside_form_html(); ?>
  
</div>