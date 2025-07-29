<?php

namespace StoreEngine\Classes\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Exceptions\StoreEngineInvalidArgumentException;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Interfaces\OrderStatus;

class AutoDraft implements OrderStatus {
	const STATUS = 'auto-draft';

	public function proceed_to_next_status( OrderContext $context, string $trigger = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInImplementedInterfaceBeforeLastUsed
		switch ( $trigger ) {
			case 'update_checkout':
				$context->set_order_status( new Draft() );
				break;
			case 'finalized_order': // for admin stat-update.
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
	}

	public function get_status(): string {
		return self::STATUS;
	}

	public function get_status_title(): string {
		return __( 'Admin Draft', 'storeengine' );
	}

	public function get_possible_next_statuses(): array {
		return [
			Draft::STATUS,
			PendingPayment::STATUS,
		];
	}

	public function get_possible_triggers(): array {
		return [
			'update_checkout',
			'finalized_order',
			'order_placed',
		];
	}
}
