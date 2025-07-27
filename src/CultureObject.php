<?php

namespace CultureObject;

class CultureObject extends Core {


	public $helper = false;

	function __construct() {
		$settings     = new Settings();
		$this->helper = new Helper();
		add_action( 'init', array( $this, 'wordpress_init' ) );
		add_action( 'parse_request', array( $this, 'should_sync' ) );
		add_action( 'wp_ajax_cos_sync', array( $this, 'should_ajax_sync' ) );
		add_action( 'init', array( $this, 'purge_objects' ) );
		add_action( 'plugins_loaded', array( $this, 'load_co_languages' ) );
	}

	function load_co_languages() {
		load_plugin_textdomain( 'culture-object', false, basename( __DIR__ ) . '/languages/' );
	}

	static function check_versions() {
		global $wp_version;
		$wp  = '4.5';
		$php = '7.3';

		if ( version_compare( PHP_VERSION, $php, '<' ) ) {
			$flag = 'PHP';
		} elseif ( version_compare( $wp_version, $wp, '<' ) ) {
			$flag = 'WordPress';
		} else {
			return;
		}
		$version = 'PHP' == $flag ? $php : $wp;
		deactivate_plugins( basename( __FILE__ ) );

		$error_type   = esc_html__( 'Plugin Activation Error', 'culture-object' );
		$error_string = sprintf(
			/* Translators: 1: Either WordPress or PHP, depending on the version mismatch 2: Required version number */
			esc_html__( 'Culture Object requires %1$s version %2$s or greater.', 'culture-object' ),
			$flag,
			$version
		);

		wp_die(
			'<p>' . wp_kses_post( $error_string ) . '</p>',
			wp_kses_post( $error_type ),
			array(
				'response'  => 200,
				'back_link' => true,
			)
		);
	}


	static function regenerate_permalinks() {
		flush_rewrite_rules();
	}

