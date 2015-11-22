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
		add_action('init', array($this, 'purge_objects'));
		add_action('plugins_loaded', array($this, 'load_co_languages'));
		register_activation_hook(__FILE__, array($this, 'regenerate_permalinks'));
		register_activation_hook(__FILE__, array($this, 'check_versions'));
		register_deactivation_hook(__FILE__, array($this, 'regenerate_permalinks'));
	}
	
	function load_co_languages() {
		load_plugin_textdomain('culture-object', FALSE, basename(dirname( __FILE__ )).'/languages/');
	}
	
	function check_versions() {
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
		
		$error_type = __('Plugin Activation Error', 'culture-object');
		$error_string = sprintf(
			/* translators: 1: Either WordPress or PHP, depending on the version mismatch 2: Required version number */
			__('Culture Object requires %1$s version %2$s or greater.', 'culture-object'),
			$flag,
			$version
		);
		
		wp_die('<p>'.$error_string.'</p>', $error_type,  array('response'=>200, 'back_link'=>TRUE));
	}

	
	function regenerate_permalinks() {
		flush_rewrite_rules();
	}
	
	function should_sync() {
		if (isset($_GET['perform_culture_object_sync']) && isset($_GET['key'])) {
			if (get_option('cos_core_sync_key') == $_GET['key']) {
				$provider = $this->get_sync_provider();
				if ($provider) {
					if (!class_exists($provider['class'])) include_once($provider['file']);
					$provider_class = new $provider['class'];
					$info = $provider_class->get_provider_information();
					
					if (!$info['cron']) die("Culture Object Sync Provider (".$info['name'].") does not support automated sync.");
					
					try {
						$provider_class->perform_sync();
					} catch (ProviderException $e) {
						echo "A sync exception occurred during sync:<br />";
						echo $e->getMessage();
					} catch (Exception $e) {
						echo "An unknown exception occurred during sync:<br />";
						echo $e->getMessage();
					}
					exit();
				}
			}
		}
	}
	
	function purge_objects() {
		if (is_admin() && isset($_GET['perform_cos_debug_purge'])) {
			$all_objects = get_posts(array('post_status'=>'any','post_type'=>'object','posts_per_page'=>-1));
			foreach($all_objects as $obj) {
				wp_delete_post($obj->ID,true);
			}
			die("Deleted all COS objects.");
		}
	}
	
	function wordpress_init() {
			
		register_post_type('object', array(
			'labels' => array(
					"name" => "Objects",
					"singular_name" => "Object",
					"add_new_item" => "Add new object",
					"edit_item" => "Edit object",
					"new_item" => "New object",
					"view_item" => "View object",
					"search_items" => "Search objects",
					"not_found" => "No objects found",
					"not_found_in_trash" => "No objects found in the trash"
				),
			'public' => true,
			'has_archive' => true,
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
			1 => sprintf( __('Object updated. <a href="%s">View object</a>', 'culture_object_sync'), esc_url( get_permalink($post_ID) ) ),
			2 => __('Custom field updated.', 'culture_object_sync'),
			3 => __('Custom field deleted.', 'culture_object_sync'),
			4 => __('Object updated.', 'culture_object_sync'),
			5 => isset($_GET['revision']) ? sprintf( __('Object restored to revision from %s', 'culture_object_sync'), wp_post_revision_title( (int) $_GET['revision'], false)) : false,
			6 => sprintf(__('Object published. <a href="%s">View object</a>', 'culture_object_sync'), esc_url( get_permalink($post_ID) ) ),
			7 => __('Object saved.', 'culture_object_sync'),
			8 => sprintf(__('Object submitted. <a target="_blank" href="%s">Preview object</a>', 'culture_object_sync'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID)))),
			9 => sprintf(__('Object scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview object</a>', 'culture_object_sync'), date_i18n(__('M j, Y @ G:i'), strtotime($post->post_date)), esc_url(get_permalink($post_ID))),
			10 => sprintf(__('Object draft updated. <a target="_blank" href="%s">Preview object</a>', 'culture_object_sync'), esc_url(add_query_arg('preview', 'true', get_permalink($post_ID)))),
		);
	
		return $messages;
	}
}