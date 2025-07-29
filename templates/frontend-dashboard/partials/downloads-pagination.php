<?php

use StoreEngine\Utils\Helper;

if ( ! defined('ABSPATH') ) {
	exit; // Exit if accessed directly
}

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$per_page          = 10;
$total_permissions = Helper::get_download_permissions_count_by_customer_id();
$downloads_url     = storeengine_get_dashboard_endpoint_url( 'downloads' );
$total_pages       = ceil( $total_permissions / $per_page );
$current_page      = max( 1, get_query_var( 'paged' ) );
$previous_page     = $current_page - 1;
?>
<div class="storeengine__order-pagination">
	<?php if ( $total_pages > 1 ) : ?>
		<ul class="pagination">
			<?php if ( $previous_page > 0 ) : ?>
				<li>
					<a href="<?php echo esc_attr(add_query_arg('paged', max(1, $current_page - 1), $downloads_url)); ?>" class="prev">
						<i class="storeengine-icon storeengine-icon--arrow-left"></i>
					</a>
				</li>
			<?php endif; ?>
			<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
				<li>
					<a href="<?php echo esc_attr(add_query_arg('paged', $i, $downloads_url)); ?>" class="<?php echo esc_attr($i === $current_page ? 'active' : ''); ?>">
						<?php echo esc_html($i); ?>
					</a>
				</li>
			<?php endfor; ?>
			<?php if ( $total_pages > $current_page ) : ?>
				<li>
					<a href="<?php echo esc_attr(add_query_arg('paged', min($total_pages, $current_page + 1), $downloads_url)); ?>" class="next">
						<i class="storeengine-icon storeengine-icon--arrow-right"></i>
					</a>
				</li>
			<?php endif; ?>
		</ul>
	<?php endif; ?>
</div>