	function should_sync() {
		$cli_cron = false;
		if ( defined( 'CO_CLI_CRON' ) && CO_CLI_CRON ) {
			$cli_cron = true;
		}
		if ( $cli_cron || ( isset( $_GET['perform_culture_object_sync'] ) && isset( $_GET['key'] ) ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification is not needed here.
			if ( $cli_cron || ( get_option( 'cos_core_sync_key' ) == $_GET['key'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification is not needed here.
				$provider = $this->get_sync_provider();
				if ( $provider ) {
					if ( ! class_exists( $provider['class'] ) ) {
						include_once $provider['file'];
					}
					$provider_class = new $provider['class']();
					$info           = $provider_class->get_provider_information();

					if ( ! $info['cron'] ) {
						die(
							sprintf(
								/* Translators: %s: is the name of the provider. */
								esc_html__( 'Culture Object provider (%s) does not support automated sync.', 'culture-object' ),
								esc_html( $info['name'] )
							)
						);
					}

					try {
						$provider_class->perform_sync();
					} catch ( Exception\ProviderException $e ) {
						echo esc_html__( 'A sync exception occurred during sync', 'culture-object' ) . ':<br />';
						echo wp_kses_post( $e->getMessage() );
					} catch ( Exception\Exception $e ) {
						echo esc_html__( 'An unknown exception occurred during sync', 'culture-object' ) . ':<br />';
						echo wp_kses_post( $e->getMessage() );
					}
					die( 'Sync Complete' . PHP_EOL );
				}
			}
		}
	}

	function should_ajax_sync() {

		if ( isset( $_POST['key'] ) ) {
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( wp_verify_nonce( $nonce, 'cos_ajax_import_request' ) ) {
				$sync_key = sanitize_text_field( wp_unslash( $_POST['key'] ) );
				if ( get_option( 'cos_core_sync_key' ) === $sync_key ) {
					$provider = $this->get_sync_provider();
					if ( $provider ) {
						if ( ! class_exists( $provider['class'] ) ) {
							include_once $provider['file'];
						}
						$provider_class = new $provider['class']();
						$info           = $provider_class->get_provider_information();

						if ( ! $info['ajax'] ) {
							die(
								sprintf(
								/* Translators: %s: is the name of the provider. */
									esc_html__( 'Culture Object provider (%s) does not support AJAX sync.', 'culture-object' ),
									esc_html( $info['name'] )
								)
							);
						}

						try {
							$result = $provider_class->perform_ajax_sync();
							echo wp_json_encode( $result );
							wp_die();
						} catch ( Exception\ProviderException $e ) {
							$result            = array();
							$result['state']   = 'error';
							$result['message'] = esc_html__( 'A sync exception occurred during sync', 'culture-object' );
							$result['detail']  = esc_html( $e->getMessage() );
							echo wp_json_encode( $result );
							wp_die();
						} catch ( Exception\Exception $e ) {
							$result            = array();
							$result['state']   = 'error';
							$result['message'] = esc_html__( 'An unknown exception occurred during sync', 'culture-object' );
							$result['detail']  = esc_html( $e->getMessage() );
							echo wp_json_encode( $result );
							wp_die();
						}
					}
				} else {
					$result            = array();
					$result['state']   = 'error';
					$result['message'] = esc_html__( 'Security Violation', 'culture-object' );
					$result['detail']  = esc_html__( 'Invalid Sync Key', 'culture-object' );
					echo wp_json_encode( $result );
					wp_die();
				}
			} else {
				$result            = array();
				$result['state']   = 'error';
				$result['message'] = esc_html__( 'Security Violation', 'culture-object' );
				$result['detail']  = esc_html__( 'Nonce verification failed: ', 'culture-object' ) . esc_html( $nonce );
				echo wp_json_encode( $result );
				wp_die();
			}
		}

		$result            = array();
		$result['state']   = 'error';
		$result['message'] = esc_html__( 'An unknown error occurred during AJAX sync', 'culture-object' );
		$result['detail']  = esc_html__( 'An unknown error occurred during AJAX sync', 'culture-object' );
		echo wp_json_encode( $result );
		wp_die();
	}

	function purge_objects() {
		if ( is_admin() && isset( $_GET['perform_cos_debug_purge'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification is not needed here.
			$all_objects = get_posts(
				array(
					'post_status'    => 'any',
					'post_type'      => 'object',
					'posts_per_page' => -1,
				)
			);
			foreach ( $all_objects as $obj ) {
				wp_delete_post( $obj->ID, true );
			}
			wp_die( esc_html__( 'Deleted all COS objects.', 'culture-object' ) );
		}
	}

	function wordpress_init() {

		register_post_type(
			'object',
			array(
				'labels'       => array(
					'name'               => esc_html__( 'Objects', 'culture-object' ),
					'singular_name'      => esc_html__( 'Object', 'culture-object' ),
					'add_new_item'       => esc_html__( 'Add new object', 'culture-object' ),
					'edit_item'          => esc_html__( 'Edit object', 'culture-object' ),
					'new_item'           => esc_html__( 'New object', 'culture-object' ),
					'view_item'          => esc_html__( 'View object', 'culture-object' ),
					'search_items'       => esc_html__( 'Search objects', 'culture-object' ),
					'not_found'          => esc_html__( 'No objects found', 'culture-object' ),
					'not_found_in_trash' => esc_html__( 'No objects found in the trash', 'culture-object' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-list-view',
				'supports'     => array( 'title', 'custom-fields', 'thumbnail' ),
				'rewrite'      => array(
					'slug'       => apply_filters( 'co_object_slug ', 'object' ),
					'with_front' => false,
				),
			)
		);

		add_filter( 'post_updated_messages', array( $this, 'object_updated_messages' ) );
	}


	function object_updated_messages( $messages ) {
		global $post, $post_ID;

		$messages['object'] = array(
			0  => '',
			1  => sprintf( esc_html__( 'Object updated.', 'culture-object' ) . ' <a href="%s">' . esc_html__( 'View object', 'culture-object' ) . '</a>', esc_url( get_permalink( $post_ID ) ) ),
			2  => esc_html__( 'Custom field updated.', 'culture-object' ),
			3  => esc_html__( 'Custom field deleted.', 'culture-object' ),
			4  => esc_html__( 'Object updated.', 'culture-object' ),
			5  => isset( $_GET['revision'] ) ? sprintf( esc_html__( 'Object restored to revision from %s', 'culture-object' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false, //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification is not needed here.
			6  => sprintf( esc_html__( 'Object published.', 'culture-object' ) . ' <a href="%s">' . esc_html__( 'View object', 'culture-object' ) . '</a>', esc_url( get_permalink( $post_ID ) ) ),
			7  => esc_html__( 'Object saved.', 'culture-object' ),
			8  => sprintf( esc_html__( 'Object submitted.', 'culture-object' ) . ' <a target="_blank" href="%s">' . esc_html__( 'Preview object', 'culture-object' ) . '</a>', esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
			9  => sprintf( esc_html__( 'Object scheduled for', 'culture-object' ) . ': <strong>%1$s</strong>. <a target="_blank" href="%2$s">' . esc_html__( 'Preview object', 'culture-object' ) . '</a>', date_i18n( esc_html__( 'M j, Y @ G:i', 'culture-object' ), strtotime( $post->post_date ) ), esc_url( get_permalink( $post_ID ) ) ),
			10 => sprintf( esc_html__( 'Object draft updated.', 'culture-object' ) . ' <a target="_blank" href="%s">' . esc_html__( 'Preview object', 'culture-object' ) . '</a>', esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
		);

		return $messages;
	}
}
