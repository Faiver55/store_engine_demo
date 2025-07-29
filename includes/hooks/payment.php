<?php

namespace StoreEngine\hooks;

use StoreEngine\Classes\Order;
use StoreEngine\Classes\PaymentTokens\PaymentTokens;
use StoreEngine\Classes\Refund;
use StoreEngine\Utils\Helper;

class Payment {

	public static function init() {
		$self = new self();

		add_action( 'storeengine/order/status_changed', [ $self, 'update_customer' ], 10, 4 );
		add_action( 'storeengine/order_refund_created', [ $self, 'update_customer_refund' ], 10, 2 );

		add_filter( 'storeengine/saved_payment_methods_list', [ __CLASS__, 'get_account_saved_payment_methods_list' ], 10, 2 );
		add_action( 'storeengine_dashboard_handle_delete-payment-method_request', [ __CLASS__, 'delete_payment_method_action' ], 20 );
		add_action( 'storeengine_dashboard_handle_set-default-payment-method_request', [ __CLASS__, 'set_default_payment_method_action' ], 20 );
	}

	public function update_customer( $order_id, string $old_status, string $new_status, Order $order ) {
		$paid_statuses = Helper::get_order_paid_statuses();

		$old_status_exists = in_array( $old_status, $paid_statuses, true );
		$new_status_exists = in_array( $new_status, $paid_statuses, true );

		if ( ( ! $old_status_exists && ! $new_status_exists ) || ( $old_status_exists && $new_status_exists ) ) {
			return;
		}

		$customer = $order->get_customer();
		if ( ! $customer ) {
			return;
		}

		$order_total = $order->get_total() - $order->get_total_refunded();
		if ( $old_status_exists && ! $new_status_exists ) {
			$customer->set_total_orders( $customer->get_total_orders() - 1 );
			$total_spent = (float) ( $customer->get_total_spent() - $order_total );
			$customer->set_total_spent( $total_spent );
		}

		if ( ! $old_status_exists && $new_status_exists ) {
			$customer->set_total_orders( $customer->get_total_orders() + 1 );
			$total_spent = (float) ( $customer->get_total_spent() + $order_total );
			$customer->set_total_spent( $total_spent );
		}

		$customer->save();
	}

	public function update_customer_refund( Refund $refund, Order $order ) {
		$customer = $order->get_customer();
		if ( ! $customer || ! in_array( $order->get_status(), Helper::get_order_paid_statuses(), true ) ) {
			return;
		}

		$total_spent = (float) $customer->get_total_spent();
		$total_spent = $total_spent - (float) $refund->get_total();
		$customer->set_total_spent( $total_spent );
		$customer->save();
	}

	public static function get_account_saved_payment_methods_list( $list, $customer_id ): array {
		$payment_tokens = PaymentTokens::get_customer_tokens( $customer_id );
		foreach ( $payment_tokens as $payment_token ) {
			$delete_url = Helper::get_account_endpoint_url( 'delete-payment-method', $payment_token->get_id() );
			$delete_url = wp_nonce_url( $delete_url, 'delete-payment-method-' . $payment_token->get_id() );
			$actions    = [
				'delete' => [
					'url'  => $delete_url,
					'name' => esc_html__( 'Delete', 'storeengine' ),
				],
			];

			if ( ! $payment_token->is_default() ) {
				$set_default_url    = Helper::get_account_endpoint_url( 'set-default-payment-method', $payment_token->get_id() );
				$set_default_url    = wp_nonce_url( $set_default_url, 'set-default-payment-method-' . $payment_token->get_id() );
				$actions['default'] = [
					'url'  => $set_default_url,
					'name' => esc_html__( 'Make default', 'storeengine' ),
				];
			}

			$type = strtolower( $payment_token->get_type() );
			$item = [
				'method'     => [ 'gateway' => $payment_token->get_gateway_id() ],
				'expires'    => esc_html__( 'N/A', 'storeengine' ),
				'is_default' => $payment_token->is_default(),
				'actions'    => $actions,
			];

			if ( 'cc' === $type ) {
				$card_type               = $payment_token->get_card_type();
				$item['method']['last4'] = $payment_token->get_last4();
				$item['method']['brand'] = ( ! empty( $card_type ) ? ucwords( str_replace( '_', ' ', $card_type ) ) : esc_html__( 'Credit card', 'storeengine' ) );
				$item['expires']         = $payment_token->get_expiry_month() . '/' . substr( $payment_token->get_expiry_year(), - 2 );
			}

			if ( 'echeck' === $type ) {
				$item['method']['last4'] = $payment_token->get_last4();
				$item['method']['brand'] = esc_html__( 'eCheck', 'storeengine' );
			}


			$list[ $type ][] = apply_filters( 'storeengine/payment_methods_list_item', $item, $payment_token );
		}

		return $list;
	}

	/**
	 * Process the delete payment method form.
	 */
	public static function delete_payment_method_action( $token_id ) {
		$token = PaymentTokens::get( absint( $token_id ) );

		if ( is_null( $token ) || get_current_user_id() !== $token->get_user_id() || ! isset( $_REQUEST['_wpnonce'] ) || false === wp_verify_nonce( wp_unslash( $_REQUEST['_wpnonce'] ), 'delete-payment-method-' . $token_id ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wp_die( esc_html__( 'Invalid payment method.', 'storeengine' ), esc_html__( 'Invalid payment method.', 'storeengine' ), [ 'back_link' => true ] );
		} else {
			PaymentTokens::delete( $token_id );
			// @TODO notification handler.
			//__( 'Payment method deleted.', 'storeengine' )
		}

		wp_safe_redirect( Helper::get_account_endpoint_url( 'payment-methods' ) );
		exit();
	}

	/**
	 * Process the delete payment method form.
	 */
	public static function set_default_payment_method_action( $token_id ) {
		$token = PaymentTokens::get( absint( $token_id ) );

		if ( is_null( $token ) || get_current_user_id() !== $token->get_user_id() || ! isset( $_REQUEST['_wpnonce'] ) || false === wp_verify_nonce( wp_unslash( $_REQUEST['_wpnonce'] ), 'set-default-payment-method-' . $token_id ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wp_die( esc_html__( 'Invalid payment method.', 'storeengine' ), esc_html__( 'Invalid payment method.', 'storeengine' ), [ 'back_link' => true ] );
		} else {
			PaymentTokens::set_users_default( $token->get_user_id(), intval( $token_id ) );
			// @TODO notification handler.
			// __( 'This payment method was successfully set as your default.', 'storeengine' )
		}

		wp_safe_redirect( Helper::get_account_endpoint_url( 'payment-methods' ) );
		exit();
	}
}
