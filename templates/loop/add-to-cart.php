<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

global $product;

$cart_item             = storeengine_cart()->get_cart_item_by_product( get_the_ID() );
$product_count_in_cart = $cart_item ? $cart_item->quantity : 0;
$prices                = $product->get_prices();
if ( empty( $prices) || ! count($prices) ) {
	return;
}
?>

<?php if ( 'variable' === $product->get_type() ) : ?>
	<a href="<?php echo esc_url( get_the_permalink( $product->get_id() ) ); ?>" class="storeengine-btn storeengine-btn--view-options"><?php esc_html_e( 'View Options', 'storeengine' ); ?></a>
<?php else : ?>
	<form class="storeengine-ajax-add-to-cart-form storeengine-ajax-add-to-cart-form--loop" action="#" method="post">
		<div class="storeengine-ajax__amount storeengine-mb-4">
			<?php
			if ( count($prices) === 1 ) {
				Template::get_template( 'loop/price.php', [
					'price' => current( $prices),
				] );
			} else {
				Template::get_template( 'loop/prices.php', [
					'prices' => $prices,
				] );
			}
			?>
		</div>
		<div class="storeengine-add-to-cart-buttons">
			<?php wp_nonce_field( 'storeengine_add_to_cart', 'storeengine_nonce' ); ?>
			<input type="hidden" name="product_id" value="<?php the_ID(); ?>">
			<?php if ( Helper::get_settings( 'enable_direct_checkout' ) ) : ?>
				<button class="storeengine-btn storeengine-btn--direct-checkout" type="submit" data-action="buy_now"><?php esc_html_e( 'Buy Now', 'storeengine' ); ?></button>
			<?php else : ?>
				<button class="storeengine-btn storeengine-btn--add-to-cart" type="submit" data-action="add_to_cart">
					<?php
					if ( $product_count_in_cart > 0 ) {
						/* translators: %d is the number of products in the cart */
						printf( esc_html__( '%d in cart', 'storeengine' ), esc_html( $product_count_in_cart ) );
					} else {
						esc_html_e( 'Add to Cart', 'storeengine' );
					}
					?>
				</button>
			<?php endif; ?>
		</div>
	</form>
<?php endif; ?>

