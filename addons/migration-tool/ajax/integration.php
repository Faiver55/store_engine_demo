<?php

namespace StoreEngine\Addons\MigrationTool\Ajax;

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\EventStreamServer;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\Helper;
use StoreEngine\Addons\MigrationTool\Migration\Woocommerce\Migration as WoocommerceMigration;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


class Integration extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [
			'migration-tool/check-plugin'    => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'check_plugin' ],
				'fields'     => [ 'plugin_name' => 'string' ],
			],
			'migration-tool/start-migration' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'start_migration' ],
				'fields'     => [ 'plugin_name' => 'string' ],
			],
		];
	}

	private function check_required_plugin( array $payload ) {
		if ( empty( $payload['plugin_name'] ) ) {
			throw new StoreEngineException( __( "Sorry, you haven't select any plugin to migrate.", 'storeengine' ), 'required-field-missing' );
		}

		switch ( $payload['plugin_name'] ) {
			case 'woocommerce':
				if ( ! Helper::is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
					throw new StoreEngineException(
						sprintf(
						// translators: %s. Plugin name.
							esc_html__( 'You need to install/activate %s to run this migration.', 'storeengine' ),
							esc_html__( 'WooCommerce', 'storeengine' )
						),
						'missing-required-plugin',
						[ 'plugin_name' => $payload['plugin_name'] ]
					);
				}
				break;
			default:
				throw new StoreEngineException(
					__( 'Plugin is not supported.', 'storeengine' ),
					'plugin-not-supported',
					[ 'plugin_name' => $payload['plugin_name'] ]
				);
		}
	}

	public function check_plugin( array $payload ) {
		try {
			$this->check_required_plugin( $payload );
			wp_send_json_success( esc_html__( 'Start migration.', 'storeengine' ) );
		} catch ( StoreEngineException $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	public function start_migration( array $payload ) {
		$sse = new EventStreamServer();
		$sse->listen( function () use ( $sse, $payload ) {
			$this->check_required_plugin( $payload );

			if ( 'woocommerce' === $payload['plugin_name'] ) {
				call_user_func( [ WoocommerceMigration::class, 'migrate' ], $sse );
			}

			sleep( 2 );

			$sse->emitEvent( [
				'type'    => 'complete',
				'message' => esc_html__( 'Migration completed successfully!', 'storeengine' ),
			], true );
		} );
	}
}
