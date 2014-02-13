<?php

require_once('culture-object-sync/core.class.php');
require_once('culture-object-sync/exceptions.class.php');
require_once('culture-object-sync/provider.class.php');
require_once('culture-object-sync/settings.class.php');
require_once('culture-object-sync/providers/culturegrid.class.php');

class Culture_Object_Sync extends Culture_Object_Sync_Core {
  
  function __construct() {
    $settings = new Culture_Object_Sync_Settings();
    add_action('init', array($this, 'wordpress_init'));
  }
  
  function wordpress_init() {
    
    
  }
  
}