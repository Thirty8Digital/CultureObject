<?php
    
require('vendor/autoload.php');

class CSV2Exception extends \CultureObject\Exception\ProviderException { }

class CSV2 extends \CultureObject\Provider {
    
    private $provider = array(
        'name' => 'CSV2',
        'version' => '2.0',
        'developer' => 'Thirty8 Digital',
        'cron' => false,
        'supports_remap' => false,
        'no_options' => true,
        'ajax' => true
    );
    
    function __construct() {
        if (is_admin()) {
            add_action('admin_enqueue_scripts', array($this,'add_provider_assets'));
        }
    }
    
    function add_provider_assets() {
        $js_url = plugins_url('/assets/admin.js?nc='.time(), __FILE__);
        wp_enqueue_script('jquery-ui');
        wp_enqueue_script('jquery-ui-core');
        wp_register_script('csv2_admin_js', $js_url, array('jquery','jquery-ui-core','jquery-ui-progressbar'), '1.0.0', true);
        wp_enqueue_script('csv2_admin_js');
        
		wp_enqueue_style('jquery-ui-css', plugin_dir_url( __FILE__ ).'assets/jquery-ui-fresh.css');
		wp_enqueue_style('csv2_admin_css', plugin_dir_url( __FILE__ ).'assets/admin.css?nc='.time());
    }
    
    function get_provider_information() {
        return $this->provider;
    }
    
    function execute_load_action() {
        if (isset($_FILES['cos_csv_import_file']) && isset($_POST['cos_csv_nonce'])) {
            if (wp_verify_nonce($_POST['cos_csv_nonce'], 'cos_csv_import') && current_user_can('upload_files')) {
                $this->save_upload();
            } else {
                wp_die(__("Security Violation.", 'culture-object'));
            }
        }
        if (isset($_POST['delete_uploaded_file']) && isset($_POST['cos_csv_nonce'])) {
            if (wp_verify_nonce($_POST['cos_csv_nonce'], 'cos_csv_delete_uploaded_file')) {
                $this->delete_uploaded_file();
            } else {
                wp_die(__("Security Violation.", 'culture-object'));
            }
        }

    }
    
    function register_settings() {
        return;
    }
    
    function generate_settings_outside_form_html() {
        
        $this->output_js_localization();
    
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
        
        if ($path = $this->has_uploaded_file()) {
            $this->parse_uploaded_file($path);
        } else {
            $this->display_upload_prompt();
        }
    }
    
    function parse_uploaded_file($path) {
        if (!is_file($path)) throw new CSV2Exception(__("An error occurred when trying to parse the CSV.", 'culture-object'));

        $headers = $this->get_csv_chunk($path,1,1);
        $data = $this->get_csv_data($path);
        
        echo '<p>';
        printf(
            /* Translators: 1: CSV File Name 2: Number of columns in the CSV 3: Provider Developer */
            __('Your uploaded CSV "%1$s" contains %2$d columns and %3$d rows.', 'culture-object'),
            basename($path),
            $data[0]['totalColumns'],
            $data[0]['totalRows']
        );
        echo ' <a id="delete_uploaded_csv" href="#">';
        _e('Remove uploaded CSV?', 'culture-object');
        echo '</a>';
        echo '</p>';
        
        echo '<p>'.__('To begin the import, click the button below.', 'culture-object').'</p>';
        
        echo '<input id="csv_perform_ajax_import" data-sync-key="'.get_option('cos_core_sync_key').'" data-starting-nonce="'.wp_create_nonce('cos_ajax_import_request').'" type="button" class="button button-primary" value="';
        _e('Process Import', 'culture-object');
        echo '" />';
        
        echo '<div id="csv_import_progressbar"><div class="progress-label">Starting Import...</div></div>';
        echo '<div id="csv_import_detail"></div>';
        
        echo '<form id="delete_uploaded_csv_form" method="post" action="">';
            echo '<input type="hidden" name="cos_csv_nonce" value="'.wp_create_nonce('cos_csv_delete_uploaded_file').'" /><br /><br />';
            echo '<input type="hidden" name="delete_uploaded_file" value="true" />';
        echo '</form>';
    }
    
