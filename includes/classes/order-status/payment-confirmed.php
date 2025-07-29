<?php

namespace StoreEngine\Classes\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Exceptions\StoreEngineInvalidArgumentException;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Interfaces\OrderStatus;
use WP_Error;

class PaymentConfirmed implements OrderStatus {
	const STATUS = 'payment_confirmed';

	public function proceed_to_next_status( OrderContext $context, string $trigger = '' ) {
		switch ( $trigger ) {
			case 'start_processing':
				$context->set_order_status( new Processing() );
				break;
			case 'cancel':
				$context->set_order_status( new Cancelled() );
				break;
			default:
				throw new StoreEngineInvalidArgumentException(
					sprintf(
					/* translators: %1$s. Requested status transition, %2$s. Current Status */
						__( 'Invalid trigger (%1$s) for next status from %2$s', 'storeengine' ),
						self::STATUS,
						$trigger
					),
					'invalid-trigger'
				);
		}

		// @TODO throw: Invalid transition from payment_confirmed.
	}

	public function get_status(): string {
		return self::STATUS;
	}

	public function get_status_title(): string {
		return __( 'Payment Confirmed', 'storeengine' );
	}

	public function get_status_description(): string {
		return 'Payment Confirmed Description';
	}

	public function get_possible_next_statuses(): array {
		return [
			Processing::STATUS,
			Cancelled::STATUS,
		];
	}

	public function get_possible_triggers(): array {
		return [
			'start_processing',
			'cancel',
		];
	}
}
