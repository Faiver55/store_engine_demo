<?php
/**
 * Class WC_Payment_Token_eCheck file.
 *
 * @package WooCommerce\PaymentTokens
 */

namespace StoreEngine\Classes\PaymentTokens;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WooCommerce eCheck Payment Token.
 *
 * Representation of a payment token for eChecks.
 */
class PaymentTokenEcheck extends PaymentToken {

	/**
	 * Token Type String.
	 *
	 * @var string
	 */
	protected string $type = 'eCheck';

	/**
	 * Stores eCheck payment token data.
	 *
	 * @var array
	 */
	protected array $extra_data = [
		'last4' => '',
	];

	/**
	 * Get type to display to user.
	 *
	 * @return string
	 */
	public function get_display_name(): string {
		return sprintf(
			/* translators: 1: last 4 digits */
			__( 'eCheck ending in %1$s', 'storeengine' ),
			$this->get_last4()
		);
	}

	/**
	 * Validate eCheck payment tokens.
	 *
	 * These fields are required by all eCheck payment tokens:
	 * last4  - string Last 4 digits of the check
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
	public function get_last4( $context = 'view' ) {
		return $this->get_prop( 'last4', $context );
	}

	/**
	 * Set the last four digits.
	 *
	 * @param string $last4 eCheck last four digits.
	 */
	public function set_last4( $last4 ) {
		$this->set_prop( 'last4', $last4 );
	}
}
