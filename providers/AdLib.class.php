<?php

class CollectionSpaceException extends \CultureObject\Exception\ProviderException { }

class CollectionSpace extends \CultureObject\Provider {
	
	private $provider = array(
		'name' => 'CollectionSpace',
		'version' => '1.0',
		'developer' => 'Thirty8 Digital',
		'cron' => false
	);
	
	function get_provider_information() {
		return $this->provider;
	}
	
	function execute_load_action() {
		if (isset($_FILES['cos_collectionspace_import_file']) && isset($_POST['cos_collectionspace_nonce'])) {
			if (wp_verify_nonce($_POST['cos_collectionspace_nonce'], 'cos_collectionspace_import')) {
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
		
		$end = microtime(true);
		
		$import_duration = $end-$start;
		
		set_transient('cos_message', "CollectionSpace import completed with ".$created." objects created, ".$updated." updated and ".$deleted." deleted in ".round($import_duration, 2)." seconds.", 0);
		
		set_transient('cos_collectionspace_show_message', true, 0);
		set_transient('cos_collectionspace_status', $import_status, 0);
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
			$import_delete[] = "Removed Post ID $remove_id as it is no longer in the exported list of objects from CollectionSpace";
			$deleted++;
		}
		
		set_transient('cos_collectionspace_deleted', $import_delete, 0);
		
		return $deleted;
		
	}
	
	function create_object($doc) {
		$post = array(
			'post_title'				=> $doc['title'],
			'post_type'				 => 'object',
			'post_status'			 => 'publish',
		);
		$post_id = wp_insert_post($post);
		$this->update_object_meta($post_id,$doc);
		return $post_id;
	}
	
	
	function update_object($doc) {
		$existing_id = $this->existing_object_id($doc['object_number']);
		$post = array(
			'ID'								=> $existing_id,
			'post_title'				=> $doc['title'],
			'post_type'				 => 'object',
			'post_status'			 => 'publish',
		);
		$post_id = wp_update_post($post);
		$this->update_object_meta($post_id,$doc);
		return $post_id;
	}
	
	function update_object_meta($post_id,$doc) {
		foreach($doc as $key => $value) {
			if (is_array($value)) {
				$value = array_filter($value);
				$value = array_unique($value, SORT_REGULAR);
				if (count($value) == 1) $value = array_pop($value);
			}
			if ($key == "reproduction.reference") {
				if (is_array($value)) {
					$newvalue = array();
					foreach($value as $img) {
						$newvalue[] = str_replace('\\','/',$img);
					}
					$value = $newvalue;
				} else {
					$value = str_replace('\\','/',$value);
				}
			}
			update_post_meta($post_id,$key,$value);
		}
	}
	
	function object_exists($id) {
		$args = array(
			'post_type' => 'object',
			'meta_key' => 'object_number',
			'meta_value' => $id,
		);
		return (count(get_posts($args)) > 0) ? true : false;
	}
	
	function existing_object_id($id) {
		$args = array(
			'post_type' => 'object',
			'meta_key' => 'object_number',
			'meta_value' => $id
		);
		$posts = get_posts($args);
		if (count($posts) == 0) throw new Exception("BUG: called existing_object_id for an object that doesn't exist.");
		return $posts[0]->ID;
	}
	
	
}

?>