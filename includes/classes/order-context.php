<?php

namespace StoreEngine\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusTransitionException;
use StoreEngine\Interfaces\OrderStatus;

use StoreEngine\Classes\OrderStatus\ {
	Active,
	AutoDraft,
	Cancelled,
	Completed,
	Draft,
	OnHold,
	PaymentConfirmed,
	PaymentFailed,
	PendingPayment,
	Processing,
	Refunded,
	Trash
};

class OrderContext {
	private OrderStatus $order_status;

	public function __construct( string $order_status ) {
		switch ( $order_status ) {
			case 'auto-draft':
				$this->set_order_status( new AutoDraft() );
				break;
			case 'draft':
				$this->set_order_status( new Draft() );
				break;
			case 'pending':
			case 'pending_payment':
				$this->set_order_status( new PendingPayment() );
				break;
			case 'active':
				$this->set_order_status( new Active() );
				break;
			case 'on_hold':
				$this->set_order_status( new OnHold() );
				break;
			case 'processing':
				$this->set_order_status( new Processing() );
				break;
			case 'payment_confirmed':
				$this->set_order_status( new PaymentConfirmed() );
				break;
			case 'completed':
				$this->set_order_status( new Completed() );
				break;
			case 'payment_failed':
				$this->set_order_status( new PaymentFailed() );
				break;
			case 'refunded':
				$this->set_order_status( new Refunded() );
				break;
			case 'cancelled':
				$this->set_order_status( new Cancelled() );
				break;
			case 'trash':
				$this->set_order_status( new Trash() );
				break;
			default:
				throw StoreEngineInvalidOrderStatusException::invalid_order_status( esc_html( $order_status ) );
		}
	}

	public function set_order_status( OrderStatus $order_status ) {
		$this->order_status = $order_status;
	}

	public function get_order_status(): string {
		return $this->order_status->get_status();
	}

	public function get_order_status_title(): string {
		return $this->order_status->get_status_title();
	}

	/**
	 * @param $trigger
	 * @param Order $order
	 * @param string|array{note:string,manual_update:bool,transaction_id:string|int} $args
	 *
	 * @return $this
	 * @throws StoreEngineInvalidOrderStatusTransitionException
	 */
	public function proceed_to_next_status( $trigger, Order $order, $args = [] ): OrderContext {
		if ( ! in_array( $trigger, $this->get_possible_triggers(), true ) ) {
			throw StoreEngineInvalidOrderStatusTransitionException::invalid_order_status_transition( esc_html( $this->get_order_status() ), esc_html( $trigger ), esc_html( get_class( $this->order_status )) );
		}

		if ( is_string( $args ) ) {
			$args = [ 'note' => $args ];
		}

		$args = wp_parse_args( $args, [
			'note'           => '',
			'manual_update'  => false,
			'transaction_id' => '',
		] );

		if ( ! $args['note'] ) {
			$args['note'] = sprintf(
				/* translators: %s: Status transition trigger name. E.g. payment_initiate, payment_confirmed, active, etc. */
				__( 'Order status transition triggered by %s.', 'storeengine' ),
				$trigger,
			);
		}


		// transition to next stage status.
		$this->order_status->proceed_to_next_status( $this, $trigger );

		$new_status = $this->get_order_status();

		// Set new status.
		$order->update_status( $new_status, $args['note'], $args['manual_update'] );

		do_action( 'storeengine/order/proceed_to_next_status', $trigger, $order, $args );

		if ( ! empty( $args['transaction_id'] ) ) {
			$order->set_transaction_id( $args['transaction_id'] );
		}

		$unpaid_statuses = [ AutoDraft::STATUS, Draft::STATUS, PendingPayment::STATUS, OnHold::STATUS ];

		if ( in_array( $new_status, $unpaid_statuses, true ) ) {
			$order->set_paid_status( 'unpaid' );
		}

		if ( PaymentFailed::STATUS === $new_status ) {
			$order->set_paid_status( 'failed' );
		}

		if ( $new_status === Processing::STATUS && 'paid' === $order->get_paid_status() ) {
			if ( $order->get_auto_complete_digital_order() ) {
				$this->proceed_to_next_status( 'completed', $order, _x( 'Auto complete digital order.', 'Auto complete note for virtual product', 'storeengine' ) );
				$order->save();
			}
		}

		if ( $new_status === Completed::STATUS ) {
			$order->set_date_completed_gmt( time() );
		}

		$order->save();

		return $this;
	}

	public function get_possible_next_statuses(): array {
		return $this->order_status->get_possible_next_statuses();
	}

	public function get_possible_triggers(): array {
		return $this->order_status->get_possible_triggers();
	}
}
