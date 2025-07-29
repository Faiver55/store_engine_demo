<?php

namespace StoreEngine\Classes\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Exceptions\StoreEngineInvalidArgumentException;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Interfaces\OrderStatus;

class Draft implements OrderStatus {
	const STATUS = 'draft';

	public function proceed_to_next_status( OrderContext $context, string $trigger = '' ) {
		switch ( $trigger ) {
			case 'update_checkout':
				$context->set_order_status( new Draft() );
				break;
			case 'order_placed':
				$context->set_order_status( new PendingPayment() );
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

		// @TODO throw: Invalid transition from draft.
	}

	public function get_status(): string {
		return self::STATUS;
	}

	public function get_status_title(): string {
		return __( 'Draft', 'storeengine' );
	}

	public function get_possible_next_statuses(): array {
		return [
			self::STATUS,
			PendingPayment::STATUS,
		];
	}

	public function get_possible_triggers(): array {
		return [
			'update_checkout',
			'order_placed',
		];
	}
}
