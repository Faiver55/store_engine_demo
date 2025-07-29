<?php
/**
 * @var \StoreEngine\Classes\Price[] $prices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="storeengine-single-product-prices">
	<?php foreach ( $prices as $i => $price ) :
		$checked = ( 0 === $i );
		$id_attr = 'product-price-' . $price->get_id();
		?>
		<label class="storeengine-single-product-price" for="<?php echo esc_attr( $id_attr ); ?>">
		<span class="storeengine-single-product-price-summery">
			<span class="storeengine-single-product-price-label">
				<input type="radio" id="<?php echo esc_attr( $id_attr ); ?>" name="price_id"
					   value="<?php echo esc_attr( $price->get_id() ); ?>"<?php checked( $checked ); ?>/>
				<span class="storeengine-single-product-price-name"><?php echo esc_html( $price->get_name() ); ?></span>
			</span>
			<span class="storeengine-single-product-price-value">
				<?php $price->print_price_summery_html(); ?>
			</span>
		</span>
			<span
				class="storeengine-single-product-price-details"><?php $price->print_formatted_price_meta_html(); ?></span>
		</label>
	<?php endforeach; ?>
</div>

