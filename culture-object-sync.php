<?php
/**
 * Plugin Name: Culture Object Sync
 * Plugin URI: http://www.gladdy.co.uk/projects/culture-object-sync
 * Description: A framework as a plugin to enable sync of culture objects into WordPress.
 * Version: 2.0
 * Author: Liam Gladdy / Thirty8 Digital
 * Author URI: https://www.gladdy.uk / http://www.thirty8digital.co.uk
 * License: Apache 2 License
 */
 
require_once('CultureObject/CultureObject.class.php');
$cos = new \CultureObject\CultureObject();

function cos_get_remapped_field_name($field_key) {
	global $cos;
	return $cos->helper->cos_get_remapped_field_name($field_key);
}

function cos_remapped_field_name($field_key) {
	global $cos;
	return $cos->helper->cos_remapped_field_name($field_key);
}