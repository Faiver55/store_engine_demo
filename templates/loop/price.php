<?php
/**
 * @var Price $price
 * @var bool $checked
 * @var bool $hidden
 */

use StoreEngine\Classes\Price;
use StoreEngine\Classes\Product\SimpleProduct;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! isset( $product ) ) {
	/** @var SimpleProduct $product */
	global $product;
}


?>
<div class="storeengine-product__simple-price">
	<input type="hidden" name="price_id" value="<?php echo esc_attr( $price->get_id() ); ?>" />
	<?php echo esc_html($price->print_price_html()); ?>
</div>
