<?php

class Culture_Object_Sync_Provider_AdLib_Exception extends Culture_Object_Sync_Provider_Exception { }

class Culture_Object_Sync_Provider_AdLib extends Culture_Object_Sync_Provider {
  
  private $provider = array(
    'name' => 'AdLib',
    'version' => '1.0',
    'developer' => 'Thirty8 Digital',
    'cron' => false
  );
  
  function get_provider_information() {
    return $this->provider;
  }
  
  function register_settings() {
    add_settings_section('cos_provider_settings','Provider Settings',array($this,'generate_settings_group_content'),'cos_settings');
  }
  
  function generate_settings_group_content() {
    echo "<p>You're currently using version ".$this->provider['version']." of the ".$this->provider['name']." sync provider by ".$this->provider['developer'].".</p>";
    
    echo "<p>You need to upload an xml export file from AdLib in order to import.</p>";
    
    echo '<form method="post" action="" enctype="multipart/form-data">';
      echo '<input type="file" name="cos_adlib_import_file" />';
      echo '<input type="hidden" name="cos_adlib_nonce" value="'.wp_create_nonce('cos_adlib_import').'" />';
      echo '<input type="submit" class="button" value="Import AdLib Dump" />';
    echo '</form>';
        
  }
  
  function execute_load_action() {
    
  }
  
  function generate_settings_field_input_text($args) {
    $field = $args['field'];
    $value = get_option($field);
    echo sprintf('<input type="text" name="%s" id="%s" value="%s" />', $field, $field, $value);
  }
  
  function perform_sync() {
    set_time_limit(0);
    ini_set('memory_limit','768M');
    
    $start = microtime(true);
        
    $previous_posts = $this->get_current_object_ids();
    
    $result = $this->perform_request($url);
    
    
    /*
    $number_of_objects = $result['response']['numFound'];
    if ($number_of_objects > 0) {
      foreach($result['response']['docs'] as $doc) {
        $object_exists = $this->object_exists($doc['dc.identifier']);
        if (!$object_exists) {
          $current_objects[] = $this->create_object($doc);
          echo "Created object ".$doc['dc.title'][0]."<br />\r\n";
        } else {
          $current_objects[] = $this->update_object($doc);
          echo "Updated object ".$doc['dc.title'][0]."<br />\r\n";
        }
      }
      $this->clean_objects($current_objects,$previous_posts);
    }*/
      
    $end = microtime(true);
    
    echo "Sync Complete in ".($end-$start)." seconds\r\n";
    
  }
  
  function get_current_object_ids() {
    $args = array('post_type'=>'object','posts_per_page'=>-1);
    $posts = get_posts($args);
    $current_posts = array();
    foreach($posts as $post) {
      $current_posts[] = $post->ID;
    }
    return $current_posts;
  }
  
  function clean_objects($current_objects,$previous_objects) {
    $to_remove = array_diff($previous_objects, $current_objects);
    
    foreach($to_remove as $remove_id) {
      wp_delete_post($remove_id,true);
      echo "Removed Post ID $remove_id as it is no longer in the list of objects from AdLib<br />";
    }
    
  }
  
  function create_object($doc) {
    $post = array(
      'post_title'        => $doc['dc.title'][0],
      'post_type'         => 'object',
      'post_status'       => 'publish',
    );
    $post_id = wp_insert_post($post);
    $this->update_object_meta($post_id,$doc);
    return $post_id;
  }
  
  
  function update_object($doc) {
    $existing_id = $this->existing_object_id($doc['dc.identifier']);
    $post = array(
      'ID'                => $existing_id,
      'post_title'        => $doc['dc.title'][0],
      'post_type'         => 'object',
      'post_status'       => 'publish',
    );
    $post_id = wp_update_post($post);
    $this->update_object_meta($post_id,$doc);
    return $post_id;
  }
  
  function update_object_meta($post_id,$doc) {
    foreach($doc as $key => $value) {
      if (is_array($value)) $value = $value[0];
      update_post_meta($post_id,$key,$value);
    }
  }
  
  function object_exists($id) {
    $args = array(
      'post_type' => 'object',
      'meta_key' => 'dc.identifier',
      'meta_value' => $id,
    );
    return (count(get_posts($args)) > 0) ? true : false;
  }
  
  function existing_object_id($id) {
    $args = array(
      'post_type' => 'object',
      'meta_key' => 'dc.identifier',
      'meta_value' => $id
    );
    $posts = get_posts($args);
    if (count($posts) == 0) throw new Exception("BUG: called existing_object_id for an object that doesn't exist.");
    return $posts[0]->ID;
  }
  
  
}

?>