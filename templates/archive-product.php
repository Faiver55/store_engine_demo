<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Utils\Helper;

storeengine_get_header();

$sidebar_position = Helper::get_settings( 'product_archive_sidebar_position', 'right' );

/**
 * @hook - storeengine/templates/before_main_content
 */
do_action( 'storeengine/templates/before_main_content', 'archive-product.php' );
?>
	<div class="storeengine-products">
	<div class="storeengine-container">
		<div class="storeengine-row ">
			<div class="storeengine-col-12">
				<?php do_action( 'storeengine/templates/archive_product_header' ); ?>
			</div>
			<?php if ( 'left' === $sidebar_position ) : ?>
			<div class="storeengine-col-md-3">
				<?php do_action( 'storeengine/templates/archive_product_sidebar' ); ?>
			</div>
			<?php endif; ?>
			<div class="<?php echo esc_attr( 'none' === $sidebar_position ? 'storeengine-col-12' : 'storeengine-col-md-9' ); ?>">
				<?php do_action( 'storeengine/templates/archive_product_content' ); ?>
			</div>
			<?php if ( 'right' === $sidebar_position ) : ?>
			<div class="storeengine-col-md-3">
				<?php do_action( 'storeengine/templates/archive_product_sidebar' ); ?>
			</div>
			<?php endif; ?>
		</div>
	</div>
	</div>
<?php
/**
 * @hook - storeengine/templates/after_main_content
 */
do_action( 'storeengine/templates/after_main_content', 'archive-product.php' );
?>

<?php
storeengine_get_footer();
