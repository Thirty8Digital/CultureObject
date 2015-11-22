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
  
</div>