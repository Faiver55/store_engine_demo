<?php

namespace StoreEngine\Addons\Invoice\Ajax;

use StoreEngine\Addons\Invoice\HelperAddon;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\EventStreamServer;

class FontDownloader extends AbstractAjaxHandler {

	protected string $namespace = STOREENGINE_PLUGIN_SLUG . '_invoice';

	protected static EventStreamServer $sse;

	public function __construct() {
		$this->actions = [
			'download_fonts' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'download_fonts' ],
			],
		];
	}

	public function download_fonts() {
		self::$sse = new EventStreamServer();
		self::$sse->listen( function () {
			$this->fonts_download();
			update_option( 'storeengine_invoice_fonts_downloaded', true );
			self::$sse->emitEvent( [
				'type'    => 'complete',
				'message' => esc_html__( 'Fonts download completed successfully!', 'storeengine' ),
			], true );
		} );
	}

	private function fonts_download(): void {
		$font_zip_url = 'https://kodezen.com/wp-content/uploads/assets/ttfonts.zip';
		$filename     = 'ttfonts.zip';
		$fonts_dir    = HelperAddon::get_fonts_dir();
		$sse          = self::$sse;

		if ( ! is_dir( $fonts_dir ) ) {
			if ( ! wp_mkdir_p( $fonts_dir ) ) {
				$sse->emitEvent( [
					'type'    => 'message',
					'message' => esc_html__( 'Failed to create fonts directory.', 'storeengine' ),
				], true );
			}
		}

		$filepath = trailingslashit( $fonts_dir ) . $filename;

		$fp = fopen( $filepath, 'w+' );
		if ( ! $fp ) {
			$sse->emitEvent( [
				'type'    => 'message',
				'message' => esc_html__( 'Failed to open file for writing.', 'storeengine' ),
			], true );
		}
		fclose( $fp );

		add_action( 'requests-curl.before_send', [ __CLASS__, 'percentage_callback' ] );
		$result = wp_remote_get( $font_zip_url, [
			'stream'      => true,
			'filename'    => $filepath,
			'timeout'     => 300,
			'redirection' => 5,
		] );
		remove_action( 'requests-curl.before_send', [ __CLASS__, 'percentage_callback' ] );

		if ( wp_remote_retrieve_response_code( $result ) >= 400 ) {
			$sse->emitEvent( [
				'type'    => 'message',
				'message' => esc_html__( 'Failed to download zip file.', 'storeengine' ),
			], true );
		}
		$sse->emitEvent( [
			'type'    => 'message',
			'message' => esc_html__( 'Download complete. Extracting...', 'storeengine' ),
		] );

		// Load WP_Filesystem
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		$unzip_result = unzip_file( $filepath, $fonts_dir );
		if ( is_wp_error( $unzip_result ) ) {
			$sse->emitEvent( [
				'type'    => 'message',
				'message' => sprintf( __( 'Failed to extract zip file: %s', 'storeengine' ), $unzip_result->get_error_message() ),
			], true );
		}

		$sse->emitEvent( [
			'type'    => 'message',
			'message' => esc_html__( 'Unzip complete!', 'storeengine' ),
		] );

		// Delete the zip file
		if ( file_exists( $filepath ) ) {
			unlink( $filepath );
			$sse->emitEvent( [
				'type'    => 'message',
				'message' => esc_html__( 'Zip file deleted.', 'storeengine' ),
			] );
		}
	}

	public static function percentage_callback( $args ) {
		curl_setopt( $args, CURLOPT_NOPROGRESS, false );
		curl_setopt( $args, CURLOPT_PROGRESSFUNCTION, function ( $resource, $download_size, $downloaded ) {
			if ( $download_size > 0 ) {
				$percent = round( ( $downloaded / $download_size ) * 100 );
				self::$sse->emitEvent( [
					'type'    => 'percentage',
					'message' => $percent,
				] );
			}
		} );
	}

}
