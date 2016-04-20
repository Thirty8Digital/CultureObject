<?php
	
namespace CultureObject;

class Settings extends Core {

	private $plugin_directory;
	private $plugin_url;

	function __construct() {
		$this->plugin_directory = dirname(__DIR__.'../');
		$this->plugin_url = plugins_url('/',__DIR__);
		
		add_action('admin_menu', array($this,'add_menu_item'));
		add_action('admin_enqueue_scripts', array($this,'add_admin_assets'));
		add_action('admin_init', array($this,'register_settings'));
		add_action('init', array($this,'provide_init_action'));
	}
	
	function register_settings() {
		
		add_settings_section('cos_core_settings','Main Settings',array($this,'generate_settings_group_content'),'cos_settings');
	
		register_setting('cos_settings', 'cos_core_sync_provider');
		register_setting('cos_settings', 'cos_core_sync_key');
		register_setting('cos_settings', 'cos_core_import_images');
		
		add_settings_field('cos_core_sync_provider', 'Sync Provider', array($this,'generate_settings_sync_providers_input'), 'cos_settings', 'cos_core_settings', array('field'=>'cos_core_sync_provider'));
		
		add_settings_field('cos_core_sync_key', 'Sync Key', array($this,'generate_settings_field_input_text'), 'cos_settings', 'cos_core_settings', array('field'=>'cos_core_sync_key'));
		
		add_settings_field('cos_core_import_images', 'Import Images', array($this,'generate_settings_field_input_checkbox'), 'cos_settings', 'cos_core_settings', array('field'=>'cos_core_import_images'));
	
		$provider = $this->get_sync_provider();
		if ($provider) {
			if (!class_exists($provider['class'])) include_once($provider['file']);
			$provider_class = new $provider['class'];
			$info = $provider_class->get_provider_information();
			$provider_class->register_settings();
			
			//If the provider supports remapping, it must implement register_remappable_fields. Fatal if not.
			if (isset($info['supports_remap']) && $info['supports_remap']) {
				if (!method_exists($provider_class, 'register_remappable_fields')) {
					update_option('cos_core_sync_provider', false);
					throw new Exception\ProviderException('The activated provider plugin claims to support remappable fields, but doesn\'t provide the list of remappable fields. This should never happen in a production environment. Please contact the plugin developer, '.$info['developer'].'. To stop this breaking your site, the provider has been disabled.');
				} else {
					$fields = $provider_class->register_remappable_fields();
					add_settings_section('cos_remaps', 'Field Mappings', array($this,'generate_settings_group_content'), 'cos_provider_settings');
					if (current_theme_supports('cos-remaps')) {
						foreach($fields as $field_key => $field_default) {
							register_setting('cos_provider_settings', 'cos_remap_'.strtolower($field_key));
							add_settings_field('cos_remap_'.strtolower($field_key), $field_key, array($this,'generate_settings_field_input_text'), 'cos_provider_settings', 'cos_remaps', array('field'=>'cos_remap_'.strtolower($field_key),'default'=>$field_default));
						}
					}
				}
			}
		}
		
		
	}
	
	function add_admin_assets($page) {
		if ($page == 'toplevel_page_cos_settings' || $page == 'culture-object_page_cos_provider_settings') {
			wp_register_style('cos_admin_css', $this->plugin_url . '/css/culture-object-sync.css', false, '1.0.0');
			wp_enqueue_style('cos_admin_css');
			wp_register_script('cos_admin_js', $this->plugin_url . '/js/culture-object-sync.js', array('jquery','jquery.qtip.js'), '1.0.0', true);
			wp_enqueue_script('cos_admin_js');
		}
	}
	
	function add_menu_item() {
		$options_page = add_menu_page('Culture Object Settings', 'Culture Object', 'administrator', 'cos_settings', array($this,'generate_settings_page'), '
dashicons-update');
		$provider_page = add_submenu_page('cos_settings', 'Provider Settings', 'Provider Settings', 'administrator', 'cos_provider_settings', array($this,'generate_provider_page'));
		add_action('load-'.$provider_page, array($this,'provide_load_action'));
	}
	
	function provide_load_action() {
		$provider = $this->get_sync_provider();
		if ($provider) {
			if (!class_exists($provider['class'])) include_once($provider['file']);
			$provider_class = new $provider['class'];
			if (method_exists($provider_class, 'execute_load_action')) $provider_class->execute_load_action();
		}
		
	}
	
	function provide_init_action() {
		
		$provider = $this->get_sync_provider();
		if ($provider) {
			if (!class_exists($provider['class'])) include_once($provider['file']);
			$provider_class = new $provider['class'];
			if (method_exists($provider_class, 'execute_init_action')) $provider_class->execute_init_action();
		}
		
	}
	
	function generate_settings_page() {		
		
		$provider = $this->get_sync_provider();
		if ($provider) {
			if (!class_exists($provider['class'])) include_once($provider['file']);
			$provider_class = new $provider['class'];
			$provider_info = $provider_class->get_provider_information();
		}
		
		include($this->plugin_directory.'/views/settings.php');
	}
	
	function generate_provider_page() {		
		
		$provider = $this->get_sync_provider();
		if ($provider) {
			if (!class_exists($provider['class'])) include_once($provider['file']);
			$provider_class = new $provider['class'];
			$provider_info = $provider_class->get_provider_information();
		}
		
		include($this->plugin_directory.'/views/provider.php');
	}
	
	function generate_settings_group_content($group) {
		$group_id = $group['id'];
		switch ($group_id) {
			case 'cos_core_settings':
				$message = 'These settings relate to the overall plugin and how it works.';
				break;
			case 'cos_remaps':
				if (current_theme_supports('cos-remaps')) {
					$message = 'Your plugin provider supports remappable fields. You can override the default display name for each field imported.';
				} else {
					$message = 'Your plugin provider supports remappable fields, but your theme does not declare support. If you are a theme developer, see the CultureObject documentation for more details.';
				}
				break;
			default:
				$message = '';
		}
		echo $message;
	}
	
	function generate_settings_sync_providers_input($args) {
		$field = $args['field'];
		$value = get_option($field);
		$providers = $this->find_providers();
		echo '<select name="'.$field.'" id="'.$field.'">';
		foreach($providers as $provider) {
			$selected = ($value == $provider['class']) ? ' selected="selected"' : '';
			echo '<option value="'.$provider['class'].'"'.$selected.'>'.$provider['info']['name'].'</option>';
		}
		echo '</select>';
		
	}
	
	function generate_settings_field_input_text($args) {
		$field = $args['field'];
		$value = get_option($field);
		if (empty($value) && isset($args['default'])) $value = $args['default'];
		echo sprintf('<input type="text" name="%s" id="%s" value="%s" />', $field, $field, $value);
		if ($field == "cos_core_sync_key") echo '<br /><small>This key forms part of the sync URL for a little bit more security.</small>';
		
	}
	
	function generate_settings_field_input_checkbox($args) {
		$field = $args['field'];
		$value = get_option($field);
		if (empty($value) && isset($args['default'])) $value = $args['default'];
		if ($value) {
			echo sprintf('<input type="checkbox" name="%s" value="1" id="%s" checked="checked" />', $field, $field);
		} else {
			echo sprintf('<input type="checkbox" name="%s" value="1" id="%s" />', $field, $field);
		}
		if ($field == "cos_core_import_images") echo '<br /><small>Some providers support importing images into the WordPress Media Gallery.<br />Tick this box to request the provider does this.</small>';
		
	}
	
}