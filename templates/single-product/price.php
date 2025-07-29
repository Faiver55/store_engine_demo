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

$id_attr = 'product-price-' . $product->get_id() . '-' . $price->get_id();

?>
<div class="storeengine-single-product-simple-price">
	<input type="hidden" name="price_id" value="<?php echo esc_attr( $price->get_id() ); ?>" />
	<?php $price->print_price_html(); ?>
</div>

