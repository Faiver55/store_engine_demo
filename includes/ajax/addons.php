<?php

namespace StoreEngine\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\Helper;

class Addons extends AbstractAjaxHandler {
	public function __construct() {
		$this->actions = [
			'get_all_addons'     => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_all_addons' ],
			],
			'saved_addon_status' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'saved_addon_status' ],
				'fields'     => [
					'addon_name'      => 'string',
					'addon_slug'      => 'string',
					'status'          => 'boolean',
					'required_plugin' => [
						'plugin_dir_path' => 'string',
						'plugin_name'     => 'string',
					],
				],
			],
		];
	}

	protected function get_all_addons() {
		wp_send_json_success( json_decode( get_option( STOREENGINE_ADDONS_SETTINGS_NAME, '{}' ) ) );
	}


	protected function saved_addon_status( $payload ) {
		if ( empty( $payload['addon_name'] ) ) {
			wp_send_json_error( __( 'Addon name is required.', 'storeengine' ) );
		}

		if ( empty( $payload['addon_slug'] ) ) {
			wp_send_json_error( __( 'Addon slug is required.', 'storeengine' ) );
		}

		if ( ! array_key_exists( 'status', $payload ) ) {
			wp_send_json_error( __( 'Addon status is required.', 'storeengine' ) );
		}

		if ( $payload['status'] && ! empty( $payload['required_plugin'] ) && is_array( $payload['required_plugin'] ) ) {
			foreach ( $payload['required_plugin'] as $plugin ) {
				if ( ! Helper::is_plugin_active( $plugin['plugin_dir_path'] ) ) {
					wp_send_json_error( sprintf(
					/* translators: %1$s. Plugin Name, %2$s. Addon name */
						esc_html__( '%1$s Plugin is required to activate %2$s addon.', 'storeengine' ),
						esc_html( $plugin['plugin_name'] ),
						esc_html( $payload['addon_name'] )
					) );
				}
			}
		}

		// Saved Data.
		$saved_addons = json_decode( get_option( STOREENGINE_ADDONS_SETTINGS_NAME, '{}' ), true );
		if ( ! is_array( $saved_addons ) ) {
			$saved_addons = [];
		}

		// Update status.
		$addon_slug                  = $payload['addon_slug'];
		$saved_addons[ $addon_slug ] = $payload['status'];

		// Fire Addon Action
		if ( $payload['status'] ) {
			do_action( "storeengine/addons/activated_{$addon_slug}" );
		} else {
			do_action( "storeengine/addons/deactivated_{$addon_slug}" );
		}

		// Save data.
		update_option( STOREENGINE_ADDONS_SETTINGS_NAME, wp_json_encode( $saved_addons ) );

		// response
		wp_send_json_success( $saved_addons );
	}
}
