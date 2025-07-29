<?php

namespace StoreEngine\Classes;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tools {

	private function get_memory_limit() {
		// WP memory limit.
		$wp_memory_limit = WP_MEMORY_LIMIT;
		if ( function_exists( 'memory_get_usage' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$wp_memory_limit = max( $wp_memory_limit, @ini_get( 'memory_limit' ) );
		}

		return $wp_memory_limit;
	}

	private function get_curl_version() {
		$curl_version = '';
		if ( function_exists( 'curl_version' ) ) {
			$curl_version = curl_version();
			$curl_version = $curl_version['version'] . ', ' . $curl_version['ssl_version'];
		}

		return $curl_version ?: __( 'N/A', 'storeengine' );
	}

	public function get_wordpress_environment_status(): array {
		$theme        = wp_get_theme();
		$currentTheme = sprintf( '%s – %s', $theme->display( 'Name' ), $theme->display( 'Version' ) );
		if ( $theme->parent() ) {
			$parentTheme = sprintf( '%s – %s', $theme->parent()->display( 'Name' ), $theme->parent()->display( 'Version' ) );
		} else {
			$parentTheme = __( 'N/A', 'storeengine' );
		}

		return apply_filters(
			'storeengine/tools/wordpress_environment_status',
			[
				[
					'label' => __( 'Home URL', 'storeengine' ),
					'value' => get_bloginfo( 'url' ),
				],
				[
					'label' => __( 'Site URL', 'storeengine' ),
					'value' => get_bloginfo( 'wpurl' ),
				],
				[
					'label' => __( 'WordPress Version', 'storeengine' ),
					'value' => get_bloginfo( 'version' ),
				],
				[
					'label' => __( 'StoreEngine Version', 'storeengine' ),
					'value' => defined( 'STOREENGINE_VERSION' ) ? STOREENGINE_VERSION : '',
				],
				[
					'label' => __( 'Current Theme', 'storeengine' ),
					'value' => $currentTheme,
				],
				[
					'label' => __( 'Parent Theme', 'storeengine' ),
					'value' => $parentTheme,
				],
				[
					'label' => __( 'Is FSE Theme', 'storeengine' ),
					'value' => Helper::is_fse_theme(),
				],
				[
					'label' => __( 'Language', 'storeengine' ),
					'value' => get_locale(),
				],
				[
					'label' => __( 'User Language', 'storeengine' ),
					'value' => get_user_locale(),
				],
				[
					'label' => __( 'WordPress Multisite', 'storeengine' ),
					'value' => is_multisite() ? __( 'Yes', 'storeengine' ) : __( 'No', 'storeengine' ),
				],
				[
					'label' => __( 'Timezone', 'storeengine' ),
					'value' => wp_timezone_string(),
				],
				[
					'label' => __( 'WordPress Memory Limit', 'storeengine' ),
					'value' => $this->get_memory_limit(),
				],
				[
					'label' => __( 'WordPress Max Memory Limit', 'storeengine' ),
					'value' => defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : __( 'N/A', 'storeengine' ),
				],
				[
					'label' => __( 'WordPress Max File Upload Size', 'storeengine' ),
					'value' => size_format( wp_max_upload_size() ),
				],
				[
					'label' => __( 'WP Caching', 'storeengine' ),
					'value' => defined( 'WP_CACHE' ) && WP_CACHE,
				],
				[
					'label' => __( 'External object cache', 'storeengine' ),
					'value' => ! ! wp_using_ext_object_cache(),
				],
				[
					'label' => __( 'WordPress Cron', 'storeengine' ),
					'value' => ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ),
				],
				[
					'label' => __( 'WordPress Debug Mode', 'storeengine' ),
					'value' => defined( 'WP_DEBUG' ) && WP_DEBUG,
				],
				[
					'label' => __( 'WordPress Script Debug', 'storeengine' ),
					'value' => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
				],
				[
					'label' => __( 'WordPress Debug Display', 'storeengine' ),
					'value' => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
				],
				[
					'label' => __( 'WordPress Debug Log', 'storeengine' ),
					'value' => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
				],
				[
					'label' => __( 'WP Debug Log Path', 'storeengine' ),
					'value' => ini_get( 'error_log' ) ? str_replace( ABSPATH, '/', ini_get( 'error_log' ) ) : __( 'N/A', 'storeengine' ),
				],
			]
		);
	}

	public function get_server_environment_status(): array {
		// phpcs:disable
		global $wpdb;

		$os = php_uname( 's' );
		if ( php_uname( 's' ) == 'Darwin' ) {
			$os = 'MacOS';
		}

		return apply_filters(
			'storeengine/tools/server_environment_status',
			[
				[
					'label' => __( 'OS', 'storeengine' ),
					'value' => sprintf( "%s – %s (%s)", $os, php_uname( 'r' ), php_uname( 'm' ) ),
				],
				[
					'label' => __( 'Server info', 'storeengine' ),
					'value' => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : __( 'N/A', 'storeengine' ),
				],
				[
					'label' => __( 'PHP version', 'storeengine' ),
					'value' => phpversion(),
				],
				[
					'label' => __( 'PHP post max size', 'storeengine' ),
					'value' => @ini_get( 'post_max_size' ),
				],
				[
					'label' => __( 'PHP max execution time', 'storeengine' ),
					'value' => @ini_get( 'max_execution_time' ),
				],
				[
					'label' => __( 'PHP max input vars', 'storeengine' ),
					'value' => @ini_get( 'max_input_vars' ),
				],
				[
					'label' => __( 'PHP fsockopen enabled', 'storeengine' ),
					'value' => function_exists( 'fsockopen' ),
				],
				[
					'label' => __( 'PHP Ext. cURL', 'storeengine' ),
					'value' => extension_loaded( 'curl' ) && function_exists( 'curl_init' ) ? phpversion( 'curl' ) : __( 'N/A', 'storeengine' ),
				],
				[
					'label' => __( 'cURL version', 'storeengine' ),
					'value' => $this->get_curl_version(),
				],
				[
					'label' => __( 'Suhosin installed', 'storeengine' ),
					'value' => extension_loaded( 'suhosin' ),
				],
				[
					'label' => __( 'Max upload size', 'storeengine' ),
					'value' => @ini_get( 'max_file_uploads' ),
				],
				[
					'label' => __( 'MySQL version', 'storeengine' ),
					'value' => $wpdb->db_version(),
				],
				[
					'label' => __( 'Default timezone', 'storeengine' ),
					'value' => date_default_timezone_get(),
				],
				[
					'label' => __( 'PHP SOAP Client enabled', 'storeengine' ),
					'value' => class_exists( 'SoapClient' ),
				],
				[
					'label' => __( 'PHP DOMDocument enabled', 'storeengine' ),
					'value' => class_exists( 'DOMDocument' ),
				],
				[
					'label' => __( 'PHP Ext. Zip', 'storeengine' ),
					'value' => extension_loaded( 'zip' ),
				],
				[
					'label' => __( 'PHP Ext. gZip', 'storeengine' ),
					'value' => is_callable( 'gzopen' ),
				],
				[
					'label' => __( 'PHP Ext. Multibyte string', 'storeengine' ),
					'value' => extension_loaded( 'mbstring' ),
				],
			]
		);
		// phpcs:enable
	}
}
