<?php
/**
 * @var string $empty_message
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Classes\Cart;
use StoreEngine\Utils\Template;

?>

<?php if ( storeengine_cart()->is_cart_empty() ) : ?>
	<p><?php echo esc_html( $empty_message ); ?></p>
<?php else : ?>
	<table class="storeengine-cart-table">
		<tbody class="storeengine-cart-table__body">
		<?php foreach ( storeengine_cart()->get_cart_items() as $key => $cart_item ) : ?>
			<tr class="storeengine-cart-table__row cart-item-<?php echo esc_attr( $key ); ?>"
				data-cart_item_id="<?php echo esc_attr( $key ); ?>">
				<td class="storeengine-cart-table__body-td"
					data-title="<?php esc_attr_e( 'Product', 'storeengine' ); ?>">
					<div class="storeengine-cart-product">
						<div class="storeengine-cart-product__thumbnail">
							<a href="<?php the_permalink( $cart_item->product_id ); ?>">
								<?php storeengine_product_image( 'storeengine_thumbnail', apply_filters( 'storeengine/cart/item_image_post_id', $cart_item->product_id, $cart_item ), [ 'class' => 'storeengine-thumbnail' ] ); ?>
							</a>
						</div>
						<div class="storeengine-cart-product__content">
							<h6 class="storeengine-cart-product-title">
								<a href="<?php echo esc_attr( apply_filters( 'storeengine/cart/item_permalink', get_the_permalink( $cart_item->product_id ), $cart_item ) ); ?>"><?php echo esc_html( apply_filters( 'storeengine/cart/item_name', $cart_item->name, $cart_item ) ); ?></a>
							</h6>
							<div
								class="storeengine-cart-product-price"><?php echo esc_html( $cart_item->price_name ); ?></div>
							<div class="storeengine-cart-product-quantity-wrap">
								<?php
								Template::get_template( 'cart/quantity-form.php', apply_filters( 'storeengine/cart/quantity_form_args', [
									'item_key'   => $key,
									'product_id' => $cart_item->product_id,
									'quantity'   => $cart_item->quantity,
									'price_id'   => $cart_item->price_id,
									'disabled'   => 'subscription' === $cart_item->price_type,
								], $cart_item ) );
								?>
								<a class="storeengine-remove-cart-item"
								   href="<?php echo esc_url( Cart::get_remove_item_url( $key ) ); ?>"
								   data-item_key="<?php echo esc_attr( $key ); ?>"
								   aria-label="<?php esc_attr_e( 'Remove this item', 'storeengine' ); ?>">
									<i class="storeengine-icon storeengine-icon--trash" aria-hidden="true"></i>
								</a>
							</div>
						</div>
					</div>
				</td>
				<td class="storeengine-cart-table__body-td storeengine-cart-table__body-td--price"
					data-title="<?php esc_attr_e( 'Price', 'storeengine' ); ?>">
					<?php echo apply_filters( 'storeengine/cart/item_price', storeengine_cart()->get_product_price( $cart_item->get_price(), $cart_item->price_id, $cart_item->product_id ), $cart_item, $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php
endif;

/**
 * @hook -'storeengine/templates/after_cart_list_table
 */
do_action( 'storeengine/templates/after_cart_list_table', 'cart-list-table.php' );
