<?php

namespace StoreEngine\Addons\Stripe;

use stdClass;
use StoreEngine\Addons\Stripe\PaymentTokens\StripePaymentTokenCc;
use StoreEngine\Addons\Stripe\PaymentTokens\StripePaymentTokenSepa;
use StoreEngine\Classes\Customer;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Payment\Gateways\PaymentGateway;
use StoreEngine\Stripe\Account;
use StoreEngine\Stripe\BankAccount;
use StoreEngine\Stripe\Card;
use StoreEngine\Stripe\Exception\ApiErrorException;
use StoreEngine\Stripe\PaymentMethod;
use StoreEngine\Stripe\Source;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_User;


if ( ! defined( 'ABSPATH' ) ) {
	exit(); // Exit if accessed directly.
}

class StripeCustomer {

	/**
	 * String prefix for Stripe payment methods request transient.
	 */
	const PAYMENT_METHODS_TRANSIENT_KEY = 'stripe_payment_methods_';

	/**
	 * Queryable Stripe payment method types.
	 */
	const STRIPE_PAYMENT_METHODS = [
		'card',
		'link',
		'sepa_debit',
		'cashapp',
		'us_bank_account',
		'bacs_debit',
	];

	/**
	 * Stripe customer ID
	 *
	 * @var string
	 */
	private string $id = '';

	/**
	 * WP User ID
	 *
	 * @var int
	 */
	private int $user_id = 0;

	/**
	 * Data from API
	 *
	 * @var ?\StoreEngine\Stripe\Customer
	 */
	private ?\StoreEngine\Stripe\Customer $customer_data = null;

	/**
	 * Constructor
	 *
	 * @param int|string $user_id The WP user ID
	 */
	public function __construct( $user_id = 0 ) {
		if ( $user_id ) {
			$this->set_user_id( $user_id );
			$this->set_id( $this->get_id_from_meta( $user_id ) );
		}
	}

	/**
	 * Get Stripe customer ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * Set Stripe customer ID.
	 *
	 * @param [type] $id [description]
	 */
	public function set_id( $id ) {
		$this->id = Formatting::clean( $id );
	}

	/**
	 * User ID in WordPress.
	 *
	 * @return int
	 */
	public function get_user_id(): int {
		return $this->user_id;
	}

	/**
	 * Set User ID used by WordPress.
	 *
	 * @param int|string $user_id
	 */
	public function set_user_id( $user_id ) {
		$this->user_id = absint( $user_id );
	}

	/**
	 * Get user object.
	 *
	 * @return WP_User|false
	 */
	protected function get_user() {
		return $this->get_user_id() ? get_user_by( 'id', $this->get_user_id() ) : false;
	}

	/**
	 * Store data from the Stripe API about this customer
	 *
	 * @param \StoreEngine\Stripe\Customer $data
	 */
	public function set_customer_data( \StoreEngine\Stripe\Customer $data ) {
		$this->customer_data = $data;
	}

