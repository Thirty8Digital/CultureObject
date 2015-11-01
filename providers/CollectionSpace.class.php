<?php

class CollectionSpaceException extends \CultureObject\Exception\ProviderException { }

class CollectionSpace extends \CultureObject\Provider {
	
	private $provider = array(
		'name' => 'CollectionSpace',
		'version' => '1.0',
		'developer' => 'Thirty8 Digital',
		'cron' => true
	);
	
	function init() {
		add_action('init', array($this, 'register_prevent_safe_password_save'));
	}
	
	function register_prevent_safe_password_save() {
		add_filter( 'pre_update_option_cos_provider_collectionspace_password', array($this, 'prevent_safe_password_save'), 10, 2 );
	}
	
	function prevent_safe_password_save($new,$old) {
		if ($new == str_repeat('*', strlen($new))) return $old; else return $new;
	}
	
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
		add_settings_section('cos_provider_settings','Provider Settings',array($this,'generate_settings_group_content'),'cos_settings');
	
		register_setting('cos_settings', 'cos_provider_collectionspace_host_uri');
		
		add_settings_field('cos_provider_collectionspace_host_uri', 'CollectionSpace Host URI', array($this,'generate_settings_field_input_text'), 'cos_settings', 'cos_provider_settings', array('field'=>'cos_provider_collectionspace_host_uri'));
	
		register_setting('cos_settings', 'cos_provider_collectionspace_username');
		
		add_settings_field('cos_provider_collectionspace_username', 'CollectionSpace Username', array($this,'generate_settings_field_input_text'), 'cos_settings', 'cos_provider_settings', array('field'=>'cos_provider_collectionspace_username'));
	
		register_setting('cos_settings', 'cos_provider_collectionspace_password');
		
