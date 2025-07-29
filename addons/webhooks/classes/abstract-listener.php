<?php
/**
 * Abstract listener.
 */

namespace StoreEngine\Addons\Webhooks\Classes;

use StoreEngine\Addons\Webhooks\Interfaces\ListenersInterface;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

abstract class AbstractListener implements ListenersInterface {

	protected function __construct() {
	}

	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is forbidden.', 'storeengine' ), '0.0.6' );
	}

	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing instances of this class is forbidden.', 'storeengine' ), '0.0.6' );
	}

	protected static function prepare_meta_data( array $meta_data ): array {
		if ( ! $meta_data ) {
			return [];
		}

		return array_combine( array_map( fn( $key ) => str_replace( '_' . Helper::DB_PREFIX, '', $key ), array_keys( $meta_data ) ), $meta_data );
	}
}
