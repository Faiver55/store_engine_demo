<?php

namespace StoreEngine\Utils\traits;

use Exception;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order as OrderClass;
use StoreEngine\Classes\Order\OrderItemProduct;
use StoreEngine\Classes\OrderCollection;
use StoreEngine\Classes\Orders;
use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Classes\Refund;
use StoreEngine\Payment_Gateways;
use StoreEngine\Utils\Caching;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\StringUtil;
use WP_Error;

trait Order {

	public static function get_order_paid_statuses(): array {
		return apply_filters( 'storeengine/order_paid_statuses', [ 'completed', 'payment_confirmed' ] );
	}

	/**
	 * @param $id
	 *
	 * @return OrderClass|WP_Error|false
	 */
	public static function get_order( $id ) {
		try {
			return new OrderClass( $id );
		} catch ( StoreEngineException $e ) {
			return $e->toWpError();
		} catch ( Exception $e ) {
			new WP_Error( 'error', $e->getMessage(), [ 'status' => 500 ] );
		}

		return false;
	}

	public static function get_order_by_key( string $key ) {
		try {
			return ( new OrderClass() )->get_by_key( $key );
		} catch ( StoreEngineException $e ) {
			return $e->toWpError();
		} catch ( Exception $e ) {
			return new WP_Error( 'error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Get orders of customer.
	 *
	 * @param int $customer_id [Optional] Customer id. Default to zero (current user).
	 *
	 * @return OrderClass[]
	 * @see OrderCollection
	 *
	 * @deprecated Use Order collection class to query and count results together.
	 */
	public static function get_customer_orders( int $customer_id = 0, int $page = 1, int $per_page = 10 ): array {
		return ( new Orders( $page, $per_page ) )->get( array(
			'customer_id' => array(
				'condition' => '=',
				'formatter' => '%d',
				'value'     => 0 !== $customer_id ? $customer_id : get_current_user_id(),
			),
		) );
	}

	public static function get_payment_data( OrderClass $order ): array {
		$data = [
			'id'                       => $order->get_id(),
			'status'                   => $order->get_status(),
			'customer_id'              => $order->get_customer_id(),
			'total_amount'             => $order->get_total(),
			'date_created_gmt'         => $order->get_date_created_gmt()->format( 'Y-m-d H:i:s' ),
			'date_updated_gmt'         => $order->get_date_updated_gmt()->format( 'Y-m-d H:i:s' ),
			'payment_method'           => $order->get_payment_method(),
			'payment_method_title'     => $order->get_payment_method_title(),
			'refunds_total'            => $order->get_total_refunded(),
			'refunded_amount'          => $order->get_total_refunded(),
			'can_refund'               => false,
			'gateway_can_refund_order' => false,
			'currency'                 => $order->get_currency(),
			'is_paid'                  => $order->is_paid(),
		];

		if ( $order->is_paid() && 'refunded' !== $order->get_status() ) {
			$data['can_refund'] = (bool) apply_filters(
				'storeengine/refund/can_admin_refund_order',
				(
					0 < $order->get_total() - $order->get_total_refunded() ||
					0 < absint( $order->get_item_count() - $order->get_item_count_refunded() )
				),
				$order->get_id(),
				$order
			);

			$payment_gateway = Helper::get_payment_gateway_by_order( $order );

			if ( false !== $payment_gateway ) {
				$data['gateway_name'] = ( ! empty( $payment_gateway->method_title ) ? $payment_gateway->method_title : $payment_gateway->get_title() );

				if ( $payment_gateway->can_refund_order( $order ) ) {
					$data['gateway_can_refund_order'] = true;
				}
			} else {
				$data['gateway_name'] = __( 'Payment gateway', 'storeengine' );
			}
		}

		return $data;
	}

	/**
	 * Get orders by page and conditions.
	 *
	 * @param array $args Conditional Args.
	 * @param array $pagination Pagination array.
	 *
	 * @return OrderClass[]
	 * @see OrderCollection
	 *
	 * @deprecated Use Order collection class to query and count results together.
	 */
	public static function get_orders( array $args = [], array $pagination = [] ): array {
		$pagination = wp_parse_args( $pagination, [
			'page'     => 1,
			'per_page' => 10,
		] );

		return ( new Orders( $pagination['page'], $pagination['per_page'] ) )->get( $args );
	}

	/**
	 * @param array $conditions
	 *
	 * @return int
	 * @see OrderCollection
	 *
	 * @deprecated Use Order collection class to query and count results together.
	 */
	public static function get_total_orders_count( array $conditions = [] ): int {
		return ( new Orders() )->get_total_orders_count( $conditions );
	}

	public static function get_recent_draft_order( int $customer_id = 0, ?string $cart_hash = null, bool $create = true ) {
		return ( new OrderClass() )->get_recent_draft_order( $customer_id, null, $create );
	}

	public static function get_order_by_meta( string $meta_key, $meta_value ) {
		return ( new OrderClass() )->get_by_meta( $meta_key, $meta_value );
	}

	public static function create_refund( $args = [] ) {
		$default_args = [
			'amount'         => 0,
			'reason'         => null,
			'order_id'       => 0,
			'refund_id'      => 0,
			'line_items'     => [],
			'refund_payment' => false,
			'restock_items'  => false,
		];

		try {
			$args  = wp_parse_args( $args, $default_args );
			$order = self::get_order( absint( $args['order_id'] ) );

			if ( ! $order ) {
				throw new StoreEngineException( __( 'Invalid order ID.', 'storeengine' ), 'invalid-order-id' );
			}

			$remaining_refund_amount     = $order->get_remaining_refund_amount();
			$remaining_refund_items      = $order->get_remaining_refund_items();
			$refund_item_count           = 0;
			$refund                      = new Refund( $args['refund_id'] );
			$refunded_order_and_products = [];

			if ( 0 > $args['amount'] || $args['amount'] > $remaining_refund_amount ) {
				throw new StoreEngineException( __( 'Invalid refund amount.', 'storeengine' ), 'invalid-refund-amount' );
			}

			$refund->set_currency( $order->get_currency() );
			$refund->set_amount( $args['amount'] );
			$refund->set_status( OrderStatus::COMPLETED );
			$refund->set_parent_order_id( absint( $args['order_id'] ) );
			$refund->set_refunded_by( get_current_user_id() );
			$refund->set_prices_include_tax( $order->get_prices_include_tax() );

			if ( ! StringUtil::is_null_or_whitespace( $args['reason'] ) ) {
				$refund->set_reason( (string) $args['reason'] );
			}

			// Negative line items.
			if ( is_array( $args['line_items'] ) && count( $args['line_items'] ) > 0 ) {
				$items = $order->get_items( [ 'line_item', 'fee', 'shipping' ] );

				foreach ( $items as $item_id => $item ) {
					if ( ! isset( $args['line_items'][ $item_id ] ) ) {
						continue;
					}

					$qty          = $args['line_items'][ $item_id ]['qty'] ?? 0;
					$refund_total = $args['line_items'][ $item_id ]['refund_total'];
					$refund_tax   = isset( $args['line_items'][ $item_id ]['refund_tax'] ) ? array_filter( (array) $args['line_items'][ $item_id ]['refund_tax'] ) : [];

					if ( empty( $qty ) && empty( $refund_total ) && empty( $args['line_items'][ $item_id ]['refund_tax'] ) ) {
						continue;
					}

					// array of order id and product id which were refunded.
					// later to be used for revoking download permission.
					// checking if the item is a product, as we only need to revoke download permission for products.
					if ( $item->is_type( 'line_item' ) ) {
						$refunded_order_and_products[ $item_id ] = [
							'order_id'   => $order->get_id(),
							'product_id' => $item->get_product_id(),
						];
					}

					$class         = get_class( $item );
					$refunded_item = new $class( $item );
					$refunded_item->set_id( 0 );
					$refunded_item->add_meta_data( '_refunded_item_id', $item_id, true );
					$refunded_item->set_total( Formatting::format_refund_total( $refund_total ) );
					$refunded_item->set_taxes( [
						'total'    => array_map( [ Formatting::class, 'format_refund_total' ], $refund_tax ),
						'subtotal' => array_map( [ Formatting::class, 'format_refund_total' ], $refund_tax ),
					] );

					if ( is_callable( [ $refunded_item, 'set_subtotal' ] ) ) {
						$refunded_item->set_subtotal( Formatting::format_refund_total( $refund_total ) );
					}

					if ( is_callable( [ $refunded_item, 'set_quantity' ] ) ) {
						$refunded_item->set_quantity( $qty * - 1 );
					}

					$refund->add_item( $refunded_item );
					$refund_item_count += $qty;
				}
			}

			$refund->update_taxes();
			$refund->calculate_totals( false );
			$refund->set_total( $args['amount'] * - 1 );

			// this should remain after update_taxes(), as this will save the order, and write the current date to the db
			// so we must wait until the order is persisted to set the date.
			if ( isset( $args['date_created'] ) ) {
				$refund->set_date_created( $args['date_created'] );
			}

			/**
			 * Action hook to adjust refund before save.
			 */
			do_action( 'storeengine/create_refund', $refund, $args );

			if ( $refund->save() ) {
				if ( $args['refund_payment'] ) {
					$result = self::refund_payment( $order, $refund->get_amount(), $refund->get_reason() );

					if ( is_wp_error( $result ) ) {
						$refund->delete();

						return $result;
					}

					$refund->set_refunded_payment( true );
					$refund->save();
				}

				$cache_key = Caching::get_cache_prefix( 'orders' ) . 'refunds' . $order->get_id();
				wp_cache_delete( $cache_key, 'storeengine_orders' );
				wp_cache_delete( Caching::get_cache_prefix( 'orders' ) . 'total_refunded' . $order->get_id(), 'storeengine_orders' );

				if ( $args['restock_items'] ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
					// @TODO restock items.
				}

				// delete downloads that were refunded using order and product id, if present.
				// @TODO remove download permission.

				/**
				 * Trigger notification emails.
				 *
				 * Filter hook to modify the partially-refunded status conditions.
				 *
				 * @param bool $is_partially_refunded Whether the order is partially refunded.
				 * @param int $order_id The order id.
				 * @param int $refund_id The refund id.
				 */
				if ( apply_filters( 'storeengine/order_is_partially_refunded', ( $remaining_refund_amount - $args['amount'] ) > 0 || ( $order->has_free_item() && ( $remaining_refund_items - $refund_item_count ) > 0 ), $order->get_id(), $refund->get_id() ) ) {
					do_action( 'storeengine/order/partially_refunded', $order->get_id(), $refund->get_id() );
				} else {
					do_action( 'storeengine/order/fully_refunded', $order->get_id(), $refund->get_id() );

					/**
					 * Filter the status to set the order to when fully refunded.
					 *
					 * @param string $parent_status The status to set the order to when fully refunded.
					 * @param int $order_id The order ID.
					 * @param int $refund_id The refund ID.
					 */
					$parent_status = apply_filters( 'storeengine/order/fully_refunded_status', OrderStatus::REFUNDED, $order->get_id(), $refund->get_id() );

					if ( $parent_status ) {
						$order->update_status( $parent_status );
					}
				}
			}

			$order->set_date_modified( time() );
			$order->save();

			do_action( 'storeengine/order/refund_created', $refund, $args );
			do_action( 'storeengine/order/order_refunded', $order->get_id(), $refund->get_id() );
		} catch ( StoreEngineException $e ) {
			try {
				if ( isset( $refund ) && is_a( $refund, Refund::class ) ) {
					$refund->delete( true );
				}
			} catch ( StoreEngineException $ex ) {
				// @TODO Implement error logger.
				Helper::log_error( $ex );
			}

			return $e->toWpError();
		}

		return $refund;
	}

	/**
	 * Try to refund the payment for an order via the gateway.
	 *
	 * @param OrderClass $order Order instance.
	 * @param string $amount Amount to refund.
	 * @param string $reason Refund reason.
	 *
	 * @return bool|WP_Error
	 */
	public static function refund_payment( OrderClass $order, string $amount, string $reason = '' ) {
		try {
			$gateway = Payment_Gateways::get_instance()->get_gateway( $order->get_payment_method() ) ?? false;

			if ( ! $gateway ) {
				throw new StoreEngineException( __( 'The payment gateway for this order does not exist.', 'storeengine' ), 'gateway_not_found_for_order' );
			}

			if ( ! $gateway->supports( 'refunds' ) ) {
				throw new StoreEngineException( __( 'The payment gateway for this order does not support automatic refunds.', 'storeengine' ), 'gateway_does_not_support_refund' );
			}

			$result = $gateway->process_refund( $order->get_id(), $amount, $reason );

			if ( ! $result ) {
				throw new StoreEngineException( __( 'An error occurred while attempting to create the refund using the payment gateway API.', 'storeengine' ) );
			}

			if ( is_wp_error( $result ) ) {
				throw StoreEngineException::from_wp_error( $result );
			}

			return true;
		} catch ( StoreEngineException $e ) {
			return $e->toWpError();
		}
	}

	public static function get_order_item( int $id ) {
		return ( new OrderItemProduct( $id ) );
	}
}
