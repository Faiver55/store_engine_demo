<?php
namespace StoreEngine\Interfaces;

use StoreEngine\Classes\OrderContext;

interface OrderStatus {
	/**
	 * @param OrderContext $context
	 * @param string $trigger
	 */
	public function proceed_to_next_status( OrderContext $context, string $trigger = '' );

	public function get_status(): string;

	public function get_status_title(): string;

	public function get_possible_next_statuses(): array;

	public function get_possible_triggers(): array;
}