    function get_csv_data($path) {
        $objReader = PHPExcel_IOFactory::createReader('CSV');
        return $objReader->listWorksheetInfo($path);
    }
    
    function get_csv_chunk($path,$start,$count = 50) {
        $objReader = PHPExcel_IOFactory::createReader('CSV'); 
        $chunkFilter = new chunkReadFilter();
        $objReader->setReadFilter($chunkFilter); 
        
        $chunkFilter->setRows($start+1,$count);  
        $csv = $objReader->load($path); 
        $worksheet = $csv->getActiveSheet();
        
        return $worksheet->toArray();
    }
    
    function perform_ajax_sync() {
    	$start = $_POST['start'];
    	$count = 50;
    	if ($path = $this->has_uploaded_file()) {
        	$info = $this->get_csv_data($path);
            $data = $this->get_csv_chunk($path, $start, $count);
            $result = $this->import_chunk($data);
            $result['total_rows'] = $info[0]['totalRows'];
            if ($start + $count < $result['total_rows']) {
                $result['next_start'] = $start + $count;
                $result['complete'] = false;
                $result['percentage'] = round((100/$info[0]['totalRows'])*($start+$count));
                $result['next_nonce'] = wp_create_nonce('cos_ajax_import_request');
            } else {
                $result['complete'] = true;
                $result['percentage'] = 100;
            }
            return $result;
        } else throw new CSV2Exception(__('Attempted to import without a file uploaded.', 'culture-object'));
    }
    
    function import_chunk($data) {
        $fields = array_shift($data);
        $number_of_fields = count($fields);
        
        $data_array = $current_objects = [];
        $updated = $created = 0;
        
        foreach($data as $new_row) {
            if (empty($new_row[0])) continue;
            if (count($new_row) > $number_of_fields) {
                throw new CSV2Exception(sprintf(
                    /* Translators: 1: A row number from the CSV 2: The number of fields in that row 3: The number of fields defined by the first row. */
                    __("Row %1$s of this CSV file contains %2$s fields, but the field keys only provides names for %3$s.\r\nTo prevent something bad happening, we're bailing on this import.", 'culture-object'),
                    count($data_array)+2,
                    count($new_row),
                    $number_of_fields
                ));
                return;
            }
            $data_array[] = $new_row;
        }
        
        $number_of_objects = count($data_array);
        if ($number_of_objects > 0) {
            foreach($data_array as $doc) {
                
                $object_exists = $this->object_exists($doc[0]);
                
                if (!$object_exists) {
                    $current_objects[] = $this->create_object($doc, $fields);
                    $import_status[] = __("Created object", 'culture-object').': '.$doc[0];
                    $created++;
                } else {
                    $current_objects[] = $this->update_object($doc, $fields);
                    $import_status[] = __("Updated object", 'culture-object').': '.$doc[0];
                    $updated++;
                }
            }
        }
        
        $return = [];
        $return['chunk_objects'] = $current_objects;
        $return['import_status'] = $import_status;
        $return['processed_count'] = $number_of_objects;
        $return['created'] = $created;
        $return['updated'] = $updated;
        return $return;

    }
    
    function output_js_localization() {
        echo '<script>
        strings = {};
        strings.uploading_please_wait = "'.esc_html__('Uploading... This may take some time...', 'culture-object').'";
        strings.importing_please_wait = "'.esc_html__('Importing... This may take some time...', 'culture-object').'";
        </script>';
    }
    
    function display_upload_prompt() {
        echo '<p>';
        _e('Select a CSV to upload','culture-object');
        echo '</p>';
        
        echo '<form id="csv_import_form" method="post" action="" enctype="multipart/form-data">';
            echo '<input type="file" name="cos_csv_import_file" />';
            echo '<input type="hidden" name="cos_csv_nonce" value="'.wp_create_nonce('cos_csv_import').'" /><br /><br />';
            echo '<input id="csv_import_submit" type="button" class="button button-primary" value="';
            _e('Upload CSV File', 'culture-object');
            echo '" />';
        echo '</form>';
    }
    
