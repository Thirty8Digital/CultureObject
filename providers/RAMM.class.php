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
		add_settings_section('cos_provider_settings','Provider Settings',array($this,'generate_settings_group_content'),'cos_settings');
	
		register_setting('cos_settings', 'cos_provider_feed_url');
		
		add_settings_field('cos_provider_feed_url', 'RAMM Feed URL', array($this,'generate_settings_field_input_text'), 'cos_settings', 'cos_provider_settings', array('field'=>'cos_provider_feed_url'));
	}
	
	function generate_settings_group_content() {
		echo "<p>You're currently using version ".$this->provider['version']." of the ".$this->provider['name']." sync provider by ".$this->provider['developer'].".</p>";
		
		
		$authority = get_option('cos_provider_feed_url');
		if (!empty($authority)) {
			echo "<p>RAMM's JSON data takes a while to generate, so we're unable to show a preview here, and import could take a very long time.</p>";
		}
		
	}
	
	function perform_request($url) {
		$json = file_get_contents($url);
		$data = json_decode($json,true);
		if ($data) {
			if (isset($data[0]['Id'])) {
				return $data;
			} else {
				throw new RAMMException("RAMM returned an invalid JSON response");
			}
		} else {
			throw new RAMMException("RAMM returned an invalid response: ".$json);
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
			throw new RAMMException("You haven't yet configured a URL in the Culture Object Sync settings");
		}
		
		$previous_posts = $this->get_current_object_ids();
		
		$result = $this->perform_request($url);
		
		$number_of_objects = count($result);
		echo "Importing ".$number_of_objects." objects.<br />\r\n";
		if ($number_of_objects > 0) {
			foreach($result as $doc) {
				$doc['identifier'] = $doc['Id'];
				unset($doc['Id']);
				$object_exists = $this->object_exists($doc['identifier']);
				if (!$object_exists) {
					$current_objects[] = $this->create_object($doc);
					echo "Created object ".$doc['Title']."<br />\r\n";
				} else {
					$current_objects[] = $this->update_object($doc);
					echo "Updated object ".$doc['Title']."<br />\r\n";
				}
			}
			$this->clean_objects($current_objects,$previous_posts);
		}
			
		$end = microtime(true);
		
		echo "Sync Complete in ".($end-$start)." seconds\r\n";
		
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
			echo "Removed Post ID $remove_id as it is no longer in the list of objects from RAMM<br />";
		}
		
	}
	
	function create_object($doc) {
		$post = array(
			'post_title'				=> $doc['Title'],
			'post_type'				 => 'object',
			'post_status'			 => 'publish',
		);
		$post_id = wp_insert_post($post);
		$this->update_object_meta($post_id,$doc);
		return $post_id;
	}
	
	
	function update_object($doc) {
		$existing_id = $this->existing_object_id($doc['identifier']);
		$post = array(
			'ID'								=> $existing_id,
			'post_title'				=> $doc['Title'],
			'post_type'				 => 'object',
			'post_status'			 => 'publish',
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
	
}

?>