<?php

namespace CultureObject;

class Helper extends Core {


	function cos_get_remapped_field_name( $key ) {
		$field_remap = get_option( 'cos_remap_' . strtolower( $key ) );
		if ( isset( $field_remap ) && ! empty( $field_remap ) ) {
			return $field_remap;
		}
		$provider = $this->get_sync_provider();
		if ( $provider ) {
			if ( ! class_exists( $provider['class'] ) ) {
				include_once $provider['file'];
			}
			$provider_class = new $provider['class']();
			$info           = $provider_class->get_provider_information();
			if ( isset( $info['supports_remap'] ) && $info['supports_remap'] ) {
				if ( method_exists( $provider_class, 'register_remappable_fields' ) ) {
					$fields = $provider_class->register_remappable_fields();
					if ( isset( $fields[ $key ] ) ) {
						return $fields[ $key ];
					}
				}
			}
		}
		return $key;
	}

	function cos_remapped_field_name( $key ) {
		echo wp_kses_post( cos_get_remapped_field_name( $key ) );
	}

	/**
	 * Whether an imported field name may be written as post meta. Field names come from uploaded
	 * files or remote feeds, so WordPress' protected (underscore prefixed) keys are refused.
	 *
	 * @param string $key Meta key.
	 * @return boolean
	 */
	function is_safe_meta_key( $key ) {
		$key = (string) $key;
		if ( '_cos_object_id' === $key ) {
			return true;
		}
		return '' !== $key && ! is_protected_meta( $key, 'post' );
	}

