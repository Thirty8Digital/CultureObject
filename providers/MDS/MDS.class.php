<?php

class MDSException extends \CultureObject\Exception\ProviderException {

}

class MDS extends \CultureObject\Provider {


	private $provider = array(
		'name'      => 'Museum Data Service',
		'version'   => '1.0.0',
		'developer' => 'Thirty8 Digital',
		'cron'      => false,
		'ajax'      => true,
	);

	function add_provider_assets() {
		$screen = get_current_screen();
		if ( $screen->base != 'culture-object_page_cos_provider_settings' ) {
			return;
		}
		$js_url = plugins_url( '/assets/admin.js?nc=' . time(), __FILE__ );
		wp_enqueue_script( 'jquery-ui' );
		wp_enqueue_script( 'jquery-ui-core' );
		wp_register_script( 'mds_admin_js', $js_url, array( 'jquery', 'jquery-ui-core', 'jquery-ui-progressbar' ), $this->provider['version'], true );
		wp_enqueue_script( 'mds_admin_js' );

		wp_enqueue_style( 'jquery-ui-css', plugin_dir_url( __FILE__ ) . 'assets/jquery-ui-fresh.css', array(), $this->provider['version'] );
		wp_enqueue_style( 'mds_admin_css', plugin_dir_url( __FILE__ ) . 'assets/admin.css', array(), $this->provider['version'] );
	}

	function get_provider_information() {
		return $this->provider;
	}

	function register_settings() {
		add_settings_section( 'cos_provider_settings', esc_html__( 'Provider Settings', 'culture-object' ), array( $this, 'generate_settings_group_content' ), 'cos_provider_settings' );

		register_setting( 'cos_provider_settings', 'cos_provider_resumption_token' );

		add_settings_field( 'cos_provider_resumption_token', esc_html__( 'MDS Resumption Token', 'culture-object' ), array( $this, 'generate_settings_field_input_textarea' ), 'cos_provider_settings', 'cos_provider_settings', array( 'field' => 'cos_provider_resumption_token' ) );
	}

	function generate_settings_group_content() {

		echo '<p>';
		printf(
			/* Translators: 1: Provider Plugin Version 2: Provider Name 3: Provider Developer */
			esc_html__( 'You\'re currently using version %1$s of the %2$s sync provider by %3$s.', 'culture-object' ),
			esc_html( $this->provider['version'] ),
			esc_html( $this->provider['name'] ),
			esc_html( $this->provider['developer'] )
		);
		echo '</p>';

		$authority = get_option( 'cos_provider_feed_url' );
		if ( ! empty( $authority ) ) {
			echo '<p>' . esc_html__( 'MDS\'s JSON data takes a while to generate, so we\'re unable to show a preview here, and import could take a very long time.', 'culture-object' ) . '</p>';
		}
	}

