<?php
	
namespace CultureObject;

class Helper extends Core {
	
	function add_image_to_gallery_from_url($url) {
		$upload_dir = wp_upload_dir();
		$save_path = $upload_dir['path'];
		$img = @file_get_contents($url);
		if ($img) {
			//TODO: Handle save
		} else return false;
	}
	
}