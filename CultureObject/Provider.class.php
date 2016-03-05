<?php
    
namespace CultureObject;

abstract class Provider {

    //This must return an array of provider information, see the example.
    abstract function get_provider_information();
    
    //You must provide this, even if you don't want to register any settings. You can then use standard wordpress functions to add settings to the 'cos_settings' page which will be handled by WordPress's Settings API.
    abstract function register_settings();
    
    //This method must perform the sync for your provider. All errors must throw a \CultureObject\Exception\ProviderException exception, or one extended from it.
    abstract function perform_sync();
    
}

?>