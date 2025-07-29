<?php

namespace StoreEngine\Addons\Email\order;

use StoreEngine\Classes\Order;
use StoreEngine\Addons\Email\Traits\Email;

class Confirm {

	use Email {
		Email::__construct as private __EmailConstruct;
	}

	public function __construct() {
		$this->__EmailConstruct( 'order_confirmation' );

		add_action( 'storeengine/checkout/after_place_order', [ $this, 'send_order_email' ], 99 );
	}

	public function send_order_email( Order $order ) {
		$is_for_admin    = $this->get_settings( 'admin' );
		$is_for_customer = $this->get_settings( 'customer' );

		if ( ( ! is_array( $is_for_admin ) && ! is_array( $is_for_customer ) ) ) {
			return;
		}

		if ( $is_for_admin['is_enable'] ) {
			$this->send_admin_mail( $order, $is_for_admin );
		}

		if ( $is_for_customer['is_enable'] ) {
			$this->send_customer_mail( $order, $is_for_customer );
		}
	}

	private function send_admin_mail( Order $order, array $settings ) {
		$subject = $this->get_email_subject( $order, $settings['email_subject'] );
		// get email data.
		list( $headers, $body ) = $this->get_the_email_body( $settings, 'email/order-confirmation-admin.php' );

		$body = $this->get_order_email_body( $order, $body );

		$this->mail_send( get_option( 'admin_email' ), $subject, $body, $headers, [
			'order_id' => $order->get_id(),
		] );
	}

	private function send_customer_mail( Order $order, array $settings ) {
		$subject = $this->get_email_subject( $order, $settings['email_subject'] );
		// get email data.
		list( $headers, $body ) = $this->get_the_email_body( $settings, 'email/order-confirmation-customer.php' );

		$body = $this->get_order_email_body( $order, $body );

		$this->mail_send( $order->get_billing_email(), $subject, $body, $headers, [
			'order_id' => $order->get_id(),
		] );
	}

}