	/**
	 * Generates the customer request, used for both creating and updating customers.
	 *
	 * @param array $args Additional arguments (optional).
	 *
	 * @return array
	 */
	protected function generate_customer_request( array $args = [] ): array {
		$user    = $this->get_user();
		$address = $user ? wp_json_encode( get_user_meta( $user->ID, Helper::DB_PREFIX . 'billing_address', true ), true ) : [];

		if ( $user ) {
			$billing_first_name = $address['first_name'] ?? '';
			$billing_last_name  = $address['last_name'] ?? '';

			// If billing first name does not exists try the user first name.
			if ( empty( $billing_first_name ) ) {
				$billing_first_name = get_user_meta( $user->ID, 'first_name', true );
			}

			// If billing last name does not exists try the user last name.
			if ( empty( $billing_last_name ) ) {
				$billing_last_name = get_user_meta( $user->ID, 'last_name', true );
			}

			// translators: %1$s First name, %2$s Second name, %3$s Username.
			$description = sprintf( __( 'Name: %1$s %2$s, Username: %3$s', 'storeengine' ), $billing_first_name, $billing_last_name, $user->user_login );

			$defaults = [
				'email'       => $user->user_email,
				'description' => $description,
			];
		} else {
			$billing_email      = $this->get_billing_data_field( 'billing_email', $args );
			$billing_first_name = $this->get_billing_data_field( 'billing_first_name', $args );
			$billing_last_name  = $this->get_billing_data_field( 'billing_last_name', $args );

			// translators: %1$s First name, %2$s Second name.
			$description = sprintf( __( 'Name: %1$s %2$s, Guest', 'storeengine' ), $billing_first_name, $billing_last_name );

			$defaults = [
				'email'       => $billing_email,
				'description' => $description,
			];
		}

		$billing_full_name = trim( $billing_first_name . ' ' . $billing_last_name );
		if ( ! empty( $billing_full_name ) ) {
			$defaults['name'] = $billing_full_name;
		}

		$metadata                      = [];
		$defaults['metadata']          = apply_filters( 'storeengine/stripe/customer_metadata', $metadata, $user );
		$defaults['preferred_locales'] = $this->get_customer_preferred_locale( $user );

		// Add customer address default values.
		$address_fields = [
			'line1'       => 'billing_address_1',
			'line2'       => 'billing_address_2',
			'postal_code' => 'billing_postcode',
			'city'        => 'billing_city',
			'state'       => 'billing_state',
			'country'     => 'billing_country',
		];
		foreach ( $address_fields as $key => $field ) {
			if ( $user ) {
				$defaults['address'][ $key ] = $address[ str_replace( 'billing_', '', $key ) ] ?? '';
			} else {
				$defaults['address'][ $key ] = $this->get_billing_data_field( $field, $args );
			}
		}

		if ( isset( $args['order'] ) ) {
			unset( $args['order'] );
		}

		return wp_parse_args( $args, $defaults );
	}

