<?php

namespace StoreEngine\Addons\Subscription\API\Controllers;

use WP_Error;
use WP_REST_Request;
use DateTimeImmutable;
use StoreEngine\Utils\Constants;
use StoreEngine\Addons\Subscription\Classes\Subscription as SubsModel;
use StoreEngine\Addons\Subscription\Traits\Scheduler;
use StoreEngine\Addons\Subscription\API\Schema\SubscriptionSchema;
use StoreEngine\Classes\Order;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Create extends Abstracts\SubscriptionController {
	use Traits\Helper, SubscriptionSchema, Scheduler;

	protected string $route = '';

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function create( WP_REST_Request $request ) {
		try {
			$order = new Order( $request['order_id'] ?? null );
		} catch ( Throwable $e ) {
			return rest_ensure_response( new WP_Error( 'invalid_order_id', __( 'Order not found.', 'storeengine' ), [ 'status' => 404 ] ) );
		}

		$meta_fields = $this->validate_meta_fields( $request );
		if ( is_object( $meta_fields ) ) {
			return $meta_fields;
		}

		if ( ( new DateTimeImmutable( $meta_fields['start_date'] ) ) > ( new DateTimeImmutable( 'now' ) ) ) {
			$request['status'] = Constants::SUBSCRIPTION_STATUS_PENDING;
		}
		// set subs data
		do_action( 'storeengine/before_create_subscription', $order );

		$subscription_instance = new SubsModel();
		$this->set_core_data( $request, $subscription_instance, $order );
		$this->set_address( __FUNCTION__, $request, $subscription_instance, $order );
		$this->set_meta_data( $subscription_instance, $meta_fields, $order );
		$this->set_items( $request, $subscription_instance, $order );
		$this->apply_coupons( $request, $subscription_instance, $order );

		// recalcilate
		$order->calculate();
		$subscription_instance->calculate();

		// save
		$order->save();
		$subscription_instance->save();

		// update cheduler
		$this->add_to_schedule( $subscription_instance );

		do_action( 'storeengine/after_create_subscription', $subscription_instance );

		$response = rest_ensure_response( [
			'message'         => __( 'Subscription successfully created!', 'storeengine' ),
			'subscription_id' => $subscription_instance->get_id(),
			'order_id'        => $order->get_id(),
		], [ 'status' => 201 ] );
		$response->add_links( $this->prepare_links( $subscription_instance, $request ) );

		return $response;
	}

	protected function apply_coupons( WP_REST_Request $request, SubsModel $subscription_instance, ?Order $order ): void {
		if ( count( $request['coupons'] ?? [] ) > 1 ) {
			foreach ( $request['coupons'] as $coupon ) {
				$coupon   = new Coupon( $coupon );
				$is_valid = $coupon->validate_coupon();
				if ( $is_valid ) {
					$order->apply_coupon(
						$coupon->coupon_code,
						$coupon->calculate( $order->get_total() ),
						[
							'coupon_id' => $coupon->get_id(),
						]
					);
					$subscription_instance->apply_coupon(
						$coupon->coupon_code,
						$coupon->calculate( $subscription_instance->get_total() ),
						[
							'coupon_id' => $coupon->get_id(),
						]
					);
				}
			}
			$order->calculate();
			$subscription_instance->calculate();
		}
	}

	protected function set_items( WP_REST_Request $request, SubsModel $subscription_instance, ?Order $order ): void {
		// add product to order, subs
		foreach ( $request['purchase_items'] ?? [] as $purchase_item ) {
			$order->add_product( $purchase_item['price_id'], $purchase_item['product_qty'] );
			$subscription_instance->add_product( $purchase_item['price_id'], $purchase_item['product_qty'] );
		}
	}

	protected function set_address( string $action, WP_REST_Request $request, SubsModel $subscription_instance, ?Order $order ): void {
		if ( ! is_callable( [ $this, "{$action}_args" ] ) ) {
			return;
		}

		$ret = call_user_func( [ $this, "{$action}_args" ] );

		foreach ( [ 'billing', 'shipping' ] as $addr_type ) {
			foreach ( $ret[ "{$addr_type}_address" ]['properties'] ?? [] as $key => $opt ) {
				$subscription_instance->{"set_{$addr_type}_{$key}"}( $request[ "{$addr_type}_address" ][ $key ] ?? $order->{"get_{$addr_type}_{$key}"}() );
			}
		}
	}

	protected function set_meta_data( SubsModel $subscription_instance, array $meta_fields, ?Order $order ): void {
		// add meta data
		$subscription_instance->set_trial( intval( $meta_fields['trial'] ) );
		$subscription_instance->set_trial_days( intval( $meta_fields['trial_days'] ) );
		$subscription_instance->set_initial_order_id( $order->get_id() );

		$subscription_instance->set_payment_duration( intval( $meta_fields['payment_duration'] ) );
		$subscription_instance->set_payment_duration_type( $meta_fields['payment_duration_type'] );

		$subscription_instance->set_start_date( $meta_fields['start_date'] );
		$subscription_instance->set_trial_end_date( $meta_fields['trial_end_date'] );
		$subscription_instance->set_next_payment_date( $meta_fields['next_payment_date'] );
		$subscription_instance->set_end_date( $meta_fields['end_date'] );
	}

	protected function set_core_data( WP_REST_Request $request, SubsModel $subscription_instance, ?Order $order ): void {
		$subscription_instance->set_parent_order_id( $order->get_id() );
		$subscription_instance->set_status( SubsModel::validate_status( $request['status'] ?? '' ) );
		$subscription_instance->set_currency( $request['currency'] ?? $order->get_currency() );
		$subscription_instance->set_order_email( $request['customer_email'] ?? $order->get_order_email() );
		$subscription_instance->set_prices_include_tax( $order->get_prices_include_tax() );
		$subscription_instance->set_discount_tax( $order->get_discount_tax() );
		$subscription_instance->set_shipping_tax( $order->get_shipping_tax() );
		$subscription_instance->set_cart_tax( $order->get_cart_tax() );
		$subscription_instance->set_shipping_tax_amount( $order->get_shipping_tax_amount() );
		$subscription_instance->set_discount_tax_amount( $order->get_discount_tax_amount() );
		$subscription_instance->set_date_created_gmt( current_time( 'mysql' ) );
		$subscription_instance->set_customer_id( $request['customer_id'] ?? $order->get_customer_id() );
		$subscription_instance->set_payment_method( $request['payment_method'] ?? $order->get_payment_method() );
		$subscription_instance->set_payment_method_title( $request['payment_method_title'] ?? $order->get_payment_method_title() );
		$subscription_instance->set_transaction_id( $order->get_transaction_id() );


		$subscription_instance->set_ip_address( $order->get_ip_address() );
		$subscription_instance->set_user_agent( $order->get_user_agent() );
		$subscription_instance->set_created_via( $order->get_created_via() );
		$subscription_instance->set_customer_note( $request['customer_note'] ?? $order->get_customer_note() );
		$subscription_instance->set_date_completed_gmt( $order->get_date_completed_gmt() );
		$subscription_instance->set_date_paid_gmt( $order->get_date_paid_gmt() );
		$subscription_instance->set_cart_hash( $order->get_cart_hash() );
		$subscription_instance->set_hash( $order->get_hash() );
		$subscription_instance->set_order_stock_reduced( $order->get_order_stock_reduced() );
		$subscription_instance->set_download_permissions_granted( $order->get_download_permissions_granted() );
		$subscription_instance->set_new_order_email_sent( $order->get_new_order_email_sent() );
		$subscription_instance->set_recorded_sales( $order->get_recorded_sales() );
		$subscription_instance->set_total_amount( $order->get_total_amount() );
	}


	protected function validate_meta_fields( WP_REST_Request $request ) {
		$start_date            = $request['start_date'] ?? null;
		$trial_end_date        = $request['trial_end_date'] ?? null;
		$next_payment_date     = $request['next_payment_date'] ?? null;
		$end_date              = $request['end_date'] ?? null;
		$trial                 = boolval( $request['trial'] ?? 0 );
		$trial_days            = absint( $request['trial_days'] ?? 0 );
		$payment_duration      = absint( $request['payment_duration'] ?? 0 );
		$payment_duration_type = $request['payment_duration_type'] ?? '';

		if ( empty( $start_date ) ) {
			return rest_ensure_response( new WP_Error( 'Start date field is required.', __( 'Start date field is required.', 'storeengine' ), [ 'status' => 422 ] ) );
		} else {
			try {
				$start_date = new DateTimeImmutable( $start_date );
			} catch ( Exception $e ) {
				return rest_ensure_response( new WP_Error( 'Invalid start date!', __( 'Invalid start date!', 'storeengine' ), [ 'status' => 422 ] ) );
			}
		}

		if ( empty( $next_payment_date ) ) {
			return rest_ensure_response( new WP_Error( 'Next payment date field is required.', __( 'Next payment date field is required.', 'storeengine' ), [ 'status' => 422 ] ) );
		} else {
			try {
				$next_payment_date = new DateTimeImmutable( $next_payment_date );
			} catch ( Exception $e ) {
				return rest_ensure_response( new WP_Error( 'Invalid Next payment date!', __( 'Invalid Next payment date!', 'storeengine' ), [ 'status' => 422 ] ) );
			}
		}

		if ( empty( $end_date ) ) {
			return rest_ensure_response( new WP_Error( 'End date field is required.', __( 'End date field is required.', 'storeengine' ), [ 'status' => 422 ] ) );
		} else {
			try {
				$end_date = new DateTimeImmutable( $end_date );
			} catch ( Exception $e ) {
				return rest_ensure_response( new WP_Error( 'Invalid end date!', __( 'Invalid end date!', 'storeengine' ), [ 'status' => 422 ] ) );
			}
		}

		if ( ! empty( $trial_end_date ) || $trial_days > 0 ) {
			try {
				if ( ! empty( $trial_end_date ) ) {
					$trial_end_date = new DateTimeImmutable( $trial_end_date );
				} else {
					$trial_end_date = $start_date->modify( "+{$trial_days} day" );
				}
			} catch ( Exception $e ) {
				return rest_ensure_response( new WP_Error( 'Invalid trial days date!', __( 'Invalid trial days date!', 'storeengine' ), [ 'status' => 422 ] ) );
			}

			if ( $trial_end_date >= $next_payment_date ) {
				return rest_ensure_response( new WP_Error( 'Next payment date must be greater than start date + trial days.', __( 'Next payment date must be greater than start date + trial days.', 'storeengine' ), [ 'status' => 422 ] ) );
			}

			if ( $trial_end_date >= $end_date ) {
				return rest_ensure_response( new WP_Error( 'End date must be greater than start date + trial dys.', __( 'End date must be greater than start date + trial dys.', 'storeengine' ), [ 'status' => 422 ] ) );
			}
		}

		if ( $start_date >= $next_payment_date ) {
			return rest_ensure_response( new WP_Error( 'Next payment date must be greater than start date.', __( 'Next payment date must be greater than start date.', 'storeengine' ), [ 'status' => 422 ] ) );
		}

		if ( $start_date >= $end_date ) {
			return rest_ensure_response( new WP_Error( 'End date must be greater than start date.', __( 'End date must be greater than start date.', 'storeengine' ), [ 'status' => 422 ] ) );
		}

		if ( $next_payment_date > $end_date ) {
			return rest_ensure_response( new WP_Error( 'End date must be greater than Next payment.', __( 'End date must be greater than Next payment.', 'storeengine' ), [ 'status' => 422 ] ) );
		}

		if ( $trial && $trial_end_date ) {
			try {
				$next_payment_date->modify( "+{$trial_days} day" );
			} catch ( Exception $e ) {
				return rest_ensure_response( new WP_Error( 'Invalid trial days date!', __( 'Invalid trial days date!', 'storeengine' ), [ 'status' => 422 ] ) );
			}
		}

		return [
			'start_date'            => $start_date->format( 'c' ),
			'trial_end_date'        => is_null( $trial_end_date ) ? null : $trial_end_date->format( 'c' ),
			'next_payment_date'     => $next_payment_date->format( 'c' ),
			'end_date'              => $end_date->format( 'c' ),
			'trial'                 => $trial,
			'trial_days'            => $trial_days,
			'payment_duration'      => $payment_duration,
			'payment_duration_type' => $payment_duration_type,
		];
	}

}
