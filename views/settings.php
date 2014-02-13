<div class="wrap">
  <?php $plugin_data = get_plugin_data(__DIR__.'/../culture-object-sync.php'); ?>
  <h2>Culture Object Sync Settings <small>Version <?php echo $plugin_data['Version']; ?></small></h2>

  <form method="POST" action="options.php">
    <?php 
      settings_fields('cos_settings');
      do_settings_sections('cos_settings');
      submit_button();
    ?>
  </form>
  
</div>