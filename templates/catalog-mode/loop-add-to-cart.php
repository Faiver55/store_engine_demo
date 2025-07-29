<?php
/**
 * @var bool $hide_pricing
 * @var bool $hide_add_to_cart
 * @var string $pricing_placeholder
 * @var string $add_to_cart_placeholder
 * @var ?array $add_to_cart_button
 */

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

global $product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<form class="storeengine-catalog-mode storeengine-catalog-mode--loop storeengine-ajax-add-to-cart-form storeengine-ajax-add-to-cart-form--loop" action="#" method="post">
	<?php
	if ( $hide_pricing ) {
		if ( $pricing_placeholder ) {
			?>
			<div class="storeengine-catalog-mode--pricing-placeholder storeengine-mt-6">
				<p><?php echo wp_kses_post( $pricing_placeholder ); ?></p>
			</div>
			<?php
		}
	} else {
		if ( 'variable' !== $product->get_type() ) {
			$prices = $product->get_prices();
			?>
			<div class="storeengine-ajax__amount storeengine-mb-4">
				<?php
				if ( count( $prices ) === 1 ) {
					Template::get_template( 'loop/price.php', [ 'price' => current( $prices ) ] );
				} else {
					Template::get_template( 'loop/prices.php', [ 'prices' => $prices ] );
				}
				?>
			</div>
			<?php
		}
	}
	if ( $hide_add_to_cart ) {
		if ( $add_to_cart_placeholder ) {
			?>
			<div class="storeengine-catalog-mode--add-to-cart-placeholder storeengine-mt-6">
				<p><?php echo wp_kses_post( $add_to_cart_placeholder ); ?></p>
			</div>
			<?php
		}
		if ( $add_to_cart_button ) {
			?>
			<div class="storeengine-catalog-mode--add-to-cart-replacement storeengine-mt-6">
				<div class="storeengine-add-to-cart-buttons">
					<a href="<?php echo esc_url( $add_to_cart_button['button_link'] ); ?>" class="storeengine-btn storeengine-btn--add-to-cart-replacement" rel="<?php echo esc_attr( $add_to_cart_button['button_rel'] ); ?>" target="<?php echo esc_attr( $add_to_cart_button['button_target'] ); ?>"><?php echo esc_html( $add_to_cart_button['button_text'] ); ?></a>
				</div>
			</div>
			<?php
		}
	} else {
		if ( 'variable' === $product->get_type() ) {
			?>
			<a href="<?php echo esc_url( get_the_permalink( $product->get_id() ) ); ?>" class="storeengine-btn storeengine-btn--view-options"><?php esc_html_e( 'View Options', 'storeengine' ); ?></a>
			<?php
		} else {
			$cart_item             = storeengine_cart()->get_cart_item_by_product( get_the_ID() );
			$product_count_in_cart = $cart_item ? $cart_item->quantity : 0;
			wp_nonce_field( 'storeengine_add_to_cart', 'storeengine_nonce' );
			?>
			<div class="storeengine-add-to-cart-buttons">
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
			<?php
		}
	}
	?>
</form>
