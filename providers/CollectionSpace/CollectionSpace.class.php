<?php

class CollectionSpaceException extends \CultureObject\Exception\ProviderException { }

class CollectionSpace extends \CultureObject\Provider {
    
    private $provider = array(
        'name' => 'CollectionSpace',
        'version' => '1.0',
        'developer' => 'Thirty8 Digital',
        'cron' => true,
        'supports_remap' => true
    );
    
    function register_remappable_fields() {
        return array(
            'objectNumber' => 'Object Number',
            'briefDescription' => 'Brief Description',
            'objectName' => 'Object Name',
            'responsibleDepartment' => 'Responsible Department',
            'contentConcept' => 'Concept',
            'contentLanguage' => 'Language',
            'dimension' => 'Dimension',
            'material' => 'Material',
            'objectProductionDate' => 'Object Production Date',
            'dateEarliestSingleYear' => 'Earliest Year',
            'dateLatestSingleYear' => 'Latest Year',
            'objectProductionOrganization' => 'Production Organization',
            'objectProductionOrganizationRole' => 'Production Organization Role',
            'objectProductionPeople' => 'Production People',
            'objectProductionPeopleRole' => 'Production People Role',
            'objectProductionPerson' => 'Production Person',
            'objectProductionPersonRole' => 'Production Person Role',
            'technique' => 'Technique', 
            'fieldCollectionDate' => 'Field Collection Date', 
            'fieldCollectionMethod' => 'Field Collection Method', 
            'fieldCollectionNote' => 'Field Collection Note', 
            'fieldCollectionNumber' => 'Field Collection Number', 
            'fieldCollectionPlace' => 'Field Collection Place',
            'fieldCollector' => 'Field Collector',
            'fieldColEventName' => 'Field Collection Event Name'
        );
    }
    
    function register_prevent_safe_password_save() {
        add_filter( 'pre_update_option_cos_provider_collectionspace_password', array($this, 'prevent_safe_password_save'), 10, 2 );
    }
    
    function prevent_safe_password_save($new,$old) {
        if ($new == str_repeat('*', strlen($new))) return $old; else return $new;
    }
    
    function get_provider_information() {
        return $this->provider;
    }
    
    function execute_init_action() {
        $this->register_prevent_safe_password_save();
        $this->register_taxonomies();
    }
    
