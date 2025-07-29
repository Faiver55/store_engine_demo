<?php

namespace StoreEngine\Addons\Stripe;

use StoreEngine;
use StoreEngine\Addons\Stripe\Constants\StripePaymentMethods;
use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusTransitionException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Stripe\PaymentIntent;
use StoreEngine\Utils\PaymentUtil;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class Hooks {
	protected static ?Hooks $instance = null;
	protected static GatewayStripe $gateway;
	public static function init( GatewayStripe $gateway ): ?Hooks {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$gateway  = &$gateway;

			if ( $gateway->is_available() ) {
				add_filter( 'storeengine/frontend_scripts_payment_method_data', [ __CLASS__, 'gateway_javascript_params' ] );

				// storeengine/checkout/after_place_order -> self::$instance -> add_stripe_meta
				// storeengine/checkout/after_pay_order -> self::$instance -> add_stripe_meta
				// storeengine/stripe/after_add_stripe_meta -> self::$instance -> verify_payment_update_order_status
			}

			add_filter( 'storeengine/subscription/my_payment_method', [ __CLASS__, 'maybe_render_subscription_payment_method' ], 10, 2 );
		}

		return self::$instance;
	}

	public static function gateway_javascript_params( $payment_method ) {
		if ( self::$gateway->is_available() ) {
			$is_production = self::$gateway->get_option( 'is_production', true );
			$key_type      = $is_production ? '' : 'test_';
			$cart          = StoreEngine::init()->get_cart();

			// Stripe Params.
			$payment_method['stripe'] = [
				'is_production'    => $is_production,
				'has_subscription' => $cart && $cart->has_subscription_product(),
				'has_trial'        => $cart && $cart->get_meta( 'has_trial' ),
				'publishable_key'  => self::$gateway->get_option( $key_type . 'publishable_key' ),
				'cart_total'       => StripeService::get_stripe_amount( Storeengine::init()->get_cart()->get_total( 'stripe-js' ) ),
			];
		}

		return $payment_method;
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @param string $payment_method_to_display the default payment method text to display
	 * @param Subscription $subscription the subscription details
	 *
	 * @return string the subscription payment method
	 */
	public static function maybe_render_subscription_payment_method( string $payment_method_to_display, Subscription $subscription ): string {
		$customer_user = $subscription->get_customer_id();

		// bail for other payment methods
		if ( $subscription->get_payment_method() !== self::$gateway->id || ! $customer_user ) {
			return $payment_method_to_display;
		}

		$stripe_source_id = $subscription->get_meta( '_stripe_source_id', true );

		$stripe_customer    = new StripeCustomer();
		$stripe_customer_id = $subscription->get_meta( '_stripe_customer_id', true );

		// If we couldn't find a Stripe customer linked to the subscription, fallback to the user meta data.
		if ( ! $stripe_customer_id || ! is_string( $stripe_customer_id ) ) {
			$user_id            = $customer_user;
			$stripe_customer_id = get_user_option( '_stripe_customer_id', $user_id );
			$stripe_source_id   = get_user_option( '_stripe_source_id', $user_id );
		}

		// If we couldn't find a Stripe customer linked to the account, fallback to the order meta data.
		if ( ( ! $stripe_customer_id || ! is_string( $stripe_customer_id ) ) && false !== $subscription->get_parent() ) {
			$parent_order       = $subscription->get_parent_order();
			$stripe_customer_id = $parent_order ? $parent_order->get_meta( '_stripe_customer_id', true ) : '';
			$stripe_source_id   = $parent_order ? $parent_order->get_meta( '_stripe_source_id', true ) : '';
		}

		if ( $stripe_customer_id ) {
			$stripe_customer->set_id( $stripe_customer_id );
		}

		$payment_method_to_display = __( 'N/A', 'storeengine' );

		try {
			// Retrieve all possible payment methods for subscriptions.
			foreach ( StripeCustomer::STRIPE_PAYMENT_METHODS as $payment_method_type ) {
				foreach ( $stripe_customer->get_payment_methods( $payment_method_type ) as $source ) {
					if ( $source->id !== $stripe_source_id ) {
						continue;
					}

					switch ( $source->type ) {
						case StripePaymentMethods::CARD:
							/* translators: 1) card brand 2) last 4 digits */
							$payment_method_to_display = sprintf( __( 'Via %1$s card ending in %2$s', 'storeengine' ), ( isset( $source->card->brand ) ? PaymentUtil::get_credit_card_type_label( $source->card->brand ) : __( 'N/A', 'storeengine' ) ), $source->card->last4 );
							break 3;
						case StripePaymentMethods::SEPA_DEBIT:
							/* translators: 1) last 4 digits of SEPA Direct Debit */
							$payment_method_to_display = sprintf( __( 'Via SEPA Direct Debit ending in %1$s', 'storeengine' ), $source->sepa_debit->last4 );
							break 3;
						case StripePaymentMethods::CASHAPP_PAY:
							/* translators: 1) Cash App Cashtag */
							$payment_method_to_display = sprintf( __( 'Via Cash App Pay (%1$s)', 'storeengine' ), $source->cashapp->cashtag );
							break 3;
						case StripePaymentMethods::LINK:
							/* translators: 1) email address associated with the Stripe Link payment method */
							$payment_method_to_display = sprintf( __( 'Via Stripe Link (%1$s)', 'storeengine' ), $source->link->email );
							break 3;
						case StripePaymentMethods::ACH:
							$payment_method_to_display = sprintf(
							/* translators: 1) account type (checking, savings), 2) last 4 digits of account. */
								__( 'Via %1$s Account ending in %2$s', 'storeengine' ),
								ucfirst( $source->us_bank_account->account_type ),
								$source->us_bank_account->last4
							);
							break 3;
						case StripePaymentMethods::BECS_DEBIT:
							$payment_method_to_display = sprintf(
							/* translators: last 4 digits of account. */
								__( 'BECS Direct Debit ending in %s', 'storeengine' ),
								$source->au_becs_debit->last4
							);
							break 3;
						case StripePaymentMethods::ACSS_DEBIT:
							$payment_method_to_display = sprintf(
							/* translators: 1) bank name, 2) last 4 digits of account. */
								__( 'Via %1$s ending in %2$s', 'storeengine' ),
								$source->acss_debit->bank_name,
								$source->acss_debit->last4
							);
							break 3;
						case StripePaymentMethods::BACS_DEBIT:
							/* translators: 1) the Bacs Direct Debit payment method's last 4 numbers */
							$payment_method_to_display = sprintf( __( 'Via Bacs Direct Debit ending in (%1$s)', 'storeengine' ), $source->bacs_debit->last4 );
							break 3;
						case StripePaymentMethods::AMAZON_PAY:
							/* translators: 1) the Amazon Pay payment method's email */
							$payment_method_to_display = sprintf( __( 'Via Amazon Pay (%1$s)', 'storeengine' ), $source->billing_details->email ?? '' );
							break 3;
					}
				}
			}
		} catch ( \Exception $e ) {
			StoreEngine\Utils\Helper::log_error( $e );
		}

		return $payment_method_to_display;
	}

	/**
	 * @param Order $order Order Object.
	 *
	 * @return void
	 *
	 * @throws StoreEngineInvalidOrderStatusException Throws if order status is unsupported.
	 * @throws StoreEngineInvalidOrderStatusTransitionException Throws if order status transition is invalid.
	 * @throws StoreEngineException
	 */
	public function add_stripe_meta( Order $order ) {
		// maybe deprecated...
		if ( 'stripe' !== $order->get_payment_method() ) {
			return;
		}

		$stripe_payment_intent_id = $order->get_meta( '_stripe_intent_id', true, 'edit' );
		if ( ! $stripe_payment_intent_id ) {
			return;
		}

		$stripe_service               = StripeService::init();
		$stripe_payment_intent_object = $stripe_service->get_payment_intent( $stripe_payment_intent_id );
		if ( ! $stripe_payment_intent_object instanceof PaymentIntent ) {
			return;
		}

		$order->set_transaction_id( $stripe_payment_intent_id );
		$order->add_meta_data( '_stripe_customer_id', $stripe_payment_intent_object->customer, true );
		$order->add_meta_data( '_stripe_payment_method_id', $stripe_payment_intent_object->payment_method, true );

		$order_context = new OrderContext( $order->get_status() );
		// update order status to processing
		$order_context->proceed_to_next_status( 'payment_initiate', $order );

		if ( $stripe_payment_intent_object->cancellation_reason ) {
			// translators: %s contains the reason of cancellation.
			$order->add_order_note( sprintf( __( 'Payment cancellation reason: %s', 'storeengine' ), $stripe_payment_intent_object->cancellation_reason ) );
			$order_context->proceed_to_next_status( 'payment_fail', $order );
		}

		$order->save();

		/**
		 * Fires after adding stripe metadata on Order.
		 *
		 * @param Order $order Order object.
		 */
		do_action( 'storeengine/stripe/after_add_stripe_meta', $order );
	}

	/**
	 * @param Order $order Order object.
	 *
	 * @return void
	 * @throws StoreEngineInvalidOrderStatusException
	 * @throws StoreEngineInvalidOrderStatusTransitionException
	 * @throws StoreEngineException
	 */
	public function verify_payment_update_order_status( Order $order ): void {
		// Check if the payment method is stripe.
		if ( 'stripe' !== $order->get_payment_method() ) {
			return;
		}

		$stripe_service               = StripeService::init();
		$stripe_payment_intent_object = $stripe_service->get_payment_intent( $order->get_transaction_id() );
		if ( ! $stripe_payment_intent_object instanceof PaymentIntent ) {
			return;
		}

		$order_context = new OrderContext( $order->get_status() );
		if ( 'succeeded' === $stripe_payment_intent_object->status ) {
			$order_context->proceed_to_next_status( 'payment_confirm', $order );
			if ( is_user_logged_in() ) {
				update_user_meta( get_current_user_id(), 'storeengine_stripe_customer_pm', '' );
			}
		} else {
			$order_context->proceed_to_next_status( 'payment_fail', $order );
		}

		$order->save();
	}
}
