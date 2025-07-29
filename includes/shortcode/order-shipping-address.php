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

class OrderShippingAddress {

	public function __construct() {
		add_shortcode( 'storeengine_order_shipping_address', [ $this, 'render' ] );
	}

	public function render( $atts ) {
		$attributes  = shortcode_atts( [
			'dummy' => false,
		], $atts );
		$dummy_order = Formatting::string_to_bool( $attributes['dummy'] );

		if ( $dummy_order ) {
			$order = new Order();
			$order->set_shipping_first_name( 'John' );
			$order->set_shipping_last_name( 'Doe' );
			$order->set_shipping_address_1( 'Chuadanga Bus Stand, Abdur Razzak Complex' );
			$order->set_shipping_address_2( '4th Floor' );
			$order->set_shipping_city( 'Khulna' );
			$order->set_shipping_state( 'Jhenaidah' );
			$order->set_shipping_country( 'BD' );
			$order->set_shipping_email( 'support@storeengine.pro' );
			$order->set_shipping_phone( '01234567890123' );
			$order->set_shipping_company( 'Kodezen' );
			$order->set_shipping_postcode( '7300' );
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_hash = isset( $_GET['order_hash'] ) ? sanitize_text_field( wp_unslash( $_GET['order_hash'] ) ) : '';
			$order      = Helper::get_order_by_key( $order_hash );
			$order      = $order instanceof WP_Error ? false : $order;

			if ( ! $order || ! $order->has_shipping_address() ) {
				return '';
			}
		}

		ob_start();
		Template::get_template( 'shortcode/order-shipping-address.php', [
			'order' => $order,
		] );

		return ob_get_clean();
	}
}
