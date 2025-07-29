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

class StripePaymentTokenCc extends PaymentTokenCc {
	use StripePaymentTokenTrait;

	/**
	 * Constructor.
	 *
	 * @inheritDoc
	 */
	public function __construct( $token = '' ) {
		// Add fingerprint to extra data to be persisted.
		$this->extra_data['fingerprint']        = '';
		$this->meta_key_to_props['fingerprint'] = 'fingerprint';

		parent::__construct( $token );
	}

	/**
	 * Checks if the payment method token is equal a provided payment method.
	 *
	 * @param PaymentMethod $payment_method Payment method object.
	 *
	 * @return bool
	 */
	public function is_equal_payment_method( PaymentMethod $payment_method ): bool {
		$fingerprint = $payment_method->card->fingerprint ?? null;

		return StripePaymentMethods::CARD === $payment_method->type && $fingerprint && $fingerprint === $this->get_fingerprint();
	}
}

// End of file stripe-payment-token.php.
