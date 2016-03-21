<?php
/**
 * Plugin Name: Culture Object
 * Plugin URI: http://cultureobject.co.uk
 * Description: A framework as a plugin to enable sync of culture objects into WordPress.
 * Version: 3.0.0-alpha.2
 * Author: Liam Gladdy / Thirty8 Digital
 * Text Domain: culture-object
 * Author URI: https://github.com/lgladdy
 * GitHub Plugin URI: Thirty8Digital/CultureObject
 * GitHub Branch: schemas
 * License: Apache 2 License
 */

require_once('CultureObject/CultureObject.class.php');
$cos = new \CultureObject\CultureObject();

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
