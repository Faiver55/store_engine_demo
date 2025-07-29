<?php

namespace StoreEngine\Classes\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Exceptions\StoreEngineInvalidArgumentException;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Interfaces\OrderStatus;

class Processing implements OrderStatus {
	const STATUS = 'processing';

	public function proceed_to_next_status( OrderContext $context, string $trigger = '' ) {
		switch ( $trigger ) {
			case 'completed':
				$context->set_order_status( new Completed() );
				break;
			case 'payment_confirm':
				$context->set_order_status( new PaymentConfirmed() );
				break;
			case 'payment_fail':
				$context->set_order_status( new PaymentFailed() );
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
	}

	public function get_status(): string {
		return self::STATUS;
	}

	public function get_status_title(): string {
		return __( 'Processing', 'storeengine' );
	}

	public function get_possible_next_statuses(): array {
		return [
			Completed::STATUS,
			PaymentConfirmed::STATUS,
			PaymentFailed::STATUS,
			Cancelled::STATUS,
		];
	}

	public function get_possible_triggers(): array {
		return [
			'completed',
			'payment_confirm',
			'payment_fail',
			'cancel',
		];
	}
}