	/**
	 * Get value of billing data field, either from POST or order object.
	 *
	 * @param string $field Field name.
	 * @param array $args  Additional arguments (optional).
	 *
	 * @return string
	 */
	private function get_billing_data_field( string $field, array $args = [] ): string {
		$valid_fields = [
			'billing_email',
			'billing_first_name',
			'billing_last_name',
			'billing_address_1',
			'billing_address_2',
			'billing_postcode',
			'billing_city',
			'billing_state',
			'billing_country',
		];

		// Restrict field parameter to list of known billing fields.
		if ( ! in_array( $field, $valid_fields, true ) ) {
			return '';
		}

		// Prioritize POST data, if available.
		if ( isset( $_POST[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( 'billing_email' === $field ) {
				return filter_var( wp_unslash( $_POST[ $field ] ), FILTER_SANITIZE_EMAIL ); // phpcs:ignore WordPress.Security.NonceVerification
			}

			return filter_var( wp_unslash( $_POST[ $field ] ), FILTER_SANITIZE_SPECIAL_CHARS ); // phpcs:ignore WordPress.Security.NonceVerification
		} elseif ( isset( $args['order'] ) && $args['order'] instanceof Order ) {
			switch ( $field ) {
				case 'billing_email':
					return $args['order']->get_billing_email();
				case 'billing_first_name':
					return $args['order']->get_billing_first_name();
				case 'billing_last_name':
					return $args['order']->get_billing_last_name();
				case 'billing_address_1':
					return $args['order']->get_billing_address_1();
				case 'billing_address_2':
					return $args['order']->get_billing_address_2();
				case 'billing_postcode':
					return $args['order']->get_billing_postcode();
				case 'billing_city':
					return $args['order']->get_billing_city();
				case 'billing_state':
					return $args['order']->get_billing_state();
				case 'billing_country':
					return $args['order']->get_billing_country();
				default:
					return '';
			}
		}

		return '';
	}

	/**
	 * If customer does not exist, create a new customer. Else retrieve the Stripe customer through the API to check it's existence.
	 * Recreate the customer if it does not exist in this Stripe account.
	 *
	 * @return string Customer ID
	 *
	 * @throws StoreEngineException
	 */
	public function maybe_create_customer() {
		if ( ! $this->get_id() ) {
			$this->set_id( $this->create_customer() );
			return $this->get_id();
		}

		try {
			$response = StripeService::init()->getClient()->customers->retrieve( $this->get_id() );
		} catch ( ApiErrorException $e ) {
			if ( $e->getStripeCode() && $this->is_no_such_customer_error( $e->getStripeCode(), $e->getMessage() ) ) {
				// This can happen when switching the main Stripe account or importing users from another site.
				// Recreate the customer in this case.
				return $this->recreate_customer();
			}
			throw new StoreEngineException( $e->getMessage(), 'error-retrieving-stripe-customer', [
				'response' => [
					'http_status' => $e->getHttpStatus(),
					'requestId'   => $e->getRequestId(),
					'stripeCode'  => $e->getStripeCode(),
					'body'        => $e->getJsonBody() ?? $e->getHttpBody(),
				],
			] );
		}

		return $response->id;
	}

	/**
	 * Search for an existing customer in Stripe account by email and name.
	 *
	 * @param string $email Customer email.
	 * @param string $name  Customer name.
	 *
	 * @return ?\StoreEngine\Stripe\Customer
	 */
	public function get_existing_customer( string $email, string $name ): ?\StoreEngine\Stripe\Customer {
		try {
			$response = StripeService::init()->getClient()->customers->search( [ 'query' => 'name:\'' . $name . '\' AND email:\'' . $email . '\'' ] );
			return $response->data[0] ?? null;
		} catch ( ApiErrorException $e ) {
			return null;
		}
	}

	/**
	 * Create a customer via API.
	 *
	 * @param array $args
	 *
	 * @return string
	 * @throws StoreEngineException
	 */
	public function create_customer( array $args = [] ): string {
		$args = $this->generate_customer_request( $args );

		// For guest users, check if a customer already exists with the same email and name in Stripe account before creating a new one.
		if ( ! $this->get_id() && 0 === $this->get_user_id() ) {
			$response = $this->get_existing_customer( $args['email'], $args['name'] );
		}

		try {
			if ( empty( $response ) ) {
				$response = StripeService::init()->getClient()->customers->create( apply_filters( 'storeengine/stripe/create_customer_args', $args ) );
			} else {
				$response = StripeService::init()->getClient()->customers->update( $response->id, apply_filters( 'storeengine/stripe/update_customer_args', $args ) );
			}
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'error-creating-updating-stripe-customer', [
				'response' => [
					'http_status' => $e->getHttpStatus(),
					'requestId'   => $e->getRequestId(),
					'stripeCode'  => $e->getStripeCode(),
					'body'        => $e->getJsonBody() ?? $e->getHttpBody(),
				],
			] );
		}

		$this->set_id( $response->id );
		$this->clear_cache();
		$this->set_customer_data( $response );

		if ( $this->get_user_id() ) {
			$this->update_id_in_meta( $response->id );
		}

		do_action( 'storeengine/stripe/add_customer', $args, $response );

		return $response->id;
	}

	/**
	 * Updates the Stripe customer through the API.
	 *
	 * @param array $args     Additional arguments for the request (optional).
	 * @param bool $is_retry Whether the current call is a retry (optional, defaults to false). If true, then an exception will be thrown instead of further retries on error.
	 *
	 * @return string Customer ID
	 *
	 * @throws StoreEngineException
	 */
	public function update_customer( array $args = [], bool $is_retry = false ): string {
		if ( empty( $this->get_id() ) ) {
			throw new StoreEngineException( __( 'Attempting to update a Stripe customer without a customer ID.', 'storeengine' ), 'id_required_to_update_user' );
		}

		try {
			$args     = $this->generate_customer_request( $args );
			$args     = apply_filters( 'storeengine/stripe/update_customer_args', $args );
			$response = StripeService::init()->getClient()->customers->retrieve( $this->get_id() );
		} catch ( ApiErrorException $e ) {
			if ( $e->getStripeCode() && $this->is_no_such_customer_error( $e->getStripeCode(), $e->getMessage() ) ) {
				// This can happen when switching the main Stripe account or importing users from another site.
				// Recreate the customer in this case.
				$this->recreate_customer();
				return $this->update_customer( $args, true );
			}

			throw new StoreEngineException( $e->getMessage(), 'error-retrieving-stripe-customer', [
				'response' => [
					'http_status' => $e->getHttpStatus(),
					'requestId'   => $e->getRequestId(),
					'stripeCode'  => $e->getStripeCode(),
					'body'        => $e->getJsonBody() ?? $e->getHttpBody(),
				],
			] );
		}

		$this->clear_cache();
		$this->set_customer_data( $response );

		do_action( 'storeengine/stripe/update_customer', $args, $response );

		return $this->get_id();
	}

	/**
	 * Updates existing Stripe customer or creates new customer for User through API.
	 *
	 * @param array $args     Additional arguments for the request (optional).
	 * @param bool  $is_retry Whether the current call is a retry (optional, defaults to false). If true, then an exception will be thrown instead of further retries on error.
	 *
	 * @return string Customer ID
	 *
	 * @throws StoreEngineException
	 */
	public function update_or_create_customer( $args = [], $is_retry = false ) {
		if ( empty( $this->get_id() ) ) {
			return $this->recreate_customer( $args );
		} else {
			return $this->update_customer( $args, true );
		}
	}

	/**
	 * Checks to see if error is of invalid request
	 * error, and it is no such customer.
	 *
	 * @param string|null $error
	 * @param string|null $message
	 *
	 * @return bool
	 */
	public function is_no_such_customer_error( ?string $error = null, ?string $message = null ): bool {
		return ( 'resource_missing' === $error && preg_match( '/No such customer/i', $message ) );
	}

	/**
	 * Checks to see if error is of invalid request
	 * error, and it is no such customer.
	 *
	 * @param string|null $error
	 * @param string|null $message
	 *
	 * @return bool
	 */
	public function is_source_already_attached_error( ?string $error = null, ?string $message = null ): bool {
		return ( 'invalid_request_error' === $error && preg_match( '/already been attached to a customer/i', $message ) );
	}

	/**
	 * Add a source for this stripe customer.
	 *
	 * @param string $source_id
	 *
	 * @return WP_Error|string
	 */
	public function add_source( string $source_id ) {
		$response = StripeService::init()->get_payment_method( $source_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Add token to WooCommerce.
		$cc_token = false;

		if ( $this->get_user_id() ) {
			if ( ! empty( $response->type ) ) {
				switch ( $response->type ) {
					case 'alipay':
						break;
					case 'sepa_debit':
						$wc_token = new StripePaymentTokenSepa();
						$wc_token->set_token( $response->id );
						$wc_token->set_gateway_id( 'stripe_sepa' );
						$wc_token->set_last4( $response->sepa_debit->last4 );
						$wc_token->set_fingerprint( $response->sepa_debit->fingerprint );
						break;
					default:
						if ( StripeService::is_card_payment_method( $response ) ) {
							$cc_token = new StripePaymentTokenCc();
							$cc_token->set_token( $response->id );
							$cc_token->set_gateway_id( 'stripe' );
							$cc_token->set_card_type( strtolower( $response->card->brand ) );
							$cc_token->set_last4( $response->card->last4 );
							$cc_token->set_expiry_month( $response->card->exp_month );
							$cc_token->set_expiry_year( $response->card->exp_year );
							$cc_token->set_fingerprint( $response->card->fingerprint );
						}
						break;
				}
			}

			if ( $cc_token ) {
				$cc_token->set_user_id( $this->get_user_id() );
				$cc_token->save();
			}
		}

		$this->clear_cache();

		do_action( 'storeengine/stripe/add_source', $this->get_id(), $cc_token, $response, $source_id );

		return $response->id;
	}


	/**
	 *Attaches a source to the Stripe customer.
	 *
	 * @param string $source_id The ID of the new source.
	 *
	 * @return Account|BankAccount|Card|PaymentMethod|Source|WP_Error Either a source object, or a WP error.
	 * @throws StoreEngineException
	 */
	public function attach_source( string $source_id ) {
		try {
			if ( ! $this->get_id() ) {
				$this->set_id( $this->create_customer() );
			}

			return StripeService::init()->attach_payment_method_to_customer( $this->get_id(), $source_id );
		} catch ( StoreEngineException $e ) {
			$response = $e->get_data( 'response' );

			if ( $response && ! empty( $response['stripeCode'] ) ) {
				// It is possible the WC user once was linked to a customer on Stripe
				// but no longer exists. Instead of failing, lets try to create a
				// new customer.
				if ( $this->is_no_such_customer_error( $response['stripeCode'], $e->getMessage() ) ) {
					$recreate = $this->recreate_customer();
					if ( is_wp_error( $recreate ) ) {
						return $recreate;
					}

					return $this->attach_source( $source_id );
				} elseif ( $this->is_source_already_attached_error( $response['stripeCode'], $e->getMessage() ) ) {
					return StripeService::init()->get_payment_method( $source_id );
				}
			}

			return $e->toWpError();
		}
	}

	/**
	 * Get a customers saved sources using their Stripe ID.
	 *
	 * @return PaymentMethod[]
	 * @throws StoreEngineException
	 */
	public function get_sources(): array {
		if ( ! $this->get_id() ) {
			return [];
		}

		$sources = get_transient( 'stripe_sources_' . $this->get_id() );

		if ( false === $sources ) {
			try {
				$response = StripeService::init()->getClient()->customers->allPaymentMethods( $this->get_id(), [ 'limit' => 100 ] );

				if ( is_array( $response->data ) ) {
					$sources = $response->data;
				}

				set_transient( 'stripe_sources_' . $this->get_id(), $sources, DAY_IN_SECONDS );
			} catch ( ApiErrorException $e ) {
				throw new StoreEngineException( $e->getMessage(), 'error-retrieving-customer-payment-methods', [
					'response' => [
						'http_status' => $e->getHttpStatus(),
						'requestId'   => $e->getRequestId(),
						'stripeCode'  => $e->getStripeCode(),
						'body'        => $e->getJsonBody() ?? $e->getHttpBody(),
					],
				] );
			}
		}

		return $sources ?: [];
	}

	/**
	 * Gets saved payment methods for a customer using Intentions API.
	 *
	 * @param string $payment_method_type Stripe ID of payment method type
	 *
	 * @return array
	 * @throws StoreEngineException
	 */
	public function get_payment_methods( string $payment_method_type ) {
		if ( ! $this->get_id() ) {
			return [];
		}

		$payment_methods = get_transient( self::PAYMENT_METHODS_TRANSIENT_KEY . $payment_method_type . $this->get_id() );

		if ( false === $payment_methods ) {
			try {
				$args = [
					'customer' => $this->get_id(),
					'type'     => $payment_method_type,
					'limit'    => 100, // Maximum allowed value.
					'expand'   => [],
				];

				if ( 'sepa_debit' === $payment_method_type ) {
					$args['expand'][] = 'data.sepa_debit.generated_from.charge';
					$args['expand'][] = 'data.sepa_debit.generated_from.setup_attempt';
				}

				$response = StripeService::init()->getClient()->paymentMethods->all( $args );

				if ( is_array( $response->data ) ) {
					$payment_methods = $response->data;
				}

				set_transient( self::PAYMENT_METHODS_TRANSIENT_KEY . $payment_method_type . $this->get_id(), $payment_methods, DAY_IN_SECONDS );
			} catch ( ApiErrorException $e ) {
				throw new StoreEngineException( $e->getMessage(), 'error-retrieving-customer-all-payment-methods', [
					'response' => [
						'http_status' => $e->getHttpStatus(),
						'requestId'   => $e->getRequestId(),
						'stripeCode'  => $e->getStripeCode(),
						'body'        => $e->getJsonBody() ?? $e->getHttpBody(),
					],
				] );
			}
		}

		return $payment_methods ?: [];
	}

	/**
	 * Delete a source from stripe.
	 *
	 * @param string $source_id
	 *
	 * @throws StoreEngineException
	 */
	public function delete_source( $source_id ) {
		if ( empty( $source_id ) || ! $this->get_id() ) {
			return false;
		}

		try {
			$response = StripeService::detach_payment_method_from_customer( $this->get_id(), $source_id );

			$this->clear_cache();
			do_action( 'storeengine/stripe/delete_source', $this->get_id(), $response );
			return true;
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'error-deleting-stripe-customer-source', [
				'response' => [
					'http_status' => $e->getHttpStatus(),
					'requestId'   => $e->getRequestId(),
					'stripeCode'  => $e->getStripeCode(),
					'body'        => $e->getJsonBody() ?? $e->getHttpBody(),
				],
			] );
		}
	}

	/**
	 * Detach a payment method from stripe.
	 *
	 * @param string $payment_method_id
	 *
	 * @throws StoreEngineException
	 */
	public function detach_payment_method( $payment_method_id ) {
		if ( ! $this->get_id() ) {
			return false;
		}

		try {
			$response = StripeService::detach_payment_method_from_customer( $this->get_id(), $payment_method_id );
			$this->clear_cache();
			do_action( 'storeengine/stripe/detach_payment_method', $this->get_id(), $response );

			return true;
		} catch ( StoreEngineException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'error-detach-payment-source', [
				'response' => [
					'http_status' => $e->getHttpStatus(),
					'requestId'   => $e->getRequestId(),
					'stripeCode'  => $e->getStripeCode(),
					'body'        => $e->getJsonBody() ?? $e->getHttpBody(),
				],
			] );
		}
	}

