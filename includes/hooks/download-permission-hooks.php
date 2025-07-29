<?php

namespace StoreEngine\hooks;

use StoreEngine\Classes\DownloadPermission;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Helper;

class DownloadPermissionHooks {

	protected static ?DownloadPermissionHooks $instance = null;

	public static function init(): DownloadPermissionHooks {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected function __construct() {
		// storeengine/checkout/after_place_order -> $self -> check_if_paid_after_checkout
		// storeengine/order/status_changed -> $self -> check_if_paid_after_status_change
		add_action( 'storeengine/order/payment_status_changed', [ $this, 'set_download_permissions' ], 10, 2 );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['download_file'], $_GET['order'], $_GET['user'], $_GET['key'] ) ) {
			add_action( 'init', [ $this, 'download_product' ] );
		}
	}

	public function set_download_permissions( Order $order, $status ) {
		if ( 'paid' === $status ) {
			$this->give_permissions( $order );
		} else {
			$this->delete_permissions( $order );
		}
	}

	public function check_if_paid_after_checkout( $order ) {
		if ( in_array( $order->get_status(), Helper::get_order_paid_statuses(), true ) ) {
			$this->give_permissions( $order );
		}
	}

	public function check_if_paid_after_status_change( $order_id, $old_status, $new_status, $order ) {
		if ( ( in_array( $new_status, Helper::get_order_paid_statuses(), true ) && in_array( $old_status, Helper::get_order_paid_statuses(), true ) ) || ( ! in_array( $new_status, Helper::get_order_paid_statuses(), true ) && ! in_array( $old_status, Helper::get_order_paid_statuses(), true ) ) ) {
			return;
		}

		if ( ! in_array( $new_status, Helper::get_order_paid_statuses(), true ) && in_array( $old_status, Helper::get_order_paid_statuses(), true ) ) {
			$this->delete_permissions( $order );

			return;
		}

		if ( in_array( $new_status, Helper::get_order_paid_statuses(), true ) && ! in_array( $old_status, Helper::get_order_paid_statuses(), true ) ) {
			$this->give_permissions( $order );
		}
	}

	/**
	 * Check if we need to download a file and check validity.
	 */
	public function download_product() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$product_id        = absint( $_GET['download_file'] ); // phpcs:ignore WordPress.VIP.SuperGlobalInputUsage.AccessDetected, WordPress.VIP.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$product           = Helper::get_product( $product_id );
		$key               = empty( $_GET['key'] ) ? '' : sanitize_text_field( wp_unslash( $_GET['key'] ) );
		$downloadable_file = ! $product ? [] : array_filter( $product->get_downloadable_files(), fn( $download ) => $download['id'] === $key );
		$downloadable_file = reset( $downloadable_file );

		if ( ! $product || empty( $downloadable_file ) || empty( $key ) || ! isset( $_GET['order'], $_GET['user'] ) ) {
			self::download_error( __( 'Invalid download link.', 'storeengine' ) );
		}
		$email     = sanitize_email( wp_unslash( $_GET['user'] ) );
		$order_key = sanitize_text_field( wp_unslash( $_GET['order'] ) );
		$order     = Helper::get_order_by_key( $order_key );
		if ( ! $order || is_wp_error( $order ) || ! in_array( $order->get_status(), Helper::get_order_paid_statuses(), true ) ) {
			self::download_error( __( 'Invalid download link.', 'storeengine' ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( is_user_logged_in() ) {
			$user = get_user( get_current_user_id() );
			if ( ! $user || ! $user->exists() || $user->ID !== $order->get_customer_id() ) {
				self::download_error( __( 'Invalid download link.', 'storeengine' ) );
			}
		} else {
			$email_hash = function_exists( 'hash' ) ? hash( 'sha256', $order->get_billing_email() ) : sha1( $order->get_billing_email() );
			if ( ! hash_equals( $email, $email_hash ) ) {
				self::download_error( __( 'Invalid download link.', 'storeengine' ) );
			}
		}

		// @TODO: track download.
		self::download_file_force( $downloadable_file['file'], basename( $downloadable_file['file'] ) );
	}

	/**
	 * Force download - this is the default method.
	 *
	 * @param string $file_path File path.
	 * @param string $filename File name.
	 */
	public function download_file_force( string $file_path, string $filename ) {
		$download_range = $this->get_download_range( @filesize( $file_path ) ); // @codingStandardsIgnoreLine.

		$this->download_headers( $file_path, $filename, $download_range );

		$start  = isset( $download_range['start'] ) ? $download_range['start'] : 0;
		$length = isset( $download_range['length'] ) ? $download_range['length'] : 0;
		if ( ! self::readfile_chunked( $file_path, $start, $length ) ) {
			$this->download_error( __( 'File not found', 'storeengine' ) );
		}

		exit;
	}

	/**
	 * Read file chunked.
	 *
	 * Reads file in chunks so big downloads are possible without changing PHP.INI - http://codeigniter.com/wiki/Download_helper_for_large_files/.
	 *
	 * @param string $file File.
	 * @param int $start Byte offset/position of the beginning from which to read from the file.
	 * @param int $length Length of the chunk to be read from the file in bytes, 0 means full file.
	 *
	 * @return bool Success or fail
	 */
	protected function readfile_chunked( $file, $start = 0, $length = 0 ) {
		// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
		$handle = @fopen( $file, 'r' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_fopen

		if ( false === $handle ) {
			return false;
		}

		if ( ! $length ) {
			$length = @filesize( $file ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		}

		$read_length = 1024 * 1024;

		if ( $length ) {
			$end = $start + $length - 1;

			@fseek( $handle, $start ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			$p = @ftell( $handle ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged

			while ( ! @feof( $handle ) && $p <= $end ) { // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
				// Don't run past the end of file.
				if ( $p + $read_length > $end ) {
					$read_length = $end - $p + 1;
				}

				echo @fread( $handle, $read_length ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_read_fread
				$p = @ftell( $handle ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged

				if ( ob_get_length() ) {
					ob_flush();
					flush();
				}
			}
		} else {
			while ( ! @feof( $handle ) ) { // @codingStandardsIgnoreLine.
				// phpcs:ignore
				echo @fread( $handle, $read_length ); // @codingStandardsIgnoreLine.
				if ( ob_get_length() ) {
					ob_flush();
					flush();
				}
			}
		}

		return @fclose( $handle ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_fclose
		// phpcs:enable WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Set headers for the download.
	 *
	 * @param string $file_path File path.
	 * @param string $filename File name.
	 * @param array $download_range Array containing info about range download request (see {@see get_download_range} for structure).
	 */
	private function download_headers( $file_path, $filename, $download_range = array() ) {
		// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.Risky
		if ( function_exists( 'set_time_limit' ) && false === strpos( ini_get( 'disable_functions' ), 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) { // phpcs:ignore PHPCompatibility.IniDirectives.RemovedIniDirectives.safe_modeDeprecatedRemoved
			@set_time_limit( 0 ); // @codingStandardsIgnoreLine
		}
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', 1 ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_apache_setenv
		}
		@ini_set( 'zlib.output_compression', 'Off' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_ini_set
		@session_write_close(); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.VIP.SessionFunctionsUsage.session_session_write_close

		if ( ob_get_level() ) {
			$levels = ob_get_level();
			for ( $i = 0; $i < $levels; $i ++ ) {
				@ob_end_clean();
			}
		} else {
			@ob_end_clean();
		}

		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Content-Type: ' . $this->get_download_content_type( $file_path ) );
		header( 'Content-Description: File Transfer' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '";' );
		header( 'Content-Transfer-Encoding: binary' );

		$file_size = @filesize( $file_path );
		if ( ! $file_size ) {
			return;
		}

		if ( isset( $download_range['is_range_request'] ) && true === $download_range['is_range_request'] ) {
			if ( false === $download_range['is_range_valid'] ) {
				header( 'HTTP/1.1 416 Requested Range Not Satisfiable' );
				header( 'Content-Range: bytes 0-' . ( $file_size - 1 ) . '/' . $file_size );
				exit;
			}

			$start  = $download_range['start'];
			$end    = $download_range['start'] + $download_range['length'] - 1;
			$length = $download_range['length'];

			header( 'HTTP/1.1 206 Partial Content' );
			header( "Accept-Ranges: 0-$file_size" );
			header( "Content-Range: bytes $start-$end/$file_size" );
			header( "Content-Length: $length" );
		} else {
			header( 'Content-Length: ' . $file_size );
		}
		// phpcs:enable WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.Risky
	}

	/**
	 * Get content type of a download.
	 *
	 * @param string $file_path File path.
	 *
	 * @return string
	 */
	private static function get_download_content_type( $file_path ) {
		$file_extension = strtolower( substr( strrchr( $file_path, '.' ), 1 ) );
		$ctype          = 'application/force-download';

		foreach ( get_allowed_mime_types() as $mime => $type ) {
			$mimes = explode( '|', $mime );
			if ( in_array( $file_extension, $mimes, true ) ) {
				$ctype = $type;
				break;
			}
		}

		return $ctype;
	}

	/**
	 * Parse the HTTP_RANGE request from iOS devices.
	 * Does not support multi-range requests.
	 *
	 * @param int $file_size Size of file in bytes.
	 *
	 * @return array {
	 *     Information about range download request: beginning and length of
	 *     file chunk, whether the range is valid/supported and whether the request is a range request.
	 *
	 * @type int $start Byte offset of the beginning of the range. Default 0.
	 * @type int $length Length of the requested file chunk in bytes. Optional.
	 * @type bool $is_range_valid Whether the requested range is a valid and supported range.
	 * @type bool $is_range_request Whether the request is a range request.
	 * }
	 */
	protected function get_download_range( int $file_size ): array {
		$start          = 0;
		$download_range = array(
			'start'            => $start,
			'is_range_valid'   => false,
			'is_range_request' => false,
		);

		if ( ! $file_size ) {
			return $download_range;
		}

		$end                      = $file_size - 1;
		$download_range['length'] = $file_size;

		if ( isset( $_SERVER['HTTP_RANGE'] ) ) { // @codingStandardsIgnoreLine.
			$http_range                         = sanitize_text_field( wp_unslash( $_SERVER['HTTP_RANGE'] ) ); // WPCS: input var ok.
			$download_range['is_range_request'] = true;

			$c_start = $start;
			$c_end   = $end;
			// Extract the range string.
			list( , $range ) = explode( '=', $http_range, 2 );
			// Make sure the client hasn't sent us a multibyte range.
			if ( strpos( $range, ',' ) !== false ) {
				return $download_range;
			}

			/*
			 * If the range starts with an '-' we start from the beginning.
			 * If not, we forward the file pointer
			 * and make sure to get the end byte if specified.
			 */
			if ( '-' === $range[0] ) {
				// The n-number of the last bytes is requested.
				$c_start = $file_size - substr( $range, 1 );
			} else {
				$range   = explode( '-', $range );
				$c_start = ( isset( $range[0] ) && is_numeric( $range[0] ) ) ? (int) $range[0] : 0;
				$c_end   = ( isset( $range[1] ) && is_numeric( $range[1] ) ) ? (int) $range[1] : $file_size;
			}

			/*
			 * Check the range and make sure it's treated according to the specs: http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html.
			 * End bytes can not be larger than $end.
			 */
			$c_end = ( $c_end > $end ) ? $end : $c_end;
			// Validate the requested range and return an error if it's not correct.
			if ( $c_start > $c_end || $c_start > $file_size - 1 || $c_end >= $file_size ) {
				return $download_range;
			}
			$start  = $c_start;
			$end    = $c_end;
			$length = $end - $start + 1;

			$download_range['start']          = $start;
			$download_range['length']         = $length;
			$download_range['is_range_valid'] = true;
		}

		return $download_range;
	}

	/**
	 * Die with an error message if the download fails.
	 *
	 * @param string $message Error message.
	 * @param string $title Error title.
	 * @param integer $status Error status.
	 */
	protected function download_error( string $message, string $title = '', int $status = 404 ) {
		/*
		 * Since we will now render a message instead of serving a download, we should unwind some of the previously set
		 * headers.
		 */
		if ( ! headers_sent() ) {
			header( 'Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option( 'blog_charset' ) );
			header_remove( 'Content-Description;' );
			header_remove( 'Content-Disposition' );
			header_remove( 'Content-Transfer-Encoding' );
		}

		if ( ! strstr( $message, '<a ' ) ) {
			$message .= ' <a href="' . esc_url( Helper::get_page_permalink( 'shop_page' ) ) . '" class="storeengine-forward">' . esc_html__( 'Go to shop', 'storeengine' ) . '</a>';
		}
		wp_die( wp_kses_post( $message ), esc_html( $title ), array( 'response' => esc_html( $status ) ) );
	}

	protected function delete_permissions( $order ) {
		global $wpdb;
		$table = $wpdb->prefix . Helper::DB_PREFIX . 'downloadable_product_permissions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE order_id = %d", $order->get_id() ) );
		wp_cache_flush_group( DownloadPermission::CACHE_GROUP );
	}

	/**
	 * @param Order $order
	 *
	 * @return void
	 */
	protected function give_permissions( Order $order ) {
		$product_data = [];
		foreach ( $order->get_items() as $order_item ) {
			$product_id = $order_item->get_product_id();
			if ( 'digital' === $order_item->get_product_type() && isset( $product_data[ $product_id ] ) ) {
				continue;
			}
			$downloadable_files = get_post_meta( $product_id, '_storeengine_product_downloadable_files', true );
			if ( empty( $downloadable_files ) ) {
				continue;
			}
			$downloadable_files = maybe_unserialize( $downloadable_files );
			if ( ! is_array( $downloadable_files ) ) {
				continue;
			}

			$product_data[ $order_item->get_product_id() ] = wp_list_pluck( $downloadable_files, 'id' );
		}

		if ( empty( $product_data ) ) {
			return;
		}

		$rows = [];
		global $wpdb;
		foreach ( $product_data as $product_id => $downloadable_files ) {
			foreach ( $downloadable_files as $download_id ) {
				$rows[] = $wpdb->prepare( '( %d, %d, %s, %d, %s )', $order->get_customer_id(), $order->get_id(), $download_id, $product_id, current_time( 'mysql' ) );
			}
		}
		$rows = implode( ',', $rows );

		$table = $wpdb->prefix . Helper::DB_PREFIX . 'downloadable_product_permissions';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "INSERT INTO $table ( user_id, order_id, download_id, product_id, access_granted  ) VALUES $rows" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		wp_cache_flush_group( DownloadPermission::CACHE_GROUP );
	}
}
