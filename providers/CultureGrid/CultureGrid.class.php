<?php

class CultureGridException extends \CultureObject\Exception\ProviderException {
}

class CultureGrid extends \CultureObject\Provider {


	private $provider = array(
		'name'      => 'CultureGrid',
		'version'   => '1.0',
		'developer' => 'Thirty8 Digital',
		'cron'      => true,
	);

	function get_provider_information() {
		return $this->provider;
	}

	function register_settings() {
		add_settings_section( 'cos_provider_settings', esc_html__( 'Provider Settings', 'culture-object' ), array( $this, 'generate_settings_group_content' ), 'cos_provider_settings' );

		register_setting( 'cos_provider_settings', 'cos_provider_search_authority', array( 'sanitize_callback' => 'sanitize_text_field' ) );

		add_settings_field( 'cos_provider_search_authority', esc_html__( 'CultureGrid Search Authority', 'culture-object' ), array( $this, 'generate_settings_field_input_text' ), 'cos_provider_settings', 'cos_provider_settings', array( 'field' => 'cos_provider_search_authority' ) );
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

		$authority = get_option( 'cos_provider_search_authority' );
		if ( ! empty( $authority ) ) {
			$url               = 'http://www.culturegrid.org.uk/index/select?fl=*&wt=json&rows=1&indent=on&q=authority:' . rawurlencode( $authority ) . '&start=0';
			$result            = $this->perform_request( $url );
			$number_of_objects = $result['response']['numFound'];
			echo '<p>';
			printf(
				/* translators: %d: Number of objects available to sync */
				esc_html__( 'There are %d objects currently available to sync from CultureGrid based on your current authority.', 'culture-object' ),
				number_format( $number_of_objects )
			);
			echo '</p><p>';
			printf(
				/* translators: %d: Estimated number of minutes for the sync to complete */
				esc_html__( 'Based on this number, you should expect a sync to take approximately %d minutes to complete.', 'culture-object' ),
				intval( round( $number_of_objects / 420 ) )
			);
			echo '<br /><small>';
			esc_html_e( 'This number can vary significantly on the speed on your network, server, and database.', 'culture-object' );
			echo '</small></p>';
			if ( $number_of_objects > 100000 ) {
				echo '<p>' . esc_html__( 'CultureGrid sync only supports 100,000 objects maximum for the sake of performance. Only the first 100,000 objects will sync.', 'culture-object' ) . '</p>';
			}
		}
	}

	function perform_request( $url ) {
		$json = file_get_contents( $url );
		$data = json_decode( $json, true );
		if ( $data ) {
			if ( isset( $data['response'] ) ) {
				return $data;
			} else {
				/* translators: %s: Provider name */
				throw new CultureGridException( sprintf( esc_html__( '%s returned an invalid JSON response', 'culture-object' ), 'CultureGrid' ) );
			}
		} else {
			/* translators: 1: Provider name 2: Excerpt of the response returned by the provider */
			throw new CultureGridException( sprintf( esc_html__( '%1$s returned an invalid response: %2$s', 'culture-object' ), 'CultureGrid', esc_html( substr( (string) $json, 0, 500 ) ) ) );
		}
	}

	function generate_settings_field_input_text( $args ) {
		$field = $args['field'];
		$value = get_option( $field );
		printf( '<input type="text" name="%s" id="%s" value="%s" />', esc_attr( $field ), esc_attr( $field ), esc_attr( $value ) );
	}

	function perform_sync() {
		set_time_limit( 0 );
		ini_set( 'memory_limit', '768M' );

		$start = microtime( true );

		$authority = get_option( 'cos_provider_search_authority' );
		if ( empty( $authority ) ) {
			throw new CultureGridException( esc_html__( "You haven't yet configured a search authority in the Culture Object Sync settings", 'culture-object' ) );
		}

		$previous_posts = $this->get_current_object_ids();

		$url = 'http://www.culturegrid.org.uk/index/select?fl=*&wt=json&rows=100000&indent=on&q=authority:' . $authority . '&start=0';

		$result = $this->perform_request( $url );

		$number_of_objects = $result['response']['numFound'];
		if ( $number_of_objects > 0 ) {
			foreach ( $result['response']['docs'] as $doc ) {
				$object_exists = $this->object_exists( $doc['dc.identifier'] );
				if ( ! $object_exists ) {
					$current_objects[] = $this->create_object( $doc );
					echo esc_html__( 'Created object', 'culture-object' ) . ': ' . esc_html( $doc['dc.title'][0] ) . "<br />\r\n";
				} else {
					$current_objects[] = $this->update_object( $doc );
					echo esc_html__( 'Updated object', 'culture-object' ) . ': ' . esc_html( $doc['dc.title'][0] ) . "<br />\r\n";
				}
			}
			$this->clean_objects( $current_objects, $previous_posts );
		}

		$end = microtime( true );

		printf(
			/* translators: %d: Number of seconds the sync took */
			esc_html__( 'Sync Complete in %d seconds', 'culture-object' ) . "\r\n",
			intval( $end - $start )
		);
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

		foreach ( $to_remove as $remove_id ) {
			wp_delete_post( $remove_id, true );
			printf(
				/* Translators: 1: A WordPress Post ID 2: The type of file or the provider name (CSV, AdLib, etc) */
				esc_html__( 'Removed Post ID %1$d as it is no longer in the exported list of objects from %2$s', 'culture-object' ),
				intval( $remove_id ),
				'CultureGrid'
			) . '<br />';
		}
	}

	function create_object( $doc ) {
		$post    = array(
			'post_title'  => $doc['dc.title'][0],
			'post_type'   => 'object',
			'post_status' => 'publish',
		);
		$post_id = wp_insert_post( $post );
		$this->update_object_meta( $post_id, $doc );
		return $post_id;
	}


	function update_object( $doc ) {
		$existing_id = $this->existing_object_id( $doc['dc.identifier'] );
		$post        = array(
			'ID'          => $existing_id,
			'post_title'  => $doc['dc.title'][0],
			'post_type'   => 'object',
			'post_status' => 'publish',
		);
		$post_id     = wp_update_post( $post );
		$this->update_object_meta( $post_id, $doc );
		return $post_id;
	}

	function update_object_meta( $post_id, $doc ) {
		$helper = new \CultureObject\Helper();
		foreach ( $doc as $key => $value ) {
			if ( ! $helper->is_safe_meta_key( $key ) ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$value = $value[0];
			}
			update_post_meta( $post_id, $key, $value );
		}
	}

	function object_exists( $id ) {
		$args = array(
			'post_type'  => 'object',
			'meta_key'   => 'dc.identifier',
			'meta_value' => $id,
		);
		return ( count( get_posts( $args ) ) > 0 ) ? true : false;
	}

	function existing_object_id( $id ) {
		$args  = array(
			'post_type'  => 'object',
			'meta_key'   => 'dc.identifier',
			'meta_value' => $id,
		);
		$posts = get_posts( $args );
		if ( count( $posts ) == 0 ) {
			throw new Exception( esc_html__( "Called existing_object_id for an object that doesn't exist. This is likely a bug in your provider plugin, but because it is probably unsafe to continue the import, it has been aborted.", 'culture-object' ) );
		}
		return $posts[0]->ID;
	}
}
