<?php

class IndexPlusException extends \CultureObject\Exception\ProviderException { }

class IndexPlus extends \CultureObject\Provider {
    
    private $provider = array(
        'name' => 'Index+',
        'version' => '2.0',
        'developer' => 'Thirty8 Digital & System Simulation',
        'cron' => false,
        'ajax' => true
    );
    
    function add_provider_assets() {
        $screen = get_current_screen();
        if ($screen->base != 'culture-object_page_cos_provider_settings') return;
        $js_url = plugins_url('/assets/admin.js?nc='.time(), __FILE__);
        wp_enqueue_script('jquery-ui');
        wp_enqueue_script('jquery-ui-core');
        wp_register_script('indexplus_admin_js', $js_url, array('jquery','jquery-ui-core','jquery-ui-progressbar'), '1.0.0', true);
        wp_enqueue_script('indexplus_admin_js');
        
		wp_enqueue_style('jquery-ui-css', plugin_dir_url( __FILE__ ).'assets/jquery-ui-fresh.css');
		wp_enqueue_style('indexplus_admin_css', plugin_dir_url( __FILE__ ).'assets/admin.css?nc='.time());
    }
    
    function get_provider_information() {
        return $this->provider;
    }
    
    function register_settings() {
        add_settings_section('cos_provider_settings',__('Provider Settings','culture-object'),array($this,'generate_settings_group_content'),'cos_provider_settings');
    
        register_setting('cos_provider_settings', 'cos_provider_feed_url1');
        register_setting('cos_provider_settings', 'cos_provider_feed_url2');
        register_setting('cos_provider_settings', 'cos_provider_feed_url3');
        register_setting('cos_provider_settings', 'cos_provider_feed_url4');

        add_settings_field('cos_provider_feed_url1', 'Index+ REST API URL', array($this,'generate_settings_field_input_text'), 'cos_provider_settings', 'cos_provider_settings', array('field'=>'cos_provider_feed_url1'));
        add_settings_field('cos_provider_feed_url2', 'Index+ REST API URL', array($this,'generate_settings_field_input_text'), 'cos_provider_settings', 'cos_provider_settings', array('field'=>'cos_provider_feed_url2'));
        add_settings_field('cos_provider_feed_url3', 'Index+ REST API URL', array($this,'generate_settings_field_input_text'), 'cos_provider_settings', 'cos_provider_settings', array('field'=>'cos_provider_feed_url3'));
        add_settings_field('cos_provider_feed_url4', 'Index+ REST API URL', array($this,'generate_settings_field_input_text'), 'cos_provider_settings', 'cos_provider_settings', array('field'=>'cos_provider_feed_url4')); 
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
            echo "<p>".__('IndexPlus\'s JSON data takes a while to generate, so we\'re unable to show a preview here, and import could take a very long time.','culture-object')."</p>";
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
    	
        if (is_admin()) {
            add_action('admin_enqueue_scripts', array($this,'add_provider_assets'));
        }
    }
    
