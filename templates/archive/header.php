<?php

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="storeengine-products__header">
	<?php
	if ( ! Helper::is_fse_theme() ) :
		$current_page_obj = get_queried_object();
		$shop_page        = true;
		if ( $current_page_obj instanceof WP_Term ) {
			$shop_page     = false;
			$term_taxonomy = get_taxonomy( $current_page_obj->taxonomy );
			$page_title    = $term_taxonomy->labels->singular_name . ': ' . $current_page_obj->name;
			$description   = $current_page_obj->description;
		} else {
			global $storeengine_settings;
			$page_title  = get_the_title( $storeengine_settings->shop_page );
			$description = '';
		}
		?>
	<div>
		<h1 class="entry-title">
			<?php echo esc_html( $page_title ); ?>
		</h1>
		<?php if ( ! $shop_page ) : ?>
			<div class="term-description">
				<p><?php echo esc_html($description); ?></p>
			</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>
	<?php
	/**
	 * Hook: storeengine/templates/archive_product_header.
	 *
	 * @Hooked: storeengine_archive_course_header_filter - 10
	 */
	?>
	<div class="storeengine-products__filter-wrapper">
		<?php do_action( 'storeengine/templates/archive_header_filter' ); ?>
	</div>
</div>
