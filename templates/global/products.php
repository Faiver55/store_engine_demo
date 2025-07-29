<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="storeengine-products__body">
	<div class="storeengine-row storeengine-gap-20">
		<?php
			do_action( 'storeengine/templates/before_product_loop' );
		if ( have_posts() ) {
			// Load posts loop.
			while ( have_posts() ) {
				the_post();
				/**
				 * Hook: storeengine/templates/product_loop.
				 */
				do_action( 'storeengine/templates/product_loop' );

				StoreEngine\Utils\Template::get_template_part( 'content', 'product' );
			}

			/**
			 * Hook: storeengine/templates/after_product_loop
			 *
			 * @Hooked: storeengine_product_pagination - 10
			 */
			do_action( 'storeengine/templates/after_product_loop' );
		} else {
			// If no content, include the "No posts found" template.
			/**
			 * Hook: storeengine/templates/no_product_found
			 *
			 * @Hooked: storeengine_no_product_found - 10
			 */
			do_action( 'storeengine/templates/no_product_found' );
		}//end if
		?>
			<?php do_action( 'storeengine/templates/archive_product_footer' ); ?>
	</div>
</div>
