<?php

class CSV2Exception extends \CultureObject\Exception\ProviderException { }

class CSV2 extends \CultureObject\Provider {
    
    private $provider = array(
        'name' => 'CSV2',
        'version' => '2.0',
        'developer' => 'Thirty8 Digital',
        'cron' => false,
        'supports_remap' => false,
        'no_options' => true
    );
    
    function get_provider_information() {
        return $this->provider;
    }
    
    function execute_load_action() {
        if (isset($_FILES['cos_csv_import_file']) && isset($_POST['cos_csv_nonce'])) {
            if (wp_verify_nonce($_POST['cos_csv_nonce'], 'cos_csv_import')) {
                $this->perform_sync();
            } else {
                wp_die(__("Security Violation.", 'culture-object'));
            }
        }
    }
    
    function register_settings() {
        return;
    }
    
    function generate_settings_outside_form_html() {
    
        echo "<h3>".__('Provider Settings','culture-object')."</h3>";
        
        echo '<p>';
        printf(
            /* Translators: 1: Provider Plugin Version 2: Provider Name 3: Provider Developer */
            __('You\'re currently using version %1$s of the %2$s sync provider by %3$s.', 'culture-object'),
            $this->provider['version'],
            $this->provider['name'],
            $this->provider['developer']
        );
        echo '</p>';
        
    }
    
    function generate_settings_field_input_text($args) {
        $field = $args['field'];
        $value = get_option($field);
        echo sprintf('<input type="text" name="%s" id="%s" value="%s" />', $field, $field, $value);
    }
    
    function perform_sync() {
    }
}

?>