    function ixflatten(&$arr) {
        #we allow 1 dimensional array returns and flatten with 3 pipe characters
        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                #echo "key is ".$key."<br>";
                $arr[$key] = implode("|||", $value);
                #echo "value is ".$arr[$key]."<br>";
            }
        }
        
        return $arr;
    }
    
    function perform_request($url) {
        $json = file_get_contents($url);
        $data = json_decode($json,true);
        if ($data) {
            if (isset($data['result']['found'])) {
                return $data;
            } else {
                throw new IndexPlusException(sprintf(__("%s returned an invalid JSON response", 'culture-object'), 'IndexPlus'));
            }
        } else {
            throw new IndexPlusException(sprintf(__("%s returned an invalid response: ", 'culture-object').$json, 'IndexPlus'));
        }
    }
    
    function generate_settings_field_input_text($args) {
        $field = $args['field'];
        $value = get_option($field);
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        echo sprintf('<input type="text" name="%s" id="%s" value="%s" placeholder="%s" />', $field, $field, $value, $placeholder);
    }
    
    function perform_ajax_sync() {
        
    	if (!isset($_POST['start']) || !isset($_POST['import_id'])) throw new IndexPlusException(__('Invalid AJAX import request', 'culture-object'));
    	
    	$start = $_POST['start'];
    	$service = intval($_POST['service']);
    	$import_id = $_POST['import_id'];
    	$result = [];
    	
    	if ($start === "cleanup") {
        	
            ini_set('memory_limit','2048M');
            
            $objects = get_option('cos_indexplus_import_'.$import_id, array());
            $previous_posts = $this->get_current_object_ids();
            delete_option('cos_indexplus_import_'.$import_id, array());
            return $this->clean_objects($objects,$previous_posts);
            
        } else {
                    	
        	$cleanup = isset($_POST['perform_cleanup']) && $_POST['perform_cleanup'];
        	
        	$result = $this->import_page($service, $start);
        	
            if ($result['imported_count'] < $result['total_objects']) {
                $result['complete'] = false;
                $result['percentage'] = round((100/$result['total_objects'])*$result['imported_count']);
            } else {
                $result['complete'] = true;
                $result['percentage'] = 100;
                if ($service < 4) {
	                $result['next_service'] = $service + 1;
                } else {
	                $result['next_service'] = 'cleanup';
                }
            }
            
            $result['next_nonce'] = wp_create_nonce('cos_ajax_import_request');
            
            if ($cleanup) {
                $objects = get_option('cos_indexplus_import_'.$import_id, array());
                update_option('cos_indexplus_import_'.$import_id, array_merge($objects, $result['chunk_objects']));
            }
            
            return $result;
        }

        
    }
    
    function import_page($service, $offset) {
        $url = get_option('cos_provider_feed_url'.$service);
        if (empty($url)) {
	        $return = [];
	        $return['total_objects'] = $return['next_start'] = $return['imported_count'] = $return['updated'] = $return['processed_count'] = $return['created'] = 0;
	        $return['chunk_objects'] = [];
	        $return['import_status'] = ['Service URL '.$service.' is invalid or not provided'];
	        return $return;
        }
        
        $url = $url.'?limit=20&offset='.intval($offset);
        
        $result = $this->perform_request($url);
    
        $current_objects = [];
        $updated = $created = 0;
        $number_of_objects = count($result['result']['items']);
        
        foreach($result['result']['items'] as $doc) {
	  
            $doc = $this->ixflatten($doc);
            $doc['_cos_object_id'] = $doc['uniqueID'];
            $object_exists = $this->object_exists($doc['uniqueID']);
            if (!$object_exists) {
                $current_objects[] = $this->create_object($doc);
                $import_status[] = __("Created object for service URL", 'culture-object').' '.$service.': '.$doc['uniqueID'];
                $created++;
            } else {
                $current_objects[] = $this->update_object($doc);
                $import_status[] = __("Updated object for service URL", 'culture-object').' '.$service.': '.$doc['uniqueID'];
                $updated++;
            }
        }
        
        $return = [];
        $return['total_objects'] = $result['result']['found'];
        $return['next_start'] = $offset + $number_of_objects;
        $return['imported_count'] = $offset + $number_of_objects;
        $return['chunk_objects'] = $current_objects;
        $return['import_status'] = $import_status;
        $return['processed_count'] = $number_of_objects;
        $return['created'] = $created;
        $return['updated'] = $updated;
        return $return;
    }
    
    function perform_sync() {
        throw new IndexPlusException(__('Only AJAX sync is supported for this provider.', 'culture-object'));
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
        $return = [];
        
        $return['prev'] = $previous_objects;
        $return['new'] = $current_objects;
        
        $import_delete = array();
        
        $deleted = 0;
        
        foreach($to_remove as $remove_id) {
            wp_delete_post($remove_id,true);
            $import_delete[] = sprintf(
                /* Translators: 1: A WordPress Post ID 2: The type of file or the provider name (CSV, AdLib, etc) */
                __('Removed Post ID %1$d as it is no longer in the exported list of objects from %2$s', 'culture-object'),
                $remove_id,
                'CSV'
            );
            $deleted++;
        }
        
        set_transient('cos_indexplus_deleted', $import_delete, 0);
        
        $return['deleted_count'] = $deleted;
        $return['deleted_status'] = $import_delete;
        return $return;
    }
    
    function create_object($doc) {
        $post = array(
            'post_title'    => $doc['title'],
            'post_type'     => 'object',
            'post_status'   => 'publish',
        );
        $post_id = wp_insert_post($post);
        $this->update_object_meta($post_id,$doc);
        return $post_id;
    }
    
    
    function update_object($doc) {
        $existing_id = $this->existing_object_id($doc['uniqueID']);
        $post = array(
            'ID'            => $existing_id,
            'post_title'    => $doc['title'],
            'post_type'     => 'object',
            'post_status'   => 'publish',
			'image'			=> '_nelioefi_url',
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
            'meta_key' => 'uniqueID',
            'meta_value' => $id,
        );
        return (count(get_posts($args)) > 0) ? true : false;
    }
    
    function existing_object_id($id) {
        $args = array(
            'post_type' => 'object',
            'meta_key' => 'uniqueID',
            'meta_value' => $id
        );
        $posts = get_posts($args);
        if (count($posts) == 0) throw new Exception("BUG: called existing_object_id for an object that doesn't exist.");
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
    
    function generate_settings_outside_form_html() {
        
        $this->output_js_localization();
    
        echo "<h3>".__('AJAX Import', 'culture-object')."</h3>";
        
        echo '<div id="hide-on-import">';
        echo "<p>".__('Once you have saved your settings above, you can begin your import by clicking below.', 'culture-object')."</p>";
        
        echo '<fieldset>
        	<label for="perform_cleanup">
        		<input name="perform_cleanup" type="checkbox" id="perform_cleanup" value="1" />
        		<span>'.esc_attr__('Delete existing objects not in this import?', 'culture-object').'</span>
        	</label>
        </fieldset>';
        
        echo '<input id="csv_perform_ajax_import" data-import-id="'.uniqid('', true).'" data-sync-key="'.get_option('cos_core_sync_key').'" data-starting-nonce="'.wp_create_nonce('cos_ajax_import_request').'" type="button" class="button button-primary" value="';
        _e('Begin Import', 'culture-object');
        echo '" />';
        echo "</div>";
        
        echo '<div id="csv_import_progressbar"><div class="progress-label">'.__('Starting Import...', 'culture-object').'</div></div>';
        echo '<div id="csv_import_detail"></div>';
    }
    
    function output_js_localization() {
        echo '<script>
        strings = {};
        strings.uploading_please_wait = "'.esc_html__('Uploading... This may take some time...', 'culture-object').'";
        strings.importing_please_wait = "'.esc_html__('Importing... This may take some time...', 'culture-object').'";
        strings.imported = "'.esc_html__('Imported', 'culture-object').'";
        strings.objects = "'.esc_html__('objects', 'culture-object').'";
        strings.service = "'.esc_html__('for service URL', 'culture-object').'";
        strings.objects_imported = "'.esc_html__('objects imported', 'culture-object').'";
        strings.objects_deleted = "'.esc_html__('objects deleted', 'culture-object').'";
        strings.import_complete = "'.esc_html__('Import complete.', 'culture-object').'";
        strings.performing_cleanup = "'.esc_html__('Performing cleanup, please wait... This can take a long time if you have deleted a lot of objects.', 'culture-object').'";
        </script>';
    }
    
}

?>