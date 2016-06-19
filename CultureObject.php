<?php
/**
 * Plugin Name: Culture Object
 * Plugin URI: http://cultureobject.co.uk
 * Description: A framework as a plugin to enable sync of culture objects into WordPress.
 * Version: 3.0.0-beta.2
 * Author: Liam Gladdy / Thirty8 Digital
 * Text Domain: culture-object
 * Author URI: https://github.com/lgladdy
 * GitHub Plugin URI: Thirty8Digital/CultureObject
 * GitHub Branch: master
 * License: Apache 2 License
 */

require_once('CultureObject/CultureObject.class.php');

register_activation_hook(__FILE__, 'regenerate_permalinks');
register_activation_hook(__FILE__, 'check_versions');
register_deactivation_hook(__FILE__, 'regenerate_permalinks');

$cos = new \CultureObject\CultureObject();

/* General Functions. These need to go into their own file one day. */

function check_versions() {
    global $wp_version;
    $wp = '4.5';
    $php = '5.5';
    
    if (version_compare(PHP_VERSION, $php, '<')) {
        $flag = 'PHP';
    } elseif (version_compare($wp_version, $wp, '<')) {
        $flag = 'WordPress';
    } else return;
    $version = 'PHP' == $flag ? $php : $wp;
    deactivate_plugins(basename( __FILE__ ));
    
    $error_type = __('Plugin Activation Error', 'culture-object');
    $error_string = sprintf(
        /* Translators: 1: Either WordPress or PHP, depending on the version mismatch 2: Required version number */
        __('Culture Object requires %1$s version %2$s or greater.', 'culture-object'),
        $flag,
        $version
    );
    
    wp_die('<p>'.$error_string.'</p>', $error_type,  array('response'=>200, 'back_link'=>TRUE));
}

    
function regenerate_permalinks() {
    flush_rewrite_rules();
}

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