	function execute_init_action() {
		$labels = array(
			'name'              => esc_html_x( 'Object Categories', 'taxonomy general name' ),
			'singular_name'     => esc_html_x( 'Object Category', 'taxonomy singular name' ),
			'search_items'      => esc_html__( 'Search Object Categories' ),
			'all_items'         => esc_html__( 'All Object Categories' ),
			'parent_item'       => esc_html__( 'Parent Object Category' ),
			'parent_item_colon' => esc_html__( 'Parent Object Category:' ),
			'edit_item'         => esc_html__( 'Edit Object Category' ),
			'update_item'       => esc_html__( 'Update Object Category' ),
			'add_new_item'      => esc_html__( 'Add New Object Category' ),
			'new_item_name'     => esc_html__( 'New Object Category Name' ),
			'menu_name'         => esc_html__( 'Object Category' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'object_category' ),
		);

		register_taxonomy( 'object_category', array( 'object' ), $args );

		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'add_provider_assets' ) );
		}
	}

	function perform_request( $url ) {
		$json = file_get_contents( $url );
		$data = json_decode( $json, true );
		if ( $data ) {
			if ( isset( $data['data'] ) ) {
				return $data;
			} else {
				throw new MDSException( sprintf( esc_html__( '%s returned an invalid JSON response', 'culture-object' ), 'MDS' ) );
			}
		} else {
			throw new MDSException( sprintf( esc_html__( '%s returned an invalid response: ', 'culture-object' ) . esc_html( $json ), 'MDS' ) );
		}
	}

	function generate_settings_field_input_textarea( $args ) {
		$field       = $args['field'];
		$value       = get_option( $field );
		$placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
		printf( '<textarea type="text" name="%s" cols="50" rows="5" id="%s" placeholder="%s">%s</textarea>', esc_attr( $field ), esc_attr( $field ), esc_attr( $placeholder ), esc_attr( $value ) );
	}

	function perform_ajax_sync() {

		//phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by CO upstream.

		if ( ! isset( $_POST['resume'] ) || ! isset( $_POST['import_id'] ) ) {
			throw new MDSException( esc_html__( 'Invalid AJAX import request', 'culture-object' ) );
		}

		$resume    = $_POST['resume'];
		$import_id = $_POST['import_id'];
		$result    = array();

		if ( $resume == 'start' ) {
			$resume = false;
		}

		if ( $resume == 'cleanup' ) {
			ini_set( 'memory_limit', '2048M' );

			$objects        = get_option( 'cos_mds_import_' . $import_id, array() );
			$previous_posts = $this->get_current_object_ids();
			delete_option( 'cos_mds_import_' . $import_id, array() );
			return $this->clean_objects( $objects, $previous_posts );
		} else {
			$cleanup = isset( $_POST['perform_cleanup'] ) && $_POST['perform_cleanup'];

			$result = $this->import_page( $resume );

			if ( $result['has_next'] ) {
				$result['complete']   = false;
				$result['percentage'] = round( 100 - ( 100 / $result['total_objects'] * $result['remaining'] ) );
			} else {
				$result['complete']   = true;
				$result['percentage'] = 100;
			}

			$result['next_nonce'] = wp_create_nonce( 'cos_ajax_import_request' );

			if ( $cleanup ) {
				$objects = get_option( 'cos_mds_import_' . $import_id, array() );
				update_option( 'cos_mds_import_' . $import_id, array_merge( $objects, $result['chunk_objects'] ) );
			}

			return $result;
		}

		//phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	function import_page( $resume = false ) {
		$import_status = array();
		if ( empty( $resume ) ) {
			$resume = get_option( 'cos_provider_resumption_token', false );
		}
		if ( empty( $resume ) ) {
			throw new MDSException( esc_html__( "You haven't yet configured your API resumption token in the Culture Object Sync settings", 'culture-object' ) );
		}

		$params = array( 'resume' => $resume );

		$url = 'https://mds-data-1.ciim.k-int.com/api/v1/extract?' . http_build_query( $params );

		$result = $this->perform_request( $url );

		$current_objects   = array();
		$updated           = 0;
		$created           = 0;
		$number_of_objects = count( $result['data'] );

		if ( $number_of_objects == 0 ) {
			$import_status[] = 'Nothing to import';
		} else {
			foreach ( $result['data'] as $obj ) {
				$identifier            = $obj['@admin']['id'];
				$obj['_cos_object_id'] = $identifier;
				$object_exists         = $this->object_exists( $identifier );
				if ( ! $object_exists ) {
					$current_objects[] = $this->create_object( $identifier, $obj );
					$import_status[]   = esc_html__( 'Created object', 'culture-object' ) . ': ' . $identifier;
					++$created;
				} else {
					$current_objects[] = $this->update_object( $identifier, $obj );
					$import_status[]   = esc_html__( 'Updated object', 'culture-object' ) . ': ' . $identifier;
					++$updated;
				}
			}
		}

		$return                    = array();
		$return['total_objects']   = $result['stats']['total'];
		$return['remaining']       = $result['stats']['remaining'];
		$return['has_next']        = $result['has_next'];
		$return['next_resume']     = $result['resume'];
		$return['imported_count']  = $result['stats']['total'] - $result['stats']['remaining'];
		$return['chunk_objects']   = $current_objects;
		$return['import_status']   = $import_status;
		$return['processed_count'] = $number_of_objects;
		$return['created']         = $created;
		$return['updated']         = $updated;

		return $return;
	}

	function concat_values( $item_array ) {
		if ( empty( $item_array ) ) {
			return false;
		}
		if ( is_string( $item_array ) ) {
			return $item_array;
		}
		return implode(
			', ',
			array_map(
				function ( $item ) {
					return $item['value'];
				},
				$item_array
			)
		);
	}

	function perform_sync() {
		throw new MDSException( esc_html__( 'Only AJAX sync is supported for this provider.', 'culture-object' ) );
	}

	function get_current_object_ids() {
		$args          = array(
			'post_type'      => 'object',
			'posts_per_page' => -1,
		);
		$posts         = get_posts( $args );
		$current_posts = array();
		foreach ( $posts as $post ) {
			$current_posts[] = $post->ID;
		}
		return $current_posts;
	}

	function clean_objects( $current_objects, $previous_objects ) {
		$to_remove = array_diff( $previous_objects, $current_objects );

		$import_delete = array();

		$deleted = 0;

		foreach ( $to_remove as $remove_id ) {
			wp_delete_post( $remove_id, true );
			$import_delete[] = sprintf(
			/* Translators: 1: A WordPress Post ID 2: The type of file or the provider name (CSV, AdLib, etc) */
				__( 'Removed Post ID %1$d as it is no longer in the exported list of objects from %2$s', 'culture-object' ),
				$remove_id,
				'MDS'
			);
			++$deleted;
		}

		set_transient( 'cos_mds_deleted', $import_delete, 0 );

		$return                   = array();
		$return['deleted_count']  = $deleted;
		$return['deleted_status'] = $import_delete;
		return $return;
	}

	function value_or_false( $fields, $key ) {
		return isset( $fields[ $key ] ) ? $fields[ $key ] : false;
	}

	function build_mds_data( $obj ) {

		$fields = array();
		foreach ( $obj['@document']['units'] as $field ) {
			$fields[ $field['type'] ] = $field['value'];
		}

		$data              = array();
		$data['@document'] = $obj['@document'];
		$data['@admin']    = $obj['@admin'];

		$data['accession_number']  = $this->value_or_false( $fields, 'spectrum/object_number' );
		$data['title']             = $this->value_or_false( $fields, 'spectrum/title' );
		$data['name']              = $this->value_or_false( $fields, 'spectrum/object_name' );
		$data['attribution']       = $this->value_or_false( $fields, 'spectrum/credit_line' );
		$data['description']       = $this->value_or_false( $fields, 'spectrum/brief_description' );
		$data['subjects']          = $this->value_or_false( $fields, 'spectrum/associated_concept' );
		$data['production_date']   = $this->value_or_false( $fields, 'spectrum/object_production_date' );
		$data['maker']             = $this->value_or_false( $fields, '' );
		$data['materials']         = $this->value_or_false( $fields, 'spectrum/material' );
		$data['related_place']     = $this->value_or_false( $fields, 'spectrum/associated_place' );
		$data['related_person']    = $this->value_or_false( $fields, 'spectrum/associated_person' );
		$data['organisation name'] = ! empty( $obj['@admin']['data_source']['organisation'] ) ? $obj['@admin']['data_source']['organisation'] : false;

		return $data;
	}

	function create_object( $identifier, $obj ) {
		$meta = $this->build_mds_data( $obj );

		$post    = array(
			'post_title'  => $meta['name'],
			'post_name'   => $identifier,
			'post_type'   => 'object',
			'post_status' => 'publish',
			'meta_input'  => $meta,
		);
		$post_id = wp_insert_post( $post );
		return $post_id;
	}


	function update_object( $identifier, $obj ) {
		$meta = $this->build_mds_data( $obj );

		$existing_id = $this->existing_object_id( $identifier );
		$post        = array(
			'ID'          => $existing_id,
			'post_title'  => $meta['name'],
			'post_name'   => $identifier,
			'post_type'   => 'object',
			'post_status' => 'publish',
			'meta_input'  => $meta,
		);
		$post_id     = wp_update_post( $post );
		return $post_id;
	}

	function object_exists( $id ) {
		$args = array(
			'post_type'  => 'object',
			'meta_key'   => '_cos_object_id',
			'meta_value' => $id,
		);
		return ( ! empty( get_posts( $args ) ) ) ? true : false;
	}

	function existing_object_id( $id ) {
		$args  = array(
			'post_type'  => 'object',
			'meta_key'   => '_cos_object_id',
			'meta_value' => $id,
		);
		$posts = get_posts( $args );
		if ( count( $posts ) == 0 ) {
			throw new Exception( esc_html__( "Called existing_object_id for an object that doesn't exist. This is likely a bug in your provider plugin, but because it is probably unsafe to continue the import, it has been aborted.", 'culture-object' ) );
		}
		return $posts[0]->ID;
	}

	function generate_settings_outside_form_html() {

		$this->output_js_localization();

		echo '<h3>' . esc_html__( 'AJAX Import', 'culture-object' ) . '</h3>';

		echo '<div id="hide-on-import">';
		echo '<p>' . esc_html__( 'Once you have saved your settings above, you can begin your import by clicking below.', 'culture-object' ) . '</p>';

		echo '<fieldset>
        	<label for="perform_cleanup">
        		<input name="perform_cleanup" type="checkbox" id="perform_cleanup" value="1" />
                <span>' . esc_attr__( 'Delete existing objects not in this import?', 'culture-object' ) . '</span>
        	</label>
        </fieldset>';

		echo '<input id="mds_perform_ajax_import" data-import-id="' . esc_attr( uniqid( '', true ) ) . '" data-sync-key="' . esc_attr( get_option( 'cos_core_sync_key' ) ) . '" data-starting-nonce="' . esc_attr( wp_create_nonce( 'cos_ajax_import_request' ) ) . '" type="button" class="button button-primary" value="';
		esc_html_e( 'Begin Import', 'culture-object' );
		echo '" />';
		echo '</div>';

		echo '<div id="mds_import_progressbar"><div class="progress-label">' . esc_html__( 'Starting Import...', 'culture-object' ) . '</div></div>';
		echo '<div id="mds_import_detail"></div>';
	}

	function output_js_localization() {
		echo '<script>
        strings = {};
        strings.importing_please_wait = "' . esc_html__( 'Importing... This may take some time...', 'culture-object' ) . '";
        strings.imported = "' . esc_html__( 'Imported', 'culture-object' ) . '";
        strings.objects = "' . esc_html__( 'objects', 'culture-object' ) . '";
        strings.objects_imported = "' . esc_html__( 'objects imported', 'culture-object' ) . '";
        strings.objects_deleted = "' . esc_html__( 'objects deleted', 'culture-object' ) . '";
        strings.import_complete = "' . esc_html__( 'Import complete.', 'culture-object' ) . '";
        strings.performing_cleanup = "' . esc_html__( 'Performing cleanup, please wait... This can take a long time if you have deleted a lot of objects.', 'culture-object' ) . '";
        </script>';
	}
}
