<?php
/**
 * @var Order $order
 * @var bool $is_cart_empty
 * @var bool $is_digital_cart
 * @var int $products
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<form class="storeengine-ajax-checkout-form" action="<?php echo esc_url(get_the_permalink()); ?>" method="post">
	<?php wp_nonce_field( 'product_add_to_checkout', 'storeengine_nonce' ); ?>
	<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">
	<input type="hidden" name="order_key" value="<?php echo esc_attr( $order->get_order_key() ); ?>">
	<?php
		do_action( 'storeengine/templates/before_checkout_form' );
		do_action( 'storeengine/templates/checkout_form_fields' );
		do_action( 'storeengine/templates/after_checkout_form' );
	?>
</form>
