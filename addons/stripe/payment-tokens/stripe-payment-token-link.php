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
 * WooCommerce Stripe Link Payment Token.
 *
 * Representation of a payment token for Link.
 */
class StripePaymentTokenLink extends PaymentToken {
	/**
	 * Stores payment type.
	 *
	 * @var string
	 */
	protected string $type = StripePaymentMethods::LINK;

	const TYPE = StripePaymentMethods::LINK;

	/**
	 * Stores Link payment token data.
	 *
	 * @var array
	 */
	protected array $extra_data = [
		'email' => '',
	];

	/**
	 * @var array
	 */
	protected array $meta_key_to_props = [
		'email' => 'email',
	];

	/**
	 * Get type to display to user.
	 *
	 * @return string
	 */
	public function get_display_name(): string {
		$display = sprintf(
			/* translators: customer email */
			__( 'Stripe Link (%s)', 'storeengine' ),
			$this->get_email()
		);

		return $display;
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
	 * Returns the customer email.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string Customer email.
	 */
	public function get_email( string $context = 'view' ) {
		return $this->get_prop( 'email', $context );
	}

	/**
	 * Set the customer email.
	 *
	 * @param string $email Customer email.
	 */
	public function set_email( $email ) {
		$this->set_prop( 'email', $email );
	}

	/**
	 * Checks if the payment method token is equal a provided payment method.
	 */
	public function is_equal_payment_method( PaymentMethod $payment_method ): bool {
		$email = $payment_method->link->email ?? null;

		return StripePaymentMethods::LINK === $payment_method->type && $email && $email === $this->get_email();
	}
}
