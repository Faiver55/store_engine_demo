<?php

namespace StoreEngine\Classes\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Exceptions\StoreEngineInvalidArgumentException;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Interfaces\OrderStatus;

class PendingPayment implements OrderStatus {
	const STATUS = 'pending_payment';

	public function proceed_to_next_status( OrderContext $context, string $trigger = '' ) {
		switch ( $trigger ) {
			case 'process_order':
			case 'processing':
				$context->set_order_status( new Processing() );
				break;
			case 'hold_order':
			case 'hold_payment':
				$context->set_order_status( new OnHold() );
				break;
			case 'payment_failed':
				$context->set_order_status( new PaymentFailed() );
				break;
			case 'cancel_payment':
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
		return __( 'Pending Payment', 'storeengine' );
	}

	public function get_possible_next_statuses(): array {
		return [
			Processing::STATUS,
			OnHold::STATUS,
			PaymentFailed::STATUS,
			Cancelled::STATUS,
		];
	}

	public function get_possible_triggers(): array {
		return [
			'process_order',
			'processing',
			'hold_order',
			'hold_payment',
			'payment_failed',
			'cancel_payment',
		];
	}
}
