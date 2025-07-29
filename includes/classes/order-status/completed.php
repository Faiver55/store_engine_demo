<?php

namespace StoreEngine\Classes\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Exceptions\StoreEngineInvalidArgumentException;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Interfaces\OrderStatus;

class Completed implements OrderStatus {
	const STATUS = 'completed';

	public function proceed_to_next_status( OrderContext $context, string $trigger = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInImplementedInterfaceBeforeLastUsed
		throw new StoreEngineInvalidArgumentException(
			sprintf(
			/* translators: %1$s. Current status, %2$s. Requested status transition. */
				esc_html__( 'Invalid transition from %1$s to %2$s', 'storeengine' ),
				self::STATUS,
				$trigger
			),
			'invalid-status-transition'
		);
	}

	public function get_status(): string {
		return self::STATUS;
	}

	public function get_status_title(): string {
		return __( 'Completed', 'storeengine' );
	}

	public function get_possible_next_statuses(): array {
		return [];
	}

	public function get_possible_triggers(): array {
		// TODO: Implement get_possible_triggers() method.
		return [];
	}
}
