<?php
/**
 * WooCommerce Stripe Cash App Pay Payment Token
 *
 * Representation of a payment token for Cash App Pay.
 */

namespace StoreEngine\Addons\Stripe\PaymentTokens;

use StoreEngine\Addons\Stripe\Constants\StripePaymentMethods;
use StoreEngine\Classes\PaymentTokens\PaymentToken;
use StoreEngine\Stripe\PaymentMethod;

// phpcs:disable WordPress.Files.FileName

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

class StripePaymentTokenCashApp extends PaymentToken {
	/**
	 * Token Type.
	 *
	 * @var string
	 */
	protected string $type = StripePaymentMethods::CASHAPP_PAY;

	const TYPE = StripePaymentMethods::CASHAPP_PAY;

	/**
	 * Extra data.
	 *
	 * @var string[]
	 */
	protected array $extra_data = [
		'cashtag' => '',
	];

	/**
	 * @var array
	 */
	protected array $meta_key_to_props = [
		'cashtag' => 'cashtag',
	];

	/**
	 * Returns the name of the token to display
	 *
	 * @return string The name of the token to display
	 */
	public function get_display_name(): string {
		$cashtag = $this->get_cashtag();

		// Translators: %s is the Cash App Pay $Cashtag.
		return empty( $cashtag ) ? __( 'Cash App Pay', 'storeengine' ) : sprintf( __( 'Cash App Pay (%s)', 'storeengine' ), $cashtag );
	}

	/**
	 * Sets the Cash App Pay $Cashtag for this token.
	 *
	 * @param string $cashtag A public identifier for buyers using Cash App.
	 */
	public function set_cashtag( $cashtag ) {
		$this->set_prop( 'cashtag', $cashtag );
	}

	/**
	 * Fetches the Cash App Pay token's $Cashtag.
	 *
	 * @return string The Cash App Pay $Cashtag.
	 */
	public function get_cashtag( string $context = 'view' ) {
		return $this->get_prop( 'cashtag', $context );
	}

	/**
	 * Checks if the payment method token is equal a provided payment method.
	 *
	 * @inheritDoc
	 */
	public function is_equal_payment_method( PaymentMethod $payment_method ): bool {
		$cashtag = $payment_method->cashapp->cashtag ?? null;

		return StripePaymentMethods::CASHAPP_PAY === $this->get_type() && $cashtag && $cashtag === $this->get_cashtag();
	}
}
