<?php

namespace StoreEngine\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Utils\Constants;
use StoreEngine\Utils\Helper;
use StoreEngine\Addons\Stripe\StripeService;
use WP_Error;

class Order extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [
			// @TODO remove this action, it seems there is not use for it.
			'get_order_payment_method_details' => [
				'callback'   => [ $this, 'get_order_payment_method_details' ],
				'capability' => 'manage_options',
				'fields'     => [
					'order_id' => 'id',
				],
			],
			'change_order_status'              => [
				'callback'   => [ $this, 'change_order_status' ],
				'capability' => 'manage_options',
				'fields'     => [
					'order_id' => 'id',
					'status'   => 'string',
				],
			],
			'analytics/start_date'             => [
				'callback'   => [ $this, 'analytics_start_date' ],
				'capability' => 'manage_options',
			],
			'order/get_notes'                  => [
				'callback'   => [ $this, 'get_order_notes' ],
				'capability' => 'manage_options',
				'fields'     => [ 'order_id' => 'id' ],
			],
			'order/add_note'                   => [
				'callback'   => [ $this, 'add_order_note' ],
				'capability' => 'manage_options',
				'fields'     => [
					'order_id' => 'id',
					'note'     => 'string',
					'type'     => 'text',
				],
			],
			'order/delete_note'                => [
				'callback'   => [ $this, 'delete_order_note' ],
				'capability' => 'manage_options',
				'fields'     => [ 'id' => 'id' ],
			],
		];
	}

	protected function get_order_notes( array $payload ) {
		$order = Helper::get_order( $payload['order_id'] ?? 0 );
		wp_send_json_success( $order->get_order_notes() );
	}

	protected function add_order_note( array $payload ) {
		if ( empty( $payload['order_id'] ) ) {
			wp_send_json_error( __( 'Order ID is required', 'storeengine' ) );
		}

		if ( empty( $payload['note'] ) ) {
			wp_send_json_error( __( 'Note content is required.', 'storeengine' ) );
		}

		if ( empty( $payload['type'] ) ) {
			wp_send_json_error( __( 'Note type is required.', 'storeengine' ) );
		}

		if ( ! in_array( $payload['type'], [ 'admin', 'customer' ] ) ) {
			wp_send_json_error( __( 'Invalid note type.', 'storeengine' ) );
		}

		$order = Helper::get_order( $payload['order_id'] );

		if ( is_wp_error( $order ) ) {
			wp_send_json_error( $order->get_error_message() );
		}

		if ( ! $order || ! $order->get_id() ) {
			wp_send_json_error( __( 'Invalid order id.', 'storeengine' ) );
		}

		if ( $order->has_status( 'trash' ) ) {
			wp_send_json_error( __( 'Please restore the order first.', 'storeengine' ) );
		}

		$note = $order->add_order_note( $payload['note'], 'customer' === $payload['type'], true );

		wp_send_json_success( \StoreEngine\Classes\Order::get_order_note( $note ) );
	}

	/**
	 * @throws StoreEngineException
	 */
	protected function delete_order_note( array $payload ) {
		if ( empty( $payload['id'] ) ) {
			wp_send_json_error( __( 'Note ID is required.', 'storeengine' ) );
		}

		\StoreEngine\Classes\Order::delete_order_note( $payload['id'] );
		wp_send_json_success( __( 'Order note deleted', 'storeengine' ) );
	}

	protected function analytics_start_date() {
		global $wpdb;

		$date = wp_cache_get( 'storeengine_analytics_start_date' );

		if ( false === $date ) {
			$date = $wpdb->get_var( "SELECT date_created_gmt FROM {$wpdb->prefix}storeengine_orders ORDER BY date_created_gmt ASC LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

			if ( $date ) {
				$date = gmdate( 'Y-m-d H:i', strtotime( $date ) );
				wp_cache_set( 'storeengine_analytics_start_date', $date );
			} else {
				$date = gmdate( 'Y-m-d H:i' );
			}
		}

		wp_send_json_success( $date );
	}

	public function get_order_payment_method_details( $payload ) {
		if ( empty( $payload['order_id'] ) ) {
			wp_send_json_error( esc_html__( 'Order ID is required.', 'storeengine' ) );
		}

		$order_model = new \StoreEngine\models\Order();
		$order       = $order_model->get_by_primary_key( $payload['order_id'] );
		if ( ! $order ) {
			wp_send_json_error( esc_html__( 'Order not found.', 'storeengine' ) );
		}

		if ( ! $order['payment_method'] ) {
			wp_send_json_error( esc_html__( 'Payment method not found.', 'storeengine' ) );
		}

		if ( ! $order['payment_method'] ) {
			wp_send_json_error( esc_html__( 'Payment method not found', 'storeengine' ) );
		}

		$gateway = Helper::get_payment_gateway( $order['payment_method'] );

		if ( ! $gateway || 'stripe' !== $gateway->id ) {
			wp_send_json_error( esc_html__( 'Stripe addon is not active', 'storeengine' ) );
		}

		if ( ! $gateway->is_enabled ) {
			wp_send_json_error( esc_html__( 'Stripe is not enabled.', 'storeengine' ) );
		}

		$payment_method = StripeService::init()->get_payment_method( $order['meta']['stripe_payment_method_id'] );

		if ( is_wp_error( $payment_method ) ) {
			wp_send_json_error(
				sprintf(
				/* translators: %s: Error message. */
					esc_html__( 'Error getting data from stripe. Error: %s', 'storeengine' ),
					$payment_method->get_error_message()
				)
			);
		}

		wp_send_json_success( [
			'brand'        => $payment_method->card->brand,
			'last4'        => $payment_method->card->last4,
			'expire_month' => $payment_method->card->exp_month,
			'expire_year'  => $payment_method->card->exp_year,
		] );
	}

	public function change_order_status( $payload ) {
		if ( empty( $payload['order_id'] ) ) {
			wp_send_json_error( esc_html__( 'Order ID is required.', 'storeengine' ) );
		}

		if ( empty( $payload['status'] ) ) {
			wp_send_json_error( esc_html__( 'Order status is required.', 'storeengine' ) );
		}

		$order = Helper::get_order( $payload['order_id'] );

		if ( is_wp_error( $order ) ) {
			wp_send_json_error( $order->get_error_message() );
		}

		if ( ! $order ) {
			wp_send_json_error( esc_html__( 'Order not found', 'storeengine' ) );
		}

		if ( 'trash' === $order->get_status() ) {
			if ( 'restore' !== $payload['status'] ) {
				return new WP_Error( 'storeengine_order_cannot_update_status', __( 'Please restore the order first.', 'storeengine' ), [ 'status' => 500 ] );
			}

			if ( ! $order->untrash() ) {
				return new WP_Error( 'storeengine_order_cannot_untrash', __( 'Failed to restore the order.', 'storeengine' ), [ 'status' => 500 ] );
			}
		} else {
			if ( 'trash' === $payload['status'] ) {
				$order_id = $order->get_id();
				$force    = ! empty( $payload['force'] );
				if ( $force ) {
					$order->delete( true );
					$result = 0 === $order->get_id();
				} else {
					if ( 'trash' === $order->get_status() ) {
						/* translators: %s: post type */
						return new WP_Error( 'storeengine_order_already_trashed', __( 'The order has already been trashed.', 'storeengine' ), [ 'status' => 410 ] );
					}

					$order->trash();
					$result = 'trash' === $order->get_status();
				}

				if ( ! $result ) {
					return new WP_Error( 'storeengine_order_cannot_delete', __( 'Failed to delete order.', 'storeengine' ), [ 'status' => 500 ] );
				}

				do_action( 'storeengine/api/after_delete_order', $order, $order_id, $force );
			} else {
				$order->set_status( $payload['status'] );
				$order->save();
			}
		}

		wp_send_json_success( [
			'id'                    => $order->get_id(),
			'is_editable'           => $order->is_editable(),
			'currency'              => $order->get_currency(),
			'status'                => $order->get_status(),
			'paid_status'           => $order->get_paid_status(),
			'transaction_id'        => $order->get_transaction_id(),
			'meta'                  => $order->get_meta_data(),
			'notes'                 => $order->get_order_notes(),
			'type'                  => $order->get_type(),
			'date_paid_gmt'         => $order->get_date_paid_gmt() ? $order->get_date_paid_gmt()->format( 'Y-m-d H:i:s' ) : null,
			'date_created_gmt'      => $order->get_date_created_gmt() ? $order->get_date_created_gmt()->format( 'Y-m-d H:i:s' ) : null,
			'order_placed_date_gmt' => $order->get_order_placed_date_gmt() ? $order->get_order_placed_date_gmt()->format( 'Y-m-d H:i:s' ) : null,
			'order_placed_date'     => $order->get_order_placed_date() ? $order->get_order_placed_date()->format( 'Y-m-d H:i:s' ) : null,
		] );
	}
}
