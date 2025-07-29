<?php

namespace StoreEngine\Addons\Paypal;

use StoreEngine\Payment_Gateways;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit(); // Exit if accessed directly.
}

class Assets {
	protected GatewayPaypal $gateway;
	public static function init( $gateway ) {
		$self          = new self();
		$self->gateway = $gateway;
		add_action( 'wp_enqueue_scripts', [ $self, 'load_paypal_js_frontend' ], 10 );
		add_filter( 'storeengine/frontend_scripts_data', [ $self, 'enqueue_order_data' ] );
	}

	public function load_paypal_js_frontend() {
		if ( Helper::is_checkout() || Helper::is_add_payment_method_page() ) {
			if ( $this->gateway->is_available() ) {
				$key_type = $this->gateway->get_option( 'is_production', true ) ? 'production' : 'sandbox';
				$src      = 'https://www.paypal.com/sdk/js?client-id=' . $this->gateway->get_option( 'client_id_' . $key_type, '' ) . '&components=buttons';
				if ( Helper::cart()->has_subscription_product() ) {
					$src .= '&vault=true&intent=subscription';
				}

				wp_enqueue_script( 'storeengine-paypal-script', $src, [], null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			}
		}
	}

	public function enqueue_order_data( $data ) {
		$data['is_subscription_order'] = Helper::cart()->has_subscription_product();

		return $data;
	}

}
