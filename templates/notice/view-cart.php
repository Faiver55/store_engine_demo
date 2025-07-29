<?php
/**
 * @var int $total_quantity_in_cart
 * @var int $num_prices_in_cart
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use StoreEngine\Utils\Helper;
?>
<div class="storeengine-notice storeengine-notice--info storeengine-notice--alt">
	<div class="storeengine-notice--message">
		<p>
			<i class="storeengine-icon storeengine-icon--info" aria-hidden="true"></i>
			<?php
			if ( 1 === $num_prices_in_cart ) {
				printf(
				/* translators: 1: number of products, 2: product name */
					esc_html__( '%1$d × %2$s have been added to your cart.', 'storeengine' ),
					esc_html( $total_quantity_in_cart ),
					esc_html( get_the_title() )
				);
			} else {
				printf(
				/* translators: 1: number of products, 2: product name, 3. price count */
					esc_html__( '%1$d × %2$s have been added to your cart with %3$s.', 'storeengine' ),
					esc_html( $total_quantity_in_cart ),
					esc_html( get_the_title() ),
					sprintf(
					/* translators: %d: num price in cart. */
						_n( '%d different price', '%d different prices', $num_prices_in_cart, 'storeengine' ),
						esc_html( $num_prices_in_cart )
					)
				);
			}
			?>
		</p>
		<div class="storeengine-notice--control">
			<a href="<?php echo esc_url( Helper::get_page_permalink( 'cart_page' ) ); ?>"><?php esc_attr_e( 'View Cart', 'storeengine' ); ?></a>
		</div>
	</div>
</div>