    function save_upload() {
        $upload = wp_upload_dir();
        $upload_dir = $upload['basedir'];
        $upload_dir = $upload_dir . '/culture-object/';
        if (! is_dir($upload_dir)) {
           mkdir( $upload_dir, 0700 );
        }
        
        $file = $_FILES['cos_csv_import_file'];
        if ($file['error'] !== 0) {
            throw new CSV2Exception(sprintf(
                /* Translators: %s: The numeric error code PHP reported for an upload failure */
                __("Unable to import. PHP reported an error code: %s", 'culture-object'),
                $file['error']
            ));
            return;
        }
        
        if ($file['type'] != 'text/csv') {
            throw new CSV2Exception(__("Unable to import. You didn't upload a CSV file.", 'culture-object'));
            return;
        }
        
        $current_path = $this->has_uploaded_file();
        if ($current_path && is_file($current_path)) {
            unlink($current_path);
            delete_site_option('cos_csv2_uploaded_file_path');
        }
        
        $file_name = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $file['name']);
        $full_path = $upload_dir.$file_name;
        if (move_uploaded_file($file['tmp_name'], $full_path)) {
            update_site_option('cos_csv2_uploaded_file_path', $full_path);
            return;
        } else {
            throw new CSV2Exception(__("Unable to import. Could not write file to uploads folder.", 'culture-object'));
        }
    }
    
    function delete_uploaded_file() {
        $path = get_site_option('cos_csv2_uploaded_file_path');
        if (is_file($path)) {
            unlink($path);
        }
        delete_site_option('cos_csv2_uploaded_file_path');
    }
    
    function has_uploaded_file() {
        $path = get_site_option('cos_csv2_uploaded_file_path');
        if ($path) {
            if (is_file($path)) {
                return $path;
            } else {
                delete_site_option('cos_csv2_uploaded_file_path');
                return false;
            }
        }
        return false;
    }
    
    function generate_settings_field_input_text($args) {
        $field = $args['field'];
        $value = get_option($field);
        echo sprintf('<input type="text" name="%s" id="%s" value="%s" />', $field, $field, $value);
    }
    
    function create_object($doc, $fields) {
        $post = array(
            'post_title'             => $doc[0],
            'post_type'              => 'object',
            'post_status'            => 'publish',
        );
        $post_id = wp_insert_post($post);
        $this->update_object_meta($post_id,$doc,$fields);
        return $post_id;
    }
    
    
    function update_object($doc, $fields) {
        $existing_id = $this->existing_object_id($doc[0]);
        $post = array(
            'ID'                     => $existing_id,
            'post_title'             => $doc[0],
            'post_type'              => 'object',
            'post_status'            => 'publish',
        );
        $post_id = wp_update_post($post);
        $this->update_object_meta($post_id,$doc,$fields);
        return $post_id;
    }
    
    function update_object_meta($post_id,$doc,$fields) {
        foreach($fields as $key=>$value) {
            if (!empty($value)) {
                update_post_meta($post_id,$value,$doc[$key]);
            }
        }
    }
    
    function object_exists($id) {
        $post = get_page_by_title($id, ARRAY_A, 'object');
        return (!empty($post)) ? true : false;
    }
    
    function existing_object_id($id) {
        $post = get_page_by_title($id, ARRAY_A, 'object');
        if (empty($post)) throw new Exception(__("Called existing_object_id for an object that doesn't exist. This is likely a bug in your provider plugin, but because it is probably unsafe to continue the import, it has been aborted.",'culture-object'));
        return $post['ID'];
    }

    function perform_sync() {
        throw new CSV2Exception(__('Only AJAX sync is supported for this provider.', 'culture-object'));
    }
    
}

class chunkReadFilter implements PHPExcel_Reader_IReadFilter 
{ 
    private $_startRow = 0; 
    private $_endRow   = 0; 

    /**  Set the list of rows that we want to read  */ 
    public function setRows($startRow, $chunkSize) { 
        $this->_startRow = $startRow; 
        $this->_endRow   = $startRow + $chunkSize; 
    } 

    public function readCell($column, $row, $worksheetName = '') { 
        //  Only read the heading row, and the configured rows 
        if (($row == 1) || ($row >= $this->_startRow && $row < $this->_endRow)) { 
            return true; 
        } 
        return false; 
    } 
} 

?>