    function register_taxonomies() {
        $labels = array(
            'name'              => __('People'),
            'singular_name'     => __('Person'),
            'search_items'      => __('Search People'),
            'all_items'         => __('All People'),
            'parent_item'       => __('Parent Person'),
            'parent_item_colon' => __('Parent Person:'),
            'edit_item'         => __('Edit Person'),
            'update_item'       => __('Update Person'),
            'add_new_item'      => __('Add New Person'),
            'new_item_name'     => __('New Person Name'),
            'menu_name'         => __('People'),
        );
    
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'person', 'with_front' => false),
        );
        
        register_taxonomy('people', array('object'), $args);
        
        
        $labels = array(
            'name'              => __('Organizations'),
            'singular_name'     => __('Organization'),
            'search_items'      => __('Search Organizations'),
            'all_items'         => __('All Organizations'),
            'parent_item'       => __('Parent Organization'),
            'parent_item_colon' => __('Parent Organization:'),
            'edit_item'         => __('Edit Organization'),
            'update_item'       => __('Update Organization'),
            'add_new_item'      => __('Add New Organization'),
            'new_item_name'     => __('New Organization Name'),
            'menu_name'         => __('Organizations'),
        );
    
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'organization', 'with_front' => false),
        );
        
        register_taxonomy('organizations', array('object'), $args);
    }
    
    function register_settings() {
        add_settings_section('cos_provider_settings',__('Provider Settings','culture-object'),array($this,'generate_settings_group_content'),'cos_provider_settings');
    
        register_setting('cos_provider_settings', 'cos_provider_collectionspace_host_uri');
        add_settings_field('cos_provider_collectionspace_host_uri', __('CollectionSpace Host URI','culture-object'), array($this,'generate_settings_field_input_text'), 'cos_provider_settings', 'cos_provider_settings', array('field'=>'cos_provider_collectionspace_host_uri'));
    
        register_setting('cos_provider_settings', 'cos_provider_collectionspace_username');
        add_settings_field('cos_provider_collectionspace_username', __('CollectionSpace Username','culture-object'), array($this,'generate_settings_field_input_text'), 'cos_provider_settings', 'cos_provider_settings', array('field'=>'cos_provider_collectionspace_username'));
    
        register_setting('cos_provider_settings', 'cos_provider_collectionspace_password');
        add_settings_field('cos_provider_collectionspace_password', __('CollectionSpace Password','culture-object'), array($this,'generate_settings_field_input_password'), 'cos_provider_settings', 'cos_provider_settings', array('field'=>'cos_provider_collectionspace_password'));
        
    }
    
    function generate_settings_field_input_text($args) {
        $field = $args['field'];
        $value = get_option($field);
        echo sprintf('<input type="text" name="%s" id="%s" value="%s" />', $field, $field, $value);
    }
    
    function generate_settings_field_input_password($args) {
        $field = $args['field'];
        $value = get_option($field);
        $blurred = str_repeat('*', strlen($value));
        echo sprintf('<input type="password" name="%s" id="%s" value="%s" />', $field, $field, $blurred);
    }
    
    function generate_settings_group_content() {
        
        echo '<p>';
        printf(
            /* Translators: 1: Provider Plugin Version 2: Provider Name 3: Provider Developer */
            __('You\'re currently using version %1$s of the %2$s sync provider by %3$s.', 'culture-object'),
            $this->provider['version'],
            $this->provider['name'],
            $this->provider['developer']
        );
        echo '</p>';
        
        
        $host = get_option('cos_provider_collectionspace_host_uri');
        $user = get_option('cos_provider_collectionspace_username');
        $pass = get_option('cos_provider_collectionspace_password');
        
        if (!empty($host) && !empty($user) && !empty($pass)) {
            $url = $host.'/collectionobjects/?wf_deleted=false';
            $result = $this->perform_request($url, $this->generate_stream_context($user,$pass));
            if ($result) {
                $number_of_objects = $result['totalItems'];
                echo "<p>";
                printf(
                    __('There are %d objects currently available to sync from CollectionSpace.'),
                    number_format($number_of_objects)
                );
                echo "</p><p>";
                echo "<p>";
                printf(
                    __("Based on this number, you should expect a sync to take approximately %d minutes to complete."),
                    ceil(($number_of_objects/30)+2)
                );
                echo '<br /><small>';
                _e('This number can vary significantly on the speed on your network, server, and database.','culture-object');
                echo "</small></p>";
            } else {
                echo "<p>".__("We couldn't connect to CollectionSpace. Please check the details below and try again.",'culture-object')."</p>";
            }
        }
        
    }
    
    function generate_stream_context($user,$pass) {
        $creds = sprintf('Authorization: Basic %s', base64_encode($user.':'.$pass));
        $opts = array(
            'http' => array(
                'method' => 'GET',
                'header' => $creds,
                'user_agent' => 'CultureObject (http://www.cultureobject.co.uk), CollectionSpace Provider 1.0'
            ) 
        );
        return stream_context_create($opts);
    }
    
    function perform_request($url, $stream_context, $trim_namespace = false) {
        $xml = file_get_contents($url, false, $stream_context);
        if (!$xml) return false;
        $array = $this->xml2array($xml, $trim_namespace);
        return $array;
    }
    
    function perform_sync() {
        set_time_limit(0);
        ini_set('memory_limit','2048M');
        
        $start = microtime(true);
        
        echo "<pre>";
        
        $previous_posts = $this->get_current_object_ids();
        
        $host = get_option('cos_provider_collectionspace_host_uri');
        $user = get_option('cos_provider_collectionspace_username');
        $pass = get_option('cos_provider_collectionspace_password');
        
        if (empty($host) || empty($user) || empty($pass)) throw new CollectionSpaceException(__('Host, Username or Password is not defined','culture-object'));

        $this->import_people_taxonomy();
        echo __('Imported People Taxonomies','culture-object')."\r\n";
        $this->import_organizations_taxonomy();
        echo __('Imported Organization Taxonomies','culture-object')."\r\n";
        
        $page = 0;
            
        $created = 0;
        $updated = 0;
        $current_objects = array();
        
        $import_complete = false;
            
        
        while(!$import_complete) {
        
            $url = $host.'/collectionobjects/?pgSz=250&wf_deleted=false&pgNum='.$page;
            $import = $this->perform_request($url, $this->generate_stream_context($user,$pass));            
            
            $number_of_objects = count($import['list-item']);
            
            if ($number_of_objects > 0) {
                foreach($import['list-item'] as $doc) {
                    $object_exists = $this->object_exists($doc['csid']);
                    
                    if (!$object_exists) {
                        $current_objects[] = $this->create_object($doc);
                        $ttext = __("Created initial object: %s",$doc['csid']);
                        $import_status[] = $ttext;
                        echo $ttext;
                        $created++;
                    } else {
                        $current_objects[] = $this->update_object($doc);
                        $ttext = __("Updated initial object: %s",$doc['csid']);
                        $import_status[] = $ttext;
                        echo $ttext;
                        $updated++;
                    }
                    
                }
            }
            
            $imported_count = ($import['pageNum'] + 1) * $import['pageSize'];
            
            if ($imported_count > $import['totalItems']) {
                $import_complete = true;
                echo "Imported final page (".($page+1)."), ".$import['totalItems']." objects.\r\n";
            } else {
                echo "Imported page ".($page+1).". [Objects ".(($import['pageNum'] + 1) * $import['pageSize'])."/".$import['totalItems']."]\r\n";
            }
            
            $page++;
            
            flush();
            
        }
        
        $deleted = $this->clean_objects($current_objects,$previous_posts);
        
        //Now we need to get a list of new items.
        $args = array(
            'post_type' => 'object',
            'post_status' => 'any',
            'meta_key' => 'cos_init',
            'order' => 'ASC',
            'orderby' => 'meta_value',
            'posts_per_page' => -1
        );
        $posts = get_posts($args);
        foreach($posts as $post) {
            
            $init = get_post_meta($post->ID,'cos_init',true);
            $uri = get_post_meta($post->ID,'uri',true);
            $url = $host.$uri;
            $object = $this->perform_request($url, $this->generate_stream_context($user,$pass), true);
            
            $this->update_collectionspace_object($post,$object);
            
            $saved_image = $this->check_for_image($post);
            if ($saved_image) {
                $ttext = sprintf(__("Saved image for object %d",'culture-object'),$post->ID);
                $import_status[] = $ttext;
                echo $ttext."\r\n";
            }
            
            update_post_meta($post->ID,'cos_init',1);
            if (!$init) {
                $update_post = array(
                    'ID'                    => $post->ID,
                    'post_status'   => 'publish'
                );
                wp_update_post($update_post);
                $ttext = sprintf(__("Completed initial detail import for object %d",'culture-object'),$post->ID);
                $import_status[] = $ttext;
                echo $ttext."\r\n";
            } else {
                $ttext = sprintf(__("Updated details for object %d",'culture-object'),$post->ID);
                $import_status[] = $ttext;
                echo $ttext."\r\n";
            }
            
            flush();
            
        }
        
        
        $end = microtime(true);
        
        $import_duration = $end-$start;
        
        set_transient('cos_message', sprintf(
            /* Translators: 1: The name/type of import - usually the provider name. 2: The number of created objects. 3: The number of updated objects. 4: The number of deleted objects. 5: The number of seconds the whole process took to complete */
            __('%1$s import completed with %2$d objects created, %3$d updated and %4$d deleted in %5$d seconds.', 'culture-object'),
            'CollectionSpace',
            $created,
            $updated,
            $deleted,
            round($import_duration, 2)
        ), 0);
        
        set_transient('cos_collectionspace_show_message', true, 0);
        set_transient('cos_collectionspace_status', $import_status, 0);
    }
    
    function import_people_taxonomy() {
        
        $taxonomy_name = 'people';
        $cspace_path = 'personauthorities';
        
        $host = get_option('cos_provider_collectionspace_host_uri');
        $user = get_option('cos_provider_collectionspace_username');
        $pass = get_option('cos_provider_collectionspace_password');
        
        if (empty($host) || empty($user) || empty($pass)) throw new CollectionSpaceException(__('Host, Username or Password is not defined','culture-object'));

        $uri = $host.'/'.$cspace_path;
        
        $req = $this->perform_request($uri.'?pgSz=0', $this->generate_stream_context($user,$pass), true);
        
        if ($req['list-item']) {
            $parents = array();
            if (isset($req['list-item']['csid'])) {
                $parents[] = $req['list-item'];
            } else {
                foreach($req['list-item'] as $subcat) {
                    $parents[] = $subcat;
                }
            }
        }
        
        foreach($parents as $parent) {
            //Create the parent term if needbe.
            $term_name = $parent['displayName'];
            $parent_term_id = term_exists($term_name, $taxonomy_name);
            if (!$parent_term_id) {
                $parent_term_id = wp_insert_term($term_name, $taxonomy_name);
            }
            
            $authcat = $uri.'/'.$parent['csid'].'/items';
            $req = $this->perform_request($authcat.'?pgSz=0', $this->generate_stream_context($user,$pass), true);
            if (isset($req['list-item'])) {
                $person_uris = array();
                if (isset($req['list-item']['csid'])) {
                    $person_uris[] = $req['list-item']['uri'];
                } else {
                    foreach($req['list-item'] as $subcat) {
                        $person_uris[] = $subcat['uri'];
                    }
                }
            }
            
            if (!empty($person_uris)) {
                foreach($person_uris as $person_uri) {
                    $req = $this->perform_request($host.$person_uri, $this->generate_stream_context($user,$pass), true);
                    
                    if (!isset($req['persons_common']['personTermGroupList']['personTermGroup']['termDisplayName'])) continue;
                    
                    $termDisplayName = $req['persons_common']['personTermGroupList']['personTermGroup']['termDisplayName'];
                    
                    $term_id = term_exists($termDisplayName, $taxonomy_name, $parent_term_id);
                    if (!$term_id) {
                        $term_id = wp_insert_term($termDisplayName, $taxonomy_name, array('parent'=>$parent_term_id['term_id']));
                    }
                    
                    $description = array();
                    $description['termDisplayName'] = $req['persons_common']['personTermGroupList']['personTermGroup']['termDisplayName'];
                    $description['termSource'] = isset($req['persons_common']['personTermGroupList']['personTermGroup']['termSource']) ? $this->unarray($req['persons_common']['personTermGroupList']['personTermGroup']['termSource']) : '';
                    $description['birthDateGroup'] = isset($req['persons_common']['birthDateGroup']['dateDisplayDate']) ? $this->unarray($req['persons_common']['birthDateGroup']['dateDisplayDate']) : '';
                    $description['deathDateGroup'] = isset($req['persons_common']['deathDateGroup']['dateDisplayDate']) ? $this->unarray($req['persons_common']['deathDateGroup']['dateDisplayDate']) : '';
                    $description['group'] = isset($req['persons_common']['groups']) ? $this->unarray($req['persons_common']['groups']) : '';
                    $description['nationality'] = isset($req['persons_common']['nationalities']) ? $this->unarray($req['persons_common']['nationalities']) : '';
                    $description['bioNote'] = isset($req['persons_common']['bioNotes']) ? $this->unarray($req['persons_common']['bioNotes']) : '';

                    $description_text = json_encode($description);
                    
                    $result = wp_update_term($term_id['term_id'], $taxonomy_name, array('description'=>$description_text));
                    
                }
            }
            
        }
        
    }
    
    
    function import_organizations_taxonomy() {
        
        $taxonomy_name = 'organizations';
        $cspace_path = 'orgauthorities';
        
        $host = get_option('cos_provider_collectionspace_host_uri');
        $user = get_option('cos_provider_collectionspace_username');
        $pass = get_option('cos_provider_collectionspace_password');
        
        if (empty($host) || empty($user) || empty($pass)) throw new CollectionSpaceException(__('Host, Username or Password is not defined','culture-object'));

        $uri = $host.'/'.$cspace_path;
        
        $req = $this->perform_request($uri.'?pgSz=0', $this->generate_stream_context($user,$pass), true);
        
        if ($req['list-item']) {
            $parents = array();
            if (isset($req['list-item']['csid'])) {
                $parents[] = $req['list-item'];
            } else {
                foreach($req['list-item'] as $subcat) {
                    $parents[] = $subcat;
                }
            }
        }
        
        foreach($parents as $parent) {
            //Create the parent term if needbe.
            $term_name = $parent['displayName'];
            $parent_term_id = term_exists($term_name, $taxonomy_name);
            if (!$parent_term_id) {
                $parent_term_id = wp_insert_term($term_name, $taxonomy_name);
            }
            
            $authcat = $uri.'/'.$parent['csid'].'/items';
            $req = $this->perform_request($authcat.'?pgSz=0', $this->generate_stream_context($user,$pass), true);
            if (isset($req['list-item'])) {
                $org_uris = array();
                if (isset($req['list-item']['csid'])) {
                    $org_uris[] = $req['list-item']['uri'];
                } else {
                    foreach($req['list-item'] as $subcat) {
                        $org_uris[] = $subcat['uri'];
                    }
                }
            }
            
            if (!empty($org_uris)) {
                foreach($org_uris as $org_uri) {
                    $req = $this->perform_request($host.$org_uri, $this->generate_stream_context($user,$pass), true);
                    
                    if (!isset($req['organizations_common']['orgTermGroupList']['orgTermGroup']['termDisplayName'])) continue;
                    
                    $termDisplayName = $req['organizations_common']['orgTermGroupList']['orgTermGroup']['termDisplayName'];
                    
                    $term_id = term_exists($termDisplayName, $taxonomy_name, $parent_term_id);
                    if (!$term_id) {
                        $term_id = wp_insert_term($termDisplayName, $taxonomy_name, array('parent'=>$parent_term_id['term_id']));
                    }
                    
                    $description = array();
                    $description['termDisplayName'] = $req['organizations_common']['orgTermGroupList']['orgTermGroup']['termDisplayName'];
                    $description['mainBodyName'] = isset($req['organizations_common']['personTermGroupList']['personTermGroup']['mainBodyName']) ? $this->unarray($req['organizations_common']['personTermGroupList']['personTermGroup']['mainBodyName']) : '';
                    $description['termName'] = isset($req['organizations_common']['personTermGroupList']['personTermGroup']['termName']) ? $this->unarray($req['organizations_common']['personTermGroupList']['personTermGroup']['termName']) : '';
                    $description['additionsToName'] = isset($req['organizations_common']['personTermGroupList']['personTermGroup']['additionsToName']) ? $this->unarray($req['organizations_common']['personTermGroupList']['personTermGroup']['additionsToName']) : '';
                    $description['termSource'] = isset($req['organizations_common']['personTermGroupList']['personTermGroup']['termSource']) ? $this->unarray($req['organizations_common']['personTermGroupList']['personTermGroup']['termSource']) : '';
                    $description['foundingDateGroup'] = isset($req['organizations_common']['foundingDateGroup']['dateDisplayDate']) ? $this->unarray($req['organizations_common']['foundingDateGroup']['dateDisplayDate']) : '';
                    $description['dissolutionDateGroup'] = isset($req['organizations_common']['dissolutionDateGroup']['dateDisplayDate']) ? $this->unarray($req['organizations_common']['dissolutionDateGroup']['dateDisplayDate']) : '';
                    $description['function'] = isset($req['organizations_common']['functions']) ? $this->unarray($req['organizations_common']['functions']) : '';
                    $description['historyNote'] = isset($req['organizations_common']['historyNotes']) ? $this->unarray($req['organizations_common']['historyNotes']) : '';

                    $description_text = json_encode($description);
                    
                    $result = wp_update_term($term_id['term_id'], $taxonomy_name, array('description'=>$description_text));
                    
                }
            }
            
        }
        
    }
    
    function check_for_image($post) {
        
        $helper = new CultureObject\Helper;
        
        $should_import_images = $helper->get_core_setting('import_images');
        if (!$should_import_images) return false;
        
        $csid = get_post_meta($post->ID, 'csid', true);
        
        $host = get_option('cos_provider_collectionspace_host_uri');
        $user = get_option('cos_provider_collectionspace_username');
        $pass = get_option('cos_provider_collectionspace_password');
        
        $uri = $host.'/relations?sbj='.$csid.'&objType=Media';
        
        $req = $this->perform_request($uri, $this->generate_stream_context($user,$pass), true);
        
        $image = false;
        if (isset($req['relation-list-item']) && is_array($req['relation-list-item'])) {
            if (isset($req['relation-list-item']['csid'])) {
                //single item.
                if (isset($req['relation-list-item']['object']['documentType']) && $req['relation-list-item']['object']['documentType'] == "Media") {
                    $image = $req['relation-list-item']['object'];
                } else return false;
            } else {
                $req['relation-list-item'] = array_reverse($req['relation-list-item']);
                foreach($req['relation-list-item'] as $item) {
                    if (isset($item['object']['documentType']) && $item['object']['documentType'] == "Media") {
                        $image = $item['object'];
                        break;
                    }
                }
            }
        } else return false;
        
        if (!$image || !isset($image['csid'])) return;
        
        if (get_post_meta($post->ID, 'saved_image_id', true) != $image['csid']) {
        
            $image_id = $helper->add_image_to_gallery_from_url($host.'/media/'.$image['csid'].'/blob/content', $image['csid'], $this->generate_stream_context($user,$pass));
            update_post_meta($post->ID, 'saved_image_id', $image['csid']);
            set_post_thumbnail($post->ID, $image_id);
            return true;
        }
        return false;
    }
    
    function update_collectionspace_object($post,$object) {
        if (isset($object['collectionobjects_common']['objectNumber']))
            update_post_meta($post->ID,'objectNumber', $this->unarray($object['collectionobjects_common']['objectNumber']));
        if (isset($object['collectionobjects_common']['briefDescriptions']))
            update_post_meta($post->ID,'briefDescription', $this->unarray($object['collectionobjects_common']['briefDescriptions']));
        if (isset($object['collectionobjects_common']['objectNameList']['objectNameGroup']['objectName']))
            update_post_meta($post->ID,'objectName', $this->unarray($object['collectionobjects_common']['objectNameList']['objectNameGroup']['objectName']));
        if (isset($object['collectionobjects_common']['responsibleDepartments']))
            update_post_meta($post->ID,'responsibleDepartment', $this->unarray($object['collectionobjects_common']['responsibleDepartments']));
        if (isset($object['collectionobjects_common']['contentConcepts']))
            update_post_meta($post->ID,'contentConcept', $this->unarray($object['collectionobjects_common']['contentConcepts']));
        if (isset($object['collectionobjects_common']['contentLanguages']['contentLanguage']))
            update_post_meta($post->ID,'contentLanguage', $this->quick_parse_human_value_from_urn($object['collectionobjects_common']['contentLanguages']['contentLanguage']));
        if (isset($object['collectionobjects_common']['measuredPartGroupList']['measuredPartGroup']['dimensionSummary']))
            update_post_meta($post->ID,'dimension', $this->unarray($object['collectionobjects_common']['measuredPartGroupList']['measuredPartGroup']['dimensionSummary']));
        if (isset($object['collectionobjects_common']['materialGroupList']['materialGroup']['materialName']))
            update_post_meta($post->ID,'material', $this->unarray($object['collectionobjects_common']['materialGroupList']['materialGroup']['materialName']));
        if (isset($object['collectionobjects_common']['objectProductionDateGroupList']['objectProductionDateGroup']['dateDisplayDate']))
            update_post_meta($post->ID,'objectProductionDate', $this->unarray($object['collectionobjects_common']['objectProductionDateGroupList']['objectProductionDateGroup']['dateDisplayDate']));
        if (isset($object['collectionobjects_common']['objectProductionDateGroupList']['objectProductionDateGroup']['dateEarliestSingleYear']))
            update_post_meta($post->ID,'dateEarliestSingleYear', $this->unarray($object['collectionobjects_common']['objectProductionDateGroupList']['objectProductionDateGroup']['dateEarliestSingleYear']));
        if (isset($object['collectionobjects_common']['objectProductionDateGroupList']['objectProductionDateGroup']['dateLatestYear']))
            update_post_meta($post->ID,'dateLatestSingleYear', $this->unarray($object['collectionobjects_common']['objectProductionDateGroupList']['objectProductionDateGroup']['dateLatestYear']));
        if (isset($object['collectionobjects_common']['objectProductionOrganizationGroupList']['objectProductionOrganizationGroup']['objectProductionOrganizationRole']))
            update_post_meta($post->ID,'objectProductionOrganizationRole', $this->unarray($object['collectionobjects_common']['objectProductionOrganizationGroupList']['objectProductionOrganizationGroup']['objectProductionOrganizationRole']));
        if (isset($object['collectionobjects_common']['objectProductionPeopleGroupList']['objectProductionPeopleGroup']['objectProductionPeopleRole']))
            update_post_meta($post->ID,'objectProductionPeopleRole', $this->unarray($object['collectionobjects_common']['objectProductionPeopleGroupList']['objectProductionPeopleGroup']['objectProductionPeopleRole']));
        if (isset($object['collectionobjects_common']['objectProductionPersonGroupList']['objectProductionPersonGroup']['objectProductionPersonRole']))
            update_post_meta($post->ID,'objectProductionPersonRole', $this->unarray($object['collectionobjects_common']['objectProductionPersonGroupList']['objectProductionPersonGroup']['objectProductionPersonRole']));
        if (isset($object['collectionobjects_common']['techniqueGroupList']['techniqueGroup']['technique']))
            update_post_meta($post->ID,'technique', $this->unarray($object['collectionobjects_common']['techniqueGroupList']['techniqueGroup']['technique']));
        if (isset($object['collectionobjects_common']['fieldCollectionDateGroup']['dateDisplayDate']))
            update_post_meta($post->ID,'fieldCollectionDate', $this->unarray($object['collectionobjects_common']['fieldCollectionDateGroup']['dateDisplayDate']));
        if (isset($object['collectionobjects_common']['fieldCollectionMethods']))
            update_post_meta($post->ID,'fieldCollectionMethod', $this->unarray($object['collectionobjects_common']['fieldCollectionMethods']));
        if (isset($object['collectionobjects_common']['fieldCollectionNote']))
            update_post_meta($post->ID,'fieldCollectionNote', $this->unarray($object['collectionobjects_common']['fieldCollectionNote']));
        if (isset($object['collectionobjects_common']['fieldCollectionNumber']))
            update_post_meta($post->ID,'fieldCollectionNumber', $this->unarray($object['collectionobjects_common']['fieldCollectionNumber']));
        if (isset($object['collectionobjects_common']['fieldColEventNames']['fieldColEventName']))
            update_post_meta($post->ID,'fieldColEventName', $this->unarray($object['collectionobjects_common']['fieldColEventNames']['fieldColEventName']));
            
        
        if (isset($object['collectionobjects_common']['fieldCollectionPlace'])) {
            update_post_meta($post->ID,'fieldCollectionPlace', $this->quick_parse_human_value_from_urn($object['collectionobjects_common']['fieldCollectionPlace']));
        }
        
        if (isset($object['collectionobjects_common']['fieldCollectors']['fieldCollector'])) {
            update_post_meta($post->ID,'fieldCollector', $this->quick_parse_human_value_from_urn($object['collectionobjects_common']['fieldCollectors']['fieldCollector']));
            $this->add_taxonomy_to_object($post->ID, 'people', $this->quick_parse_human_value_from_urn($object['collectionobjects_common']['fieldCollectors']['fieldCollector']));
        }
        
        if (isset($object['collectionobjects_common']['objectProductionPersonGroupList']['objectProductionPersonGroup']['objectProductionPerson'])) {
            update_post_meta($post->ID,'objectProductionPerson', $this->quick_parse_human_value_from_urn($object['collectionobjects_common']['objectProductionPersonGroupList']['objectProductionPersonGroup']['objectProductionPerson']));
            $this->add_taxonomy_to_object($post->ID, 'people', $this->quick_parse_human_value_from_urn($object['collectionobjects_common']['objectProductionPersonGroupList']['objectProductionPersonGroup']['objectProductionPerson']));
        }
        
        if (isset($object['collectionobjects_common']['objectProductionPeopleGroupList']['objectProductionPeopleGroup']['objectProductionPeople'])) {
            update_post_meta($post->ID,'objectProductionPeople', $this->quick_parse_human_value_from_urn($object['collectionobjects_common']['objectProductionPeopleGroupList']['objectProductionPeopleGroup']['objectProductionPeople']));
            $this->add_taxonomy_to_object($post->ID, 'people', $this->quick_parse_human_value_from_urn($object['collectionobjects_common']['objectProductionPeopleGroupList']['objectProductionPeopleGroup']['objectProductionPeople']));
        }
        
        if (isset($object['collectionobjects_common']['objectProductionOrganizationGroupList']['objectProductionOrganizationGroup']['objectProductionOrganization'])) {
            update_post_meta($post->ID,'objectProductionOrganization', $this->quick_parse_human_value_from_urn($object['collectionobjects_common']['objectProductionOrganizationGroupList']['objectProductionOrganizationGroup']['objectProductionOrganization']));
            $this->add_taxonomy_to_object($post->ID, 'organizations', $this->quick_parse_human_value_from_urn($object['collectionobjects_common']['objectProductionOrganizationGroupList']['objectProductionOrganizationGroup']['objectProductionOrganization']));
        }
            
    }
    
    function add_taxonomy_to_object($post_id, $tax, $name) {
        if (empty($name)) return;
        $term_id = term_exists($name, $tax);
        if ($term_id) {
            echo "Adding taxonomy ".intval($term_id['term_id'])." to object ".$post_id."\r\n";
            wp_set_object_terms($post_id, intval($term_id['term_id']), $tax, true);
        }
    }
    
    function quick_parse_human_value_from_urn($urn) {
        if (is_array($urn)) {
            $urn = $this->unarray($urn);
        }
        $matches = array();
        preg_match_all('/\'(.+)\'/', $urn, $matches);
        if (isset($matches[1]) && isset($matches[1][0]) && !empty($matches[1][0])) return $matches[1][0]; else return null;
    }
    
    function unarray($value) {
        while (is_array($value)) {
            $value = array_shift($value);
        }
        return $value;
    }
    
    function get_current_object_ids() {
        $args = array('post_type'=>'object','posts_per_page'=>-1);
        $posts = get_posts($args);
        $current_posts = array();
        foreach($posts as $post) {
            $current_posts[] = $post->ID;
        }
        return $current_posts;
    }
    
    function clean_objects($current_objects,$previous_objects) {
        $to_remove = array_diff($previous_objects, $current_objects);
        
        $import_delete = array();
        
        $deleted = 0;
        
        foreach($to_remove as $remove_id) {
            wp_delete_post($remove_id,true);
            $import_delete[] = sprintf(
                /* Translators: 1: A WordPress Post ID 2: The type of file or the provider name (CSV, AdLib, etc) */
                __('Removed Post ID %1$d as it is no longer in the exported list of objects from %2$s', 'culture-object'),
                $remove_id,
                'CollectionSpace'
            );
            $deleted++;
        }
        
        set_transient('cos_collectionspace_deleted', $import_delete, 0);
        
        return $deleted;
        
    }
    
    function create_object($doc) {
        $title = (isset($doc['title']) && !empty($doc['title'])) ? $doc['title'] : $doc['csid'];
        $post = array(
            'post_title'                => $title,
            'post_type'              => 'object',
            'post_status'            => 'draft',
        );
        $post_id = wp_insert_post($post);
        update_post_meta($post_id,'cos_last_import_update',time());
        update_post_meta($post_id,'cos_init',0);
        $this->update_object_meta($post_id,$doc);
        return $post_id;
    }
    
    
    function update_object($doc) {
        $existing_id = $this->existing_object_id($doc['csid']);
        $title = (isset($doc['title']) && !empty($doc['title'])) ? $doc['title'] : $doc['csid'];
        $post = array(
            'ID'                                => $existing_id,
            'post_title'                => $title,
            'post_type'              => 'object',
        );
        $post_id = wp_update_post($post);
        update_post_meta($post_id,'cos_last_import_update',time());
        $this->update_object_meta($post_id,$doc);
        return $post_id;
    }
    
    function update_object_meta($post_id,$doc) {
        foreach($doc as $key => $value) {
            update_post_meta($post_id,$key,$value);
        }
    }
    
    function object_exists($id) {
        $args = array(
            'post_type' => 'object',
            'post_status' => 'any',
            'meta_key' => 'csid',
            'meta_value' => $id,
        );
        return (count(get_posts($args)) > 0) ? true : false;
    }
    
    function existing_object_id($id) {
        $args = array(
            'post_type' => 'object',
            'post_status' => 'any',
            'meta_key' => 'csid',
            'meta_value' => $id
        );
        $posts = get_posts($args);
        if (count($posts) == 0) throw new Exception(__("Called existing_object_id for an object that doesn't exist. This is likely a bug in your provider plugin, but because it is probably unsafe to continue the import, it has been aborted.",'culture-object'));
        return $posts[0]->ID;
    }
    
    function xml2array($string, $trim_namespace) {
        if ($trim_namespace) {
            $string = str_replace('ns2:','',$string); //This is probably evil, but simplexml doesn't seem to make it easy to get data inside namespaced elements, epsecially when you want it as an array.
        }
        $xml = simplexml_load_string($string);
        $json = json_encode($xml);
        $array = json_decode($json, true);
        return $array;
    }
    
    
}

?>