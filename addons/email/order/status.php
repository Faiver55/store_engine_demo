<?php

namespace StoreEngine\Addons\Email\order;

use StoreEngine\Classes\Order;
use StoreEngine\Addons\Email\Traits\Email;

class Status {

	use Email {
		Email::__construct as private __EmailConstruct;
	}

	public function __construct() {
		$this->__EmailConstruct('order_status');
		add_action( 'storeengine/order/status_changed', [ $this, 'send_mail' ], 10, 4 );
	}

	public function send_mail( $order_id, $old_status, $new_status, Order $order ) {
		$settings = $this->get_settings( 'customer' );

		if ( ! is_array( $settings ) || ! $settings['is_enable'] || 'draft' === $old_status ) {
			return;
		}

		$subject                = $this->get_email_subject( $order, $settings['email_subject'] );
		list( $headers, $body ) = $this->get_the_email_body( $settings, 'email/order-status-customer.php' );
		$body                   = $this->get_order_email_body( $order, $body );

		// parse `order_old_status` && `{order_new_status}` shortcode.
		$body = str_replace(
			array( '{order_old_status}', '{order_new_status}' ),
			array( ucfirst( str_replace( '_', ' ', $old_status ) ), ucfirst( str_replace( '_', ' ', $new_status ) ) ),
		$body );

		$this->mail_send( $order->get_billing_email(), $subject, $body, $headers, [
			'order_id' => $order->get_id(),
		] );
	}

}
