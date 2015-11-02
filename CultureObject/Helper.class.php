<?php
	
namespace CultureObject;

class Helper extends Core {
	
	function cos_get_remapped_field_name($key) {
		$field_remap = get_option('cos_remap_'.strtolower($key));
		if (isset($field_remap) && !empty($field_remap)) return $field_remap;
		$provider = $this->get_sync_provider();
		if ($provider) {
			if (!class_exists($provider['class'])) include_once($provider['file']);
			$provider_class = new $provider['class'];
			$info = $provider_class->get_provider_information();
			if (isset($info['supports_remap']) && $info['supports_remap']) {
				if (method_exists($provider_class, 'register_remappable_fields')) {
					$fields = $provider_class->register_remappable_fields();
					if (isset($fields[$key])) return $fields[$key];
				}
			}
		}
		return $key;
	}
	
	function cos_remapped_field_name($key) {
		echo cos_get_remapped_field_name($key);
	}
	
	function add_image_to_gallery_from_url($url) {
		$upload_dir = wp_upload_dir();
		$save_path = $upload_dir['path'];
		$img = @file_get_contents($url);
		if ($img) {
			//TODO: Handle save
		} else return false;
	}
	
}