<?php

namespace StoreEngine\Addons\Stripe\PaymentTokens;

use StoreEngine\Addons\Stripe\Constants\StripePaymentMethods;
use StoreEngine\Classes\PaymentTokens\PaymentToken;
use StoreEngine\Stripe\PaymentMethod;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.Files.FileName

/**
 * Stripe SEPA Direct Debit Payment Token.
 *
 * Representation of a payment token for SEPA.
 */
class StripePaymentTokenSepa extends PaymentToken {
	use StripePaymentTokenTrait;

	/**
	 * Stores payment type.
	 *
	 * @var string
	 */
	protected string $type = StripePaymentMethods::SEPA;

	const TYPE = StripePaymentMethods::SEPA;

	/**
	 * Stores SEPA payment token data.
	 *
	 * @var array
	 */
	protected array $extra_data = [
		'last4'               => '',
		'payment_method_type' => StripePaymentMethods::SEPA_DEBIT,
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
	 * Get type to display to user.
	 *
	 * @return string
	 */
	public function get_display_name(): string {
		$display = sprintf(
			/* translators: last 4 digits of IBAN account */
			__( 'SEPA IBAN ending in %s', 'storeengine' ),
			$this->get_last4()
		);

		return $display;
	}

	/**
	 * Validate SEPA payment tokens.
	 *
	 * These fields are required by all SEPA payment tokens:
	 * last4  - string Last 4 digits of the iBAN
	 *
	 * @return boolean True if the passed data is valid
	 */
	public function validate(): bool {
		if ( false === parent::validate() ) {
			return false;
		}

		if ( ! $this->get_last4( 'edit' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns the last four digits.
	 *
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string Last 4 digits
	 */
	public function get_last4( string $context = 'view' ): string {
		return $this->get_prop( 'last4', $context );
	}

	/**
	 * Set the last four digits.
	 *
	 * @param string $last4
	 */
	public function set_last4( $last4 ) {
		$this->set_prop( 'last4', $last4 );
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
	 * @return string $payment_method_type
	 */
	public function get_payment_method_type( string $context = 'view' ) {
		return $this->get_prop( 'payment_method_type', $context );
	}

	/**
	 * Checks if the payment method token is equal a provided payment method.
	 *
	 * @param object $payment_method Payment method object.
	 *
	 * @return bool
	 */
	public function is_equal_payment_method( PaymentMethod $payment_method ): bool {
		$fingerprint = $payment_method->sepa_debit->fingerprint ?? null;

		return StripePaymentMethods::SEPA_DEBIT === $payment_method->type && $fingerprint && $fingerprint === $this->get_fingerprint();
	}
}
