<?php
/**
 * @var SimpleProduct|VariableProduct $product
 * @var bool $direct_checkout
 * @var string $label
 * @var bool $show_quantity
 * @var int $quantity
 * @var int $price_id
 * @var int $variation_id
 * @var bool $disabled
 * @var array $prices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Classes\Product\SimpleProduct;
use StoreEngine\Classes\Product\VariableProduct;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

$cart_item             = storeengine_cart()->get_cart_item_by_product( $product->get_id() );
$product_count_in_cart = $cart_item ? $cart_item->quantity : 0;
?>
<form class="storeengine-ajax-add-to-cart-form storeengine-add-to-cart-shortcode" action="#" method="post">
	<?php wp_nonce_field( 'storeengine_add_to_cart', 'storeengine_nonce' ); ?>
	<input type="hidden" name="product_id" value="<?php echo esc_attr( $product->get_id() ); ?>">
	<input type="hidden" name="variation_id"
		   value="<?php echo esc_attr( $variation_id || $cart_item ? $cart_item->variation_id : 0 ); ?>"/>

	<?php if ( count( $prices ) > 1 && ! $price_id ) : ?>
		<div class="storeengine-single__amount storeengine-mb-2">
			<p><?php echo esc_html( 'Price' ); ?></p>
			<?php
			Template::get_template( 'single-product/prices.php', [
				'prices'  => $prices,
				'product' => $product,
			] );
			?>
		</div>
	<?php else : ?>
		<input type="hidden" name="price_id" value="<?php echo esc_attr( $price_id ); ?>">
	<?php endif; ?>

	<div class="storeengine-single-product-quantity-wrap">
		<?php if ( $show_quantity ) :
			Template::get_template( 'single-product/quantity-input.php', [
				'quantity' => $quantity,
			] );
		else : ?>
			<input type="hidden" name="quantity" value="<?php echo esc_html( $quantity ); ?>">
		<?php endif; ?>

		<button class="storeengine-btn storeengine-btn--add-to-cart "
				type="submit"
				data-label="<?php echo esc_attr( $label ); ?>"
				data-action="<?php echo esc_attr( $direct_checkout ? 'buy_now' : 'add_to_cart' ); ?>" <?php disabled( $disabled ); ?>>
			<?php echo esc_html( $label ); ?>
		</button>
	</div>
</form>
