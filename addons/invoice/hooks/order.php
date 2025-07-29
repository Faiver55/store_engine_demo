<?php

namespace StoreEngine\Addons\Invoice\Hooks;

use StoreEngine\Addons\Invoice\HelperAddon;
use StoreEngine\Utils\Helper;

class Order {

	public static function init() {
		$self = new self();
		add_filter( 'storeengine/dashboard/order/actions', [ $self, 'add_invoice_action' ], 10, 2 );
		add_action( 'init', [ $self, 'redirect_to_invoice_preview' ] );
	}

	public function add_invoice_action( array $actions, \StoreEngine\Classes\Order $order ): array {
		$display_btn = HelperAddon::get_setting( 'invoice_front_btn', 'order_paid' );

		if ( 'never' === $display_btn || ( 'order_paid' === $display_btn && ! $order->is_paid() ) ) {
			return $actions;
		}

		$download = 'download' === HelperAddon::get_setting( 'invoice_front_view', 'preview' );
		$target   = $download ? '_self' : '_blank';

		$actions['invoice'] = [
			'url'        => HelperAddon::get_pdf_url( $order->get_id(), 'invoice', $download ),
			'name'       => $download ? __( 'Download Invoice', 'storeengine' ) : __( 'View Invoice', 'storeengine' ),
			'target'     => $target,
			/* translators: %s: order number */
			'aria-label' => $download ?
				sprintf( __( 'Download invoice for order %s', 'storeengine' ), $order->get_order_number() )
				:
				sprintf( __( 'Preview invoice for order %s', 'storeengine' ), $order->get_order_number() ),
		];

		return $actions;
	}

	public function redirect_to_invoice_preview() {
		if ( ! isset( $_GET['store_document_type'], $_GET['key'] ) || 'invoice' !== $_GET['store_document_type'] ) {
			return;
		}

		$order_key = sanitize_text_field( wp_unslash( $_GET['key'] ) );
		$order_key = str_replace( 'store_order_', 'se_order_', $order_key );
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( HelperAddon::get_invoice_preview_url( $order_key ) ) );
			exit;
		}

		$order = Helper::get_order_by_key( $order_key );
		if ( is_wp_error( $order ) ) {
			wp_send_json_error( [
				'message' => __( 'Order not found', 'storeengine' ),
			] );
		}
		wp_safe_redirect( HelperAddon::get_pdf_url( $order->get_id() ) );
		exit;
	}

}
