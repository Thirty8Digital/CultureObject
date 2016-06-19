<?php
    
namespace CultureObject;

abstract class Core {

    function get_sync_provider() {
        $value = get_option('cos_core_sync_provider');
        if (empty($value)) return false;
        $providers = $this->find_providers();
        foreach($providers as $provider) {
            if ($provider['class'] == $value) return $provider;
        }
        return false;
    }

    function find_providers() {
        $valid_providers = array();
        foreach (glob(realpath(__DIR__.'/..').'/providers/*/*.class.php') as $filename) {
            $classes = $this->file_get_php_classes($filename);
            foreach($classes as $class) {
                $is_exception = substr(strtolower($class),-9);
                if ($is_exception != "exception") {
                    $provider['file'] = $filename;
                    $provider['class'] = $class;
                    
                    if (!class_exists($class)) include_once($filename);
                    $provider_class = new $class;
                    if (method_exists($provider_class, 'get_provider_information')) {
                        $provider['info'] = $provider_class->get_provider_information();
                        $valid_providers[] = $provider;
                    }
                }
            }
        }
        return $valid_providers;
    }
    
    function get_core_setting($setting) {
        return get_option('cos_core_'.$setting, false);
    }
    
    function file_get_php_classes($filepath) {
        $php_code = file_get_contents($filepath);
        $classes = $this->get_php_classes($php_code);
        return $classes;
    }

    function get_php_classes($php_code) {
        $classes = array();
        $tokens = token_get_all($php_code);
        $count = count($tokens);
        for ($i = 2; $i < $count; $i++) {
            if ($tokens[$i-2][0] == T_CLASS && $tokens[$i - 1][0] == T_WHITESPACE && $tokens[$i][0] == T_STRING) {
                $class_name = $tokens[$i][1];
                $classes[] = $class_name;
            }
        }
        return $classes;
    }
    
}

?>