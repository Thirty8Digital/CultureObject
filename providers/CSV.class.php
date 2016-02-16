<?php

class CSVException extends \CultureObject\Exception\ProviderException { }

class CSV extends \CultureObject\Provider {
	
	private $provider = array(
		'name' => 'CSV',
		'version' => '1.0',
		'developer' => 'Thirty8 Digital',
		'cron' => false,
		'supports_remap' => false,
		'no_options' => true
	);
	
	function get_provider_information() {
		return $this->provider;
	}
	
	function execute_load_action() {
		if (isset($_FILES['cos_csv_import_file']) && isset($_POST['cos_csv_nonce'])) {
			if (wp_verify_nonce($_POST['cos_csv_nonce'], 'cos_csv_import')) {
				$this->perform_sync();
			} else {
				die("Security Violation.");
			}
		}
	}
	
	function register_settings() {
		return;
	}
	
	function generate_settings_outside_form_html() {
	
		echo "<h3>Provider Settings</h3>";
		
		echo "<p>You're currently using version ".$this->provider['version']." of the ".$this->provider['name']." sync provider by ".$this->provider['developer'].".</p>";
		
		$show_message = get_transient('cos_csv_show_message');
		if ($show_message) {
			echo "<p><strong>Your CSV import was successful.</strong></p>";
			
			echo '<a href="#" id="show_csv_import_log">Show Log</a>';
			
			?>
			
			<script>
			jQuery('#show_csv_import_log').click(function(e) {
				e.preventDefault();
				jQuery('#show_csv_import_log').remove();
				jQuery('#csv_import_log').css('display','block');
			});
			</script>
			
			<?php 
			
			echo '<div id="csv_import_log" style="display: none">';
			if (get_transient('cos_csv_status')) echo implode('<br />',get_transient('cos_csv_status'))."<br />";
			if (get_transient('cos_csv_deleted')) echo implode('<br />',get_transient('cos_csv_deleted'));
			echo '</div>';
			
			delete_transient('cos_csv_show_message');
			delete_transient('cos_csv_status');
			delete_transient('cos_csv_deleted');
			
			
		} else {		
			echo "<p>Upload a CSV to import. Line 1 of the CSV must be field names. All other lines must equal the number of labels from that row. The first column of the CSV must be a unique identifier. The second column should be the title of the object and will be imported into the title field.</p>";
			
			echo '<form id="csv_import_form" method="post" action="" enctype="multipart/form-data">';
				echo '<input type="file" name="cos_csv_import_file" />';
				echo '<input type="hidden" name="cos_csv_nonce" value="'.wp_create_nonce('cos_csv_import').'" /><br /><br />';
				echo '<input id="csv_import_submit" type="button" class="button button-primary" value="Import CSV Dump" />';
			echo '</form>';
			echo '<script>
			jQuery("#csv_import_submit").click(function(e) {
				jQuery("#csv_import_submit").val("Importing... This may take some time...");
				jQuery("#csv_import_submit").addClass("button-disabled");
				window.setTimeout(\'jQuery("#csv_import_form").submit();\',100);
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
		ini_set("auto_detect_line_endings", true);
		
		$start = microtime(true);
				
		$previous_posts = $this->get_current_object_ids();
		
		$file = $_FILES['cos_csv_import_file'];
		if ($file['error'] !== 0) {
			throw new Exception("Unable to import. PHP reported an error code ".$file['error']);
			return;
		}
		$data = file_get_contents($file['tmp_name']);
		if (!$data) {
			throw new Exception("Unable to import: File upload corrupt");
			return;
		}
		
		$rows = str_getcsv($data, "\n");
		$fields = str_getcsv(array_shift($rows));
		
		if (!is_array($rows)) {
			//Somethings gone wrong. This data is invalid.
			throw new Exception("Unable to import. This file appears to be incompatible. It appears there is only one row of data in the file.");
			return;
		}
		
		$number_of_fields = count($fields);
		$data_array = array();
		$ids = array();
		foreach($rows as $row) {
			$new_row = str_getcsv($row);
			if (count($new_row) > $number_of_fields) {
				throw new Exception("Row ".(count($data_array)+2)." of this CSV file contains ".count($new_row)." fields, but the field keys only provides names for ".$number_of_fields.".\r\nTo prevent something bad happening, we're bailing on this import.");
				return;
			}
			if (in_array($new_row[0], $ids)) {
				throw new Exception("Row ".(count($data_array)+2)." of this CSV contains a duplicate unique object ID in column 1. This isn't supported.");
				return;
			}
			$ids[] = $new_row[0];
			$data_array[] = $new_row;
		}
		
		unlink($file['tmp_name']);
		
		$created = 0;
		$updated = 0;
		
		$number_of_objects = count($data_array);
		if ($number_of_objects > 0) {
			foreach($data_array as $doc) {
				
				$object_exists = $this->object_exists($doc[0]);
				
				if (!$object_exists) {
					$current_objects[] = $this->create_object($doc, $fields);
					$import_status[] = "Created object: ".$doc[0];
					$created++;
				} else {
					$current_objects[] = $this->update_object($doc, $fields);
					$import_status[] = "Updated object: ".$doc[0];
					$updated++;
				}
				
				//Send a space and a flush with every object import. This might overcome FastCGI's timeout. Worth a shot.
				echo ' ';
				flush();
				ob_flush();
				
			}
			$deleted = $this->clean_objects($current_objects,$previous_posts);
		}
					
		$end = microtime(true);
		
		$import_duration = $end-$start;
		
		set_transient('cos_message', "CSV import completed with ".$created." objects created, ".$updated." updated and ".$deleted." deleted in ".round($import_duration, 2)." seconds.", 0);
		
		set_transient('cos_csv_show_message', true, 0);
		set_transient('cos_csv_status', $import_status, 0);
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
			$import_delete[] = "Removed Post ID $remove_id as it is no longer in the exported list of objects from CSV";
			$deleted++;
		}
		
		set_transient('cos_csv_deleted', $import_delete, 0);
		
		return $deleted;
		
	}
	
	function create_object($doc, $fields) {
		$post = array(
			'post_title'				=> $doc[1],
			'post_type'				 => 'object',
			'post_status'			 => 'publish',
		);
		$post_id = wp_insert_post($post);
		$this->update_object_meta($post_id,$doc,$fields);
		return $post_id;
	}
	
	
	function update_object($doc, $fields) {
		$existing_id = $this->existing_object_id($doc[0]);
		$post = array(
			'ID'								=> $existing_id,
			'post_title'				=> $doc[0],
			'post_type'				 => 'object',
			'post_status'			 => 'publish',
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
		if (empty($post)) throw new Exception("BUG: called existing_object_id for an object that doesn't exist.");
		return $post['ID'];
	}
	
	
}

?>
