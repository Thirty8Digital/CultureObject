<?php

class RAMMException extends \CultureObject\Exception\ProviderException { }

class RAMM extends \CultureObject\Provider {
    
    private $provider = array(
        'name' => 'RAMM',
        'version' => '1.0',
        'developer' => 'Thirty8 Digital',
        'cron' => true
    );
    
    function get_provider_information() {
        return $this->provider;
    }
    
    function register_settings() {
        add_settings_section('cos_provider_settings',__('Provider Settings','culture-object'),array($this,'generate_settings_group_content'),'cos_provider_settings');
    
        register_setting('cos_provider_settings', 'cos_provider_feed_url');
        
        add_settings_field('cos_provider_feed_url', __('RAMM Feed URL','culture-object'), array($this,'generate_settings_field_input_text'), 'cos_provider_settings', 'cos_provider_settings', array('field'=>'cos_provider_feed_url'));
    }
    
    function generate_settings_group_content() {
        
        echo '<p>';
        printf(
            /* Translators: 1: Provider Plugin Version 2: Provider Name 3: Provider Developer */
            __('You\'re currently using version %1$s of the %2$s sync provider by %3$s.', 'culture-object'),
            $this->provider['version'],
            $this->provider['name'],
            $this->provider['developer']
        );
        echo '</p>';
        
        
        $authority = get_option('cos_provider_feed_url');
        if (!empty($authority)) {
            echo "<p>".__('RAMM\'s JSON data takes a while to generate, so we\'re unable to show a preview here, and import could take a very long time.','culture-object')."</p>";
        }
        
    }
    
    function perform_request($url) {
        $json = file_get_contents($url);
        $data = json_decode($json,true);
        if ($data) {
            if (isset($data[0]['Id'])) {
                return $data;
            } else {
                throw new RAMMException(sprintf(__("%s returned an invalid JSON response", 'culture-object'), 'RAMM'));
            }
        } else {
            throw new RAMMException(sprintf(__("%s returned an invalid response: ", 'culture-object').$json, 'RAMM'));
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
        
        $url = get_option('cos_provider_feed_url');
        if (empty($url)) {
            throw new RAMMException(__("You haven't yet configured a URL in the Culture Object Sync settings",'culture-object'));
        }
        
        $previous_posts = $this->get_current_object_ids();
        
        $result = $this->perform_request($url);
        
        $number_of_objects = count($result);
        printf(
            __("Importing %d objects.",'culture-object')."<br />\r\n",
            $number_of_objects
        );
        if ($number_of_objects > 0) {
            foreach($result as $doc) {
                $doc['identifier'] = $doc['Id'];
                unset($doc['Id']);
                $object_exists = $this->object_exists($doc['identifier']);
                if (!$object_exists) {
                    $current_objects[] = $this->create_object($doc);
                    echo __("Created object",'culture-object').": ".$doc['Title']."<br />\r\n";
                } else {
                    $current_objects[] = $this->update_object($doc);
                    echo __("Updated object",'culture-object').": ".$doc['Title']."<br />\r\n";
                }
            }
            $this->clean_objects($current_objects,$previous_posts);
        }
            
        $end = microtime(true);
        
        printf(
            __("Sync Complete in %d seconds",'culture-object')."\r\n",
            ($end-$start)
        );
        
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
            printf(
                /* Translators: 1: A WordPress Post ID 2: The type of file or the provider name (CSV, AdLib, etc) */
                __('Removed Post ID %1$d as it is no longer in the exported list of objects from %2$s', 'culture-object'),
                $remove_id,
                'RAMM'
            )."<br />";
        }
        
    }
    
    function create_object($doc) {
        $post = array(
            'post_title'                => $doc['Title'],
            'post_type'              => 'object',
            'post_status'            => 'publish',
        );
        $post_id = wp_insert_post($post);
        $this->update_object_meta($post_id,$doc);
        return $post_id;
    }
    
    
    function update_object($doc) {
        $existing_id = $this->existing_object_id($doc['identifier']);
        $post = array(
            'ID'                                => $existing_id,
            'post_title'                => $doc['Title'],
            'post_type'              => 'object',
            'post_status'            => 'publish',
        );
        $post_id = wp_update_post($post);
        $this->update_object_meta($post_id,$doc);
        return $post_id;
    }
    
    function update_object_meta($post_id,$doc) {
        foreach($doc as $key => $value) {
            if (empty($value)) continue;
            $key = strtolower($key);
            if (is_array($value) && $key == "data") {
                foreach($value as $single_value) {
                    $key = $this->slugify_key($single_value['Name']);
                    update_post_meta($post_id,'data_'.$key,$single_value['Value']);
                }
            } else if (is_array($value) && $key == "comments") {
                foreach($value as $key => $single_value) {
                    $key = $this->slugify_key($key);
                    update_post_meta($post_id,'comments_'.$key,$single_value);
                }
            } else {
                update_post_meta($post_id,$key,$value);
            }
        }
    }
    
    function object_exists($id) {
        $args = array(
            'post_type' => 'object',
            'meta_key' => 'identifier',
            'meta_value' => $id,
        );
        return (count(get_posts($args)) > 0) ? true : false;
    }
    
    function existing_object_id($id) {
        $args = array(
            'post_type' => 'object',
            'meta_key' => 'identifier',
            'meta_value' => $id
        );
        $posts = get_posts($args);
        if (count($posts) == 0) throw new Exception(__("Called existing_object_id for an object that doesn't exist. This is likely a bug in your provider plugin, but because it is probably unsafe to continue the import, it has been aborted.",'culture-object'));
        return $posts[0]->ID;
    }
    
    function slugify_key($text) {
        $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
        $text = trim($text, '-');
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = strtolower($text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        if (empty($text)) return 'empty';
        return $text;
    }
    
}

?>