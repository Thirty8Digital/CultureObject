<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class CSV2Exception extends \CultureObject\Exception\ProviderException {

}

class CSV2 extends \CultureObject\Provider {


	private $provider = array(
		'name'            => 'CSV2',
		'version'         => '4.1',
		'developer'       => 'Thirty8 Digital',
		'cron'            => false,
		'supports_remap'  => true,
		'supports_images' => true,
		'no_options'      => true,
		'ajax'            => true,
	);

	function __construct() {
		require 'vendor/autoload.php';
	}

	function register_remappable_fields() {
		$path = $this->has_uploaded_file();
		if ( $path ) {
			if ( is_file( $path ) ) {
				$headers = $this->get_csv_chunk( $path, 1, 0 );
				$headers = $headers[0];
				$return  = array();
				foreach ( $headers as $header ) {
					$return[ $header ] = $header;
				}
				return $return;
			} else {
				return array();
			}
		} else {
			return array();
		}
	}

	function execute_init_action() {
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'add_provider_assets' ) );
		}

		$this->register_taxonomy();
	}

	function register_taxonomy() {
		$taxonomy_field = get_option( 'cos_csv2_taxonomy_field' );
		$path           = $this->has_uploaded_file();
		if ( ( $path ) && ( intval( $taxonomy_field ) == $taxonomy_field ) && intval( $taxonomy_field ) >= 0 ) {
			$headers        = $this->get_csv_chunk( $path, 1, 0 );
			$taxonomy_field = intval( $taxonomy_field );
			$taxonomy_name  = $headers[0][ $taxonomy_field ];
			$taxonomy       = substr( sanitize_title( $taxonomy_name, 'object_taxonomy' ), 0, 32 );

			register_taxonomy( $taxonomy, 'object', array( 'label' => $taxonomy_name ) );
		}
	}

	function get_taxonomy_name() {
		$taxonomy_field = get_option( 'cos_csv2_taxonomy_field' );
		$path           = $this->has_uploaded_file();
		if ( ( $path ) && ( intval( $taxonomy_field ) == $taxonomy_field ) && intval( $taxonomy_field ) >= 0 ) {
			$headers        = $this->get_csv_chunk( $path, 1, 0 );
			$taxonomy_field = intval( $taxonomy_field );
			$taxonomy_name  = $headers[0][ $taxonomy_field ];
			return substr( sanitize_title( $taxonomy_name, 'object_taxonomy' ), 0, 32 );
		} else {
			return false;
		}
	}

	function add_provider_assets() {
		$screen = get_current_screen();
		if ( $screen->base != 'culture-object_page_cos_provider_settings' ) {
			return;
		}
		$js_url = plugins_url( '/assets/admin.js?nc=' . time(), __FILE__ );
		wp_enqueue_script( 'jquery-ui' );
		wp_enqueue_script( 'jquery-ui-core' );
		wp_register_script( 'csv2_admin_js', $js_url, array( 'jquery', 'jquery-ui-core', 'jquery-ui-progressbar' ), '1.0.0', true );
		wp_enqueue_script( 'csv2_admin_js' );

		wp_enqueue_style( 'jquery-ui-css', plugin_dir_url( __FILE__ ) . 'assets/jquery-ui-fresh.css', array(), $this->provider['version'] );
		wp_enqueue_style( 'csv2_admin_css', plugin_dir_url( __FILE__ ) . 'assets/admin.css?nc=' . time(), array(), $this->provider['version'] );
	}

	function get_provider_information() {
		return $this->provider;
	}

	function execute_load_action() {
		if ( isset( $_FILES['cos_csv_import_file'] ) && isset( $_POST['cos_csv_nonce'] ) ) {
			if ( wp_verify_nonce( $_POST['cos_csv_nonce'], 'cos_csv_import' ) && current_user_can( 'upload_files' ) ) {
				try {
					$this->save_upload();
				} catch ( Exception $e ) {
					wp_die(
						sprintf(
							/* Translators: 1: Mime type of uploaded file */
							esc_html__( 'Unable to save file: %1$s', 'culture-object' ),
							esc_html( $e->getMessage() )
						)
					);
				}
			} else {
				wp_die( esc_html__( 'Security Violation.', 'culture-object' ) );
			}
		}
		if ( isset( $_POST['delete_uploaded_file'] ) && isset( $_POST['cos_csv_nonce'] ) ) {
			if ( wp_verify_nonce( $_POST['cos_csv_nonce'], 'cos_csv_delete_uploaded_file' ) ) {
				$this->delete_uploaded_file();
			} else {
				wp_die( esc_html__( 'Security Violation.', 'culture-object' ) );
			}
		}
	}

	function register_settings() {
	}

	function generate_settings_outside_form_html() {

		$this->output_js_localization();

		echo '<h3>' . esc_html__( 'Provider Settings', 'culture-object' ) . '</h3>';

		echo '<p>';
		echo esc_html(
			sprintf(
			/* Translators: 1: Provider Plugin Version 2: Provider Name 3: Provider Developer */
				__( 'You\'re currently using version %1$s of the %2$s sync provider by %3$s.', 'culture-object' ),
				$this->provider['version'],
				$this->provider['name'],
				$this->provider['developer']
			)
		);
		echo '</p>';

		$path = $this->has_uploaded_file();
		if ( $path ) {
			$this->parse_uploaded_file( $path );
		} else {
			$this->display_upload_prompt();
		}
	}

	function parse_uploaded_file( $path ) {
		if ( ! is_file( $path ) ) {
			throw new CSV2Exception( esc_html__( 'An error occurred when trying to parse the CSV.', 'culture-object' ) );
		}

		$headers = $this->get_csv_chunk( $path, 1, 0 );
		$data    = $this->get_csv_data( $path );

		echo '<div id="hide-on-import">';

		echo '<p>';
		echo esc_html(
			sprintf(
			/* Translators: 1: CSV File Name 2: Number of columns in the CSV 3: Number of rows in the CSV */
				__( 'Your uploaded CSV "%1$s" contains %2$d columns and %3$d rows.', 'culture-object' ),
				basename( $path ),
				$data[0]['totalColumns'],
				$data[0]['totalRows']
			)
		);
		echo ' <a id="delete_uploaded_csv" href="#">';
		esc_html_e( 'Remove uploaded CSV?', 'culture-object' );
		echo '</a>';
		echo '</p>';

		$import_images = get_option( 'cos_core_import_images' );

		if ( ! $import_images ) {
			echo '<p style="font-weight:bold">' . esc_html__( 'If your CSV contains an image URL, you can automatically import them by enabling image importing in Main Settings.', 'culture-object' ) . '</p>';
		}

		echo '<p>' . esc_html__( 'To begin the import, click the button below.', 'culture-object' ) . '</p>';

		$id_field = get_option( 'cos_csv2_id_field' );

		echo '<div class="select_field">';
		echo '<select id="id_field">';
		echo '<option value="0">' . esc_attr__( '-- Object ID Field --', 'culture-object' ) . '</object>';
		foreach ( $headers[0] as $key => $header ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( trim( $id_field ), $key, false ) . '>' . esc_html( $header ) . '</option>';
		}
		echo '</select>';
		echo '<span class="description"> ';
		esc_attr_e( 'Select the column that contains the unique ID for the objects in your dataset', 'culture-object' );
		echo '</span>';
		echo '</div>';

		$title_field = get_option( 'cos_csv2_title_field' );

		echo '<div class="select_field">';
		echo '<select id="title_field">';
		echo '<option value="0">' . esc_attr__( '-- Object Title Field --', 'culture-object' ) . '</object>';
		foreach ( $headers[0] as $key => $header ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( trim( $title_field ), $key, false ) . '>' . esc_html( $header ) . '</option>';
		}
		echo '</select>';
		echo '<span class="description"> ';
		esc_attr_e( 'Select the column that contains the title for the objects in your dataset', 'culture-object' );
		echo '</span>';
		echo '</div>';

		if ( $import_images ) {
			$image_field = get_option( 'cos_csv2_image_field' );

			echo '<div class="select_field">';
			echo '<select id="image_field">';
			echo '<option value="-1">' . esc_attr__( '-- Don\'t Import Images --', 'culture-object' ) . '</object>';
			foreach ( $headers[0] as $key => $header ) {
				echo '<option value="' . esc_attr( $key ) . '" ' . selected( $image_field, $key, false ) . '>' . esc_html( $header ) . '</option>';
			}
			echo '</select>';
			echo '<span class="description"> ';
			esc_attr_e( 'If your CSV contains a URL to an image for each object, select that column to import it to the WordPress Media Library', 'culture-object' );
			echo '</span>';
			echo '</div><br />';
		}

		$taxonomy_field = get_option( 'cos_csv2_taxonomy_field' );

		echo '<div class="select_field">';
		echo '<select id="taxonomy_field">';
		echo '<option value="-1">' . esc_attr__( '-- Don\'t Import A Taxonomy --', 'culture-object' ) . '</object>';
		foreach ( $headers[0] as $key => $header ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $taxonomy_field, $key, false ) . '>' . esc_html( $header ) . '</option>';
		}
		echo '</select>';
		echo '<span class="description"> ';
		esc_attr_e( 'If your CSV contains a taxonomy column, select that column to import it as a taxonomy. The column header will be used as the taxonomy name.', 'culture-object' );
		echo '</span>';
		echo '</div><br />';

		echo '<fieldset>
            	<label for="perform_cleanup">
            		<input name="perform_cleanup" type="checkbox" id="perform_cleanup" value="1" />
            		<span>' . esc_attr__( 'Delete existing objects not in this import?', 'culture-object' ) . '</span>
            	</label>
            </fieldset>';

		echo '<input id="csv_perform_ajax_import" data-import-id="' . esc_attr( uniqid( '', true ) ) . '" data-sync-key="' . esc_attr( get_option( 'cos_core_sync_key' ) ) . '" data-starting-nonce="' . wp_create_nonce( 'cos_ajax_import_request' ) . '" type="button" class="button button-primary" value="';
		esc_html_e( 'Process Import', 'culture-object' );
		echo '" />';

		echo '</div>';

		echo '<div id="csv_import_progressbar"><div class="progress-label">' . esc_html__( 'Starting Import...', 'culture-object' ) . '</div></div>';
		echo '<div id="csv_import_detail"></div>';

		echo '<form id="delete_uploaded_csv_form" method="post" action="">';
		echo '<input type="hidden" name="cos_csv_nonce" value="' . esc_attr( wp_create_nonce( 'cos_csv_delete_uploaded_file' ) ) . '" /><br /><br />';
		echo '<input type="hidden" name="delete_uploaded_file" value="true" />';
		echo '</form>';
	}

	function get_csv_data( $path ) {
		$object_reader = IOFactory::createReader( 'Csv' );
		return $object_reader->listWorksheetInfo( $path );
	}

	function get_csv_chunk( $path, $start, $count = 5 ) {
		$object_reader = IOFactory::createReader( 'Csv' );
		$chunk_filter  = new chunkReadFilter();
		$object_reader->setReadFilter( $chunk_filter )->setContiguous( true );

		$chunk_filter->setRows( $start + 1, $count );
		$csv       = $object_reader->load( $path );
		$worksheet = $csv->getActiveSheet();

		return $worksheet->toArray();
	}

	function perform_ajax_sync() {
		if ( ! isset( $_POST['start'] ) || ! isset( $_POST['import_id'] ) ) {
			throw new CSV2Exception( esc_html__( 'Invalid AJAX import request', 'culture-object' ) );
		}

		$start     = $_POST['start'];
		$import_id = $_POST['import_id'];

		if ( $start == 'cleanup' ) {
			ini_set( 'memory_limit', '2048M' );

			$objects        = get_option( 'cos_csv2_import_' . $import_id, array() );
			$previous_posts = $this->get_current_object_ids();
			delete_option( 'cos_csv2_import_' . $import_id, array() );
			return $this->clean_objects( $objects, $previous_posts );
		} else {
			if ( ! isset( $_POST['id_field'] ) || ! isset( $_POST['title_field'] ) ) {
				throw new CSV2Exception( __( 'Invalid AJAX import request', 'culture-object' ) );
			}
			$id_field    = intval( $_POST['id_field'] );
			$title_field = intval( $_POST['title_field'] );

			if ( ! empty( $_POST['image_field'] ) && intval( $_POST['image_field'] ) >= 0 ) {
				$image_field = intval( $_POST['image_field'] );
				update_option( 'cos_csv2_image_field', $image_field );
			} else {
				$image_field = false;
				update_option( 'cos_csv2_image_field', -1 );
			}

			if ( ! empty( $_POST['taxonomy_field'] ) && intval( $_POST['taxonomy_field'] ) >= 0 ) {
				$taxonomy_field = intval( $_POST['taxonomy_field'] );
				update_option( 'cos_csv2_taxonomy_field', $taxonomy_field );
				$this->register_taxonomy();
			} else {
				$taxonomy_field = false;
				update_option( 'cos_csv2_taxonomy_field', -1 );
			}

			update_option( 'cos_csv2_id_field', $id_field );
			update_option( 'cos_csv2_title_field', $title_field );

			$cleanup = isset( $_POST['perform_cleanup'] ) && $_POST['perform_cleanup'];

			$count = 50;
			$path  = $this->has_uploaded_file();
			if ( $path ) {
				$info                 = $this->get_csv_data( $path );
				$data                 = $this->get_csv_chunk( $path, $start, $count );
				$result               = $this->import_chunk( $data, $id_field, $title_field, $image_field, $taxonomy_field );
				$result['total_rows'] = $info[0]['totalRows'];
				if ( $start + $count < $result['total_rows'] ) {
					$result['next_start'] = $start + $count;
					$result['complete']   = false;
					$result['percentage'] = round( ( 100 / $info[0]['totalRows'] ) * ( $start + $count ) );
				} else {
					$result['complete']   = true;
					$result['percentage'] = 100;
				}
				$result['next_nonce'] = wp_create_nonce( 'cos_ajax_import_request' );

				if ( $cleanup ) {
					$objects = get_option( 'cos_csv2_import_' . $import_id, array() );
					update_option( 'cos_csv2_import_' . $import_id, array_merge( $objects, $result['chunk_objects'] ) );
				}

				return $result;
			} else {
				throw new CSV2Exception( esc_html__( 'Attempted to import without a file uploaded.', 'culture-object' ) );
			}
		}
	}

	function import_chunk( $data, $id_field = 0, $title_field = 0, $image_field = false, $taxonomy_field = false ) {
		$data_array      = array();
		$current_objects = array();
		$updated         = 0;
		$created         = 0;

		$helper = new CultureObject\Helper();

		$import_images = get_option( 'cos_core_import_images' );

		$context = stream_context_create(
			array(
				'http' => array(
					'follow_location' => true,
				),
			)
		);

		$fields           = array_shift( $data );
		$number_of_fields = count( $fields );

		$taxonomy_name = $this->get_taxonomy_name();

		foreach ( $data as $new_row ) {
			if ( empty( $new_row[0] ) ) {
				continue;
			}
			if ( count( $new_row ) > $number_of_fields ) {
				throw new CSV2Exception(
					sprintf(
					/* Translators: 1: A row number from the CSV 2: The number of fields in that row 3: The number of fields defined by the first row. */
						esc_html__( 'Row %1$s of this CSV file contains %2$s fields, but the field keys only provides names for %3$s.\r\nTo prevent something bad happening, we\'re bailing on this import.', 'culture-object' ),
						count( $data_array ) + 2,
						count( $new_row ),
						intval( $number_of_fields )
					)
				);
			}
			$data_array[] = $new_row;
		}

		$number_of_objects = count( $data_array );
		$fields[]          = '_cos_object_id';

		if ( $number_of_objects > 0 ) {
			foreach ( $data_array as $doc ) {
				$doc[] = $doc[ $id_field ];

				$object_exists = $this->object_exists( $doc[ $id_field ] );

				if ( ! $object_exists ) {
					$obj_id            = $this->create_object( $doc, $fields, $id_field, $title_field );
					$current_objects[] = $obj_id;
					$import_status[]   = __( 'Created object', 'culture-object' ) . ': ' . $doc[ $title_field ];
					++$created;
				} else {
					$obj_id            = $this->update_object( $doc, $fields, $id_field, $title_field );
					$current_objects[] = $obj_id;
					$import_status[]   = __( 'Updated object', 'culture-object' ) . ': ' . $doc[ $title_field ];
					++$updated;
				}

				if ( $taxonomy_field && $taxonomy_name ) {
					$taxonomies = explode( ',', $doc[ $taxonomy_field ] );
					wp_set_post_terms( $obj_id, $taxonomies, $taxonomy_name );
				}

				if ( $import_images && $image_field ) {
					$doc[ $image_field ] = trim( $doc[ $image_field ] );
					if ( has_post_thumbnail( $obj_id ) ) {
						$import_status[] = __( 'Skipping image import for object as it already has a post thumbnail', 'culture-object' );
					} elseif ( filter_var( $doc[ $image_field ], FILTER_VALIDATE_URL ) ) {

							// Trusted extensions
							$presumed_extension = pathinfo( $doc[ $image_field ], PATHINFO_EXTENSION );
							$trusted_extensions = array( 'jpg', 'png', 'gif', 'jpeg' );
							$ext                = false;

						if ( ! in_array( $presumed_extension, $trusted_extensions, true ) ) {
							// Try to parse the extension from content headers - if we don't think we can trust pathinfo.
							$headers = get_headers( $doc[ $image_field ], 1 );
							if ( isset( $headers['Content-Type'] ) ) {
								$ext = $this->mime2ext( $headers['Content-Type'] );
							}
						}
						if ( ! $ext ) {
							$ext = $presumed_extension;
						}

							$save_path = $obj_id . '.' . $ext;
							$image_id  = $helper->add_image_to_gallery_from_url( $doc[ $image_field ], $save_path, $context, $obj_id, trim( $doc[ $title_field ] ) );
						if ( $image_id ) {
							set_post_thumbnail( $obj_id, $image_id );
							$import_status[] = __( 'Downloaded and saved image', 'culture-object' ) . ': ' . $doc[ $title_field ] . ' [' . $image_id . ']';
						} else {
							$import_status[] = __( 'Failed to download image. Check it is accessible.', 'culture-object' );
						}
					} else {
						$import_status[] = __( 'Skipping image import for object as the supplied URL is invalid', 'culture-object' );
					}
				}
			}
		}

		$return                    = array();
		$return['chunk_objects']   = $current_objects;
		$return['import_status']   = $import_status;
		$return['processed_count'] = $number_of_objects;
		$return['created']         = $created;
		$return['updated']         = $updated;
		return $return;
	}

	function mime2ext( $mime ) {
		$mime_map = array(
			'video/3gpp2'                          => '3g2',
			'video/3gp'                            => '3gp',
			'video/3gpp'                           => '3gp',
			'application/x-compressed'             => '7zip',
			'audio/x-acc'                          => 'aac',
			'audio/ac3'                            => 'ac3',
			'application/postscript'               => 'ai',
			'audio/x-aiff'                         => 'aif',
			'audio/aiff'                           => 'aif',
			'audio/x-au'                           => 'au',
			'video/x-msvideo'                      => 'avi',
			'video/msvideo'                        => 'avi',
			'video/avi'                            => 'avi',
			'application/x-troff-msvideo'          => 'avi',
			'application/macbinary'                => 'bin',
			'application/mac-binary'               => 'bin',
			'application/x-binary'                 => 'bin',
			'application/x-macbinary'              => 'bin',
			'image/bmp'                            => 'bmp',
			'image/x-bmp'                          => 'bmp',
			'image/x-bitmap'                       => 'bmp',
			'image/x-xbitmap'                      => 'bmp',
			'image/x-win-bitmap'                   => 'bmp',
			'image/x-windows-bmp'                  => 'bmp',
			'image/ms-bmp'                         => 'bmp',
			'image/x-ms-bmp'                       => 'bmp',
			'application/bmp'                      => 'bmp',
			'application/x-bmp'                    => 'bmp',
			'application/x-win-bitmap'             => 'bmp',
			'application/cdr'                      => 'cdr',
			'application/coreldraw'                => 'cdr',
			'application/x-cdr'                    => 'cdr',
			'application/x-coreldraw'              => 'cdr',
			'image/cdr'                            => 'cdr',
			'image/x-cdr'                          => 'cdr',
			'zz-application/zz-winassoc-cdr'       => 'cdr',
			'application/mac-compactpro'           => 'cpt',
			'application/pkix-crl'                 => 'crl',
			'application/pkcs-crl'                 => 'crl',
			'application/x-x509-ca-cert'           => 'crt',
			'application/pkix-cert'                => 'crt',
			'text/css'                             => 'css',
			'text/x-comma-separated-values'        => 'csv',
			'text/comma-separated-values'          => 'csv',
			'application/vnd.msexcel'              => 'csv',
			'application/x-director'               => 'dcr',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
			'application/x-dvi'                    => 'dvi',
			'message/rfc822'                       => 'eml',
			'application/x-msdownload'             => 'exe',
			'video/x-f4v'                          => 'f4v',
			'audio/x-flac'                         => 'flac',
			'video/x-flv'                          => 'flv',
			'image/gif'                            => 'gif',
			'application/gpg-keys'                 => 'gpg',
			'application/x-gtar'                   => 'gtar',
			'application/x-gzip'                   => 'gzip',
			'application/mac-binhex40'             => 'hqx',
			'application/mac-binhex'               => 'hqx',
			'application/x-binhex40'               => 'hqx',
			'application/x-mac-binhex40'           => 'hqx',
			'text/html'                            => 'html',
			'image/x-icon'                         => 'ico',
			'image/x-ico'                          => 'ico',
			'image/vnd.microsoft.icon'             => 'ico',
			'text/calendar'                        => 'ics',
			'application/java-archive'             => 'jar',
			'application/x-java-application'       => 'jar',
			'application/x-jar'                    => 'jar',
			'image/jp2'                            => 'jp2',
			'video/mj2'                            => 'jp2',
			'image/jpx'                            => 'jp2',
			'image/jpm'                            => 'jp2',
			'image/jpeg'                           => 'jpeg',
			'image/pjpeg'                          => 'jpeg',
			'application/x-javascript'             => 'js',
			'application/json'                     => 'json',
			'text/json'                            => 'json',
			'application/vnd.google-earth.kml+xml' => 'kml',
			'application/vnd.google-earth.kmz'     => 'kmz',
			'text/x-log'                           => 'log',
			'audio/x-m4a'                          => 'm4a',
			'audio/mp4'                            => 'm4a',
			'application/vnd.mpegurl'              => 'm4u',
			'audio/midi'                           => 'mid',
			'application/vnd.mif'                  => 'mif',
			'video/quicktime'                      => 'mov',
			'video/x-sgi-movie'                    => 'movie',
			'audio/mpeg'                           => 'mp3',
			'audio/mpg'                            => 'mp3',
			'audio/mpeg3'                          => 'mp3',
			'audio/mp3'                            => 'mp3',
			'video/mp4'                            => 'mp4',
			'video/mpeg'                           => 'mpeg',
			'application/oda'                      => 'oda',
			'audio/ogg'                            => 'ogg',
			'video/ogg'                            => 'ogg',
			'application/ogg'                      => 'ogg',
			'font/otf'                             => 'otf',
			'application/x-pkcs10'                 => 'p10',
			'application/pkcs10'                   => 'p10',
			'application/x-pkcs12'                 => 'p12',
			'application/x-pkcs7-signature'        => 'p7a',
			'application/pkcs7-mime'               => 'p7c',
			'application/x-pkcs7-mime'             => 'p7c',
			'application/x-pkcs7-certreqresp'      => 'p7r',
			'application/pkcs7-signature'          => 'p7s',
			'application/pdf'                      => 'pdf',
			'application/octet-stream'             => 'pdf',
			'application/x-x509-user-cert'         => 'pem',
			'application/x-pem-file'               => 'pem',
			'application/pgp'                      => 'pgp',
			'application/x-httpd-php'              => 'php',
			'application/php'                      => 'php',
			'application/x-php'                    => 'php',
			'text/php'                             => 'php',
			'text/x-php'                           => 'php',
			'application/x-httpd-php-source'       => 'php',
			'image/png'                            => 'png',
			'image/x-png'                          => 'png',
			'application/powerpoint'               => 'ppt',
			'application/vnd.ms-powerpoint'        => 'ppt',
			'application/vnd.ms-office'            => 'ppt',
			'application/msword'                   => 'ppt',
			'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
			'application/x-photoshop'              => 'psd',
			'image/vnd.adobe.photoshop'            => 'psd',
			'audio/x-realaudio'                    => 'ra',
			'audio/x-pn-realaudio'                 => 'ram',
			'application/x-rar'                    => 'rar',
			'application/rar'                      => 'rar',
			'application/x-rar-compressed'         => 'rar',
			'audio/x-pn-realaudio-plugin'          => 'rpm',
			'application/x-pkcs7'                  => 'rsa',
			'text/rtf'                             => 'rtf',
			'text/richtext'                        => 'rtx',
			'video/vnd.rn-realvideo'               => 'rv',
			'application/x-stuffit'                => 'sit',
			'application/smil'                     => 'smil',
			'text/srt'                             => 'srt',
			'image/svg+xml'                        => 'svg',
			'application/x-shockwave-flash'        => 'swf',
			'application/x-tar'                    => 'tar',
			'application/x-gzip-compressed'        => 'tgz',
			'image/tiff'                           => 'tiff',
			'font/ttf'                             => 'ttf',
			'text/plain'                           => 'txt',
			'text/x-vcard'                         => 'vcf',
			'application/videolan'                 => 'vlc',
			'text/vtt'                             => 'vtt',
			'audio/x-wav'                          => 'wav',
			'audio/wave'                           => 'wav',
			'audio/wav'                            => 'wav',
			'application/wbxml'                    => 'wbxml',
			'video/webm'                           => 'webm',
			'image/webp'                           => 'webp',
			'audio/x-ms-wma'                       => 'wma',
			'application/wmlc'                     => 'wmlc',
			'video/x-ms-wmv'                       => 'wmv',
			'video/x-ms-asf'                       => 'wmv',
			'font/woff'                            => 'woff',
			'font/woff2'                           => 'woff2',
			'application/xhtml+xml'                => 'xhtml',
			'application/excel'                    => 'xl',
			'application/msexcel'                  => 'xls',
			'application/x-msexcel'                => 'xls',
			'application/x-ms-excel'               => 'xls',
			'application/x-excel'                  => 'xls',
			'application/x-dos_ms_excel'           => 'xls',
			'application/xls'                      => 'xls',
			'application/x-xls'                    => 'xls',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
			'application/vnd.ms-excel'             => 'xlsx',
			'application/xml'                      => 'xml',
			'text/xml'                             => 'xml',
			'text/xsl'                             => 'xsl',
			'application/xspf+xml'                 => 'xspf',
			'application/x-compress'               => 'z',
			'application/x-zip'                    => 'zip',
			'application/zip'                      => 'zip',
			'application/x-zip-compressed'         => 'zip',
			'application/s-compressed'             => 'zip',
			'multipart/x-zip'                      => 'zip',
			'text/x-scriptzsh'                     => 'zsh',
		);

		return isset( $mime_map[ $mime ] ) ? $mime_map[ $mime ] : false;
	}

	function output_js_localization() {
		echo '<script>
        strings = {};
        strings.uploading_please_wait = "' . esc_html__( 'Uploading... This may take some time...', 'culture-object' ) . '";
        strings.importing_please_wait = "' . esc_html__( 'Importing... This may take some time...', 'culture-object' ) . '";
        strings.imported = "' . esc_html__( 'Imported', 'culture-object' ) . '";
        strings.objects = "' . esc_html__( 'objects', 'culture-object' ) . '";
        strings.objects_imported = "' . esc_html__( 'objects imported', 'culture-object' ) . '";
        strings.objects_deleted = "' . esc_html__( 'objects deleted', 'culture-object' ) . '";
        strings.import_complete = "' . esc_html__( 'Import complete.', 'culture-object' ) . '";
        strings.performing_cleanup = "' . esc_html__( 'Performing cleanup, please wait... This can take a long time if you have deleted a lot of objects.', 'culture-object' ) . '";
        </script>';
	}

	function display_upload_prompt() {
		echo '<p>';
		esc_html_e( 'Select a CSV to upload', 'culture-object' );
		echo '</p>';

		echo '<form id="csv_import_form" method="post" action="" enctype="multipart/form-data">';
		echo '<input type="file" name="cos_csv_import_file" />';
		echo '<input type="hidden" name="cos_csv_nonce" value="' . esc_attr( wp_create_nonce( 'cos_csv_import' ) ) . '" /><br /><br />';
		echo '<input id="csv_import_submit" type="button" class="button button-primary" value="';
		esc_html_e( 'Upload CSV File', 'culture-object' );
		echo '" />';
		echo '</form>';
	}

	function save_upload() {

		$valid_file_types = array( 'text/csv', 'application/vnd.ms-excel' );

		$upload     = wp_upload_dir();
		$upload_dir = $upload['basedir'];
		$upload_dir = $upload_dir . '/culture-object/';
		if ( ! is_dir( $upload_dir ) ) {
			mkdir( $upload_dir, 0700 ); //phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		$file = $_FILES['cos_csv_import_file']; //phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified before this method is called.
		if ( $file['error'] !== 0 ) {
			throw new CSV2Exception(
				sprintf(
				/* Translators: %s: The numeric error code PHP reported for an upload failure */
					esc_html__( 'Unable to import. PHP reported an error code: %s', 'culture-object' ),
					esc_html( $file['error'] )
				)
			);
		}

		if ( ! in_array( $file['type'], $valid_file_types, true ) ) {
			throw new CSV2Exception( esc_html__( 'Unable to import. You didn\'t upload a CSV file', 'culture-object' ) );
		}

		$current_path = $this->has_uploaded_file();
		if ( $current_path && is_file( $current_path ) ) {
			wp_delete_file( $current_path );
			delete_option( 'cos_csv2_uploaded_file_path' );
		}

		$file_name = preg_replace( '/[^A-Za-z0-9_\-.]/', '_', $file['name'] );
		$full_path = $upload_dir . $file_name;
		if ( move_uploaded_file( $file['tmp_name'], $full_path ) ) {
			update_option( 'cos_csv2_uploaded_file_path', $full_path );
			return;
		} else {
			throw new CSV2Exception( esc_html__( 'Unable to import. Could not write file to uploads folder.', 'culture-object' ) );
		}
	}

	function delete_uploaded_file() {
		$path = get_option( 'cos_csv2_uploaded_file_path' );
		if ( is_file( $path ) ) {
			wp_delete_file( $path );
		}
		delete_option( 'cos_csv2_uploaded_file_path' );
	}

	function has_uploaded_file() {
		$path = get_option( 'cos_csv2_uploaded_file_path' );
		if ( $path ) {
			if ( is_file( $path ) ) {
				return $path;
			} else {
				delete_option( 'cos_csv2_uploaded_file_path' );
				return false;
			}
		}
		return false;
	}

	function generate_settings_field_input_text( $args ) {
		$field = $args['field'];
		$value = get_option( $field );
		printf( '<input type="text" name="%s" id="%s" value="%s" />', esc_attr( $field ), esc_attr( $field ), esc_attr( $value ) );
	}

	function create_object( $doc, $fields, $id_field, $title_field ) {
		$post    = array(
			'post_title'  => trim( $doc[ $title_field ] ),
			'post_type'   => 'object',
			'post_status' => 'publish',
			'post_name'   => trim( $doc[ $id_field ] ),
		);
		$post_id = wp_insert_post( $post );
		$this->update_object_meta( $post_id, $doc, $fields );
		return $post_id;
	}


	function update_object( $doc, $fields, $id_field, $title_field ) {
		$existing_id = $this->existing_object_id( $doc[ $id_field ] );
		$post        = array(
			'ID'          => $existing_id,
			'post_title'  => trim( $doc[ $title_field ] ),
			'post_name'   => trim( $doc[ $id_field ] ),
			'post_type'   => 'object',
			'post_status' => 'publish',
		);
		$post_id     = wp_update_post( $post );
		$this->update_object_meta( $post_id, $doc, $fields );
		return $post_id;
	}

	function update_object_meta( $post_id, $doc, $fields ) {
		foreach ( $fields as $key => $value ) {
			if ( ! empty( $value ) ) {
				update_post_meta( $post_id, trim( $value ), trim( $doc[ $key ] ) );
			}
		}
	}

	function object_exists( $id ) {

		$post = get_posts(
			array(
				'meta_key'   => '_cos_object_id',
				'meta_value' => $id,
				'post_type'  => 'object',
			)
		);
		return $post ? true : false;
	}

	function existing_object_id( $id ) {

		$post = get_posts(
			array(
				'meta_key'   => '_cos_object_id',
				'meta_value' => $id,
				'post_type'  => 'object',
			)
		);

		if ( empty( $post ) ) {
			throw new Exception( esc_html__( "Called existing_object_id for an object that doesn't exist. This is likely a bug in your provider plugin, but because it is probably unsafe to continue the import, it has been aborted.", 'culture-object' ) );
		}

		$post = array_shift( $post );
		return $post->ID;
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
				'CSV'
			);
			++$deleted;
		}

		set_transient( 'cos_csv_deleted', $import_delete, 0 );

		$return                   = array();
		$return['deleted_count']  = $deleted;
		$return['deleted_status'] = $import_delete;
		return $return;
	}

	function perform_sync() {
		throw new CSV2Exception( esc_html__( 'Only AJAX sync is supported for this provider.', 'culture-object' ) );
	}
}
