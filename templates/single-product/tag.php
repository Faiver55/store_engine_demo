<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( function_exists( 'storeengine_get_the_product_tag' ) ) {
	$product_id   = get_the_ID();
	$product_tags = storeengine_get_the_product_tag( $product_id );
	if ( $product_tags && ! is_wp_error( $product_tags ) ) {
		?>
		<div class="storeengine-single-product-tags">
			<span class="storeengine-single-product-tags__label"><?php echo esc_html__('Tags:', 'storeengine'); ?></span>
			<span class="storeengine-single-product-tags__items">
				<?php
					$tag_links = array();
				foreach ( $product_tags as $product_tag ) {
					$tag_links[] = '<a href="' . esc_url( get_term_link( $product_tag ) ) . '">' . esc_html( $product_tag->name ) . '</a>';
				}
					echo wp_kses( implode( ',', $tag_links ), [
						'a' => [
							'href'   => true,
							'target' => true,
							'title'  => true,
						],
					] );
				?>
			</span>
		</div>
		<?php
	}
}
