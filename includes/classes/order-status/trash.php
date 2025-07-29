<?php

namespace StoreEngine\Classes\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Exceptions\StoreEngineInvalidArgumentException;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Interfaces\OrderStatus;

class Trash implements OrderStatus {
	const STATUS = 'trash';
	public function proceed_to_next_status( OrderContext $context, string $trigger = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInImplementedInterfaceBeforeLastUsed
		switch ( $trigger ) {
			case 'restore':
				$context->set_order_status( new Draft() );
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
		return __( 'Trash', 'storeengine' );
	}

	public function get_possible_next_statuses(): array {
		return [ Draft::STATUS ];
	}

	public function get_possible_triggers(): array {
		return [ 'restore' ];
	}
}
