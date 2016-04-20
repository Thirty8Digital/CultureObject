<?php

class EmuException extends \CultureObject\Exception\ProviderException { }

class Emu extends CultureObject\Provider {
    
    private $provider = array(
        'name' => 'Emu',
        'version' => '1.0',
        'developer' => 'Thirty8 Digital',
        'cron' => false,
        'no_options' => true
    );
    
    function get_provider_information() {
        return $this->provider;
    }
    
    function execute_load_action() {
        if (isset($_FILES['cos_emu_import_file']) && isset($_POST['cos_emu_nonce'])) {
            if (wp_verify_nonce($_POST['cos_emu_nonce'], 'cos_emu_import')) {
                $this->perform_sync();
            } else {
                wp_die(__("Security Violation.", 'culture-object'));
            }
        }
    }
    
    function register_settings() {
        return;
    }
    
    function generate_settings_outside_form_html() {
    
        echo "<h3>".__('Provider Settings','culture-object')."</h3>";
        
        echo '<p>';
        printf(
            /* Translators: 1: Provider Plugin Version 2: Provider Name 3: Provider Developer */
            __('You\'re currently using version %1$s of the %2$s sync provider by %3$s.', 'culture-object'),
            $this->provider['version'],
            $this->provider['name'],
            $this->provider['developer']
        );
        echo '</p>';
        
        $show_message = get_transient('cos_emu_show_message');
        if ($show_message) {
            echo "<p><strong>";
            _e('Your CSV import was successful', 'culture-object');
            echo "</strong></p>";
            
            echo '<a href="#" id="show_emu_import_log">';
            _e('Show Log', 'culture-object');
            echo '</a>';
            
            ?>
            
            <script>
            jQuery('#show_emu_import_log').click(function(e) {
                e.preventDefault();
                jQuery('#show_emu_import_log').remove();
                jQuery('#emu_import_log').css('display','block');
            });
            </script>
            
            <?php 
            
            echo '<div id="emu_import_log" style="display: none">';
            if (get_transient('cos_emu_status')) echo implode('<br />',get_transient('cos_emu_status'));
            if (get_transient('cos_emu_deleted')) echo implode('<br />',get_transient('cos_emu_deleted'));
            echo '</div>';
            
            delete_transient('cos_emu_show_message');
            delete_transient('cos_emu_status');
            delete_transient('cos_emu_deleted');
            
            
        } else {        
            echo "<p>".__('You need to upload the JSON export file from emu in order to import.','culture-object')."</p>";
            
            echo '<form id="emu_import_form" method="post" action="" enctype="multipart/form-data">';
                echo '<input type="file" name="cos_emu_import_file" />';
                echo '<input type="hidden" name="cos_emu_nonce" value="'.wp_create_nonce('cos_emu_import').'" /><br /><br />';
                echo '<input id="emu_import_submit" type="button" class="button button-primary" value="Import Emu Dump" />';
            echo '</form>';
            echo '<script>
            jQuery("#emu_import_submit").click(function(e) {
                $("#csv_import_submit").val("'.esc_html__('Importing... This may take some time...', 'culture-object').'");
                $("#emu_import_submit").addClass("button-disabled");
                window.setTimeout(\'$("#emu_import_form").submit();\',100);
            });
            </script>';
        }
    }
    
    function generate_settings_field_input_text($args) {
        $field = $args['field'];
        $value = get_option($field);
        echo sprintf('<input type="text" name="%s" id="%s" value="%s" />', $field, $field, $value);
    }
    
    function perform_sync() {
        set_time_limit(0);
        ini_set('memory_limit','2048M');
        
        $start = microtime(true);
                
        $previous_posts = $this->get_current_object_ids();
        
        $file = $_FILES['cos_emu_import_file'];
        if ($file['error'] !== 0) {
            throw new Exception(sprintf(
                /* Translators: %s: The numeric error code PHP reported for an upload failure */
                __("Unable to import. PHP reported an error code: %s", 'culture-object'),
                $file['error']
            ));
            return;
        }
        $data = file_get_contents($file['tmp_name']);
        if (!$data) {
            throw new Exception(__("Unable to import: File upload corrupt", 'culture-object'));
            return;
        }
        
        $data = json_decode($data,true);
        $created = $updated = 0;
        
        $number_of_objects = 0;
        
        foreach($data as $dataset) {
            if (!isset($dataset['rows']) && isset($dataset[0]['rows'])) $dataset = $dataset[0];
            $number_of_objects = $number_of_objects + count($dataset['rows']);
            foreach($dataset['rows'] as $doc) {
                
                $doc = $this->flattenWithKeys($doc);
                
                $object_exists = $this->object_exists($doc['ID']);
                
                if (!$object_exists) {
                    $current_objects[] = $this->create_object($doc);
                    $import_status[] = __("Created object",'culture-object').": ".$doc['Name'];
                    $created++;
                } else {
                    $current_objects[] = $this->update_object($doc);
                    $import_status[] = __("Updated object",'culture-object').": ".$doc['Name'];
                    $updated++;
                }
                
            }
            $deleted = $this->clean_objects($current_objects,$previous_posts);
        }
                    
        $end = microtime(true);
        
        $import_duration = $end-$start;
        
        set_transient('cos_message', sprintf(
            /* Translators: 1: The name/type of import - usually the provider name. 2: The number of created objects. 3: The number of updated objects. 4: The number of deleted objects. 5: The number of seconds the whole process took to complete */
            __('%1$s import completed with %2$d objects created, %3$d updated and %4$d deleted in %5$d seconds.', 'culture-object'),
            'Emu',
            $created,
            $updated,
            $deleted,
            round($import_duration, 2)
        ), 0);
        set_transient('cos_emu_show_message', true, 0);
        set_transient('cos_emu_status', $import_status, 0);
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
                'emu'
            );
            $deleted++;
        }
        
        set_transient('cos_emu_deleted', $import_delete, 0);
        
        return $deleted;
        
    }
    
    function create_object($doc) {
        $post = array(
            'post_title'                => $doc['Name'],
            'post_type'              => 'object',
            'post_status'            => 'publish',
        );
        $post_id = wp_insert_post($post);
        $this->update_object_meta($post_id,$doc);
        return $post_id;
    }
    
    
    function update_object($doc) {
        $existing_id = $this->existing_object_id($doc['ID']);
        $post = array(
            'ID'                                => $existing_id,
            'post_title'                => $doc['Name'],
            'post_type'              => 'object',
            'post_status'            => 'publish',
        );
        $post_id = wp_update_post($post);
        $this->update_object_meta($post_id,$doc);
        return $post_id;
    }
    
    function update_object_meta($post_id,$doc) {
        $doc['identifier'] = $doc['ID'];
        unset($doc['ID']);
        foreach($doc as $key => $value) {
            $key = strtolower($key);
            if (is_array($value)) {
                $value = array_filter($value);
                $value = array_unique($value, SORT_REGULAR);
                if (count($value) == 1) $value = array_pop($value);
            }
            update_post_meta($post_id,$key,$value);
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
    
    function flattenWithKeys(array $array, array $path = array()) {
        $result = array();

        foreach ($array as $key => $value) {
            $currentPath = array_merge($path, array($key));

            if (is_array($value)) {
                    $result = array_merge($result, $this->flattenWithKeys($value, $currentPath));
            } else {
                    $result[join('_', $currentPath)] = $value;
            }
        }

        return $result;
    }
    
}

?>