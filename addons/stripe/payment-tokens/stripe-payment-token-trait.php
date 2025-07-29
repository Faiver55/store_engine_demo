<?php
/**
 * Token trait.
 */

namespace StoreEngine\Addons\Stripe\PaymentTokens;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait StripePaymentTokenTrait {

	/**
	 * Returns the token fingerprint (unique identifier).
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return ?string Fingerprint
	 */
	public function get_fingerprint( string $context = 'view' ): ?string {
		return $this->get_prop( 'fingerprint', $context );
	}

	/**
	 * Set the token fingerprint (unique identifier).
	 *
	 * @param string $fingerprint The fingerprint.
	 */
	public function set_fingerprint( string $fingerprint ) {
		$this->set_prop( 'fingerprint', $fingerprint );
	}
}

// End of file stripe-payment-token.php.
