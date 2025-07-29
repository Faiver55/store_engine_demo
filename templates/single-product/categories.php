<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Utils\Template;

if ( function_exists( 'storeengine_get_the_product_category' ) ) {
	$product_id = get_the_ID();
	$categories = storeengine_get_the_product_category( $product_id );
	if ( $categories && ! is_wp_error( $categories ) ) {
		?>
		<div class="storeengine-single-product-categories">
			<span class="storeengine-single-product-categories__label"><?php echo esc_html__('Categories:', 'storeengine'); ?></span>
			<span class="storeengine-single-product-categories__items">
				<?php
					$category_links = array();
				foreach ( $categories as $category ) {
					$category_links[] = '<a href="' . esc_url( get_term_link( $category ) ) . '">' . esc_html( $category->name ) . '</a>';
				}
					echo wp_kses( implode( ',', $category_links ), [
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
?>
