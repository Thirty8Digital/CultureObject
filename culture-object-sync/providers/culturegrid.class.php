<?php

class Culture_Object_Sync_Provider_CultureGrid_Exception extends Culture_Object_Sync_Provider_Exception { }

class Culture_Object_Sync_Provider_CultureGrid extends Culture_Object_Sync_Provider {
  //http://www.culturegrid.org.uk/index/select?fl=*&wt=json&rows=1000000&indent=on&q=(authority:ManchesterCityGalleries)%20AND%20(dcmi.type:PhysicalObject)&start=0
  
  //http://www.culturegrid.org.uk/index/select?wt=json&rows=5000&indent=on&q=dcmi.type:PhysicalObject&start=0
  
  //http://www.culturegrid.org.uk/wp-content/uploads/2011/11/Culture-Grid-search-service-v3.pdf
  
  private $provider = array(
    'name' => 'CultureGrid',
    'version' => '1.0',
    'developer' => 'Thirty8 Digital',
  );
  
  function get_provider_information() {
    return $this->provider;
  }
  
  function register_settings() {
    add_settings_section('cos_provider_settings','Provider Settings',array($this,'generate_settings_group_content'),'cos_settings');
  
  	register_setting('cos_settings', 'cos_provider_search_authority');
  	
  	add_settings_field('cos_provider_search_authority', 'CultureGrid Search Authority', array($this,'generate_settings_field_input_text'), 'cos_settings', 'cos_provider_settings', array('field'=>'cos_provider_search_authority'));
  }
  
  function generate_settings_group_content() {
    echo "<p>You're currently using version ".$this->provider['version']." of the ".$this->provider['name']." sync provider by ".$this->provider['developer'].".</p>";
  }
  
  function generate_settings_field_input_text($args) {
    $field = $args['field'];
    $value = get_option($field);
    echo sprintf('<input type="text" name="%s" id="%s" value="%s" />', $field, $field, $value);
  }
}

?>