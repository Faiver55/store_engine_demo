<?php
/**
 * Add to cart quantity input.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! isset( $quantity ) ) {
	$quantity = 1;
}

?>
<div class="storeengine-single-product-quantity">
	<input
		aria-label="<?php esc_attr_e( 'Product quantity', 'storeengine' ); ?>"
		type="number"
		name="quantity"
		value="<?php echo esc_attr( $quantity ); ?>"
		min="1"
		step="1"
		autocomplete="on"
	/>
</div>
