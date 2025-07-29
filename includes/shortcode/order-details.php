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

class OrderDetails {

	public function __construct() {
		add_shortcode( 'storeengine_order_details', [ $this, 'render' ] );
	}

	public function render( $atts ) {
		$attributes = shortcode_atts( [
			'dummy' => false,
		], $atts );
		$dummy      = Formatting::string_to_bool( $attributes['dummy'] );

		if ( $dummy ) {
			$order = new Order();
			$order->set_payment_method( 'check' );
			$order->set_payment_method_title( 'Check payments' );
			$order->set_total( 35 );
			add_filter( 'storeengine/get_order_item_totals', [ __CLASS__, 'set_dummy_totals' ] );

			$new_order_item = new Order\OrderItemProduct();
			$new_order_item->set_subtotal( 20.00 );
			$new_order_item->set_quantity( 1 );
			$new_order_item->set_name( 'Sample product' );
			$order_items        = [ $new_order_item ];
			$show_purchase_note = true;
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_hash = isset( $_GET['order_hash'] ) ? sanitize_text_field( wp_unslash( $_GET['order_hash'] ) ) : '';
			$order      = Helper::get_order_by_key( $order_hash );
			$order      = $order instanceof WP_Error ? false : $order;

			if ( ! $order ) {
				return '';
			}

			$order_items        = $order->get_items( apply_filters( 'storeengine/purchase_order_item_types', 'line_item' ) );
			$show_purchase_note = $order->has_status( apply_filters( 'storeengine/purchase_note_order_statuses', [
				'completed',
				'processing',
			] ) );
		}

		ob_start();
		Template::get_template( 'shortcode/order-details.php', [
			'order'              => $order,
			'order_items'        => $order_items,
			'show_purchase_note' => $show_purchase_note,
		] );
		$output = ob_get_clean();

		if ( $dummy ) {
			remove_filter( 'storeengine/get_order_item_totals', [ __CLASS__, 'set_dummy_totals' ] );
		}

		return $output;
	}

	public static function set_dummy_totals(): array {
		$total_rows                  = [];
		$total_rows['cart_subtotal'] = [
			'type'  => 'cart_subtotal',
			'label' => __( 'Subtotal:', 'storeengine' ),
			'value' => Formatting::price( 20.00 ),
		];

		$total_rows['shipping'] = [
			'type'  => 'shipping',
			'label' => __( 'Shipping:', 'storeengine' ),
			'value' => Formatting::price( 15.00 ) . '&nbsp;<small class="shipped_via">via Flat rate</small>',
		];

		$total_rows['cart_total'] = [
			'type'  => 'cart_total',
			'label' => __( 'Total:', 'storeengine' ),
			'value' => Formatting::price( 35.00 ),
		];

		$total_rows['payment_method'] = [
			'type'  => 'payment_method',
			'label' => __( 'Payment method:', 'storeengine' ),
			'value' => __( 'Check payments', 'storeengine' ),
		];

		return $total_rows;
	}
}
