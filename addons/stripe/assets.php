<?php

namespace StoreEngine\Addons\Stripe;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit(); // Exit if accessed directly.
}

class Assets {
	protected GatewayStripe $gateway;
	public static function init( $gateway ) {
		$self          = new self();
		$self->gateway = $gateway;
		add_action( 'storeengine/enqueue_frontend_scripts', [ $self, 'load_stripe_js_frontend' ] );
	}

	public function load_stripe_js_frontend() {
		if ( Helper::is_checkout() || Helper::is_add_payment_method_page() ) {
			if ( $this->gateway->is_available() ) {
				wp_enqueue_script( 'storeengine-stripe-script', 'https://js.stripe.com/v3/', [], false, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion
			}
		}
	}
}
