<?php
    
namespace CultureObject;

class Helper extends Core {
    
    function cos_get_remapped_field_name($key) {
        $field_remap = get_option('cos_remap_'.strtolower($key));
        if (isset($field_remap) && !empty($field_remap)) return $field_remap;
        $provider = $this->get_sync_provider();
        if ($provider) {
            if (!class_exists($provider['class'])) include_once($provider['file']);
            $provider_class = new $provider['class'];
            $info = $provider_class->get_provider_information();
            if (isset($info['supports_remap']) && $info['supports_remap']) {
                if (method_exists($provider_class, 'register_remappable_fields')) {
                    $fields = $provider_class->register_remappable_fields();
                    if (isset($fields[$key])) return $fields[$key];
                }
            }
        }
        return $key;
    }
    
    function cos_remapped_field_name($key) {
        echo cos_get_remapped_field_name($key);
    }
    
    function add_image_to_gallery_from_url($url, $save_as, $stream_context = false, $post_parent = 0) {
        $upload_dir = wp_upload_dir();
        $img = @file_get_contents($url, false, $stream_context);
        if ($img) {
            foreach($http_response_header as $header) {
                if (strpos(strtolower($header),'content-disposition') !== false) {
                    $tmp_name = explode('=', $header);
                    if ($tmp_name[1]) $file_name = trim($tmp_name[1],'";\'');
                }
            }
            if (isset($file_name) && $file_name) $save_as = $file_name;
            $file_location = $upload_dir['path'].'/'.$save_as;
            file_put_contents($file_location, $img);
            
            $filetype = wp_check_filetype( basename( $file_location ), null );
            
            // Prepare an array of post data for the attachment.
            $attachment = array(
                'guid'           => $upload_dir['url'] . '/' . basename( $file_location ), 
                'post_mime_type' => $filetype['type'],
                'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file_location ) ),
                'post_content'   => '',
                'post_status'    => 'inherit'
            );
            
            // Insert the attachment.
            $attach_id = wp_insert_attachment($attachment, $file_location, $post_parent);
            
            // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            
            // Generate the metadata for the attachment, and update the database record.
            $attach_data = wp_generate_attachment_metadata($attach_id, $file_location);
            wp_update_attachment_metadata($attach_id, $attach_data);
            
            return $attach_id;
        } else return false;
    }
    
}