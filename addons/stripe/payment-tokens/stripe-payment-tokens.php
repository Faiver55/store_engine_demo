<?php

namespace StoreEngine\Addons\Stripe\PaymentTokens;

use stdClass;
use StoreEngine\Addons\Stripe\StripeCustomer;
use StoreEngine\Addons\Stripe\StripeService;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\PaymentTokens\PaymentToken;
use StoreEngine\Classes\PaymentTokens\PaymentTokenCc;
use StoreEngine\Classes\PaymentTokens\PaymentTokens;
use StoreEngine\Payment_Gateways;
use StoreEngine\Stripe\PaymentMethod;
use StoreEngine\Stripe\Source;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles and process WC payment tokens API.
 * Seen in checkout page and my account->add payment method page.
 *
 * @see \PaymentTokens
 */
class StripePaymentTokens {
	use Singleton;

	/**
	 * List of reusable payment gateways by payment method.
	 *
	 * The keys are the possible values for the type of the PaymentMethod object in Stripe.
	 * https://docs.stripe.com/api/payment_methods/object#payment_method_object-type
	 *
	 * The values are the related gateway ID we use for them in the extension.
	 */
	const UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD = [
		'card'            => 'stripe',
		'link'            => 'stripe',
		'us_bank_account' => 'stripe_us_bank_account',
		'bancontact'      => 'stripe_bancontact',
		'ideal'           => 'stripe_ideal',
		'sepa_debit'      => 'stripe_sepa_debit',
		'sofort'          => 'stripe_sofort',
		'cashapp'         => 'stripe_cashapp',
		'bacs_debit'      => 'stripe_bacs_debit',
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'storeengine/get_customer_payment_tokens', [ $this, 'storeengine_get_customer_payment_tokens' ], 10, 3 );
		add_filter( 'storeengine/payment_methods_list_item', [ $this, 'get_account_saved_payment_methods_list_item' ], 10, 2 );
		add_filter( 'storeengine/get_credit_card_type_label', [ $this, 'normalize_sepa_label' ] );
		add_filter( 'storeengine/payment_token/token_classname', [ $this, 'get_token_classname' ], 10, 3 );
		add_action( 'storeengine/payment_token/deleted', [ $this, 'storeengine_payment_token_deleted' ], 10, 2 );
		add_action( 'storeengine/payment_token/set_default', [ $this, 'storeengine_payment_token_set_default' ] );
	}

	/**
	 * Normalizes the SEPA IBAN label on My Account page.
	 *
	 * @param string $label
	 *
	 * @return string $label
	 */
	public function normalize_sepa_label( $label ) {
		if ( 'sepa iban' === strtolower( $label ) ) {
			return 'SEPA IBAN';
		}

		return $label;
	}

	/**
	 * Extract the payment token from the provided request.
	 *
	 * TODO: Once php requirement is bumped to >= 7.1.0 set return type to ?\PaymentToken
	 * since the return type is nullable, as per
	 * https://www.php.net/manual/en/functions.returning-values.php#functions.returning-values.type-declaration
	 *
	 * @param array $request Associative array containing payment request information.
	 *
	 * @return PaymentToken|NULL
	 */
	public static function get_token_from_request( array $request ): ?PaymentToken {
		$payment_method    = ! is_null( $request['payment_method'] ) ? $request['payment_method'] : null;
		$token_request_key = 'storeengine-' . $payment_method . '-payment-token';
		if ( ! isset( $request[ $token_request_key ] ) || 'new' === $request[ $token_request_key ] ) {
			return null;
		}

		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$token = PaymentTokens::get( Formatting::clean( $request[ $token_request_key ] ) );

		// If the token doesn't belong to this gateway or the current user it's invalid.
		if ( ! $token || $payment_method !== $token->get_gateway_id() || $token->get_user_id() !== get_current_user_id() ) {
			return null;
		}

		return $token;
	}

	/**
	 * Checks if customer has saved payment methods.
	 *
	 * @param int $customer_id
	 *
	 * @return bool
	 */
	public static function customer_has_saved_methods( $customer_id ) {
		$gateways = [ 'stripe', 'stripe_sepa' ];

		if ( empty( $customer_id ) ) {
			return false;
		}

		$has_token = false;

		foreach ( $gateways as $gateway ) {
			$tokens = PaymentTokens::get_customer_tokens( $customer_id, $gateway );

			if ( ! empty( $tokens ) ) {
				$has_token = true;
				break;
			}
		}

		return $has_token;
	}

	/**
	 * Gets saved tokens from Stripe, if they don't already exist in WooCommerce.
	 *
	 * @param array $tokens Array of tokens
	 * @param string $user_id WC User ID
	 * @param string $gateway_id WC Gateway ID
	 *
	 * @return array
	 */
	public function storeengine_get_customer_payment_tokens( $tokens, $user_id, $gateway_id ) {
		return $tokens;
	}

	/**
	 * Gets saved tokens from Sources API if they don't already exist in WooCommerce.
	 *
	 * @param array $tokens
	 * @param string|int $customer_id
	 * @param string $gateway_id
	 *
	 * @return array
	 * @throws StoreEngineException
	 */
	public function storeengine_get_customer_payment_tokens_legacy( array $tokens, $customer_id, string $gateway_id ): array {
		if ( is_user_logged_in() ) {
			$stored_tokens = [];

			foreach ( $tokens as $token ) {
				$stored_tokens[ $token->get_token() ] = $token;
			}

			if ( 'stripe' === $gateway_id ) {
				$stripe_customer = new StripeCustomer( $customer_id );
				$stripe_sources  = $stripe_customer->get_sources();

				foreach ( $stripe_sources as $source ) {
					if ( isset( $source->type ) && 'card' === $source->type ) {
						if ( ! isset( $stored_tokens[ $source->id ] ) ) {
							$token = new StripePaymentTokenCc();
							$token->set_token( $source->id );
							$token->set_gateway_id( 'stripe' );

							if ( StripeService::is_card_payment_method( $source ) ) {
								$token->set_card_type( strtolower( $source->card->brand ) );
								$token->set_last4( $source->card->last4 );
								$token->set_expiry_month( $source->card->exp_month );
								$token->set_expiry_year( $source->card->exp_year );
								if ( isset( $source->card->fingerprint ) ) {
									$token->set_fingerprint( $source->card->fingerprint );
								}
							}

							$token->set_user_id( $customer_id );
							$token->save();
							$tokens[ $token->get_id() ] = $token;
						} else {
							unset( $stored_tokens[ $source->id ] );
						}
					} elseif ( ! isset( $stored_tokens[ $source->id ] ) && 'card' === $source->object ) {
						$token = new PaymentTokenCc();
						$token->set_token( $source->id );
						$token->set_gateway_id( 'stripe' );
						$token->set_card_type( strtolower( $source->brand ) );
						$token->set_last4( $source->last4 );
						$token->set_expiry_month( $source->exp_month );
						$token->set_expiry_year( $source->exp_year );
						$token->set_user_id( $customer_id );
						$token->save();
						$tokens[ $token->get_id() ] = $token;
					} else {
						unset( $stored_tokens[ $source->id ] );
					}
				}
			}
		}

		return $tokens;
	}

	/**
	 * Gets saved tokens from Intentions API if they don't already exist in WooCommerce.
	 *
	 * @param array $tokens Array of tokens
	 * @param string $user_id WC User ID
	 * @param string $gateway_id WC Gateway ID
	 *
	 * @return array
	 */
	public function storeengine_get_customer_upe_payment_tokens( $tokens, $user_id, $gateway_id ) {
		if ( ! is_user_logged_in() || ( ! empty( $gateway_id ) && ! in_array( $gateway_id, self::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD, true ) ) ) {
			return $tokens;
		}

		if ( count( $tokens ) >= get_option( 'posts_per_page' ) ) {
			// The tokens data store is not paginated and only the first "post_per_page" (defaults to 10) tokens are retrieved.
			// Having 10 saved credit cards is considered an unsupported edge case, new ones that have been stored in Stripe won't be added.
			return $tokens;
		}

		$stored_tokens = [];

		foreach ( $tokens as $token ) {
			if ( in_array( $token->get_gateway_id(), self::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD, true ) ) {
				$stored_tokens[ $token->get_token() ] = $token;
			}
		}

		$gateway = Payment_Gateways::get_instance()->get_gateway( 'stripe' );

		// Get customer id.
		$customer = new StripeCustomer( $user_id );

		// Retrieve the payment methods for the enabled reusable gateways.
		$payment_methods = [];
		foreach ( self::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD as $payment_method_type => $reausable_gateway_id ) {

			// The payment method type doesn't match the ones we use. Nothing to do here.
			if ( ! isset( $gateway->payment_methods[ $payment_method_type ] ) ) {
				continue;
			}

			$payment_method_instance = $gateway->payment_methods[ $payment_method_type ];
			if ( $payment_method_instance->is_enabled() ) {
				$payment_methods[] = $customer->get_payment_methods( $payment_method_type );
			}
		}

		// @TODO Add SEPA if it is disabled and iDEAL or Bancontact are enabled. iDEAL and Bancontact tokens are saved as SEPA tokens.

		$payment_methods = array_merge( ...$payment_methods );

		// Prevent unnecessary recursion, PaymentToken::save() ends up calling 'storeengine_get_customer_payment_tokens' in some cases.
		remove_action( 'storeengine/get_customer_payment_tokens', [
			$this,
			'storeengine_get_customer_payment_tokens',
		], 10, 3 );

		foreach ( $payment_methods as $payment_method ) {
			if ( ! isset( $payment_method->type ) ) {
				continue;
			}

			// Retrieve the real APM behind SEPA PaymentMethods.
			$payment_method_type = $this->get_original_payment_method_type( $payment_method );

			// The corresponding method for the payment method type is not enabled, skipping.
			if ( ! $gateway->payment_methods[ $payment_method_type ]->is_enabled() ) {
				continue;
			}

			// Create a new token when:
			// - The payment method doesn't have an associated token in WooCommerce.
			// - The payment method is a valid PaymentMethodID (i.e. only support IDs starting with "src_" when using the card payment method type.
			// - The payment method belongs to the gateway ID being retrieved or the gateway ID is empty (meaning we're looking for all payment methods).
			if (
				! isset( $stored_tokens[ $payment_method->id ] ) &&
				$this->is_valid_payment_method_id( $payment_method->id, $payment_method_type ) &&
				( empty( $gateway_id ) || $this->is_valid_payment_method_type_for_gateway( $payment_method_type, $gateway_id ) )
			) {
				$token                      = $this->add_token_to_user( $payment_method, $customer );
				$tokens[ $token->get_id() ] = $token;
			} else {
				unset( $stored_tokens[ $payment_method->id ] );
			}
		}

		add_action( 'storeengine/get_customer_payment_tokens', [ $this, 'storeengine_get_customer_payment_tokens' ], 10, 3 );

		remove_action( 'storeengine/payment_token/deleted', [ $this, 'storeengine_payment_token_deleted' ], 10, 2 );

		// Remove the payment methods that no longer exist in Stripe's side.
		foreach ( $stored_tokens as $token ) {
			unset( $tokens[ $token->get_id() ] );
			$token->delete();
		}

		add_action( 'storeengine/payment_token/deleted', [ $this, 'storeengine_payment_token_deleted' ], 10, 2 );

		return $tokens;
	}

	/**
	 * Returns original Stripe payment method type from payment token
	 *
	 * @param PaymentToken $payment_token WC Payment Token (CC or SEPA)
	 *
	 * @return string
	 */
	private function get_payment_method_type_from_token( $payment_token ) {
		return 'CC' === $payment_token->get_type() ? 'card' : $payment_token->get_type();
	}

	/**
	 * Controls the output for some payment methods on the my account page.
	 *
	 * @param array $item Individual list item from woocommerce_saved_payment_methods_list.
	 * @param PaymentToken $payment_token The payment token associated with this method entry.
	 *
	 * @return array $item Modified list item.
	 */
	public function get_account_saved_payment_methods_list_item( $item, $payment_token ) {
		switch ( strtolower( $payment_token->get_type() ) ) {
			case 'sepa':
				$item['method']['last4'] = $payment_token->get_last4();
				$item['method']['brand'] = esc_html__( 'SEPA IBAN', 'storeengine' );
				break;
			case 'bacs_debit':
				$item['method']['last4'] = $payment_token->get_last4();
				$item['method']['brand'] = esc_html__( 'Bacs Direct Debit', 'storeengine' );
				break;
			case 'cashapp':
				$item['method']['brand'] = esc_html__( 'Cash App Pay', 'storeengine' );
				break;
			case 'us_bank_account':
				$item['method']['brand'] = $payment_token->get_display_name();
				break;
			case 'link':
				$item['method']['brand'] = sprintf(
				/* translators: customer email */
					esc_html__( 'Stripe Link (%s)', 'storeengine' ),
					esc_html( $payment_token->get_email() )
				);
				break;
		}

		return $item;
	}

	/**
	 * Deletes a token from Stripe.
	 *
	 * @param int $token_id The WooCommerce token ID.
	 * @param PaymentToken $token The PaymentToken object.
	 */
	public function storeengine_payment_token_deleted( $token_id, $token ) {
		$stripe_customer = new StripeCustomer( $token->get_user_id() );
		try {
			$stripe_customer->delete_source( $token->get_token() );
		} catch ( StoreEngineException $e ) {
			// @TODO implement error logger.
			Helper::log_error( $e );
		}
	}

	/**
	 * Set as default in Stripe.
	 */
	public function storeengine_payment_token_set_default( $token_id ) {
		$token           = PaymentTokens::get( $token_id );
		$stripe_customer = new StripeCustomer( get_current_user_id() );

		try {
			if ( false === strpos( $token->get_token(), 'src_' ) ) {
				$stripe_customer->set_default_payment_method( $token->get_token() );
			} else {
				$stripe_customer->set_default_source( $token->get_token() );
			}
			$stripe_customer->set_default_payment_method( $token->get_token() );
		} catch ( StoreEngineException $e ) {
			// @TODO implement error logger.
			Helper::log_error( $e );
		}
	}

	/**
	 * Returns boolean value if payment method type matches relevant payment gateway.
	 *
	 * @param string $payment_method_type Stripe payment method type ID.
	 * @param string $gateway_id WC Stripe gateway ID.
	 *
	 * @return bool True, if payment method type matches gateway, false if otherwise.
	 */
	private function is_valid_payment_method_type_for_gateway( string $payment_method_type, string $gateway_id ): bool {
		$reusable_gateway = self::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ $payment_method_type ] ?? null;

		return $reusable_gateway === $gateway_id;
	}

	/**
	 * Creates and add a token to an user, based on the PaymentMethod object.
	 *
	 * @param object|stdClass|PaymentMethod|Source $payment_method Payment method to be added.
	 * @param StripeCustomer $customer StripeCustomer we're processing the tokens for.
	 *
	 * @return  PaymentToken   The WC object for the payment token.
	 */
	private function add_token_to_user( $payment_method, StripeCustomer $customer ) {
		// Clear cached payment methods.
		$customer->clear_cache();

		$payment_method_type = $this->get_original_payment_method_type( $payment_method );
		$gateway_id          = self::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ $payment_method_type ];

		$found_token = $this->get_duplicate_token( $payment_method, $customer->get_user_id(), $gateway_id );
		if ( $found_token ) {
			// Update the token with the new payment method ID.
			$found_token->set_token( $payment_method->id );
			$found_token->save();

			return $found_token;
		}

		switch ( $payment_method_type ) {
			case 'card':
				$token = new StripePaymentTokenCc();
				$token->set_expiry_month( $payment_method->card->exp_month );
				$token->set_expiry_year( $payment_method->card->exp_year );
				$token->set_card_type( strtolower( $payment_method->card->display_brand ?? $payment_method->card->networks->preferred ?? $payment_method->card->brand ) );
				$token->set_last4( $payment_method->card->last4 );
				$token->set_fingerprint( $payment_method->card->fingerprint );
				break;
			case 'bacs_debit':
				$token = new StripePaymentTokenBacsDebit();
				$token->set_last4( $payment_method->bacs_debit->last4 );
				$token->set_fingerprint( $payment_method->bacs_debit->fingerprint );
				$token->set_payment_method_type( $payment_method_type );
				break;
			case 'link':
				$token = new StripePaymentTokenLink();
				$token->set_email( $payment_method->link->email );
				$token->set_payment_method_type( $payment_method_type );
				break;
			case 'us_bank_account':
				$token = new StripePaymentTokenAch();
				if ( isset( $payment_method->us_bank_account->last4 ) ) {
					$token->set_last4( $payment_method->us_bank_account->last4 );
				}
				if ( isset( $payment_method->us_bank_account->fingerprint ) ) {
					$token->set_fingerprint( $payment_method->us_bank_account->fingerprint );
				}
				if ( isset( $payment_method->us_bank_account->account_type ) ) {
					$token->set_account_type( $payment_method->us_bank_account->account_type );
				}
				if ( isset( $payment_method->us_bank_account->bank_name ) ) {
					$token->set_bank_name( $payment_method->us_bank_account->bank_name );
				}
				break;
			case 'cashapp':
				$token = new StripePaymentTokenCashApp();

				if ( isset( $payment_method->cashapp->cashtag ) ) {
					$token->set_cashtag( $payment_method->cashapp->cashtag );
				}
				break;
			default:
				$token = new StripePaymentTokenSEPA();
				$token->set_last4( $payment_method->sepa_debit->last4 );
				$token->set_payment_method_type( $payment_method_type );
				$token->set_fingerprint( $payment_method->sepa_debit->fingerprint );
		}

		$token->set_gateway_id( $gateway_id );
		$token->set_token( $payment_method->id );
		$token->set_user_id( $customer->get_user_id() );
		$token->save();

		return $token;
	}

	/**
	 * Returns the original type of payment method from Stripe's PaymentMethod object.
	 *
	 * APMs like iDEAL, Bancontact, and Sofort get their PaymentMethod object type set to SEPA.
	 * This method checks the extra data within the PaymentMethod object to determine the
	 * original APM type that was used to create the PaymentMethod.
	 *
	 * @param object|stdClass|PaymentMethod|Source $payment_method Stripe payment method JSON object.
	 *
	 * @return string Payment method type/ID
	 */
	private function get_original_payment_method_type( $payment_method ): string {
		if ( 'sepa_debit' === $payment_method->type ) {
			if ( ! is_null( $payment_method->sepa_debit->generated_from->charge ) ) {
				return $payment_method->sepa_debit->generated_from->charge->payment_method_details->type;
			}
			if ( ! is_null( $payment_method->sepa_debit->generated_from->setup_attempt ) ) {
				return $payment_method->sepa_debit->generated_from->setup_attempt->payment_method_details->type;
			}
		}

		return $payment_method->type;
	}

	/**
	 * Returns the list of payment tokens that belong to the current user that require a label override on the block checkout page.
	 *
	 * The block checkout will default to a string that includes the token's payment gateway ID. This method will return a list of
	 * payment tokens that should have a custom label displayed instead.
	 *
	 * @return string[] List of payment token IDs and their custom labels.
	 */
	public static function get_token_label_overrides_for_checkout(): array {
		$label_overrides      = [];
		$payment_method_types = [
			'us_bank_account',
			'cashapp',
			'link',
			'bacs_debit',
		];

		foreach ( $payment_method_types as $stripe_id ) {
			$gateway_id = self::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ $stripe_id ];

			foreach ( PaymentTokens::get_customer_tokens( get_current_user_id(), $gateway_id ) as $token ) {
				$label_overrides[ $token->get_id() ] = $token->get_display_name();
			}
		}

		return $label_overrides;
	}

	/**
	 * Updates a saved payment token from payment method details received from Stripe.
	 *
	 * @param int|string $user_id The user ID.
	 * @param string $payment_method The Stripe payment method ID.
	 * @param PaymentMethod|Source $payment_method_details The payment method object from Stripe.
	 */
	public static function update_token_from_method_details( $user_id, string $payment_method, $payment_method_details ) {
		// Payment method types that we want to update from updated payment method details.
		$payment_method_types = [ 'cashapp' ];

		// Exit early if this payment method type is not one we need to update.
		if ( ! isset( $payment_method_details->type ) || ! in_array( $payment_method_details->type, $payment_method_types, true ) ) {
			return;
		}

		$tokens = PaymentTokens::get_tokens( [
			'type'       => $payment_method_details->type,
			'gateway_id' => self::UPE_REUSABLE_GATEWAYS_BY_PAYMENT_METHOD[ $payment_method_details->type ],
			'user_id'    => absint( $user_id ),
		] );

		foreach ( $tokens as $token ) {
			if ( $token->get_token() !== $payment_method ) {
				continue;
			}

			switch ( $payment_method_details->type ) {
				case 'cashapp':
					if ( isset( $payment_method_details->cashapp->cashtag ) ) {
						$token->set_cashtag( $payment_method_details->cashapp->cashtag );
						$token->save();
					}
					break;
			}
		}
	}

	/**
	 * Returns true if the payment method ID is valid for the given payment method type.
	 *
	 * Payment method IDs beginning with 'src_' are only valid for card payment methods.
	 *
	 * @param string $payment_method_id The payment method ID (e.g. 'pm_123' or 'src_123').
	 * @param string $payment_method_type The payment method type.
	 *
	 * @return bool
	 */
	public function is_valid_payment_method_id( string $payment_method_id, string $payment_method_type = '' ): bool {
		if ( 0 === strpos( $payment_method_id, 'pm_' ) ) {
			return true;
		}

		return 0 === strpos( $payment_method_id, 'src_' ) && 'card' === $payment_method_type;
	}

	/**
	 * Searches for a duplicate token in the user's saved payment methods and returns it.
	 *
	 * @param PaymentMethod $payment_method The payment method object.
	 * @param $user_id int The user ID.
	 * @param $gateway_id string The gateway ID.
	 *
	 * @return PaymentToken|null
	 */
	public static function get_duplicate_token( $payment_method, $user_id, $gateway_id ) {
		// Using the base method instead of `PaymentTokens::get_customer_tokens` to avoid recursive calls to hooked filters and actions
		$tokens = PaymentTokens::get_tokens( [
			'user_id'    => $user_id,
			'gateway_id' => $gateway_id,
			'limit'      => 100,
		] );

		foreach ( $tokens as $token ) {
			/**
			 * Token object.
			 *
			 * @var StripePaymentTokenCashApp|StripePaymentTokenCc|StripePaymentTokenLink|StripePaymentTokenSepa|StripePaymentTokenAch $token
			 */
			if ( $token->is_equal_payment_method( $payment_method ) ) {
				return $token;
			}
		}

		return null;
	}

	/**
	 * Filters the payment token class to override the credit card class with the extension's version.
	 *
	 * @param string $classname Payment token class.
	 * @param string $type Token type.
	 * @param $gateway
	 *
	 * @return string
	 */
	public function get_token_classname( string $classname, string $type, $gateway ): string {
		if ( 'stripe' !== $gateway ) {
			return $classname;
		}

		if ( PaymentTokenCc::class === $classname ) {
			return StripePaymentTokenCc::class;
		}

		if ( StripePaymentTokenAch::TYPE === $type ) {
			return StripePaymentTokenAch::class;
		}

		if ( StripePaymentTokenBacsDebit::TYPE === $type ) {
			return StripePaymentTokenBacsDebit::class;
		}

		if ( StripePaymentTokenCashApp::TYPE === $type ) {
			return StripePaymentTokenCashApp::class;
		}

		if ( StripePaymentTokenLink::TYPE === $type ) {
			return StripePaymentTokenLink::class;
		}

		if ( StripePaymentTokenSepa::TYPE === $type ) {
			return StripePaymentTokenSepa::class;
		}

		return $classname;
	}
}
