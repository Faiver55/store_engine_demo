<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( 'variable' !== $product_type ) {
	return;
}

// Need to make it as function/method
$variation_attributes = [];

/** @var AttributeData[] $variant_with_attributes */
$variant_with_attributes = array_merge( ...array_map( fn( $v ) => $v->get_attributes(), $available_variants ) );
foreach ( $variant_with_attributes as $variant_with_attribute ) {
	$attribute_taxonomy = get_taxonomy( $variant_with_attribute->taxonomy );
	if ( ! $attribute_taxonomy ) {
		continue;
	}

	if ( ! isset( $variation_attributes[ $variant_with_attribute->taxonomy ] ) ) {
		$variation_attributes[ $variant_with_attribute->taxonomy ] = [
			'name'     => $attribute_taxonomy->label,
			'taxonomy' => $variant_with_attribute->taxonomy,
			'options'  => [
				[
					'id'          => $variant_with_attribute->term_id,
					'name'        => $variant_with_attribute->name,
					'slug'        => $variant_with_attribute->slug,
					'description' => $variant_with_attribute->description,
				],
			],
		];
	} else {
		$variation_attributes[ $variant_with_attribute->taxonomy ]['options'][] = [
			'id'          => $variant_with_attribute->term_id,
			'name'        => $variant_with_attribute->name,
			'slug'        => $variant_with_attribute->slug,
			'description' => $variant_with_attribute->description,
		];
		$variation_attributes[ $variant_with_attribute->taxonomy ]['options']   = array_values( array_unique( $variation_attributes[ $variant_with_attribute->taxonomy ]['options'], SORT_REGULAR ) );
	}
}

$variation_attributes = array_values( $variation_attributes );

if ( ! count( $variation_attributes ) ) {
	return;
}

?>
<div class="storeengine-single-product-variations">
	<?php foreach ( $variation_attributes as $variation_attribute ) : ?>
		<div class="storeengine-single-product-variation">
			<h5 class="storeengine-single-product-variation__label"><?php echo esc_html( $variation_attribute['name'] ); ?></h5>
			<div class="storeengine-single-product-variation-items">
				<?php foreach ( $variation_attribute['options'] as $option ) : ?>
					<div id="<?php echo esc_attr( $variation_attribute['taxonomy'] . '_' . $option['slug'] ); ?>" class="storeengine-single-product-variation-item">
						<input type="radio" class="storeengine-single-product-variation-item__radio" name="<?php echo esc_attr( $variation_attribute['taxonomy'] ); ?>" id="variation_<?php echo esc_attr( $variation_attribute['taxonomy'] . $option['id'] ); ?>" value="<?php echo esc_attr( $option['slug'] ); ?>">
						<label class="storeengine-single-product-variation-item__label" for="variation_<?php echo esc_attr( $variation_attribute['taxonomy'] . $option['id'] ); ?>">
							<?php echo esc_html( $option['name'] ); ?>
						</label>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>

