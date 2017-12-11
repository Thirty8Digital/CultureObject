<?php

class SWCEException extends \CultureObject\Exception\ProviderException { }

class SWCE extends \CultureObject\Provider {
    
    private $provider = array(
        'name' => 'SWCE',
        'version' => '1.3.1',
        'developer' => 'Thirty8 Digital',
        'cron' => false,
        'ajax' => true
    );
    
    function add_provider_assets() {
        $screen = get_current_screen();
        if ($screen->base != 'culture-object_page_cos_provider_settings') return;
        $js_url = plugins_url('/assets/admin.js?nc='.time(), __FILE__);
        wp_enqueue_script('jquery-ui');
        wp_enqueue_script('jquery-ui-core');
        wp_register_script('swce_admin_js', $js_url, array('jquery','jquery-ui-core','jquery-ui-progressbar'), '1.0.0', true);
        wp_enqueue_script('swce_admin_js');
        
		wp_enqueue_style('jquery-ui-css', plugin_dir_url( __FILE__ ).'assets/jquery-ui-fresh.css');
		wp_enqueue_style('swce_admin_css', plugin_dir_url( __FILE__ ).'assets/admin.css?nc='.time());
    }
    
    function get_provider_information() {
        return $this->provider;
    }
    
    function register_settings() {
        add_settings_section('cos_provider_settings',__('Provider Settings','culture-object'),array($this,'generate_settings_group_content'),'cos_provider_settings');
    
        register_setting('cos_provider_settings', 'cos_provider_site_id');
        register_setting('cos_provider_settings', 'cos_provider_api_token');
        register_setting('cos_provider_settings', 'cos_provider_category_slug');
        
        add_settings_field('cos_provider_site_id', __('SWCE Site ID','culture-object'), array($this,'generate_settings_field_input_text'), 'cos_provider_settings', 'cos_provider_settings', array('field'=>'cos_provider_site_id'));
        
        add_settings_field('cos_provider_category_slug', __('SWCE Category Slug','culture-object'), array($this,'generate_settings_field_input_text'), 'cos_provider_settings', 'cos_provider_settings', array('field'=>'cos_provider_category_slug', 'placeholder' => 'optional category slug'));
        
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
    	
        if (is_admin()) {
            add_action('admin_enqueue_scripts', array($this,'add_provider_assets'));
        }
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
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        echo sprintf('<input type="text" name="%s" id="%s" value="%s" placeholder="%s" />', $field, $field, $value, $placeholder);
    }
    
    function perform_ajax_sync() {
        
    	if (!isset($_POST['start']) || !isset($_POST['import_id'])) throw new SWCEException(__('Invalid AJAX import request', 'culture-object'));
    	
    	$start = $_POST['start'];
    	$import_id = $_POST['import_id'];
    	$result = [];
    	
    	if ($start == "cleanup") {
        	
            ini_set('memory_limit','2048M');
            
            $objects = get_option('cos_swce_import_'.$import_id, array());
            $previous_posts = $this->get_current_object_ids();
            delete_option('cos_swce_import_'.$import_id, array());
            return $this->clean_objects($objects,$previous_posts);
            
        } else {
                    	
        	$cleanup = isset($_POST['perform_cleanup']) && $_POST['perform_cleanup'];
        	
        	$result = $this->import_page($start);
        	
            if ($result['current_page'] != $result['last_page']) {
                $result['complete'] = false;
                $result['percentage'] = round((100/$result['last_page'])*$result['current_page']);
            } else {
                $result['complete'] = true;
                $result['percentage'] = 100;
            }
            
            $result['next_nonce'] = wp_create_nonce('cos_ajax_import_request');
            
            if ($cleanup) {
                $objects = get_option('cos_swce_import_'.$import_id, array());
                update_option('cos_swce_import_'.$import_id, array_merge($objects, $result['chunk_objects']));
            }
            
            return $result;
        }

        
    }
    
    function import_page($page) {
        $token = get_option('cos_provider_api_token');
        if (empty($token)) {
            throw new SWCEException(__("You haven't yet configured your API token in the Culture Object Sync settings",'culture-object'));
        }
        
        $site = get_option('cos_provider_site_id');
        if (empty($site) && intval($site) !== 0) {
            throw new SWCEException(__("You haven't yet configured your Site ID in the Culture Object Sync settings",'culture-object'));
        }
        
        $category = get_option('cos_provider_category_slug');
        if (empty($category)) $category = '';
        
        $url = 'https://swce.herokuapp.com/api/v1/objects?per_page=100&api_token='.urlencode($token).'&site='.urlencode($site).'&category='.urlencode($category).'&page='.intval($page);
        
        $result = $this->perform_request($url);
    
        $current_objects = [];
        $updated = $created = 0;
        $number_of_objects = count($result['data']);
        
        foreach($result['data'] as $doc) {
	        $doc['_cos_object_id'] = $doc['accession-loan-no'];
            $object_exists = $this->object_exists($doc['accession-loan-no']);
            if (!$object_exists) {
                $current_objects[] = $this->create_object($doc);
                $import_status[] = __("Created object", 'culture-object').': '.$doc['accession-loan-no'];
                $created++;
            } else {
                $current_objects[] = $this->update_object($doc);
                $import_status[] = __("Updated object", 'culture-object').': '.$doc['accession-loan-no'];
                $updated++;
            }
        }
        
        $return = [];
        $return['total_objects'] = $result['total'];
        $return['next_start'] = $result['current_page'] + 1;
        $return['current_page'] = $result['current_page'];
        $return['imported_count'] = (($result['current_page']-1)*100) + $number_of_objects;
        $return['last_page'] = $result['last_page'];
        $return['chunk_objects'] = $current_objects;
        $return['import_status'] = $import_status;
        $return['processed_count'] = $number_of_objects;
        $return['created'] = $created;
        $return['updated'] = $updated;
        return $return;
    }
    
    function perform_sync() {
        throw new SWCEException(__('Only AJAX sync is supported for this provider.', 'culture-object'));
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
        
        set_transient('cos_swce_deleted', $import_delete, 0);
        
        $return = [];
        $return['deleted_count'] = $deleted;
        $return['deleted_status'] = $import_delete;
        return $return;
    }
    
    function create_object($doc) {
        $post = array(
            'post_title'    => $doc['simple-name'],
            'post_name'     => str_replace('/','-',$doc['accession-loan-no']),
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
            'post_name'     => str_replace('/','-',$doc['accession-loan-no']),
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
        $doc['site-name'] = $doc['site']['name'];
        $doc['site-id'] = $doc['site']['id'];
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
        strings.objects_imported = "'.esc_html__('objects imported', 'culture-object').'";
        strings.objects_deleted = "'.esc_html__('objects deleted', 'culture-object').'";
        strings.import_complete = "'.esc_html__('Import complete.', 'culture-object').'";
        strings.performing_cleanup = "'.esc_html__('Performing cleanup, please wait... This can take a long time if you have deleted a lot of objects.', 'culture-object').'";
        </script>';
    }
    
}

?>