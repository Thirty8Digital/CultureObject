<?php
/**
 * Plugin Name: Culture Object
 * Plugin URI: http://cultureobject.co.uk
 * Description: A framework as a plugin to enable sync of culture objects into WordPress.
 * Version: 3.3.0
 * Author: Liam Gladdy / Thirty8 Digital
 * Text Domain: culture-object
 * Author URI: https://github.com/lgladdy
 * GitHub Plugin URI: Thirty8Digital/CultureObject
 * GitHub Branch: master
 * License: Apache 2 License
 */

require_once('CultureObject/CultureObject.class.php');
register_activation_hook(__FILE__, array('CultureObject\CultureObject', 'check_versions'));
register_activation_hook(__FILE__, array('CultureObject\CultureObject', 'regenerate_permalinks'));
register_deactivation_hook(__FILE__, array('CultureObject\CultureObject', 'regenerate_permalinks'));
$cos = new \CultureObject\CultureObject();

function cos_get_instance() {
    global $cos;
    return $cos;
}

/* General Functions. These need to go into their own file one day. */
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
