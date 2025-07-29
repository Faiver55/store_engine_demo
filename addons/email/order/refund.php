<?php

namespace StoreEngine\Addons\Email\order;

use StoreEngine\Classes\Order;
use StoreEngine\Classes\Refund as OrderRefund;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Addons\Email\Traits\Email;

class Refund {

	use Email {
		Email::__construct as private __EmailConstruct;
	}

	public function __construct() {
		$this->__EmailConstruct( 'order_refund' );
		add_action( 'storeengine/order/refund_created', [ $this, 'send_mail' ] );
	}

	public function send_mail( OrderRefund $refund ) {
		$settings = $this->get_settings( 'customer' );

		if ( ! is_array( $settings ) || ! $settings['is_enable'] ) {
			return;
		}

		$order   = new Order( $refund->get_parent_order_id() );
		$subject = $this->get_email_subject( $order, $settings['email_subject'] );

		list( $headers, $body ) = $this->get_the_email_body( $settings, 'email/order-refund-customer.php' );

		$body = $this->get_order_email_body( $order, $body );

		// parse `order_refunds` shortcode.
		$body = $this->prepare_body_without_layout(
			str_replace( array( '{order_refunds}' ),
				'<ul>' . implode( '', array_map( function ( $refund ) {
					$refund_template = '<li data-list=bullet>{refund_name}: <strong>{refund_amount}</strong></li>';
					$refund_by       = $refund->get_refunded_by() ? $refund->get_refunded_by_user()->get_display_name() : null;
					$date            = gmdate( 'F j, Y', strtotime( $refund->get_date_created_gmt() ) );
					if ( $refund_by ) {
						$name = sprintf(
						/* translators: %1$d. Refund ID. %2$s. Refund Date. %3$s. Refund by (user's display-name) */
							__( 'Refund #%1$d - %2$s by %3$s', 'storeengine' ),
							$refund->get_id(),
							$date,
							$refund_by
						);
					} else {
						$name = sprintf(
						/* translators: %1$d. Refund ID. %2$s. Refund Date. */
							__( 'Refund #%1$d - %2$s', 'storeengine' ),
							$refund->get_id(),
							$date
						);
					}

					return str_replace(
						[ '{refund_name}', '{refund_amount}' ],
						[ $name, Formatting::price( $refund->get_total() ) ],
						$refund_template
					);
				}, $order->get_refunds() ) ) . '</ul>',
				$body )
		);

		$this->mail_send( $order->get_billing_email(), $subject, $body, $headers, [
			'order_id' => $order->get_id(),
		] );
	}

}
