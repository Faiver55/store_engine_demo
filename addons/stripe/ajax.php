<?php

namespace StoreEngine\Addons\Stripe;

use StoreEngine\Addons\Stripe\Constants\StripeIntentStatus;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Stripe\Exception\ApiErrorException;
use StoreEngine\Utils\Helper;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit(); // Exit if accessed directly.
}

class Ajax extends AbstractAjaxHandler {

	public static function init() {
		$self = new self();
		$self->dispatch_actions();
	}


	public function __construct() {
		$this->actions = [
			'payment_method/stripe/create-payment-intent' => [
				'callback'             => [ $this, 'create_payment_intent' ],
				'allow_visitor_action' => true,
				'fields'               => [
					'stripe_order_id'     => 'integer',
					'payment_method_type' => 'string',
				],
			],
			'payment_method/stripe/update_payment_intent' => [
				'callback'             => [ $this, 'update_payment_intent' ],
				'allow_visitor_action' => true,
				'fields'               => [
					'stripe_order_id'       => 'integer',
					'payment_intent_id'     => 'string',
					'save_payment_method'   => 'string',
					'selected_payment_type' => 'string',
				],
			],
			'payment_method/stripe/init_setup_intent'     => [
				'callback'             => [ $this, 'init_setup_intent' ],
				'allow_visitor_action' => true,
				'fields'               => [ 'payment_method_type' => 'string' ],
			],
			'payment_method/stripe/create_and_confirm_setup_intent' => [
				'callback' => [ $this, 'handle_create_and_confirm_setup_intent_request' ],
				'fields'   => [
					'payment-method'              => 'string',
					'payment-type'                => 'string',
					'update_subscription_methods' => 'boolean',
					'return_url'                  => 'string',
					'is_checkout'                 => 'boolean',
				],
			],
		];
	}

	public function create_payment_intent( array $payload ) {
		try {
			$order_id = absint( $payload['stripe_order_id'] ?? '' );

			if ( ! $order_id ) {
				wp_send_json_error( __( 'Invalid order ID', 'storeengine' ) );
			}

			$order = Helper::get_order( $order_id );

			if ( is_wp_error( $order ) ) {
				wp_send_json_error( $order );
			}


			if ( ! $order->get_id() ) {
				wp_send_json_error( new WP_Error( 'order_not_found', __( 'Order not found', 'storeengine' ), [ 'status' => 404 ] ) );
			}

			$stripe_customer_id = null;

			if ( is_user_logged_in() ) {
				$customer = new StripeCustomer( get_current_user_id() );

				// Update customer or create customer if customer does not exist.
				if ( ! $customer->get_id() ) {
					$stripe_customer_id = $customer->create_customer( [ 'order' => $order ] );
				} else {
					$stripe_customer_id = $customer->update_customer( [ 'order' => $order ] );
				}
			}

			// Request fresh intent everytime.
			$stripe_response = StripeService::init()->create_payment_intent( $order, $stripe_customer_id );

			$order->add_meta_data( '_stripe_intent_id', $stripe_response->id, true );
			$order->add_meta_data( '_stripe_currency', $stripe_response->currency, true );
			$order->save();

			wp_send_json_success( [
				'intent_id'     => $stripe_response->id,
				'client_secret' => $stripe_response->client_secret,
			] );
		} catch ( StoreEngineException $e ) {
			wp_send_json_error( $e->toWpError() );
		}
	}

	public function update_payment_intent() {
		wp_send_json_error( new WP_Error( 'not-implemented', __( 'Not implemented', 'storeengine' ), [ 'status' => 503 ] ) );
	}

	public function init_setup_intent() {
		wp_send_json_error( new WP_Error( 'not-implemented', __( 'Not implemented', 'storeengine' ), [ 'status' => 503 ] ) );
	}

	public function handle_create_and_confirm_setup_intent_request( array $payload ) {
		try {
			// @TODO implement rate limiter.

			$payment_method = $payload['payment-method'] ?? '';
			$payment_type   = $payload['payment-type'] ?? 'card';
			$is_checkout    = $payload['is_checkout'] ?? false;

			if ( ! $payment_method ) {
				throw new StoreEngineException( 'payment_method_missing', __( "We're not able to add this payment method. Please refresh the page and try again.", 'storeengine' ) );
			}

			// Determine the customer managing the payment methods, create one if we don't have one already.
			$user     = wp_get_current_user();
			$customer = new StripeCustomer( $user->ID );

			// Manually create the payment information array to create & confirm the setup intent.
			$payment_information = [
				'payment_method' => $payment_method,
				'customer'       => $customer->update_or_create_customer(),
				'return_url'     => Helper::get_account_endpoint_url( 'payment-methods' ),
				'use_stripe_sdk' => true,
				'confirm'        => true,
				// We want the user to complete the next steps via the JS elements. ref https://docs.stripe.com/api/setup_intents/create#create_setup_intent-use_stripe_sdk
			];

			if ( $is_checkout ) {
				$payment_information['return_url'] = Helper::get_checkout_url();
			}

			// If the user has requested to update all their subscription payment methods, add a query arg to the return URL so we can handle that request upon return.
			if ( ! empty( $payload['update_subscription_methods'] ) ) {
				$payment_information['return_url'] = add_query_arg( "storeengine-{$payment_type}-update-all-subscription-payment-methods", 'true', $payment_information['return_url'] );
			}

			$setup_intent = StripeService::init()->create_and_confirm_setup_intent( $payment_information );

			if ( empty( $setup_intent->status ) || ! in_array( $setup_intent->status, StripeIntentStatus::SUCCESSFUL_SETUP_INTENT_STATUSES, true ) ) {
				throw new StoreEngineException( 'stripe-error-setting-up-intent', __( 'There was an error adding this payment method. Please refresh the page and try again', 'storeengine' ), [ 'response' => $setup_intent ] );
			}

			wp_send_json_success(
				[
					'status'        => $setup_intent->status,
					'id'            => $setup_intent->id,
					'client_secret' => $setup_intent->client_secret,
					'next_action'   => $setup_intent->next_action,
					'payment_type'  => $payment_type,
					'return_url'    => rawurlencode( $payment_information['return_url'] ),
				],
				200
			);
		} catch ( StoreEngineException $e ) {
			// @TODO implement error logger.
			// error message Failed to create and confirm setup intent.
			Helper::log_error( $e );

			// Send back error so it can be displayed to the customer.
			wp_send_json_error( $e->toWpError() );
		}
	}
}

// End of file api.php.
