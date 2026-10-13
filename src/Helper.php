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
	 * Builds a safe destination filename for a provider upload. The extension is forced to one of
	 * the allowed values rather than trusted from the client.
	 *
	 * @param string $original_name   Client supplied file name.
	 * @param string $upload_dir      Destination directory.
	 * @param array  $allowed_exts    Allowed extensions, the first is the default.
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

	function add_image_to_gallery_from_url( $url, $save_as, $stream_context = false, $post_parent = 0, $image_title = false ) {
		// Image URLs come from imported data, so only ever fetch over http(s) (not file://, php://, etc).
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$img        = @file_get_contents( $url, false, $stream_context ); //phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
		if ( $img ) {
			$response_headers = function_exists( 'http_get_last_response_headers' ) ? http_get_last_response_headers() : ( $http_response_header ?? array() );
			if ( ! empty( $response_headers ) ) {
				foreach ( $response_headers as $header ) {
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
				$ext     = explode( '|', $ext )[0];
				$save_as = sanitize_file_name( pathinfo( $save_as, PATHINFO_FILENAME ) . '.' . $ext );
				$filetype = wp_check_filetype( $save_as );
				if ( empty( $filetype['type'] ) ) {
					return false;
				}
			}

			$save_as       = wp_unique_filename( $upload_dir['path'], $save_as );
			$file_location = $upload_dir['path'] . '/' . $save_as;
			file_put_contents( $file_location, $img ); //phpcs:ignore WordPress.WP.AlternativeFunctions

			$image_title = $image_title ?: preg_replace( '/\.[^.]+$/', '', basename( $file_location ) );

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
