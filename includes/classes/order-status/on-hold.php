<?php

namespace StoreEngine\Classes\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Exceptions\StoreEngineInvalidArgumentException;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Interfaces\OrderStatus;

class OnHold implements OrderStatus {
	const STATUS = 'on_hold';

	public function proceed_to_next_status( OrderContext $context, string $trigger = '' ) {
		switch ( $trigger ) {
			case 'payment_confirm':
				$context->set_order_status( new PaymentConfirmed() );
				break;
			case 'payment_fail':
				$context->set_order_status( new PaymentFailed() );
				break;
			case 'pending_payment':
				$context->set_order_status( new PendingPayment() );
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
		return __( 'On Hold', 'storeengine' );
	}

	public function get_possible_next_statuses(): array {
		return [
			PaymentConfirmed::STATUS,
			PaymentFailed::STATUS,
			PendingPayment::STATUS,
			Cancelled::STATUS,
		];
	}

	public function get_possible_triggers(): array {
		return [
			'payment_confirm',
			'pending_payment',
			'cancel',
			'payment_failed',
		];
	}
}
