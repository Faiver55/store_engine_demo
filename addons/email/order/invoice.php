<?php

namespace StoreEngine\Addons\Email\order;

use StoreEngine\Addons\Email\Traits\Email;
use StoreEngine\Addons\Invoice\HelperAddon;
use StoreEngine\Classes\Order;

class Invoice {

	use Email {
		Email::__construct as private __EmailConstruct;
	}

	public function __construct() {
		$this->__EmailConstruct( 'order_invoice' );
	}

	public function send_email( Order $order ) {
		if ( ! class_exists( 'StoreEngine\Addons\Invoice\HelperAddon' ) ) {
			return;
		}

		$settings = $this->get_settings( 'customer' );
		if ( ! is_array( $settings ) || ! $settings['is_enable'] ) {
			return;
		}

		$invoice_file_path = HelperAddon::generate_pdf( $order );
		if ( ! $invoice_file_path ) {
			return;
		}

		$subject                = $this->get_email_subject( $order, $settings['email_subject'] );
		list( $headers, $body ) = $this->get_the_email_body( $settings, 'email/order-confirmation-customer.php' );

		$body        = $this->get_order_email_body( $order, $body );
		$preview_url = HelperAddon::get_invoice_preview_url( $order->get_order_key() );
		$body        = str_replace(
			'{invoice_button}',
			'<a href="' . esc_attr( $preview_url ) . '" target="_blank" style="display: inline-block; padding: 12px 24px; background-color: #008DFF; color: #ffffff; text-decoration: none; border-radius: 3px;">View Invoice</a>',
			$body );

		$this->mail_send( $order->get_billing_email(), $subject, $body, $headers, [
			'order_id'    => $order->get_id(),
			'attachments' => [
				$invoice_file_path,
			],
		] );
	}

}
