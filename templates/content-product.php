<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Utils\Helper;

$column_per_row = (array) Helper::get_settings( 'product_archive_products_per_row', [
	'desktop' => 3,
	'tablet'  => 2,
	'mobile'  => 1,
] );
$grid_class     = ! empty( $grid_class ) ? $grid_class : Helper::get_responsive_column( $column_per_row );
?>
<div class="<?php echo esc_attr( $grid_class ); ?>">
	<div class="storeengine-product">
		<?php
		do_action( 'storeengine/templates/before_product_loop' );
		/**
		 * @hook -'storeengine/templates/product_loop_header
		 */
		do_action( 'storeengine/templates/product_loop_header' );
		/**
		 * @hook -'storeengine/templates/product_loop_content
		 */
		do_action( 'storeengine/templates/product_loop_content' );
		/**
		 * @hook -'storeengine/templates/product_loop_footer
		 */
		do_action( 'storeengine/templates/product_loop_footer' );
		?>
	</div>
</div>