		add_settings_field('cos_provider_collectionspace_password', 'CollectionSpace Password', array($this,'generate_settings_field_input_password'), 'cos_settings', 'cos_provider_settings', array('field'=>'cos_provider_collectionspace_password'));
		
	}
	
	function generate_settings_field_input_text($args) {
		$field = $args['field'];
		$value = get_option($field);
		echo sprintf('<input type="text" name="%s" id="%s" value="%s" />', $field, $field, $value);
	}
	
	function generate_settings_field_input_password($args) {
		$field = $args['field'];
		$value = get_option($field);
		$blurred = str_repeat('*', strlen($value));
		echo sprintf('<input type="password" name="%s" id="%s" value="%s" />', $field, $field, $blurred);
	}
	
	function generate_settings_group_content() {
		echo "<p>You're currently using version ".$this->provider['version']." of the ".$this->provider['name']." sync provider by ".$this->provider['developer'].".</p>";
		
		
		$host = get_option('cos_provider_collectionspace_host_uri');
		$user = get_option('cos_provider_collectionspace_username');
		$pass = get_option('cos_provider_collectionspace_password');
		
		if (!empty($host) && !empty($user) && !empty($pass)) {
			$url = $host.'/collectionobjects/?wf_deleted=false';
			$result = $this->perform_request($url, $this->generate_stream_context($user,$pass));
			if ($result) {
				$number_of_objects = $result['totalItems'];
				echo "<p>There are ".number_format($number_of_objects)." objects currently available to sync from CollectionSpace.</p>";
				echo "<p>Based on this number, you should expect a sync to take approximately ".ceil($number_of_objects/90)." minutes to complete. <br /><small>This number can vary significantly on the speed on your network, server, and database.</small></p>";
				if ($number_of_objects > 100000) echo "<p>CollectionSpace sync only supports 100,000 objects maximum for the sake of performance. Only the first 100,000 objects will sync.</p>";
			} else {
				echo "<p>We couldn't connect to CollectionSpace. Please check the details below and try again.</p>";
			}
		}
		
	}
	
	function generate_stream_context($user,$pass) {
		$creds = sprintf('Authorization: Basic %s', base64_encode($user.':'.$pass));
		$opts = array(
			'http' => array(
				'method' => 'GET',
				'header' => $creds,
				'user_agent' => 'CultureObject (http://www.cultureobject.co.uk), CollectionSpace Provider 1.0'
			) 
		);
		return stream_context_create($opts);
	}
	
	function perform_request($url, $stream_context, $trim_namespace = false) {
		$xml = file_get_contents($url, false, $stream_context);
		if (!$xml) return false;
		$array = $this->xml2array($xml, $trim_namespace);
		return $array;
	}
	
	function perform_sync() {
		set_time_limit(0);
		ini_set('memory_limit','2048M');
		
		$start = microtime(true);
				
		$previous_posts = $this->get_current_object_ids();
		
		$host = get_option('cos_provider_collectionspace_host_uri');
		$user = get_option('cos_provider_collectionspace_username');
		$pass = get_option('cos_provider_collectionspace_password');
		
		if (empty($host) || empty($user) || empty($pass)) throw new CollectionSpaceException('Host, Username or Password is not defined');
		
		$url = $host.'/collectionobjects/?pgSz=0&wf_deleted=false';
		$import = $this->perform_request($url, $this->generate_stream_context($user,$pass));

		$created = 0;
		$updated = 0;
		
		$number_of_objects = count($import['list-item']);
		if ($number_of_objects > 0) {
			foreach($import['list-item'] as $doc) {
				$object_exists = $this->object_exists($doc['csid']);
				
				if (!$object_exists) {
					$current_objects[] = $this->create_object($doc);
					$import_status[] = "Created initial object: ".$doc['csid'];
					$created++;
				} else {
					$current_objects[] = $this->update_object($doc);
					$import_status[] = "Updated initial object: ".$doc['csid'];
					$updated++;
				}
				
			}
			$deleted = $this->clean_objects($current_objects,$previous_posts);
		}
		
		//Now we need to get a list of new items.
		$args = array(
			'post_type' => 'object',
			'post_status' => 'any',
			'meta_key' => 'cos_init',
			'order' => 'ASC',
			'orderby' => 'meta_value',
			'posts_per_page' => -1
		);
		$posts = get_posts($args);
		foreach($posts as $post) {
			
			$init = get_post_meta($post->ID,'cos_init',true);
			$uri = get_post_meta($post->ID,'uri',true);
			$url = $host.$uri;
			$object = $this->perform_request($url, $this->generate_stream_context($user,$pass), true);
			
			$this->update_collectionspace_object($post,$object);
			update_post_meta($post->ID,'cos_init',1);
			if (!$init) {
				$update_post = array(
					'ID'					=> $post->ID,
					'post_status'	=> 'publish'
				);
				wp_update_post($update_post);
				$import_status[] = "Completed initial detail import for object ".$post->ID;
			} else {
				$import_status[] = "Updated details for object ".$post->ID;
			}
			
		}
		
		
		$end = microtime(true);
		
		$import_duration = $end-$start;
		
		echo implode("\r\n<br />", $import_status);
		
		set_transient('cos_message', "CollectionSpace import completed with ".$created." objects created, ".$updated." updated and ".$deleted." deleted in ".round($import_duration, 2)." seconds.", 0);
		
		set_transient('cos_collectionspace_show_message', true, 0);
		set_transient('cos_collectionspace_status', $import_status, 0);
	}
	
	function update_collectionspace_object($post,$object) {
		update_post_meta($post->ID,'objectNumber', $this->unarray($object['collectionobjects_common']['objectNumber']));
		update_post_meta($post->ID,'briefDescription', $this->unarray($object['collectionobjects_common']['briefDescriptions']));
		if (isset($object['collectionobjects_common']['objectNameList']['objectNameGroup']['objectName']))
			update_post_meta($post->ID,'objectName', $this->unarray($object['collectionobjects_common']['objectNameList']['objectNameGroup']['objectName']));
		if (isset($object['collectionobjects_common']['responsibleDepartments']))
			update_post_meta($post->ID,'responsibleDepartment', $this->unarray($object['collectionobjects_common']['responsibleDepartments']));
		if (isset($object['collectionobjects_common']['contentConcepts']))
			update_post_meta($post->ID,'contentConcept', $this->unarray($object['collectionobjects_common']['contentConcepts']));
		if (isset($object['collectionobjects_common']['contentLanguages']['contentLanguage']))
			update_post_meta($post->ID,'contentLanguage', $this->quick_parse_human_value_from_urn($object['collectionobjects_common']['contentLanguages']['contentLanguage']));
		if (isset($object['collectionobjects_common']['measuredPartGroupList']['measuredPartGroup']['dimensionSummary']))
			update_post_meta($post->ID,'dimension', $this->unarray($object['collectionobjects_common']['measuredPartGroupList']['measuredPartGroup']['dimensionSummary']));
		if (isset($object['collectionobjects_common']['materialGroupList']['materialGroup']['materialName']))
			update_post_meta($post->ID,'material', $this->unarray($object['collectionobjects_common']['materialGroupList']['materialGroup']['materialName']));
		if (isset($object['collectionobjects_common']['objectProductionDateGroupList']['objectProductionDateGroup']['dateDisplayDate']))
			update_post_meta($post->ID,'objectProductionDate', $this->unarray($object['collectionobjects_common']['objectProductionDateGroupList']['objectProductionDateGroup']['dateDisplayDate']));
		if (isset($object['collectionobjects_common']['objectProductionDateGroupList']['objectProductionDateGroup']['dateEarliestSingleYear']))
			update_post_meta($post->ID,'dateEarliestSingleYear', $this->unarray($object['collectionobjects_common']['objectProductionDateGroupList']['objectProductionDateGroup']['dateEarliestSingleYear']));
		if (isset($object['collectionobjects_common']['objectProductionDateGroupList']['objectProductionDateGroup']['dateLatestYear']))
			update_post_meta($post->ID,'dateLatestSingleYear', $this->unarray($object['collectionobjects_common']['objectProductionDateGroupList']['objectProductionDateGroup']['dateLatestYear']));
		if (isset($object['collectionobjects_common']['objectProductionOrganizationGroupList']['objectProductionOrganizationGroup']['objectProductionOrganization']))
			update_post_meta($post->ID,'objectProductionOrganization', $this->quick_parse_human_value_from_urn($object['collectionobjects_common']['objectProductionOrganizationGroupList']['objectProductionOrganizationGroup']['objectProductionOrganization']));
		if (isset($object['collectionobjects_common']['objectProductionOrganizationGroupList']['objectProductionOrganizationGroup']['objectProductionOrganizationRole']))
			update_post_meta($post->ID,'objectProductionOrganizationRole', $this->unarray($object['collectionobjects_common']['objectProductionOrganizationGroupList']['objectProductionOrganizationGroup']['objectProductionOrganizationRole']));
		if (isset($object['collectionobjects_common']['objectProductionPeopleGroupList']['objectProductionPeopleGroup']['objectProductionPeople']))
			update_post_meta($post->ID,'objectProductionPeople', $this->quick_parse_human_value_from_urn($object['collectionobjects_common']['objectProductionPeopleGroupList']['objectProductionPeopleGroup']['objectProductionPeople']));
		if (isset($object['collectionobjects_common']['objectProductionPeopleGroupList']['objectProductionPeopleGroup']['objectProductionPeopleRole']))
			update_post_meta($post->ID,'objectProductionPeopleRole', $this->unarray($object['collectionobjects_common']['objectProductionPeopleGroupList']['objectProductionPeopleGroup']['objectProductionPeopleRole']));
		if (isset($object['collectionobjects_common']['objectProductionPersonGroupList']['objectProductionPersonGroup']['objectProductionPerson']))
			update_post_meta($post->ID,'objectProductionPerson', $this->quick_parse_human_value_from_urn($object['collectionobjects_common']['objectProductionPersonGroupList']['objectProductionPersonGroup']['objectProductionPerson']));
		if (isset($object['collectionobjects_common']['objectProductionPersonGroupList']['objectProductionPersonGroup']['objectProductionPerson']))
			update_post_meta($post->ID,'objectProductionPersonRole', $this->unarray($object['collectionobjects_common']['objectProductionPersonGroupList']['objectProductionPersonGroup']['objectProductionPerson']));
		if (isset($object['collectionobjects_common']['techniqueGroupList']['techniqueGroup']['technique']))
			update_post_meta($post->ID,'technique', $this->unarray($object['collectionobjects_common']['techniqueGroupList']['techniqueGroup']['technique']));
		if (isset($object['collectionobjects_common']['fieldCollectionDateGroup']['dateDisplayDate']))
			update_post_meta($post->ID,'fieldCollectionDate', $this->unarray($object['collectionobjects_common']['fieldCollectionDateGroup']['dateDisplayDate']));
		if (isset($object['collectionobjects_common']['fieldCollectionMethods']))
			update_post_meta($post->ID,'fieldCollectionMethod', $this->unarray($object['collectionobjects_common']['fieldCollectionMethods']));
		if (isset($object['collectionobjects_common']['fieldCollectionNote']))
			update_post_meta($post->ID,'fieldCollectionNote', $this->unarray($object['collectionobjects_common']['fieldCollectionNote']));
		if (isset($object['collectionobjects_common']['fieldCollectionNumber']))
			update_post_meta($post->ID,'fieldCollectionNumber', $this->unarray($object['collectionobjects_common']['fieldCollectionNumber']));
		if (isset($object['collectionobjects_common']['fieldCollectionPlace']))
			update_post_meta($post->ID,'fieldCollectionPlace', $this->quick_parse_human_value_from_urn($object['collectionobjects_common']['fieldCollectionPlace']));
		if (isset($object['collectionobjects_common']['fieldCollectors']['fieldCollector']))
			update_post_meta($post->ID,'fieldCollector', $this->quick_parse_human_value_from_urn($object['collectionobjects_common']['fieldCollectors']['fieldCollector']));
		if (isset($object['collectionobjects_common']['fieldColEventNames']['fieldColEventName']))
			update_post_meta($post->ID,'fieldColEventName', $this->unarray($object['collectionobjects_common']['fieldColEventNames']['fieldColEventName']));
	}
	
	function quick_parse_human_value_from_urn($urn) {
		if (is_array($urn)) {
			$urn = $this->unarray($urn);
		}
		$matches = array();
		preg_match_all('/\'(.+)\'/', $urn, $matches);
		if (isset($matches[1]) && isset($matches[1][0]) && !empty($matches[1][0])) return $matches[1][0]; else return null;
	}
	
	function unarray($value) {
		while (is_array($value)) {
			$value = array_shift($value);
		}
		return $value;
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
		$title = (isset($doc['title']) && !empty($doc['title'])) ? $doc['title'] : $doc['csid'];
		$post = array(
			'post_title'				=> $title,
			'post_type'				 => 'object',
			'post_status'			 => 'draft',
		);
		$post_id = wp_insert_post($post);
		update_post_meta($post_id,'cos_last_import_update',time());
		update_post_meta($post_id,'cos_init',0);
		$this->update_object_meta($post_id,$doc);
		return $post_id;
	}
	
	
	function update_object($doc) {
		$existing_id = $this->existing_object_id($doc['csid']);
		$title = (isset($doc['title']) && !empty($doc['title'])) ? $doc['title'] : $doc['csid'];
		$post = array(
			'ID'								=> $existing_id,
			'post_title'				=> $title,
			'post_type'				 => 'object',
		);
		$post_id = wp_update_post($post);
		update_post_meta($post_id,'cos_last_import_update',time());
		$this->update_object_meta($post_id,$doc);
		return $post_id;
	}
	
	function update_object_meta($post_id,$doc) {
		foreach($doc as $key => $value) {
			update_post_meta($post_id,$key,$value);
		}
	}
	
	function object_exists($id) {
		$args = array(
			'post_type' => 'object',
			'post_status' => 'any',
			'meta_key' => 'csid',
			'meta_value' => $id,
		);
		return (count(get_posts($args)) > 0) ? true : false;
	}
	
	function existing_object_id($id) {
		$args = array(
			'post_type' => 'object',
			'post_status' => 'any',
			'meta_key' => 'csid',
			'meta_value' => $id
		);
		$posts = get_posts($args);
		if (count($posts) == 0) throw new Exception("BUG: called existing_object_id for an object that doesn't exist.");
		return $posts[0]->ID;
	}
	
	function xml2array($string, $trim_namespace) {
		if ($trim_namespace) {
			$string = str_replace('ns2:','',$string); //This is probably evil, but simplexml doesn't seem to make it easy to get data inside namespaced elements, epsecially when you want it as an array.
		}
		$xml = simplexml_load_string($string);
		$json = json_encode($xml);
		$array = json_decode($json, true);
		return $array;
	}
	
	
}

?>