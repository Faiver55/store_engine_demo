<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\models\Cart;

$cart           = new Cart( get_current_user_id() );
$cart_sub_total = $cart->calculate_grand_total();
?>
<div class="storeengine-checkout-total">
	<h6><?php esc_html_e( 'Total', 'storeengine' ); ?></h6>
	<p><?php echo esc_html( number_format( $cart->calculate_grand_total(), 2 ) ); ?></p>u
</div>
