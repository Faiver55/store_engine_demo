<?php
/**
 * @var Order $order Order object.
 */

use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Helper;

?>

<div class="storeengine-order-billing-shortcode">
	<h4 class="storeengine-order-billing-heading"><?php esc_html_e( 'Billing Address', 'storeengine' ); ?></h4>
	<address class="storeengine-order-billing-address">
		<p><?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?></p>
		<p><?php echo esc_html( $order->get_billing_address_1() ); ?></p>
		<p><?php echo esc_html( $order->get_billing_address_2() ); ?></p>
		<p>
			<?php
			$country = $order->get_billing_country();
			$state   = $order->get_billing_state();
			echo esc_html( $country && $state && isset( Countries::init()->get_states()[ $country ][ $state ] ) ? Countries::init()->get_states()[ $country ][ $state ] : $state ); ?>
		</p>
		<p><?php echo esc_html( $order->get_billing_city() ); ?></p>
		<p><?php echo esc_html( $order->get_billing_postcode() ); ?></p>
		<p><?php echo esc_html( Helper::get_country_name( $order->get_billing_country() ) ); ?></p>
		<?php if ( $order->get_billing_email() ) { ?>
		<p><a href="mailto:<?php echo esc_attr( $order->get_billing_email() ); ?>"><?php echo esc_html( $order->get_billing_email() ); ?></a></p>
		<?php } ?>
		<?php if ( $order->get_billing_phone() ) { ?>
		<p><a href="tel:<?php echo esc_url( $order->get_billing_phone() ); ?>"><?php echo esc_html( $order->get_billing_phone() ); ?></a></p>
		<?php } ?>
	</address>
</div>
