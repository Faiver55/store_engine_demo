<?php

namespace StoreEngine\Addons\Stripe\PaymentTokens;

use StoreEngine\Addons\Stripe\Constants\StripePaymentMethods;
use StoreEngine\Classes\PaymentTokens\PaymentToken;
use StoreEngine\Stripe\PaymentMethod;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WooCommerce Stripe ACH Direct Debit Payment Token.
 *
 * Representation of a payment token for ACH.
 *
 * @see    \WC_Payment_Token_ACH
 */
class StripePaymentTokenAch extends PaymentToken {

	use StripePaymentTokenTrait;

	/**
	 * Stores payment type.
	 *
	 * @var string
	 */
	protected string $type = StripePaymentMethods::ACH;

	const TYPE = StripePaymentMethods::ACH;

	/**
	 * Stores ACH payment token data.
	 *
	 * @var array
	 */
	protected array $extra_data = [
		'bank_name'           => '',
		'account_type'        => '',
		'last4'               => '',
		'payment_method_type' => StripePaymentMethods::ACH,
		'fingerprint'         => '',
	];

	/**
	 * @var array
	 */
	protected array $meta_key_to_props = [
		'bank_name'    => 'bank_name',
		'account_type' => 'account_type',
		'last4'        => 'last4',
		'fingerprint'  => 'fingerprint',
	];

	/**
	 * Get type to display to user.
	 *
	 * @return string
	 */
	public function get_display_name(): string {
		$display = sprintf(
			/* translators: bank name, account type (checking, savings), last 4 digits of account. */
			__( '%1$s account ending in %2$s (%3$s)', 'storeengine' ),
			ucfirst( $this->get_account_type() ),
			$this->get_last4(),
			$this->get_bank_name()
		);

		return $display;
	}

	/**
	 * Validate ACH payment tokens.
	 *
	 * These fields are required by all ACH payment tokens:
	 * last4  - string Last 4 digits of the Account Number
	 * bank_name - string Name of the bank
	 * account_type - string Type of account (checking, savings)
	 * fingerprint - string Unique identifier for the bank account
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

		if ( ! $this->get_bank_name( 'edit' ) ) {
			return false;
		}

		if ( ! $this->get_account_type( 'edit' ) ) {
			return false;
		}

		if ( ! $this->get_fingerprint( 'edit' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the bank name.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string
	 */
	public function get_bank_name( string $context = 'view' ) {
		return $this->get_prop( 'bank_name', $context );
	}

	/**
	 * Set the bank name.
	 *
	 * @param string $bank_name
	 */
	public function set_bank_name( $bank_name ) {
		$this->set_prop( 'bank_name', $bank_name );
	}

	/**
	 * Get the account type.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string
	 */
	public function get_account_type( string $context = 'view' ) {
		return $this->get_prop( 'account_type', $context );
	}

	/**
	 * Set the account type.
	 *
	 * @param string $account_type
	 */
	public function set_account_type( $account_type ) {
		$this->set_prop( 'account_type', $account_type );
	}

	/**
	 * Returns the last four digits.
	 *
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string Last 4 digits
	 */
	public function get_last4( string $context = 'view' ) {
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
	 * Checks if the payment method token is equal a provided payment method.
	 */
	public function is_equal_payment_method( PaymentMethod $payment_method ): bool {
		$fingerprint = $payment_method->us_bank_account->fingerprint ?? null;

		return StripePaymentMethods::ACH === $payment_method->type && $fingerprint && $fingerprint === $this->get_fingerprint();
	}
}
