<?php
/**
 * Gateway Stripe.
 */

namespace StoreEngine\Addons\Stripe;

use Exception;
use StoreEngine;
use StoreEngine\Addons\Stripe\PaymentTokens\StripePaymentTokenCc;
use StoreEngine\Addons\Stripe\PaymentTokens\StripePaymentTokens;
use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Addons\Subscription\Classes\SubscriptionCollection;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusTransitionException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\Order\OrderItemProduct;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Classes\PaymentTokens\PaymentToken;
use StoreEngine\Classes\PaymentTokens\PaymentTokens;
use StoreEngine\Payment\Gateways\PaymentGateway;
use StoreEngine\Stripe\Exception\ApiErrorException;
use StoreEngine\Stripe\PaymentIntent;
use StoreEngine\Stripe\PaymentMethod;
use StoreEngine\Stripe\Source;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class GatewayStripe extends PaymentGateway {

	public int $index = 1;



	protected array $amex_unsupported_currencies = [
		'AFN',
		'AOA',
		'ARS',
		'BOB',
		'BRL',
		'CLP',
		'COP',
		'CRC',
		'CVE',
		'DJF',
		'FKP',
		'GNF',
		'GTQ',
		'HNL',
		'LAK',
		'MUR',
		'NIO',
		'PAB',
		'PEN',
		'PYG',
		'SHP',
		'SRD',
		'STD',
		'UYU',
		'XOF',
		'XPF',
	];


	public function __construct() {
		$this->setup();

		$this->init_admin_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->saved_cards = $this->get_option( 'saved_cards' );

		add_filter( 'storeengine/saved_payment_methods_list', [ $this, 'filter_saved_payment_methods_list' ], 10, 2 );
		// @TODO add-action storeengine/payment_gateway/$this->id/settings_saved", [ $this, 'handle_webhook_setup' ]

		add_action( 'storeengine/subscription/scheduled_payment_' . $this->id, [ $this, 'process_scheduled_payment' ] );
	}

	public function handle_webhook_setup( &$self ) {
		// @TODO implement webhook for capture delayed payments.
	}

	/**
	 * Removes all saved payment methods when the setting to save cards is disabled.
	 *
	 * @param array $list List of payment methods passed from wc_get_customer_saved_methods_list().
	 * @param int|string $customer_id The customer to fetch payment methods for.
	 *
	 * @return array  Filtered list of customers payment methods.
	 */
	public function filter_saved_payment_methods_list( array $list, $customer_id ): array {
		if ( ! $this->saved_cards ) {
			return [];
		}

		return $list;
	}

	protected function setup() {
		$this->id                 = 'stripe';
		$this->icon               = apply_filters( 'storeengine/stripe_icon', Helper::get_assets_url( 'images/payment-methods/stripe-alt.svg' ) );
		$this->method_title       = __( 'Stripe', 'storeengine' );
		$this->method_description = __( 'Stripe works by adding payment fields on the checkout and then sending the details to Stripe for verification.', 'storeengine' );
		$this->has_fields         = true;
		$this->verify_config      = true;
		$this->supports           = [
			'products',
			'refunds',
			// Subscriptions features.
			'subscriptions',
			'multiple_subscriptions',
			'subscription_cancellation',
			'subscription_reactivation',
			'subscription_suspension',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change_admin',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change',
			// Saved cards features.
			'tokenization',
			'add_payment_method',
		];
	}

	/**
	 * Verify Config.
	 *
	 * @param array $config
	 *
	 * @return void
	 * @throws StoreEngineException
	 */
	public function verify_config( array $config ) {
		$is_production = $config['is_production'] ?? true;
		if ( $is_production ) {
			$publishable_key = $config['publishable_key'] ?? '';
			$secret_key      = $config['secret_key'] ?? '';
		} else {
			$publishable_key = $config['test_publishable_key'] ?? '';
			$secret_key      = $config['test_secret_key'] ?? '';
		}

		if ( ! $publishable_key ) {
			throw new StoreEngineException( __( 'Stripe Publishable Key is required.', 'storeengine' ), 'publishable-key-is-required', 400 );
		}

		if ( ! $secret_key ) {
			throw new StoreEngineException( __( 'Stripe Secret Key is required.', 'storeengine' ), 'secret-key-is-required', 400 );
		}

		if ( ! $this->is_currency_supported() ) {
			throw new StoreEngineException(
				sprintf(
				/* translators: %1$s the shop currency, %2$s the PayPal currency support page link opening HTML tag, %3$s the link ending HTML tag. */
					esc_html__(
						'Attention: Your current StoreEngine store currency (%1$s) is not supported by Stripe. Please update your store currency to one that is supported by Stripe to ensure smooth transactions. Visit the %2$sStripe currency support page%3$s for more information on supported currencies.',
						'storeengine'
					),
					esc_html( Formatting::get_currency() ),
					'<a href="' . esc_url( 'https://docs.stripe.com/currencies#presentment-currencies' ) . '" target="_blank">',
					'</a>'
				),
				'currency-not-supported',
				null,
				400
			);
		}

		$result = StripeService::validate_publishable_key( $publishable_key );

		if ( ! $result ) {
			throw new StoreEngineException( __( 'Stripe Publishable Key Is Invalid! Please update publishable key.', 'storeengine' ), 'stripe-publishable-key-is-invalid', 400 );
		}

		$account_id = StripeService::validate_keys( $secret_key );

		if ( is_wp_error( $account_id ) ) {
			throw new StoreEngineException( esc_html__( 'Stripe Secret Key Is Invalid! Please update secret key.', 'storeengine' ), 'stripe-secret-key-is-invalid', 400 );
		}
	}

	public function is_currency_supported( string $currency = null ): bool {
		if ( ! $currency ) {
			$currency = Formatting::get_currency();
		}

		return in_array( $currency, StripeService::get_supported_currencies(), true );
	}

	public function is_available(): bool {
		if ( Helper::is_add_payment_method_page() && ! $this->saved_cards ) {
			return false;
		}

		if ( Helper::is_request( 'admin' ) && Helper::is_request( 'ajax' ) ) {
			return parent::is_available();
		}

		if ( Helper::is_request( 'ajax' ) && isset( $_REQUEST['payment_method'], $_REQUEST['storeengine-stripe-setup-intent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return parent::is_available();
		}

		if ( ! Helper::is_dashboard() && StoreEngine::init()->cart ) {
			if ( ! $this->is_currency_supported() ) {
				return false;
			}
		}

		return parent::is_available();
	}

	public function validate_minimum_order_amount( $order ) {
		if ( $order->get_total() * 100 < StripeService::get_minimum_amount() ) {
			/* translators: 1) amount (including currency symbol) */
			throw new StoreEngineException(
				sprintf(
					__( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'storeengine' ),
					Formatting::price( StripeService::get_minimum_amount() / 100 )
				),
				'did-not-meet-minimum-amount'
			);
		}
	}

	protected function init_admin_fields() {
		$this->admin_fields = [
			'title'                => [
				'label'    => __( 'Title', 'storeengine' ),
				'type'     => 'safe_text',
				'tooltip'  => __( 'Payment method description that the customer will see on your checkout.', 'storeengine' ),
				'default'  => __( 'Debit/Credit Card', 'storeengine' ),
				'priority' => 0,
			],
			'description'          => [
				'label'    => __( 'Description', 'storeengine' ),
				'type'     => 'textarea',
				'tooltip'  => __( 'Payment method description that the customer will see on your website.', 'storeengine' ),
				'priority' => 0,
			],
			'is_production'        => [
				'label'    => __( 'Is Live Mode?', 'storeengine' ),
				'tooltip'  => __( 'Enable Stripe Live (Production) Mode.', 'storeengine' ),
				'type'     => 'checkbox',
				'default'  => true,
				'priority' => 0,
			],
			'publishable_key'      => [
				'label'        => __( 'Publishable Key', 'storeengine' ),
				'type'         => 'text',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => true ],
				'autocomplete' => 'none',
				'required'     => true,
			],
			'secret_key'           => [
				'label'        => __( 'Secret Key', 'storeengine' ),
				'type'         => 'text',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => true ],
				'autocomplete' => 'none',
				'required'     => true,
			],
			'test_publishable_key' => [
				'label'        => __( 'Publishable Key (Sandbox)', 'storeengine' ),
				'type'         => 'text',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => false ],
				'autocomplete' => 'none',
				'required'     => true,
			],
			'test_secret_key'      => [
				'label'        => __( 'Secret Key (Sandbox)', 'storeengine' ),
				'type'         => 'text',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => false ],
				'autocomplete' => 'none',
				'required'     => true,
			],
			'saved_cards'          => [
				'title'       => __( 'Saved Cards', 'storeengine' ),
				'label'       => __( 'Enable Payment via Saved Cards', 'storeengine' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Stripe servers, not on your store.', 'storeengine' ),
				'default'     => true,
			],
		];
	}

	public function payment_fields() {
		$user                 = wp_get_current_user();
		$user_email           = '';
		$description          = $this->get_description();
		$description          = ! empty( $description ) ? $description : '';
		$firstname            = '';
		$lastname             = '';
		$display_tokenization = $this->supports( 'tokenization' ) && Helper::is_checkout() && $this->saved_cards;

		if ( $user && $user->ID ) {
			$user_email = get_user_meta( $user->ID, 'billing_email', true );
			$user_email = $user_email ?: $user->user_email;
			$firstname  = $user->user_firstname;
			$lastname   = $user->user_lastname;
		}

		if ( ! $this->get_option( 'is_production', true ) ) {
			/** @noinspection HtmlUnknownTarget */
			$description .= ' ' . sprintf(
				/* translators: %s: Link to Stripe test mode testing guide */
					__( 'TEST MODE ENABLED. In test mode, you can use the card number 4242424242424242 with any CVC and a valid expiration date or check the <a href="%s" target="_blank" rel="noopener noreferrer">Testing Stripe documentation</a> for more card numbers.', 'storeengine' ),
					'https://docs.stripe.com/testing'
				);
		}

		ob_start();
		?>
		<div class="storeengine-payment-method-description storeengine-mb-4">
			<?php
			// KSES is running within get_description, but not here since there may be custom HTML returned by extensions.
			echo wpautop( wptexturize( $description ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>
		<?php
		if ( $display_tokenization ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
		}
		?>
		<fieldset
			id="storeengine-<?php echo esc_attr( $this->id ); ?>-cc-form"
			class="storeengine-credit-card-form storeengine-payment-form storeengine-mt-4"
			data-email="<?php echo esc_attr( $user_email ); ?>"
			data-full-name="<?php echo esc_attr( trim( $firstname . ' ' . $lastname ) ); ?>"
			data-currency="<?php echo esc_attr( strtolower( Formatting::get_currency() ) ); ?>"
			style="background:transparent;border:none;padding:0;"
		>
			<div id="storeengine-stripe-card-element" class="storeengine-stripe-elements-field">
				<!-- A Stripe Element will be inserted here by js. -->
			</div>
		</fieldset>
		<?php

		if ( $this->is_saved_cards_enabled() ) {
			$force_save_payment = ( $display_tokenization && ! apply_filters( 'storeengine/stripe/display_save_payment_method_checkbox', $display_tokenization ) ) || Helper::is_add_payment_method_page();
			$this->save_payment_method_checkbox( $force_save_payment );
		}

		ob_end_flush();
	}

	public function save_payment_method_checkbox( $force_checked = false ) {
		$id = 'storeengine-' . $this->id . '-new-payment-method';
		?>
		<fieldset class="storeengine-save-new-payment-method--wrapper storeengine-my-2"
				  style="background:transparent;border:none;padding:0;<?php echo $force_checked ? 'display:none' : ''; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>">
			<p class="storeengine-save-new-payment-method">
				<input id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $id ); ?>"
					   name="<?php echo esc_attr( $id ); ?>" type="checkbox" value="true"
					   style="width:auto;display:unset" <?php echo $force_checked ? 'checked' : ''; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?> />
				<label for="<?php echo esc_attr( $id ); ?>"
					   style="display:inline;"><?php echo esc_html( apply_filters( 'storeengine/stripe_save_to_account_text', __( 'Save payment information to my account for future purchases.', 'storeengine' ) ) ); ?></label>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Get WC User from WC Order.
	 *
	 * @param Order $order
	 *
	 * @return WP_User
	 */
	public function get_user_from_order( $order ) {
		$user = $order->get_user();
		if ( false === $user ) {
			$user = wp_get_current_user();
		}

		return $user;
	}

	/**
	 * Get WC Stripe Customer from WC Order.
	 *
	 * @param Order $order
	 *
	 * @return StripeCustomer
	 * @throws StoreEngineException
	 */
	public function get_stripe_customer_from_order( Order $order ): StripeCustomer {
		$user = $this->get_user_from_order( $order );

		return new StripeCustomer( $user->ID );
	}

	/**
	 * Returns true if a payment is needed for the current cart or order.
	 * Pre-Orders and Subscriptions may not require an upfront payment, so we need to check whether
	 * or not the payment is necessary to decide for either a setup intent or a payment intent.
	 *
	 * @param Order $order The order ID being processed.
	 *
	 * @return bool Whether a payment is necessary.
	 */
	public function is_payment_needed( Order $order ): bool {
		return 0 < StripeService::get_stripe_amount( $this->get_total_payment( $order ), $order->get_currency() );
	}

	/**
	 * @param Order $order
	 *
	 * @return array|WP_Error
	 * @throws StoreEngineException
	 * @throws StoreEngineInvalidOrderStatusTransitionException
	 */
	public function process_payment( Order $order ) {
		$payment_needed     = $this->is_payment_needed( $order );
		$using_saved_method = false;
		// Check for selected token.
		$selected_payment_token = wp_unslash( $_POST['storeengine-stripe-payment-token'] ?? 'new' );  // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$force_save_source      = $this->has_subscription( $order ) || 'new' === $selected_payment_token && isset( $_POST['storeengine-stripe-new-payment-method'] ) && $_POST['storeengine-stripe-new-payment-method']; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// @FIXME force_save_source always returning true (has_subscription returning true for non-subscription item.

		if ( $payment_needed ) {
			$this->validate_minimum_order_amount( $order );
		}


		try {
			$order_context    = new OrderContext( $order->get_status() );
			$has_subscription = StoreEngine::init()->get_cart()->get_meta( 'has_subscription' ) ?? false;
			$has_trial        = StoreEngine::init()->get_cart()->get_meta( 'has_trial' ) ?? false;

			if ( $has_subscription && $has_trial && ! $payment_needed ) {
				if ( 'new' === $selected_payment_token ) {
					$result = $this->add_payment_method( Formatting::clean( wp_unslash( $_POST ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

					if ( ! empty( $result['error'] ) ) {
						throw new StoreEngineException( $result['error'], 'processing_error' );
					}

					$source = StripeService::init()->get_payment_method( $result['token']->get_token( 'process_trial_checkout' ), false );
				} else {
					$token = PaymentTokens::get( $selected_payment_token );

					if ( ! $token ) {
						throw new StoreEngineException( __( 'Payment token not found.', 'storeengine' ), 'payment-token-not-found', [
							'status' => 404,
							'token'  => $selected_payment_token,
						] );
					}

					$source = StripeService::init()->get_payment_method( $token->get_token( 'process_trial_checkout' ), false );
				}

				// Set order as paid
				$order->set_paid_status( 'paid' );
				$order_context->proceed_to_next_status( 'process_order', $order, [ 'note' => _x( 'Payment not needed.', 'Stripe payment method', 'storeengine' ) ] );

				$order->delete_meta_data( StripeOrder::META_STRIPE_PAYMENT_AWAITING_ACTION );
				$order->add_meta_data( '_stripe_payment_method', $source->type, true );
				$order->add_meta_data( '_stripe_customer_id', $source->customer, true );
				$order->add_meta_data( '_stripe_source_id', $source->id, true );
				$order->save();

				$this->maybe_update_source_on_subscription_order( $order, $source, $source->type );

				return [
					'result'   => 'success',
					'redirect' => $order->get_checkout_order_received_url(),
				];
			}

			/**
			 * Fires before stripe intent creation.
			 *
			 * @param Order $order Order object.
			 */
			do_action( 'storeengine/api/stripe/before_capture_payment', $order );

			$customer = $this->get_stripe_customer_from_order( $order );

			// Using saved payment method.
			if ( $selected_payment_token && 'new' !== $selected_payment_token ) {
				$force_save_source = false;
				$token             = PaymentTokens::get( $selected_payment_token );

				if ( ! $token ) {
					throw new StoreEngineException( __( 'Payment token not found.', 'storeengine' ), 'payment-token-not-found', [
						'status' => 404,
						'token'  => $selected_payment_token,
					] );
				}

				$intent             = StripeService::init()->create_payment_intent( $order, $customer->get_id(), $token->get_token( 'payment_intent' ) );
				$using_saved_method = true;
				$order->update_meta_data( '_stripe_intent_id', $intent->id );
			} else {
				// Check whether there is an existing intent.
				// This function can only deal with *payment* intents
				$payment_intent_id = $order->get_meta( '_stripe_intent_id', true, 'edit' );
				$payment_intent_id = $payment_intent_id ?: sanitize_text_field( wp_unslash( $_POST['payment_intent_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

				if ( ! $payment_intent_id ) {
					throw new StoreEngineException( __( 'Stripe payment intent id is missing!', 'storeengine' ), 'payment-intent-missing' );
				}

				$intent = StripeService::init()->get_payment_intent( $payment_intent_id );
			}

			// Newly created customer (guest checkout).
			if ( ! $customer->get_id() ) {
				$customer->set_id( $intent->customer );
				$customer->update_id_in_meta( $intent->customer );
				$customer->clear_cache();
				$customer->update_customer( [ 'order' => $order ] );
			}

			if ( $payment_needed ) {
				$response = $this->stripe_capture_payment( $order, $intent, $order_context );
				if ( is_wp_error( $response ) ) {
					return $response;
				}
			} else {
				$order->set_paid_status( 'paid' );
				$order_context->proceed_to_next_status( 'process_order', $order, [ 'note' => _x( 'Payment not needed.', 'Stripe payment method', 'storeengine' ) ] );
				$order->delete_meta_data( StripeOrder::META_STRIPE_PAYMENT_AWAITING_ACTION );
			}

			//$source = StripeService::init()->get_payment_method( $intent->payment_method, false );
			$source = StripeService::init()->get_payment_method( $response->payment_method, false );

			$order->add_meta_data( '_stripe_response_id', $response->id, true );
			$order->add_meta_data( '_stripe_currency', $response->currency, true );
			$order->add_meta_data( '_stripe_payment_method', $source->type, true );
			$order->add_meta_data( '_stripe_customer_id', $source->customer, true );
			$order->add_meta_data( '_stripe_source_id', $source->id, true );
			$order->add_meta_data( StripeOrder::META_STRIPE_CHARGE_CAPTURED, 'yes', true );
			$order->save();

			if ( $force_save_source && $source ) {
				$this->save_payment_method( $source );
			}

			if ( $this->has_subscription( $order ) ) {
				$this->maybe_update_source_on_subscription_order( $order, $source, $source->type );
			}

			/**
			 * Fires after stripe intent creation.
			 *
			 * @param PaymentIntent|WP_Error $result Result.
			 * @param Order $order Order object.
			 */
			do_action( 'storeengine/api/stripe/after_capture_payment', $response, $order );

			if ( $using_saved_method && $source ) {
				$this->update_stripe_payment_source( $order, $source );
			}

			return [
				'result'   => 'success',
				'redirect' => $order->get_checkout_order_received_url(),
			];
		} catch ( StoreEngineException $e ) {
			$order->update_status(
				OrderStatus::PAYMENT_FAILED,
				/* translators: %s. Error details. */
				sprintf( __( 'Payment failed. Error: %s', 'storeengine' ), $e->getMessage() )
			);

			throw $e;
		} catch ( Exception $e ) {
			$order->update_status(
				OrderStatus::PAYMENT_FAILED,
				/* translators: %s. Error details. */
				sprintf( __( 'Payment failed. Error: %s', 'storeengine' ), $e->getMessage() )
			);

			throw new StoreEngineException( $e->getMessage(), 'processing_error', null, 0, $e );
		}
	}

	protected function stripe_capture_payment( Order $order, $intent, $order_context ) {
		// Capture the payment.
		$response = StripeService::init()->capture_payment( $intent->id );

		try {
			$charge  = StripeService::init()->getClient()->charges->retrieve( $response->latest_charge );
			$outcome = $charge->outcome->type ?? null;

			if ( 'manual_review' === $outcome ) {
				$order->set_transaction_id( $response->id ); // Save the transaction ID to link the order to the Stripe charge ID. This is to fix reviews that result in refund.
				$order->set_paid_status( 'on_hold' );
				// Keep the order on hold for admin review.
				$order_context->proceed_to_next_status( 'hold_order', $order, [
					'note'           => __( 'Payment required review.', 'storeengine' ),
					'transaction_id' => $response->id,
				] );
			} elseif ( in_array( $outcome, [ 'succeeded', 'authorized' ], true ) ) {
				$order->set_paid_status( 'paid' );
				$order_context->proceed_to_next_status( 'process_order', $order, [
					/* translators: transaction id */
					'note'           => sprintf( __( 'Stripe charge complete (Charge ID: %s)', 'storeengine' ), $response->id ),
					'transaction_id' => $response->id,
				] );
				$order->save();
			} else {
				if ( isset( $charge->outcome->seller_message ) ) {
					$message = sprintf(
						'Reason: %s, Risk Level: %s, Advice: %s',
						esc_html( $charge->outcome->seller_message ),
						isset( $charge->outcome->risk_level ) ? esc_html( ucfirst( $charge->outcome->risk_level ) ) : esc_html__( 'Unknown', 'storeengine' ),
						isset( $charge->outcome->advice_code ) ? esc_html( ucwords( str_replace( [ '-' ], ' ', $charge->outcome->advice_code ) ) ) : esc_html__( 'Unknown', 'storeengine' ),
					);
				} else {
					$message = __( 'Charge not successful.', 'storeengine' );
				}

				$order->set_paid_status( 'failed' );
				$order_context->proceed_to_next_status( 'payment_failed', $order, $message );
				$order->save();

				return new WP_Error( 'charge_failed', __( 'Charge failed or requires review.', 'storeengine' ) );
			}

			$balance = StripeService::init()->getClient()->balanceTransactions->retrieve( $charge->balance_transaction );

			// Fees and Net needs to both come from Stripe to be accurate as the returned
			// values are in the local currency of the Stripe account, not from WC.
			$fee_refund = ! empty( $balance->fee ) ? StripeService::format_balance_fee( $balance, 'fee' ) : 0;
			$net_refund = ! empty( $balance->net ) ? StripeService::format_balance_fee( $balance, 'net' ) : 0;

			// Current data fee & net. ... Calculation.
			$order->update_meta_data( StripeOrder::META_STRIPE_FEE, (float) $order->get_meta( StripeOrder::META_STRIPE_FEE ) + (float) $fee_refund );
			$order->update_meta_data( StripeOrder::META_STRIPE_NET, (float) $order->get_meta( StripeOrder::META_STRIPE_NET ) + (float) $net_refund );
		} catch ( Exception $e ) {
			// @TODO implement error logger
			Helper::log_error( $e );

			$order->set_paid_status( 'failed' );
			/* translators: %s. Error message. */
			$order_context->proceed_to_next_status( 'payment_failed', $order, sprintf( __( 'Error while capturing the payment. Error: %s', 'storeengine' ), $e->getMessage() ) );
		}

		return $response;
	}

	public function update_stripe_payment_source( Order $order, $source ) {
		try {
			StripeService::init()->update_payment_method( $source, [
				'billing_details' => [
					'address' => [
						'city'        => $order->get_billing_city(),
						'country'     => $order->get_billing_country(),
						'line1'       => $order->get_billing_address_1(),
						'line2'       => $order->get_billing_address_2(),
						'postal_code' => $order->get_billing_postcode(),
						'state'       => $order->get_billing_state(),
					],
					'email'   => $order->get_billing_email(),
					'name'    => trim( $order->get_formatted_billing_full_name() ),
					'phone'   => $order->get_billing_phone(),
				],
			] );
		} catch ( StoreEngineException $e ) {
			// @TODO implement error logger.
			Helper::log_error( $e );
		}
	}

	public function maybe_update_source_on_subscription_order( Order $order, $source, $stripe_gateway_type = '' ) {
		if ( ! Helper::get_addon_active_status( 'subscription' ) ) {
			return;
		}

		if ( SubscriptionCollection::order_contains_subscription( $order->get_id() ) ) {
			$subscriptions = SubscriptionCollection::get_subscriptions_for_order( $order->get_id() );
		} elseif ( SubscriptionCollection::order_contains_subscription( $order->get_id(), [ 'renewal' ] ) ) {
			$subscriptions = SubscriptionCollection::get_subscriptions_for_renewal_order( $order->get_id() );
		} else {
			$subscriptions = [];
		}

		foreach ( $subscriptions as $subscription ) {
			$subscription->update_meta_data( '_stripe_customer_id', $source->customer );
			$subscription->update_meta_data( '_stripe_source_id', $source->id );

			if ( ! empty( $stripe_gateway_type ) ) {
				$subscription->update_meta_data( '_stripe_payment_method', $stripe_gateway_type );
			}

			// Update the payment method.
			$subscription->set_payment_method( $this->id );

			$subscription->save();
		}
	}

	/**
	 * Process refund.
	 *
	 * @param int $order_id Order ID.
	 * @param float|string|null $amount Refund amount.
	 * @param string $reason Refund reason.
	 *
	 * @return bool|WP_Error True or false based on success, or a WP_Error object.
	 * @throws StoreEngineException
	 */
	public function process_refund( int $order_id, $amount = null, string $reason = '' ) {
		$order = Helper::get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		// Refund without an amount is a no-op, but required to succeed
		if ( '0.00' === sprintf( '%0.2f', $amount ?? 0 ) ) {
			return true;
		}

		$intent_data   = StripeService::init()->get_payment_intent( $order->get_transaction_id() );
		$refund_result = StripeService::init()->refund( $intent_data->latest_charge, $amount, $order );

		if ( is_wp_error( $refund_result ) ) {
			return $refund_result;
		}

		$refunded = 'succeeded' === $refund_result->status;

		if ( $refunded ) {
			$order->add_meta_data( '_stripe_refund_id', $refund_result->id, true );

			// Get history.
			$balance_history = StripeService::init()->get_balance_history( $order->get_transaction_id() );

			if ( ! is_wp_error( $balance_history ) ) {
				$currency = ! empty( $balance_transaction->currency ) ? strtoupper( $balance_transaction->currency ) : null;
				if ( $currency ) {
					$order->add_meta_data( '_stripe_currency', $currency, true );
				}
			}
		}

		$order->save();

		return $refunded;
	}

	/**
	 * @param array $payload
	 *
	 * @return array
	 * @throws ApiErrorException
	 * @throws StoreEngineException
	 */
	public function add_payment_method( array $payload ): array {
		if ( ! is_user_logged_in() ) {
			throw new StoreEngineException( __( 'No logged-in user found.', 'storeengine' ), 'user-must-be-logged-in' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $payload['storeengine-stripe-setup-intent'] ) ) {
			throw new StoreEngineException( __( 'Stripe setup intent is missing.', 'storeengine' ), 'setup-intent-missing' );
		}

		$user            = wp_get_current_user();
		$setup_intent_id = Formatting::clean( wp_unslash( $payload['storeengine-stripe-setup-intent'] ) );
		$setup_intent    = StripeService::init()->get_setup_intent( $setup_intent_id );


		if ( ! empty( $setup_intent->last_payment_error ) ) {
			throw new StoreEngineException( sprintf( 'Error fetching the setup intent (ID %s) from Stripe: %s.', $setup_intent_id, ! empty( $setup_intent->last_payment_error->message ) ? $setup_intent->last_payment_error->message : 'Unknown error' ), 'setup-intent-error' );
		}

		$payment_method_object = StripeService::init()->get_payment_method( $setup_intent->payment_method, false );
		$customer              = new StripeCustomer( $user->ID );
		$customer->clear_cache();

		// Check if a token with the same payment method details exist. If so, just updates the payment method ID and return.
		$found_token = StripePaymentTokens::get_duplicate_token( $payment_method_object, $user->ID, $this->id );

		// If we have a token found, update it and return.
		if ( $found_token ) {
			$token = $this->update_payment_token( $found_token, $payment_method_object->id );
		} else {
			// Create a new token if not.
			$token = $this->create_payment_token_for_user( $user->ID, $payment_method_object );
		}

		if ( ! is_a( $token, PaymentToken::class ) ) {
			throw new StoreEngineException( sprintf( 'New payment token is not an instance of PaymentToken. Token: %s.', print_r( $token, true ) ), 'failed-to-save-token' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}

		do_action( 'storeengine/stripe/add_payment_method', $user->ID, $payment_method_object );

		return [
			'found'    => ! ! $found_token,
			'token'    => $token,
			'customer' => $customer,
			'result'   => 'success',
			'redirect' => Helper::get_account_endpoint_url( 'payment-methods' ),
		];
	}

	/**
	 * Updates a payment token.
	 *
	 * @param PaymentToken $token The token to update.
	 * @param string $payment_method_id The new payment method ID.
	 *
	 * @return PaymentToken
	 */
	public function update_payment_token( $token, $payment_method_id ) {
		$token->set_token( $payment_method_id );
		$token->save();

		return $token;
	}

	/**
	 * Create and return WC payment token for user.
	 *
	 * This will be used from the WC_Stripe_Payment_Tokens service
	 * as opposed to WC_Stripe_UPE_Payment_Gateway.
	 *
	 * @param string|int $user_id WP_User ID
	 * @param PaymentMethod $payment_method Stripe payment method object
	 *
	 * @return StripePaymentTokenCc
	 */
	public function create_payment_token_for_user( $user_id, PaymentMethod $payment_method ): StripePaymentTokenCc {
		$token = new StripePaymentTokenCc();
		$token->set_expiry_month( $payment_method->card->exp_month );
		$token->set_expiry_year( $payment_method->card->exp_year );
		$token->set_card_type( strtolower( $payment_method->card->display_brand ?? $payment_method->card->networks->preferred ?? $payment_method->card->brand ) );
		$token->set_last4( $payment_method->card->last4 );
		$token->set_gateway_id( 'stripe' );
		$token->set_token( $payment_method->id );
		$token->set_user_id( $user_id );
		$token->set_fingerprint( $payment_method->card->fingerprint );
		$token->save();

		return $token;
	}

	/**
	 * @param $payload
	 *
	 * Retrieves and returns the source_id for the given $_POST variables.
	 *
	 * @return object
	 * @throws StoreEngineException Error while attempting to retrieve the source_id.
	 */
	private function get_source_object_from_request( $payload ) {
		if ( empty( $payload['stripe_source'] ) && empty( $payload['stripe_token'] ) ) {
			throw new StoreEngineException( 'Missing stripe_source and stripe_token from the request.' );
		}

		$source = $payload['stripe_source'] ?? '';

		if ( ! empty( $source ) ) {
			// This method throws a WC_Stripe_Exception when there's an error. It's intended to be caught by the calling method.
			return StripeService::init()->get_payment_method( $source, false );
		}

		$stripe_token_as_source_id = isset( $payload['stripe_token'] ) ? Formatting::clean( wp_unslash( $payload['stripe_token'] ) ) : '';

		if ( ! empty( $stripe_token_as_source_id ) ) {
			// This method throws a WC_Stripe_Exception when there's an error. It's intended to be caught by the calling method.
			return StripeService::init()->get_payment_method( $stripe_token_as_source_id, false );
		}

		throw new StoreEngineException( "The source object couldn't be retrieved." );
	}

	/**
	 * Get source object by source ID.
	 *
	 * @param string $source_id The source ID to get source object for.
	 *
	 * @return PaymentMethod|Source|WP_Error
	 * @throws StoreEngineException
	 */
	public function get_source_object( string $source_id = '' ) {
		return StripeService::init()->get_payment_method( $source_id, false );
	}

	/**
	 * Attaches a source to the Stripe Customer object if the source type needs manual attachment.
	 *
	 * SEPA sources need to be manually attached to the customer object as they use legacy source objects.
	 * Other reusable payment methods (eg cards), are attached to the customer object via the setup/payment intent.
	 *
	 * @param PaymentMethod|Source $source The source object to attach.
	 * @param ?StripeCustomer $customer The customer object to attach the source to. Optional.
	 *
	 * @return bool True if the source was successfully attached to the customer.
	 * @throws StoreEngineException If the source could not be attached to the customer.
	 */
	private function maybe_attach_source_to_customer( $source, ?StripeCustomer $customer = null ) {
		if ( ! isset( $source->type ) || 'sepa_debit' !== $source->type ) {
			return false;
		}

		if ( ! $customer ) {
			$customer = new StripeCustomer( get_current_user_id() );
		}

		$response = $customer->attach_source( $source->id );

		if ( is_wp_error( $response ) ) {
			throw StoreEngineException::from_wp_error( $response );
		}

		return true;
	}

	/**
	 * Attaches the given payment method to the currently logged-in user.
	 *
	 * @param PaymentMethod|Source $source_object The payment method to be attached.
	 *
	 * @throws StoreEngineException
	 */
	public function save_payment_method( $source_object ) {
		$customer = new StripeCustomer( get_current_user_id() );

		if ( $customer->get_user_id() && StripeService::is_reusable_payment_method( $source_object ) ) {
			$response = $customer->add_source( $source_object->id );

			if ( is_wp_error( $response ) ) {
				throw StoreEngineException::from_wp_error( $response );
			}
		}
	}

	public function process_scheduled_payment( $renewal_order ) {
		$this->process_subscription_payment( $renewal_order );
	}

	/**
	 * Process subscription payment.
	 *
	 * @param Order $renewal_order
	 *
	 * @return void
	 * @throws StoreEngineException
	 * @throws StoreEngineInvalidOrderStatusTransitionException
	 * @throws StoreEngineInvalidOrderStatusException
	 */
	public function process_subscription_payment( Order $renewal_order ) {
		try {
			$order_context = new OrderContext( $renewal_order->get_status() );

			if ( $this->is_payment_needed( $renewal_order ) ) {
				$customer_id = $renewal_order->get_meta( '_stripe_customer_id' );
				$source_id   = $renewal_order->get_meta( '_stripe_source_id' );
				$intent      = StripeService::init()->create_payment_intent( $renewal_order, $customer_id, $source_id );
				$renewal_order->update_meta_data( '_stripe_intent_id', $intent->id );
				$response = $this->stripe_capture_payment( $renewal_order, $intent, $order_context );

				if ( is_wp_error( $response ) ) {
					return;
				}

				$source = StripeService::init()->get_payment_method( $response->payment_method, false );

				$renewal_order->add_meta_data( '_stripe_response_id', $response->id, true );
				$renewal_order->add_meta_data( '_stripe_currency', $response->currency, true );
				$renewal_order->add_meta_data( '_stripe_payment_method', $source->type, true );
				$renewal_order->add_meta_data( '_stripe_customer_id', $source->customer, true );
				$renewal_order->add_meta_data( '_stripe_source_id', $source->id, true );
				$renewal_order->add_meta_data( StripeOrder::META_STRIPE_CHARGE_CAPTURED, 'yes', true );
				$renewal_order->save();

				$this->save_payment_method( $source );
				$this->update_stripe_payment_source( $renewal_order, $source );
				$this->maybe_update_source_on_subscription_order( $renewal_order, $source, $source->type );
			} else {
				$renewal_order->set_paid_status( 'paid' );
				$order_context->proceed_to_next_status( 'process_order', $renewal_order, [ 'note' => _x( 'Payment not needed.', 'Stripe payment method', 'storeengine' ) ] );
				$renewal_order->delete_meta_data( StripeOrder::META_STRIPE_PAYMENT_AWAITING_ACTION );
			}

			$renewal_order->save();
		} catch ( Exception $e ) {
			Helper::log_error( $e );

			/* translators: %s. Error details. */
			$renewal_order->update_status( OrderStatus::PAYMENT_FAILED, sprintf( __( 'Payment failed. Error: %s', 'storeengine' ), $e->getMessage() ) );

			throw $e;
		}
	}
}

// End of file gateway-stripe.php.
