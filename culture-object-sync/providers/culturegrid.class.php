<?php

class Culture_Object_Sync_Provider_CultureGrid_Exception extends Culture_Object_Sync_Provider_Exception { }

class Culture_Object_Sync_Provider_CultureGrid extends Culture_Object_Sync_Provider {
  //http://www.culturegrid.org.uk/index/select?fl=*&wt=json&rows=1000000&indent=on&q=(authority:ManchesterCityGalleries)%20AND%20(dcmi.type:PhysicalObject)&start=0
  
  //http://www.culturegrid.org.uk/index/select?wt=json&rows=5000&indent=on&q=dcmi.type:PhysicalObject&start=0
  
  //http://www.culturegrid.org.uk/wp-content/uploads/2011/11/Culture-Grid-search-service-v3.pdf
  
  private $provider = array(
    'name' => 'CultureGrid',
    'version' => '1.0',
    'developer' => 'Thirty8 Digital',
  );
  
  function get_provider_information() {
    return $this->provider;
  }
  
  function register_settings() {
    add_settings_section('cos_provider_settings','Provider Settings',array($this,'generate_settings_group_content'),'cos_settings');
  
  	register_setting('cos_settings', 'cos_provider_search_authority');
  	
  	add_settings_field('cos_provider_search_authority', 'CultureGrid Search Authority', array($this,'generate_settings_field_input_text'), 'cos_settings', 'cos_provider_settings', array('field'=>'cos_provider_search_authority'));
  }
  
  function generate_settings_group_content() {
    echo "<p>You're currently using version ".$this->provider['version']." of the ".$this->provider['name']." sync provider by ".$this->provider['developer'].".</p>";
    
    
    $authority = get_option('cos_provider_search_authority');
    if (!empty($authority)) {
      $url = "http://www.culturegrid.org.uk/index/select?fl=*&wt=json&rows=1&indent=on&q=authority:".$authority."&start=0";
      $result = $this->perform_request($url);
      $number_of_objects = $result['response']['numFound'];
      echo "<p>There are ".number_format($number_of_objects)." objects currently available to sync from CultureGrid based on your current authority.</p>";
      echo "<p>Based on this number, you should expect a sync to take approximately ".round($number_of_objects/420)." minutes to complete. <br /><small>This number can vary significantly on the speed on your network, server, and database.</small></p>";
      if ($number_of_objects > 100000) echo "<p>CultureGrid sync only supports 100,000 objects maximum for the sake of performance. Only the first 100,000 objects will sync.</p>";
    }
    
  }
  
  function perform_request($url) {
    $json = file_get_contents($url);
    $data = json_decode($json,true);
    if ($data) {
      if (isset($data['response'])) {
        return $data;
      } else {
        throw new Culture_Object_Sync_Provider_CultureGrid_Exception("CultureGrid returned an invalid JSON response");
      }
    } else {
      throw new Culture_Object_Sync_Provider_CultureGrid_Exception("CultureGrid returned an invalid response: ".$json);
    }
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
    
    $authority = get_option('cos_provider_search_authority');
    if (empty($authority)) {
      throw new Culture_Object_Sync_Provider_CultureGrid_Exception("You haven't yet configured a search authority in the Culture Object Sync settings");
    }
    
    $previous_posts = $this->get_current_object_ids();
    
    $url = "http://www.culturegrid.org.uk/index/select?fl=*&wt=json&rows=100000&indent=on&q=authority:".$authority."&start=0";
    
    $result = $this->perform_request($url);
    
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
    }
      
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
      echo "Removed Post ID $remove_id as it is no longer in the list of objects from CultureGrid<br />";
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