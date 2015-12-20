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
		
		$show_message = get_transient('cos_csv_show_message');
		if ($show_message) {
			echo "<p><strong>";
			_e('Your CSV import was successful', 'culture-object');
			echo "</strong></p>";
			
			echo '<a href="#" id="show_csv_import_log">';
			_e('Show Log', 'culture-object');
			echo '</a>';
			
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
			echo '<p>';
			_e('Upload a CSV to import. Line 1 of the CSV must be field names. All other lines must equal the number of labels from that row. The first column of the CSV must be a unique identifier.', 'culture-object');
			echo '</p>';
			
			echo '<form id="csv_import_form" method="post" action="" enctype="multipart/form-data">';
				echo '<input type="file" name="cos_csv_import_file" />';
				echo '<input type="hidden" name="cos_csv_nonce" value="'.wp_create_nonce('cos_csv_import').'" /><br /><br />';
				echo '<input id="csv_import_submit" type="button" class="button button-primary" value="';
				_e('Import CSV File', 'culture-object');
				echo '" />';
			echo '</form>';
			echo '<script>
			jQuery("#csv_import_submit").click(function(e) {
				jQuery("#csv_import_submit").val("'.esc_html__('Importing... This may take some time...', 'culture-object').'");
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
		
		$rows = str_getcsv($data, "\n");
		$fields = str_getcsv(array_shift($rows));
		
		if (!is_array($rows)) {
			//Somethings gone wrong. This data is invalid.
			throw new Exception(__("Unable to import. This file appears to be incompatible. It appears there is only one row of data in the file.", 'culture-object'));
			return;
		}
		
		$number_of_fields = count($fields);
		$data_array = array();
		$ids = array();
		foreach($rows as $row) {
			$new_row = str_getcsv($row);
			if (count($new_row) > $number_of_fields) {
				throw new Exception(sprintf(
					/* Translators: 1: A row number from the CSV 2: The number of fields in that row 3: The number of fields defined by the first row. */
					__("Row %1$s of this CSV file contains %2$s fields, but the field keys only provides names for %3$s.\r\nTo prevent something bad happening, we're bailing on this import.", 'culture-object'),
					count($data_array)+2,
					count($new_row),
					$number_of_fields
				));
				return;
			}
			if (in_array($new_row[0], $ids)) {
				throw new Exception(sprintf(
					__("Row %s of this CSV contains a duplicate unique object ID in column 1. This isn't supported.", 'culture-object'),
					count($data_array)+2
				));
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
					$import_status[] = __("Created object", 'culture-object').': '.$doc[0];
					$created++;
				} else {
					$current_objects[] = $this->update_object($doc, $fields);
					$import_status[] = __("Updated object", 'culture-object').': '.$doc[0];
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
			'CSV',
			$created,
			$updated,
			$deleted,
			round($import_duration, 2)
		), 0);
		
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
			$import_delete[] = sprintf(
				/* Translators: 1: A WordPress Post ID 2: The type of file or the provider name (CSV, AdLib, etc) */
				__('Removed Post ID %1$d as it is no longer in the exported list of objects from %2$s', 'culture-object'),
				$remove_id,
				'CSV'
			);
			$deleted++;
		}
		
		set_transient('cos_csv_deleted', $import_delete, 0);
		
		return $deleted;
		
	}
	
	function create_object($doc, $fields) {
		$post = array(
			'post_title'				=> $doc[0],
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
		if (empty($post)) throw new Exception(__("Called existing_object_id for an object that doesn't exist. This is likely a bug in your provider plugin, but because it is probably unsafe to continue the import, it has been aborted.",'culture-object'));
		return $post['ID'];
	}
	
	
}

?>
