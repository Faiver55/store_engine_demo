<?php

namespace StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WP_Error;

class ThankyouPaymentInstructions {

	public function __construct() {
		add_shortcode( 'storeengine_thankyou_payment_instructions', [ $this, 'render' ] );
	}

	public function render( $atts ) {
		$attributes = shortcode_atts( [
			'dummy' => false,
		], $atts );
		$dummy      = Formatting::string_to_bool( $attributes['dummy'] );

		if ( $dummy ) {
			echo __( 'Please send a check to Store Name, Store Street, Store Town, Store State / County, Store Postcode.', 'storeengine' );

			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_hash = isset( $_GET['order_hash'] ) ? sanitize_text_field( wp_unslash( $_GET['order_hash'] ) ) : '';
		$order      = Helper::get_order_by_key( $order_hash );
		$order      = $order instanceof WP_Error ? false : $order;

		ob_start();
		if ( $order ) {
			do_action( 'storeengine/thankyou/' . $order->get_payment_method(), $order->get_id() );
		}
		return ob_get_clean();
	}
}
