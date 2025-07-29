<?php

namespace StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Order;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;
use WP_Error;

class OrderBillingAddress {

	public function __construct() {
		add_shortcode( 'storeengine_order_billing_address', [ $this, 'render' ] );
	}

	public function render( $atts ) {
		$attributes  = shortcode_atts( [
			'dummy' => false,
		], $atts );
		$dummy_order = Formatting::string_to_bool( $attributes['dummy'] );

		if ( $dummy_order ) {
			$order = new Order();
			$order->set_billing_first_name( 'John' );
			$order->set_billing_last_name( 'Doe' );
			$order->set_billing_address_1( 'Chuadanga Bus Stand, Abdur Razzak Complex' );
			$order->set_billing_address_2( '4th Floor' );
			$order->set_billing_city( 'Khulna' );
			$order->set_billing_state( 'Jhenaidah' );
			$order->set_billing_country( 'BD' );
			$order->set_billing_email( 'support@storeengine.pro' );
			$order->set_billing_phone( '01234567890123' );
			$order->set_billing_company( 'KodeZen' );
			$order->set_billing_postcode( '12345' );
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_hash = isset( $_GET['order_hash'] ) ? sanitize_text_field( wp_unslash( $_GET['order_hash'] ) ) : '';
			$order      = Helper::get_order_by_key( $order_hash );
			$order      = $order instanceof WP_Error ? false : $order;

			if ( ! $order ) {
				return '';
			}
		}

		ob_start();
		Template::get_template( 'shortcode/order-billing-address.php', [
			'order' => $order,
		] );

		return ob_get_clean();
	}
}
