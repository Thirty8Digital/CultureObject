<?php

class SWCEException extends \CultureObject\Exception\ProviderException { }

class SWCE extends \CultureObject\Provider {
    
    private $provider = array(
        'name' => 'SWCE',
        'version' => '1.0-alpha.1',
        'developer' => 'Thirty8 Digital',
        'cron' => true,
        'ajax' => true
    );
    
    function get_provider_information() {
        return $this->provider;
    }
    
    function register_settings() {
        add_settings_section('cos_provider_settings',__('Provider Settings','culture-object'),array($this,'generate_settings_group_content'),'cos_provider_settings');
    
        register_setting('cos_provider_settings', 'cos_provider_site_id');
        register_setting('cos_provider_settings', 'cos_provider_api_token');
        
        add_settings_field('cos_provider_site_id', __('SWCE Site ID','culture-object'), array($this,'generate_settings_field_input_text'), 'cos_provider_settings', 'cos_provider_settings', array('field'=>'cos_provider_site_id'));
        
        add_settings_field('cos_provider_api_token', __('SWCE API Token','culture-object'), array($this,'generate_settings_field_input_text'), 'cos_provider_settings', 'cos_provider_settings', array('field'=>'cos_provider_api_token'));
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
            echo "<p>".__('SWCE\'s JSON data takes a while to generate, so we\'re unable to show a preview here, and import could take a very long time.','culture-object')."</p>";
        }
        
    }
    
    function execute_init_action() {
        $labels = array(
    		'name'              => _x('Object Categories', 'taxonomy general name'),
    		'singular_name'     => _x('Object Category', 'taxonomy singular name'),
    		'search_items'      => __('Search Object Categories'),
    		'all_items'         => __('All Object Categories'),
    		'parent_item'       => __('Parent Object Category'),
    		'parent_item_colon' => __('Parent Object Category:'),
    		'edit_item'         => __('Edit Object Category'),
    		'update_item'       => __('Update Object Category'),
    		'add_new_item'      => __('Add New Object Category'),
    		'new_item_name'     => __('New Object Category Name'),
    		'menu_name'         => __('Object Category'),
    	);
    
    	$args = array(
    		'hierarchical'      => true,
    		'labels'            => $labels,
    		'show_ui'           => true,
    		'show_admin_column' => true,
    		'query_var'         => true,
    		'rewrite'           => array('slug' => 'object_category'),
    	);
    
    	register_taxonomy('object_category', array('object'), $args);
    }
    
    function perform_request($url) {
        $json = file_get_contents($url);
        $data = json_decode($json,true);
        if ($data) {
            if (isset($data['total'])) {
                return $data;
            } else {
                throw new SWCEException(sprintf(__("%s returned an invalid JSON response", 'culture-object'), 'SWCE'));
            }
        } else {
            throw new SWCEException(sprintf(__("%s returned an invalid response: ", 'culture-object').$json, 'SWCE'));
        }
    }
    
    function generate_settings_field_input_text($args) {
        $field = $args['field'];
        $value = get_option($field);
        echo sprintf('<input type="text" name="%s" id="%s" value="%s" />', $field, $field, $value);
    }
    
    function perform_ajax_sync() {
        
        set_time_limit(0);
        ini_set('memory_limit','768M');
        
        $token = get_option('cos_provider_api_token');
        if (empty($token)) {
            $result['state'] = 'error';
            $result['message'] = urlencode("You haven't yet configured your API token in the Culture Object Sync settings",'culture-object');
            echo json_encode($result);
            wp_die();
        }
        
        $site = get_option('cos_provider_site_id');
        if (empty($site)) {
            $result['state'] = 'error';
            $result['message'] = urlencode("You haven't yet configured the SWCE site ID in the Culture Object Sync settings",'culture-object');
            echo json_encode($result);
            wp_die();
        }
        
        $result = array();
        
        if (isset($_POST['phase'])) {
            if ($_POST['phase'] == "init") {
                $result['state'] = 'ok';
                $result['next_phase'] = 'page';
                $result['next_page'] = '1';
                
                $url = 'https://swce.herokuapp.com/api/v1/objects?api_token='.$token;
                $dr = $this->perform_request($url);
                
                $result['result'] = array();
                $result['result']['total'] = $dr['total'];
                $result['result']['current_page'] = $dr['current_page'];
                $result['result']['last_page'] = $dr['last_page'];
                
                $result['existing_ids'] = $this->get_current_object_ids();
                
                
            } else if ($_POST['phase'] == "page") {
                
            } else if ($_POST['phase'] == "clean") {
                
            } else {
                $result['state'] = 'error';
                $result['message'] = 'Invalid AJAX request phase';
            }
        } else {
            $result['state'] = 'error';
            $result['message'] = 'Invalid AJAX request';
        }
        
        echo json_encode($result);
        wp_die();
        
        
    }
    
    function perform_sync() {
        
        set_time_limit(0);
        ini_set('memory_limit','768M');
        
        $start = microtime(true);
        
        /*$site = get_option('cos_provider_site_id');
        if (empty($site)) {
            throw new SWCEException(__("You haven't yet configured a URL in the Culture Object Sync settings",'culture-object'));
        }*/
        
        $token = get_option('cos_provider_api_token');
        if (empty($token)) {
            throw new SWCEException(__("You haven't yet configured your API token in the Culture Object Sync settings",'culture-object'));
        }
        
        $site = get_option('cos_provider_site_id');
        if (empty($site)) {
            throw new SWCEException(__("You haven't yet configured your API token in the Culture Object Sync settings",'culture-object'));
        }
        
        $previous_posts = $this->get_current_object_ids();
        
        $url = 'https://swce.herokuapp.com/api/v1/objects?per_page=100&api_token='.$token.'&site='.$site;
        if (isset($_GET['page'])) $url .= "&page=".intval($_GET['page']);
        $result = $this->perform_request($url);
        
        $number_of_objects = $result['total'];
        
        printf(
            __("Importing %d objects.",'culture-object')."<br />\r\n",
            $number_of_objects
        );
        if ($number_of_objects > 0) {
            
            $first = true;
            while($result['next_page_url']) {
                @ob_flush(); @flush();
                if (!$first) {
                    $result = $this->perform_request($result['next_page_url']."&per_page=100&api_token=".$token.'&site='.$site);
                } else {
                    $first = false;
                }
                printf(
                    /* Translators: 1: The current page 2: The last page */
                    __('Loading Page %1$d of %2$d','culture-object')."<br />\r\n",
                    $result['current_page'],
                    $result['last_page']
                );
                foreach($result['data'] as $doc) {
                    $object_exists = $this->object_exists($doc['accession-loan-no']);
                    if (!$object_exists) {
                        $current_objects[] = $this->create_object($doc);
                        echo __("Created object",'culture-object').": ".$doc['accession-loan-no']."<br />\r\n";
                    } else {
                        $current_objects[] = $this->update_object($doc);
                        echo __("Updated object",'culture-object').": ".$doc['accession-loan-no']."<br />\r\n";
                    }
                }
            }
            
            
            $this->clean_objects($current_objects,$previous_posts);
        }
            
        $end = microtime(true);
        
        printf(
            __("Sync Complete in %d seconds",'culture-object')."\r\n",
            ($end-$start)
        );
        
        die();
        
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
                'SWCE'
            )."<br />";
        }
        
    }
    
    function create_object($doc) {
        $post = array(
            'post_title'    => $doc['simple-name'],
            'post_type'     => 'object',
            'post_status'   => 'publish',
        );
        $post_id = wp_insert_post($post);
        $this->update_object_meta($post_id,$doc);
        return $post_id;
    }
    
    
    function update_object($doc) {
        $existing_id = $this->existing_object_id($doc['accession-loan-no']);
        $post = array(
            'ID'            => $existing_id,
            'post_title'    => $doc['simple-name'],
            'post_type'     => 'object',
            'post_status'   => 'publish',
        );
        $post_id = wp_update_post($post);
        $this->update_object_meta($post_id,$doc);
        return $post_id;
    }
    
    function set_category($post_id, $category_array) {
        //Check if the category already exists.
        $term = term_exists($category_array['name'],'object_category');
        if (!$term) {
            $term = wp_insert_term($category_array['name'],'object_category');
        }
        wp_set_post_terms($post_id, $term, 'object_category');
    }
    
    function update_object_meta($post_id,$doc) {
        unset($doc['site']);
        
        //process the category.
        $this->set_category($post_id,$doc['category']);
        unset($doc['category']);
        
        foreach($doc as $key => $value) {
            if (empty($value)) continue;
            $key = strtolower($key);
            update_post_meta($post_id,$key,$value);
        }
    }
    
    function object_exists($id) {
        $args = array(
            'post_type' => 'object',
            'meta_key' => 'accession-loan-no',
            'meta_value' => $id,
        );
        return (count(get_posts($args)) > 0) ? true : false;
    }
    
    function existing_object_id($id) {
        $args = array(
            'post_type' => 'object',
            'meta_key' => 'accession-loan-no',
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