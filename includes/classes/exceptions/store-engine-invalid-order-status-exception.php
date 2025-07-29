<?php

namespace StoreEngine\Classes\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class StoreEngineInvalidOrderStatusException extends StoreEngineException {
	public static function invalid_order_status( string $status ): StoreEngineInvalidOrderStatusException {
		/* translators: %1$s: Order status. */
		return new self( sprintf( __( 'Invalid order status %1$s.', 'storeengine' ), $status ), 'invalid_order_status' );
	}
}
