<?php
/**
 * Class WC_Payment_Token_CC file.
 *
 * @package StoreEngine\PaymentTokens
 */

namespace StoreEngine\Addons\Stripe\PaymentTokens;

use StoreEngine\Addons\Stripe\Constants\StripePaymentMethods;
use StoreEngine\Classes\PaymentTokens\PaymentTokenCc;
use StoreEngine\Stripe\PaymentMethod;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StripePaymentTokenBacsDebit extends PaymentTokenCc {
	use StripePaymentTokenTrait;

	/**
	 * Token Type.
	 *
	 * @var string
	 */
	protected string $type = StripePaymentMethods::BACS_DEBIT;

	const TYPE = StripePaymentMethods::BACS_DEBIT;

	/**
	 * Bacs Debit payment token data.
	 *
	 * @var array
	 */
	protected array $extra_data = [
		'last4'               => '',
		'payment_method_type' => StripePaymentMethods::BACS_DEBIT,
		'fingerprint'         => '',
	];

	/**
	 * @var array
	 */
	protected array $meta_key_to_props = [
		'last4'       => 'last4',
		'fingerprint' => 'fingerprint',
	];

	/**
	 * Checks if the payment method token is equal a provided payment method.
	 *
	 * @param PaymentMethod $payment_method Payment method object.
	 *
	 * @return bool
	 */
	public function is_equal_payment_method( PaymentMethod $payment_method ): bool {
		$fingerprint = $payment_method->bacs_debit->fingerprint ?? null;

		return StripePaymentMethods::BACS_DEBIT === $payment_method->type && $fingerprint && $fingerprint === $this->get_fingerprint();
	}

	/**
	 * Set the last four digits for the Bacs Debit Token.
	 *
	 * @param string $last4
	 */
	public function set_last4( $last4 ) {
		$this->set_prop( 'last4', $last4 );
	}

	/**
	 * Returns the last four digits of the Bacs Debit Token.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string The last 4 digits.
	 */
	public function get_last4( $context = 'view' ) {
		return $this->get_prop( 'last4', $context );
	}

	/**
	 * Set Stripe payment method type.
	 *
	 * @param string $type Payment method type.
	 */
	public function set_payment_method_type( $type ) {
		$this->set_prop( 'payment_method_type', $type );
	}

	/**
	 * Returns Stripe payment method type.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string $payment_method_type
	 */
	public function get_payment_method_type( $context = 'view' ) {
		return $this->get_prop( 'payment_method_type', $context );
	}

	/**
	 * Get type to display to user.
	 *
	 * @return string
	 */
	public function get_display_name(): string {
		return sprintf(
		/* translators: 1: credit card type 2: last 4 digits */
			__( '%1$s ending in %2$s', 'storeengine' ),
			StripePaymentMethods::BACS_DEBIT_LABEL,
			$this->get_last4(),
		);
	}
}

// End of file stripe-payment-token.php.
