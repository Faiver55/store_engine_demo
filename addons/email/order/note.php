<?php

namespace StoreEngine\Addons\Email\order;

use StoreEngine\Classes\Order;
use StoreEngine\Addons\Email\Traits\Email;

class Note {

	use Email {
		Email::__construct as private __EmailConstruct;
	}

	public function __construct() {
		$this->__EmailConstruct( 'order_note' );

		add_action( 'storeengine/order/new_customer_note', [ $this, 'send_order_email' ], 99, 2 );
	}

	public function send_order_email( string $note, Order $order ) {
		$is_for_customer = $this->get_settings( 'customer' );

		if ( ! is_array( $is_for_customer ) ) {
			return;
		}

		if ( $is_for_customer['is_enable'] ) {
			$this->send_customer_mail( $order, $note, $is_for_customer );
		}
	}

	private function send_customer_mail( Order $order, string $note, array $settings ) {
		$subject = $this->get_email_subject( $order, $settings['email_subject'] );
		// get email data.
		list( $headers, $body ) = $this->get_the_email_body( $settings, 'email/order-note-customer.php' );

		$body = $this->get_order_email_body( $order, str_replace( '{order_note}', $note, $body ) );

		$this->mail_send( $order->get_billing_email(), $subject, $body, $headers, [
			'order_id' => $order->get_id(),
		] );
	}

}
