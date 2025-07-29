<?php
/**
 * The Template for displaying all single products
 *
 * This template can be overridden by copying it to yourtheme storeengine/single-product.php.
 *
 * the readme will list any important changes.
 *
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

if ( ! Helper::is_fse_theme() ) {
	storeengine_get_header();
}
/**
 * @hook -'storeengine/templates/before_main_content
 */
do_action( 'storeengine/templates/before_main_content', 'single-product.php' );
?>
	<div class="storeengine-single-product">
		<div class="storeengine-container">
			<div class="storeengine-row">
				<div class="storeengine-col-12">
					<?php while ( have_posts() ) : ?>
						<?php the_post(); ?>
						<?php Template::get_template_part( 'content', 'single-product' ); ?>
					<?php endwhile; // end of the loop. ?>
				</div>
			</div>
		</div>
	</div>
<?php
	/**
	 * @hook -'storeengine/templates/after_main_content
	 */
	do_action( 'storeengine/templates/after_main_content', 'single-product.php' );

if ( ! Helper::is_fse_theme() ) {
	storeengine_get_footer();
}
