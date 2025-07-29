<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

storeengine_get_header();

?>

<?php
	/**
	 * @Hook - storeengine/templates/before_main_content
	 */
	do_action( 'storeengine/templates/before_main_content', 'storeengine-canvas.php' );
?>
<div class="storeengine-canvas">
	<div class="<?php storeengine_get_the_canvas_container_class(); ?>">
		<div class="storeengine-row">
			<div class="storeengine-col-12">
			<?php
			while ( have_posts() ) :
				the_post();
				the_content();
			endwhile;
			?> 
			</div>
		</div>
	</div>
</div>

<?php
	/**
	 * @Hook - storeengine/templates/before_main_content
	 */
	do_action( 'storeengine/templates/after_main_content', 'storeengine-canvas.php' );
?>

<?php
storeengine_get_footer();
