<?php

namespace CultureObject;

require_once('Core.class.php');
require_once('Exception/Exception.class.php');
require_once('Helper.class.php');
require_once('Provider.class.php');
require_once('Settings.class.php');

class CultureObject extends Core {
    
    public $helper = false;
    
    function __construct() {
        $settings = new Settings();
        $this->helper = new Helper();
        add_action('init', array($this, 'wordpress_init'));
        add_action('parse_request', array($this, 'should_sync'));
        add_action('wp_ajax_cos_sync', array($this, 'should_ajax_sync'));
        add_action('init', array($this, 'purge_objects'));
        add_action('plugins_loaded', array($this, 'load_co_languages'));
    }
    
    function load_co_languages() {
        load_plugin_textdomain('culture-object', FALSE, basename(dirname( __FILE__ )).'/languages/');
    }
    
    static function check_versions() {
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

    
    static function regenerate_permalinks() {
        flush_rewrite_rules();
    }
    
    function should_sync() {
	    $cli_cron = false;
	    if (defined('CO_CLI_CRON') && CO_CLI_CRON) $cli_cron = true;
        if ($cli_cron || (isset($_GET['perform_culture_object_sync']) && isset($_GET['key']))) {
            if ($cli_cron || (get_option('cos_core_sync_key') == $_GET['key'])) {
                $provider = $this->get_sync_provider();
                if ($provider) {
                    if (!class_exists($provider['class'])) include_once($provider['file']);
                    $provider_class = new $provider['class'];
                    $info = $provider_class->get_provider_information();
                    
                    if (!$info['cron']) die(sprintf(
                        /* Translators: %s: is the name of the provider. */
                        __("Culture Object provider (%s) does not support automated sync.", 'culture-object'),
                        $info['name']
                    ));
                    
                    try {
                        $provider_class->perform_sync();
                    } catch (ProviderException $e) {
                        echo __("A sync exception occurred during sync", 'culture-object').":<br />";
                        echo $e->getMessage();
                    } catch (Exception $e) {
                        echo __("An unknown exception occurred during sync", 'culture-object').":<br />";
                        echo $e->getMessage();
                    }
                    die('Sync Complete');
                }
            }
        }
    }
    
    function should_ajax_sync() {
        
        if (isset($_POST['key'])) {
            if (get_option('cos_core_sync_key') == $_POST['key']) {
                if (wp_verify_nonce($_POST['nonce'], 'cos_ajax_import_request')) {
                    $provider = $this->get_sync_provider();
                    if ($provider) {
                        if (!class_exists($provider['class'])) include_once($provider['file']);
                        $provider_class = new $provider['class'];
                        $info = $provider_class->get_provider_information();
                        
                        if (!$info['ajax']) die(sprintf(
                            /* Translators: %s: is the name of the provider. */
                            __("Culture Object provider (%s) does not support AJAX sync.", 'culture-object'),
                            $info['name']
                        ));
                        
                        try {
                            $result = $provider_class->perform_ajax_sync();
                            echo json_encode($result);
                            wp_die();
                        } catch (ProviderException $e) {
                            $result = array();
                            $result['state'] = 'error';
                            $result['message'] = urlencode(__("A sync exception occurred during sync", 'culture-object'));
                            $result['detail'] = urlencode($e->getMessage());
                            echo json_encode($result);
                            wp_die();
                        } catch (Exception $e) {
                            $result = array();
                            $result['state'] = 'error';
                            $result['message'] = urlencode(__("An unknown exception occurred during sync", 'culture-object'));
                            $result['detail'] = urlencode($e->getMessage());
                            echo json_encode($result);
                            wp_die();
                        }
                    }
                } else {
                    var_dump($_POST);
                    $result = array();
                    $result['state'] = 'error';
                    $result['message'] = __("Security Violation", 'culture-object');
                    $result['detail'] = __('Nonce verification failed: '.$_POST['nonce'], 'culture-object');
                    echo json_encode($result);
                    wp_die();
                }
            } else {
                $result = array();
                $result['state'] = 'error';
                $result['message'] = __("Security Violation", 'culture-object');
                $result['detail'] = __('Invalid Sync Key', 'culture-object');
                echo json_encode($result);
                wp_die();
            }
        
            
        }
        
        $result = array();
        $result['state'] = 'error';
        $result['message'] = urlencode(__("An unknown error occurred during AJAX sync", 'culture-object'));
        $result['detail'] = __('An unknown error occurred during AJAX sync', 'culture-object');
        echo json_encode($result);
        wp_die();
    }
    
    function purge_objects() {
        if (is_admin() && isset($_GET['perform_cos_debug_purge'])) {
            $all_objects = get_posts(array('post_status'=>'any','post_type'=>'object','posts_per_page'=>-1));
            foreach($all_objects as $obj) {
                wp_delete_post($obj->ID,true);
            }
            wp_die(__("Deleted all COS objects.", 'culture-object'));
        }
    }
    
    function wordpress_init() {
            
        register_post_type('object', array(
            'labels' => array(
                    "name" => __("Objects", 'culture-object'),
                    "singular_name" => __("Object", 'culture-object'),
                    "add_new_item" => __("Add new object", 'culture-object'),
                    "edit_item" => __("Edit object", 'culture-object'),
                    "new_item" => __("New object", 'culture-object'),
                    "view_item" => __("View object", 'culture-object'),
                    "search_items" => __("Search objects", 'culture-object'),
                    "not_found" => __("No objects found", 'culture-object'),
                    "not_found_in_trash" => __("No objects found in the trash", 'culture-object')
                ),
            'public' => true,
            'has_archive' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-list-view',
            'supports' => array('title','custom-fields','thumbnail'),
            'rewrite' => array('slug' => 'object', 'with_front' => false)
            )
        );
        
        add_filter('post_updated_messages', array($this,'object_updated_messages'));
    }
    
    
    function object_updated_messages($messages) {
        global $post, $post_ID;
    
        $messages['object'] = array(
            0 => '',
            1 => sprintf( __('Object updated.', 'culture-object').' <a href="%s">'.__('View object', 'culture-object').'</a>', esc_url( get_permalink($post_ID) ) ),
            2 => __('Custom field updated.', 'culture-object'),
            3 => __('Custom field deleted.', 'culture-object'),
            4 => __('Object updated.', 'culture-object'),
            5 => isset($_GET['revision']) ? sprintf( __('Object restored to revision from %s', 'culture-object'), wp_post_revision_title( (int) $_GET['revision'], false)) : false,
            6 => sprintf(__('Object published.', 'culture-object').' <a href="%s">'.__('View object', 'culture-object').'</a>', esc_url( get_permalink($post_ID) ) ),
            7 => __('Object saved.', 'culture-object'),
            8 => sprintf(__('Object submitted.', 'culture-object').' <a target="_blank" href="%s">'.__('Preview object', 'culture-object').'</a>', esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID)))),
            9 => sprintf(__('Object scheduled for', 'culture-object').': <strong>%1$s</strong>. <a target="_blank" href="%2$s">'.__('Preview object', 'culture-object').'</a>', date_i18n(__('M j, Y @ G:i', 'culture-object'), strtotime($post->post_date)), esc_url(get_permalink($post_ID))),
            10 => sprintf(__('Object draft updated.', 'culture-object').' <a target="_blank" href="%s">'.__('Preview object', 'culture-object').'</a>', esc_url(add_query_arg('preview', 'true', get_permalink($post_ID)))),
        );
    
        return $messages;
    }
}