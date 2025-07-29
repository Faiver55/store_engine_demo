<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $authordata;
?>


<div class="storeengine-product__body">
	<?php
	/**
	 * Hook - storeengine/templates/before_course_loop_content_inner
	 */
	do_action( 'storeengine/templates/before_course_loop_content_inner' );
	?>
	<div class="storeengine-product__meta storeengine-product__meta--categroy">
		<?php do_action( 'storeengine/templates/single_categories' ); ?>
	</div>
	<h4 class="storeengine-product__title storeengine-product__text-center"><a href="<?php echo esc_url( get_the_permalink() ); ?>"><?php the_title(); ?></a></h4>
	<?php do_action( 'storeengine/templates/after_course_loop_content_inner' ); ?>
</div>
