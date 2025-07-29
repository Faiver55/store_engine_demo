<?php

namespace StoreEngine\Classes\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class StoreEngineInvalidOrderStatusTransitionException extends StoreEngineException {
	public static function invalid_order_status_transition( string $from_status, string $trigger, string $from_class = null ): StoreEngineInvalidOrderStatusTransitionException {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $from_class ) {
			/* translators: %1$s: current status of the order, %2$s: From Status Class Name, %3$s: Transition trigger. */
			return new self( sprintf( __( 'Invalid order status transition from %1$s (%2$s), triggered by %3$s', 'storeengine' ), $from_status, $from_class, $trigger ), 'invalid_order_status_transition' );
		}
		/* translators: %1$s: current status of the order, %2$s: Transition trigger. */
		return new self( sprintf( __( 'Invalid order status transition from %1$s, triggered by %2$s', 'storeengine' ), $from_status, $trigger ), 'invalid_order_status_transition' );
	}
}
