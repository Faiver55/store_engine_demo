<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="storeengine-col-lg-12">
	<nav class="navigation storeengine-product-navigation storeengine__footer-archive" aria-label="<?php esc_attr_e( 'Product navigation', 'storeengine' ); ?>">
		<h2 class="screen-reader-text"><?php esc_html_e( 'Product navigation', 'storeengine' ); ?></h2>
		<div class="nav-links storeengine-pagination storeengine-products__pagination">
			<?php
			global $wp_query;
			$max_pages = $max_num_pages ?? $wp_query->max_num_pages;

			if ( $max_pages > 1 ) {
				$big = 999999999;
				echo wp_kses_post( paginate_links( [
					'base'      => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
					'format'    => '?paged=%#%',
					'current'   => max( 1, $paged ?? get_query_var( 'paged' ) ),
					'total'     => $max_pages,
					'prev_text' => '<i class="storeengine-icon storeengine-icon--arrow-left" aria-label="' . esc_attr__( 'Previous', 'storeengine' ) . '"></i>',
					'next_text' => '<i class="storeengine-icon storeengine-icon--arrow-right" aria-label="' . esc_attr__( 'Next', 'storeengine' ) . '"></i>',
				] ) ?? '' );
			}
			?>
		</div>
	</nav>
</div>
