<?php
/**
 * @var \StoreEngine\Classes\Price $prices
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;
$cart_items = ! empty( storeengine_cart()->get_cart_items_by_product( get_the_ID() ) );
?>

<div class="storeengine-product__multi-prices storeengine-dropdown" data-dropdown>
	<div class="storeengine-dropdown__toggle" data-dropdown-toggle></div>
	<div class="storeengine-dropdown-content" data-dropdown-list>
	<?php foreach ( $prices as $i => $price ) :
		$id_attr   = 'product-price-' . $product->get_id() . '-' . $price->get_id();
		$cart_item = $cart_items ? storeengine_cart()->get_cart_item_by_product( get_the_ID(), $price->get_id() ) : null;
		$checked   = $cart_item || 0 === $i;
		?>
		<div class="storeengine-product__multi-price">
			<input
				class="storeengine-hide"
				type="radio"
				name="price_id"
				id="<?php echo esc_attr( $id_attr ); ?>"
				value="<?php echo esc_attr( $price->get_id() ); ?>"
				data-price_type="<?php echo esc_attr( $price->get_price_type() ); ?>"
				<?php echo $cart_item && $cart_item->price_id === $price->get_id() ? 'data-cart_count="' . esc_attr( $cart_item->quantity ) . '"' : ''; ?>
				<?php checked( $checked ); ?>
			/>
			<label for="<?php echo esc_attr( $id_attr ); ?>">
				<span class="storeengine-loop-product-price-summery">
					<span class="storeengine-loop-product-price-label"><?php echo esc_html( $price->get_name() ); ?></span>
					<span class="storeengine-loop-product-price-value">
						<?php $price->print_price_summery_html(); ?>
						<span class="storeengine-icon--arrow-square-down" aria-hidden="true"></span>
					</span>
				</span>
				<span class="storeengine-loop-product-price-details"><?php $price->print_formatted_price_meta_html(); ?></span>
			</label>
		</div>
	<?php endforeach; ?>
	</div>
	<input type="hidden" name="price_id" value="<?php echo esc_attr( current( $prices )->get_id() ); ?>">
</div>



