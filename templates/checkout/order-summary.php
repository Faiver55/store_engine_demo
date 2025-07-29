<?php
/**
 * @var \StoreEngine\Classes\CartItem[] $cart_items
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Template;

$cart = storeengine_cart();
?>
<h4><?php esc_html_e( 'Order details', 'storeengine' ); ?></h4>
<div class="storeengine-order-summary">
	<?php foreach ( $cart_items as $key => $cart_item ) { ?>
		<div class="storeengine-order-summary__item">
			<div class="storeengine-order-item-entry-left">
				<a href="<?php the_permalink( $cart_item->product_id ); ?>">
					<?php storeengine_product_image( 'storeengine_thumbnail', apply_filters( 'storeengine/cart/item_image_post_id', $cart_item->product_id, $cart_item ), [ 'class' => 'storeengine-product__thumbnail-image' ] ); ?>
				</a>
			</div>
			<div class="storeengine-order-item-entry-right">
				<div class="storeengine-order-item">
					<h6>
						<a href="<?php echo esc_attr( apply_filters( 'storeengine/cart/item_permalink', get_the_permalink( $cart_item->product_id ), $cart_item ) ); ?>">
							<?php echo esc_html( apply_filters( 'storeengine/cart/item_name', $cart_item->name, $cart_item ) ); ?>
						</a>
					</h6>
					<?php Template::get_template( 'cart/cart-item-data.php', [ 'item_data' => storeengine_get_cart_item_data( $cart_item ) ] ); ?>
					<div class="storeengine-order-item__the-sum">
						<div class="storeengine-order-item__price">
							<?php echo isset( $cart_item->price_html ) ? wp_kses_post( $cart_item->price_html ) : apply_filters( 'storeengine/cart/item_price', storeengine_cart()->get_product_price( $cart_item->get_price(), $cart_item->price_id, $cart_item->product_id ), $cart_item, $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
						<div class="storeengine-order-item__qty"><?php esc_attr_e( 'Quantity', 'storeengine' ); ?>
							<span>(<?php echo esc_html( $cart_item->quantity ); ?>)</span>
						</div>
					</div>
				</div>
			</div>
		</div>
	<?php } ?>
</div>