	/**
	 * Returns a protected directory inside uploads for provider file uploads, creating it if needed.
	 * Direct web access is denied and the files are never served.
	 *
	 * @return string Absolute path with trailing slash.
	 */
	function get_protected_upload_dir() {
		$upload     = wp_upload_dir();
		$upload_dir = trailingslashit( $upload['basedir'] ) . 'culture-object/';
		if ( ! is_dir( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}
		if ( ! file_exists( $upload_dir . '.htaccess' ) ) {
			file_put_contents( $upload_dir . '.htaccess', "Require all denied\nDeny from all\n" ); //phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		if ( ! file_exists( $upload_dir . 'index.php' ) ) {
			file_put_contents( $upload_dir . 'index.php', "<?php\n// Silence is golden.\n" ); //phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		return $upload_dir;
	}

	/**
	 * Moves an uploaded file into the protected upload directory using WordPress' upload handling.
	 * The extension is forced to one of $allowed_exts; the client supplied name and type are never trusted.
	 *
	 * @param array $file         The $_FILES entry.
	 * @param array $allowed_exts Allowed extensions, the first is the default.
	 * @return string|false Absolute path of the stored file, or false on failure.
	 */
	function store_protected_upload( $file, $allowed_exts ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$upload_dir = $this->get_protected_upload_dir();
		$file_name  = $this->get_safe_upload_filename( $file['name'], $upload_dir, $allowed_exts );
		if ( false === $file_name ) {
			return false;
		}

		$to_protected_dir = function ( $uploads ) use ( $upload_dir ) {
			$base = untrailingslashit( $upload_dir );
			return array_merge(
				$uploads,
				array(
					'path'    => $base,
					'url'     => '',
					'subdir'  => '',
					'basedir' => $base,
					'baseurl' => '',
					'error'   => false,
				)
			);
		};

		add_filter( 'upload_dir', $to_protected_dir );
		$result = wp_handle_upload(
			$file,
			array(
				'test_form'                => false,
				'test_type'                => false, // The allowlisted extension above is the type check.
				'unique_filename_callback' => function () use ( $file_name ) {
					return $file_name;
				},
			)
		);
		remove_filter( 'upload_dir', $to_protected_dir );

		if ( ! is_array( $result ) || ! empty( $result['error'] ) || empty( $result['file'] ) ) {
			return false;
		}
		return $result['file'];
	}

	/**
	 * Builds a safe destination filename for a provider upload. The extension is forced to one of
	 * the allowed values rather than trusted from the client.
	 *
	 * @param string $original_name Client supplied file name.
	 * @param string $upload_dir    Destination directory.
	 * @param array  $allowed_exts  Allowed extensions, the first is the default.
	 * @return string|false File name, or false if the extension is not allowed.
	 */
	function get_safe_upload_filename( $original_name, $upload_dir, $allowed_exts ) {
		$original_name = basename( $original_name );
		$extension     = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );
		if ( '' !== $extension && ! in_array( $extension, $allowed_exts, true ) ) {
			return false;
		}
		if ( '' === $extension ) {
			$extension = $allowed_exts[0];
		}
		$name = preg_replace( '/[^A-Za-z0-9_\-]/', '_', pathinfo( $original_name, PATHINFO_FILENAME ) );
		if ( '' === $name ) {
			$name = 'uploaded_file';
		}
		return wp_unique_filename( $upload_dir, $name . '.' . $extension );
	}

	/**
	 * Downloads an image from a URL and adds it to the media library.
	 *
	 * @param string           $url            Image URL.
	 * @param string           $save_as        Suggested file name.
	 * @param resource|boolean $stream_context Optional stream context (e.g. for authenticated sources).
	 * @param integer          $post_parent    Parent post ID.
	 * @param string|boolean   $image_title    Attachment title.
	 * @param boolean          $untrusted_url  True when the URL comes from uploaded data. The download then uses
	 *                                         wp_safe_remote_get(), which refuses internal hosts, including via redirects.
	 * @return integer|false Attachment ID or false.
	 */
	function add_image_to_gallery_from_url( $url, $save_as, $stream_context = false, $post_parent = 0, $image_title = false, $untrusted_url = false ) {
		// Image URLs come from imported data, so only ever fetch over http(s) (not file://, php://, etc).
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return false;
		}

		$upload_dir       = wp_upload_dir();
		$response_headers = array();
		if ( $untrusted_url ) {
			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'             => 30,
					'redirection'         => 5,
					'limit_response_size' => 20 * MB_IN_BYTES,
				)
			);
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return false;
			}
			$img = wp_remote_retrieve_body( $response );
			if ( wp_remote_retrieve_header( $response, 'content-disposition' ) ) {
				$response_headers[] = 'content-disposition: ' . wp_remote_retrieve_header( $response, 'content-disposition' );
			}
		} else {
			$img              = @file_get_contents( $url, false, $stream_context ); //phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
			$response_headers = function_exists( 'http_get_last_response_headers' ) ? http_get_last_response_headers() : ( $http_response_header ?? array() );
		}
		if ( $img ) {
			if ( ! empty( $response_headers ) ) {
				foreach ( (array) $response_headers as $header ) {
					if ( stripos( $header, 'content-disposition' ) !== false && preg_match( '/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $header, $matches ) ) {
						$save_as = rawurldecode( $matches[1] );
					}
				}
			}

			// Never trust the remote or provider supplied name: strip paths, sanitise, and require an allowed image type.
			$save_as  = sanitize_file_name( basename( (string) $save_as ) );
			$filetype = wp_check_filetype( $save_as );
			if ( empty( $filetype['type'] ) || 0 !== strpos( $filetype['type'], 'image/' ) ) {
				// No usable image extension in the name; fall back to sniffing the downloaded bytes.
				$finfo = new \finfo( FILEINFO_MIME_TYPE );
				$mime  = $finfo->buffer( $img );
				$ext   = array_search( $mime, wp_get_mime_types(), true );
				if ( ! $ext || 0 !== strpos( $mime, 'image/' ) ) {
					return false;
				}
				$ext      = explode( '|', $ext )[0];
				$save_as  = sanitize_file_name( pathinfo( $save_as, PATHINFO_FILENAME ) . '.' . $ext );
				$filetype = wp_check_filetype( $save_as );
				if ( empty( $filetype['type'] ) ) {
					return false;
				}
			}

			$save_as       = wp_unique_filename( $upload_dir['path'], $save_as );
			$file_location = $upload_dir['path'] . '/' . $save_as;
			file_put_contents( $file_location, $img ); //phpcs:ignore WordPress.WP.AlternativeFunctions

			$image_title = $image_title ? $image_title : preg_replace( '/\.[^.]+$/', '', basename( $file_location ) );

			// Prepare an array of post data for the attachment.
			$attachment = array(
				'guid'           => $upload_dir['url'] . '/' . basename( $file_location ),
				'post_mime_type' => $filetype['type'],
				'post_title'     => $image_title,
				'post_content'   => '',
				'post_status'    => 'inherit',
			);

			// Insert the attachment.
			$attach_id = wp_insert_attachment( $attachment, $file_location, $post_parent );

			// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			// Generate the metadata for the attachment, and update the database record.
			$attach_data = wp_generate_attachment_metadata( $attach_id, $file_location );
			wp_update_attachment_metadata( $attach_id, $attach_data );

			return $attach_id;
		} else {
			return false;
		}
	}
}
