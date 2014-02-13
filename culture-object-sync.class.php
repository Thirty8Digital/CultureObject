<?php

require_once('culture-object-sync/core.class.php');
require_once('culture-object-sync/exceptions.class.php');
require_once('culture-object-sync/provider.class.php');
require_once('culture-object-sync/settings.class.php');

class Culture_Object_Sync extends Culture_Object_Sync_Core {
  
  function __construct() {
    $settings = new Culture_Object_Sync_Settings();
    add_action('init', array($this, 'wordpress_init'));
  }
  
  function wordpress_init() {
      
    register_post_type('object', array(
      'labels' => array(
          "name" => "Objects",
          "singular_name" => "Object",
          "add_new_item" => "Add new object",
          "edit_item" => "Edit object",
          "new_item" => "New object",
          "view_item" => "View object",
          "search_items" => "Search objects",
          "not_found" => "No objects found",
          "not_found_in_trash" => "No objects found in the trash"
        ),
      'public' => false,
      'menu_icon' => 'dashicons-list-view',
      'show_ui' => true,
      'supports' => array('title')
      )
    );
    
    
    add_filter('post_updated_messages', array($this,'object_updated_messages'));
    
  }
  
  
  function object_updated_messages($messages) {
    global $post, $post_ID;
  
    $messages['object'] = array(
      0 => '',
      1 => sprintf( __('Object updated. <a href="%s">View object</a>', 'culture_object_sync'), esc_url( get_permalink($post_ID) ) ),
      2 => __('Custom field updated.', 'culture_object_sync'),
      3 => __('Custom field deleted.', 'culture_object_sync'),
      4 => __('Object updated.', 'culture_object_sync'),
      5 => isset($_GET['revision']) ? sprintf( __('Object restored to revision from %s', 'culture_object_sync'), wp_post_revision_title( (int) $_GET['revision'], false)) : false,
      6 => sprintf(__('Object published. <a href="%s">View object</a>', 'culture_object_sync'), esc_url( get_permalink($post_ID) ) ),
      7 => __('Object saved.', 'culture_object_sync'),
      8 => sprintf(__('Object submitted. <a target="_blank" href="%s">Preview object</a>', 'culture_object_sync'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID)))),
      9 => sprintf(__('Object scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview object</a>', 'culture_object_sync'), date_i18n(__('M j, Y @ G:i'), strtotime($post->post_date)), esc_url(get_permalink($post_ID))),
      10 => sprintf(__('Object draft updated. <a target="_blank" href="%s">Preview object</a>', 'culture_object_sync'), esc_url(add_query_arg('preview', 'true', get_permalink($post_ID)))),
    );
  
    return $messages;
  }
}