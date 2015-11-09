<?php
	
/* This file exists so we can handle versions of PHP which do not support namespaced classes without throwing a generic unexpected string fatal. */
	
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
	echo get_post_meta($id, $field_key, true);
}

function cos_the_field($field_key) {
	echo cos_get_field($field_key);
}