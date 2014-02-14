<?php

class Culture_Object_Sync_Settings extends Culture_Object_Sync_Core {

  private $plugin_directory;
  private $plugin_url;

  function __construct() {
    $this->plugin_directory = dirname(__DIR__.'../');
    $this->plugin_url = plugins_url('/',__DIR__);
    
    add_action('admin_menu', array($this,'add_menu_item'));
    add_action('admin_enqueue_scripts', array($this,'add_admin_assets'));
    add_action('admin_init', array($this,'register_settings'));
  }
  
  function register_settings() {
  
    add_settings_section('cos_core_settings','Main Settings',array($this,'generate_settings_group_content'),'cos_settings');
  
  	register_setting('cos_settings', 'cos_core_sync_provider');
  	register_setting('cos_settings', 'cos_core_sync_key');
  	
  	add_settings_field('cos_core_sync_provider', 'Sync Provider', array($this,'generate_settings_sync_providers_input'), 'cos_settings', 'cos_core_settings', array('field'=>'cos_core_sync_provider'));
  	
  	add_settings_field('cos_core_sync_key', 'Sync Key', array($this,'generate_settings_field_input_text'), 'cos_settings', 'cos_core_settings', array('field'=>'cos_core_sync_key'));
    
    $provider = $this->get_sync_provider();
    if ($provider) {
      if (!class_exists($provider['class'])) include_once($provider['file']);
      $provider_class = new $provider['class'];
      $provider_class->register_settings();
    }
  	
  }
  
  function add_admin_assets($page) {
    if ($page == 'settings_page_cos_settings') {
      wp_register_style('cos_admin_css', $this->plugin_url . '/css/culture-object-sync.css', false, '1.0.0');
      wp_enqueue_style('cos_admin_css');
      wp_register_script('cos_admin_js', $this->plugin_url . '/js/culture-object-sync.js', array('jquery','jquery.qtip.js'), '1.0.0', true);
      wp_enqueue_script('cos_admin_js');
    }
  }
  
  function add_menu_item() {
    add_options_page('Culture Object Sync Settings', 'Culture Object Sync', 'administrator', 'cos_settings', array($this,'generate_settings_page'));
  }
  
  function generate_settings_page() {
    include($this->plugin_directory.'/views/settings.php');
  }
  
  function generate_settings_group_content($group) {
    $group_id = $group['id'];
    switch ($group_id) {
      case 'cos_core_settings':
        $message = 'These settings relate to the overall plugin and how it works.';
        break;
      default:
        $message = '';
    }
    echo $message;
  }
  
  function generate_settings_sync_providers_input($args) {
    $field = $args['field'];
    $value = get_option($field);
    $providers = $this->find_providers();
    echo '<select name="'.$field.'" id="'.$field.'">';
    foreach($providers as $provider) {
      $selected = ($value == $provider['class']) ? ' selected="selected"' : '';
      echo '<option value="'.$provider['class'].'"'.$selected.'>'.$provider['info']['name'].'</option>';
    }
    echo '</select>';
    
  }
  
  function generate_settings_field_input_text($args) {
    $field = $args['field'];
    $value = get_option($field);
    echo sprintf('<input type="text" name="%s" id="%s" value="%s" />', $field, $field, $value);
    if ($field == "cos_core_sync_key") echo '<br /><small>This key forms part of the sync URL for a little bit more security.</small>';
    
  }
  
}