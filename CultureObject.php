<?php
/**
 * Plugin Name: Culture Object
 * Plugin URI: http://cultureobject.co.uk
 * Description: A framework as a plugin to enable sync of culture objects into WordPress.
 * Version: 2.1.0
 * Author: Liam Gladdy / Thirty8 Digital
 * Author URI: https://github.com/lgladdy
 * GitHub Plugin URI: Thirty8Digital/CultureObject
 * GitHub Branch: master
 * License: Apache 2 License
 */
	
register_activation_hook(__FILE__, 'activate_cultureobject');

function activate_cultureobject() {
	global $wp_version;
	$wp = '4.1';
	$php = '5.3';
	if (version_compare(PHP_VERSION, $php, '<')) {
		$flag = 'PHP';
	} elseif (version_compare($wp_version, $wp, '<')) {
		$flag = 'WordPress';
	} else return;
	$version = 'PHP' == $flag ? $php : $wp;
	deactivate_plugins(basename( __FILE__ ));
	wp_die('<p>Culture Object</strong> requires '.$flag.'  version '.$version.' or greater.</p>', 'Plugin Activation Error',  array('response'=>200, 'back_link'=>TRUE));
}

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

function cos_get_field($field_key) {
	$id = get_the_ID();
	if (!$id) return false;
	return get_post_meta($id, $field_key, true);
}

function cos_the_field($field_key) {
	echo cos_get_field($field_key);
}