	/**
	 * Set default source in Stripe
	 *
	 * @param string $source_id
	 *
	 * @return bool
	 * @throws StoreEngineException
	 */
	public function set_default_source( string $source_id ): bool {
		try {
			$response = StripeService::init()->getClient()->customers->update( $this->get_id(), [ 'default_source' => sanitize_text_field( $source_id ) ] );

			$this->clear_cache();

			do_action( 'storeengine/stripe/set_default_source', $this->get_id(), $response );
			return true;
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'error-setting-default-source', [
				'response' => [
					'http_status' => $e->getHttpStatus(),
					'requestId'   => $e->getRequestId(),
					'stripeCode'  => $e->getStripeCode(),
					'body'        => $e->getJsonBody() ?? $e->getHttpBody(),
				],
			] );
		}
	}

	/**
	 * Set default payment method in Stripe
	 *
	 * @param string $payment_method_id
	 *
	 * @return true
	 * @throws StoreEngineException
	 */
	public function set_default_payment_method( string $payment_method_id ): bool {
		try {
			$response = StripeService::init()->getClient()->customers->update( $this->get_id(), [
				'invoice_settings' => [
					'default_payment_method' => sanitize_text_field( $payment_method_id ),
				],
			] );

			$this->clear_cache();
			do_action( 'storeengine/stripe/set_default_payment_method', $this->get_id(), $response );

			return true;
		} catch ( ApiErrorException $e ) {
			throw new StoreEngineException( $e->getMessage(), 'error-setting-default-source', [
				'response' => [
					'http_status' => $e->getHttpStatus(),
					'requestId'   => $e->getRequestId(),
					'stripeCode'  => $e->getStripeCode(),
					'body'        => $e->getJsonBody() ?? $e->getHttpBody(),
				],
			] );
		}
	}

	/**
	 * Deletes caches for this users cards.
	 */
	public function clear_cache() {
		delete_transient( 'stripe_sources_' . $this->get_id() );
		delete_transient( 'stripe_customer_' . $this->get_id() );
		foreach ( self::STRIPE_PAYMENT_METHODS as $payment_method_type ) {
			delete_transient( self::PAYMENT_METHODS_TRANSIENT_KEY . $payment_method_type . $this->get_id() );
		}
		$this->customer_data = null;
	}

	/**
	 * Retrieves the Stripe Customer ID from the user meta.
	 *
	 * @param  int $user_id The ID of the WordPress user.
	 * @return string|bool  Either the Stripe ID or false.
	 */
	public function get_id_from_meta( $user_id ) {
		return get_user_option( '_stripe_customer_id', $user_id );
	}

	/**
	 * Updates the current user with the right Stripe ID in the meta table.
	 *
	 * @param string $id The Stripe customer ID.
	 */
	public function update_id_in_meta( $id ) {
		update_user_option( $this->get_user_id(), '_stripe_customer_id', $id, false );
	}

	/**
	 * Deletes the user ID from the meta table with the right key.
	 */
	public function delete_id_from_meta() {
		delete_user_option( $this->get_user_id(), '_stripe_customer_id', false );
	}

	/**
	 * Recreates the customer for this user.
	 *
	 * @param array $args Additional arguments for the request (optional).
	 *
	 * @return string ID of the new Customer object.
	 * @throws StoreEngineException
	 */
	private function recreate_customer( array $args = [] ) {
		$this->delete_id_from_meta();
		return $this->create_customer( $args );
	}

	/**
	 * Get the customer's preferred locale based on the user or site setting.
	 *
	 * @param bool|WP_User $user The user being created/modified.
	 * @return array The matched locale string wrapped in an array, or empty default.
	 */
	public function get_customer_preferred_locale( $user ): array {
		$locale = $this->get_customer_locale( $user );

		// Options based on Stripe locales.
		// https://support.stripe.com/questions/language-options-for-customer-emails
		$stripe_locales = [
			'ar'             => 'ar-AR',
			'da_DK'          => 'da-DK',
			'de_CH'          => 'de-DE',
			'de_CH_informal' => 'de-DE',
			'de_DE'          => 'de-DE',
			'de_DE_formal'   => 'de-DE',
			'en'             => 'en-US',
			'es_ES'          => 'es-ES',
			'es_CL'          => 'es-419',
			'es_AR'          => 'es-419',
			'es_CO'          => 'es-419',
			'es_PE'          => 'es-419',
			'es_UY'          => 'es-419',
			'es_PR'          => 'es-419',
			'es_GT'          => 'es-419',
			'es_EC'          => 'es-419',
			'es_MX'          => 'es-419',
			'es_VE'          => 'es-419',
			'es_CR'          => 'es-419',
			'fi'             => 'fi-FI',
			'fr_FR'          => 'fr-FR',
			'he_IL'          => 'he-IL',
			'it_IT'          => 'it-IT',
			'ja'             => 'ja-JP',
			'nl_NL'          => 'nl-NL',
			'nn_NO'          => 'no-NO',
			'pt_BR'          => 'pt-BR',
			'sv_SE'          => 'sv-SE',
		];

		$preferred = $stripe_locales[ $locale ] ?? 'en-US';

		return [ $preferred ];
	}

	/**
	 * Gets the customer's locale/language based on their setting or the site settings.
	 *
	 * @param bool|WP_User $user The user we're wanting to get the locale for.
	 * @return string The locale/language set in the user profile or the site itself.
	 */
	public function get_customer_locale( $user ): string {
		// If we have a user, get their locale with a site fallback.
		return $user ? get_user_locale( $user->ID ) : get_locale();
	}

	/**
	 * Given a WC_Order or WC_Customer, returns an array representing a Stripe customer object.
	 * At least one parameter has to not be null.
	 *
	 * @param Order|null    $order    The Woo order to parse.
	 * @param Customer|null $customer The Woo customer to parse.
	 *
	 * @return array Customer data.
	 */
	public static function map_customer_data( ?Order $order = null, ?Customer $customer = null ): array {
		if ( null === $customer && null === $order ) {
			return [];
		}

		// Where available, the order data takes precedence over the customer.
		$object_to_parse = isset( $order ) ? $order : $customer;
		$name            = $object_to_parse->get_billing_first_name() . ' ' . $object_to_parse->get_billing_last_name();
		$description     = '';
		if ( null !== $customer && ! empty( $customer->get_username() ) ) {
			// We have a logged-in user, so add their username to the customer description.
			// translators: %1$s Name, %2$s Username.
			$description = sprintf( __( 'Name: %1$s, Username: %2$s', 'storeengine' ), $name, $customer->get_username() );
		} else {
			// Current user is not logged in.
			// translators: %1$s Name.
			$description = sprintf( __( 'Name: %1$s, Guest', 'storeengine' ), $name );
		}

		$data = [
			'name'        => $name,
			'description' => $description,
			'email'       => $object_to_parse->get_billing_email(),
			'phone'       => $object_to_parse->get_billing_phone(),
			'address'     => [
				'line1'       => $object_to_parse->get_billing_address_1(),
				'line2'       => $object_to_parse->get_billing_address_2(),
				'postal_code' => $object_to_parse->get_billing_postcode(),
				'city'        => $object_to_parse->get_billing_city(),
				'state'       => $object_to_parse->get_billing_state(),
				'country'     => $object_to_parse->get_billing_country(),
			],
		];

		if ( ! empty( $object_to_parse->get_shipping_postcode() ) ) {
			$data['shipping'] = [
				'name'    => $object_to_parse->get_shipping_first_name() . ' ' . $object_to_parse->get_shipping_last_name(),
				'address' => [
					'line1'       => $object_to_parse->get_shipping_address_1(),
					'line2'       => $object_to_parse->get_shipping_address_2(),
					'postal_code' => $object_to_parse->get_shipping_postcode(),
					'city'        => $object_to_parse->get_shipping_city(),
					'state'       => $object_to_parse->get_shipping_state(),
					'country'     => $object_to_parse->get_shipping_country(),
				],
			];
		}

		return $data;
	}
}

// End of file stripe-customer.php
