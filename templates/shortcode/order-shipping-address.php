<?php
/**
 * @var Order $order Order object.
 */

use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Helper;
?>

<div class="storeengine-order-shipping-shortcode">
	<h4 class="storeengine-order-shipping-heading"><?php esc_html_e( 'Shipping Address', 'storeengine' ); ?></h4>
	<address class="storeengine-order-shipping-address">
		<p><?php echo esc_html( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ); ?></p>
		<p><?php echo esc_html( $order->get_shipping_address_1() ); ?></p>
		<p><?php echo esc_html( $order->get_shipping_address_2() ); ?></p>
		<p>
			<?php
			$country = $order->get_shipping_country();
			$state   = $order->get_shipping_state();
			echo esc_html( $country && $state && isset( Countries::init()->get_states()[ $country ][ $state ] ) ? Countries::init()->get_states()[ $country ][ $state ] : $state ); ?>
		</p>
		<p><?php echo esc_html( $order->get_shipping_city() ); ?></p>
		<p><?php echo esc_html( $order->get_shipping_postcode() ); ?></p>
		<p><?php echo esc_html( Helper::get_country_name( $order->get_shipping_country() ) ); ?></p>
		<?php if ( $order->get_shipping_email() ) { ?>
		<p><a href="mailto:<?php echo esc_attr( $order->get_shipping_email() ); ?>"><?php echo esc_html( $order->get_shipping_email() ); ?></a></p>
		<?php } ?>
		<?php if ( $order->get_shipping_phone() ) { ?>
		<p><a href="tel:<?php echo esc_url( $order->get_shipping_phone() ); ?>"><?php echo esc_html( $order->get_shipping_phone() ); ?></a></p>
		<?php } ?>
	</address>
</div>
