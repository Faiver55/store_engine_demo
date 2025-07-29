<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

<div class="storeengine-product__header">
	<?php
		do_action( 'storeengine/templates/before_product_loop_header_inner' );
	?>
	<div class="storeengine-product__thumbnail">
		<a href="<?php echo esc_url( get_the_permalink() ); ?>">
			<?php storeengine_product_image( 'storeengine_thumbnail', null, [ 'class' => 'storeengine-product__thumbnail-image' ] ); ?>
		</a>
	</div>
	<?php do_action( 'storeengine/templates/after_product_loop_header_inner' ); ?>
</div>
