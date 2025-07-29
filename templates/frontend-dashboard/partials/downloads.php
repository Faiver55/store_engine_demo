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

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$paged                    = max( 1, get_query_var('paged') );
$downloadable_permissions = Helper::get_download_permissions_by_customer_id($paged);
?>
<div class="storeengine-dashboard__section">
	<div class="storeengine-dashboard__downloads">
		<?php if ( ! empty($downloadable_permissions) ) : ?>
			<?php foreach ( $downloadable_permissions as $downloadable_permission ) : ?>
				<div class="storeengine-dashboard__downloads-items">
					<h4><?php echo esc_html($downloadable_permission->get_product_title()); ?></h4>
					<a href="<?php echo esc_attr($downloadable_permission->get_download_url()); ?>">
						<?php echo esc_html($downloadable_permission->get_file_name()); ?>
					</a>
				</div>
			<?php endforeach; ?>
			<?php
				do_action( 'storeengine/templates/dashboard_downloads_pagination', $downloadable_permissions );
			?>
		<?php else : ?>
		<div class="storeengine-oops storeengine-oops__message">
			<div class="storeengine-oops__icon">
				<h3 class="storeengine-oops__heading"><?php esc_html_e( 'No data Available!', 'storeengine' ); ?></h3>
				<h3 class="storeengine-oops__text"><?php esc_html_e( 'No purchase data was found to see the download list here.', 'storeengine' ); ?></h3>
			</div>
		</div>
		<?php endif; ?>
	</div>
</div>
