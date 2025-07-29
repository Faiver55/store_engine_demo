<?php

namespace StoreEngine\Addons\Invoice\Ajax;

use StoreEngine\Addons\Email\order\Invoice;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

class Email extends AbstractAjaxHandler {

	protected string $namespace = STOREENGINE_PLUGIN_SLUG . '_invoice';

	public function __construct() {
		$this->actions = [
			'send_invoice_customer' => [
				'callback' => [ $this, 'send_invoice_customer' ],
				'fields'   => [
					'order_id' => 'int',
				],
			],
		];
	}

	public function send_invoice_customer( array $payload ) {
		if ( ! Formatting::string_to_bool( get_option( 'storeengine_invoice_fonts_downloaded', false ) ) ) {
			wp_send_json_error( __( 'Please download the fonts before generating the PDF.', 'storeengine' ) );
		}

		if ( ! isset( $payload['order_id'] ) || ! is_numeric( $payload['order_id'] ) ) {
			wp_send_json_error( __( 'Invalid order ID!', 'storeengine' ) );
		}

		if ( ! class_exists( 'StoreEngine\Addons\Email\order\Invoice' ) ) {
			wp_send_json_error( __( 'Email addon is not enabled!', 'storeengine' ) );
		}

		$order_id = $payload['order_id'];
		$order    = Helper::get_order( $order_id );
		if ( ! $order || is_wp_error( $order ) ) {
			wp_send_json_error( __( 'Order not found!', 'storeengine' ) );
		}

		( new Invoice() )->send_email( $order );
		wp_send_json_success( __( 'Invoice mail sent to customer successfully', 'storeengine' ) );
	}
}
