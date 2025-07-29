<?php
/**
 * @var array $menu
 */

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="storeengine_dashboard-tabs">
	<?php
	$current_url = get_site_url();
	if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
		$current_url .= esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
	}
	foreach ( $menu as $menu_slug => $menu_label ) :
		$endpoint_url = Helper::get_endpoint_url( $menu_slug );
		$class        = ( $current_url === $endpoint_url ) ? 'storeengine_dashboard-tabs__selected' : '';
		?>
	<a class="storeengine_dashboard-tabs__tab <?php echo esc_html( $class ); ?>" role="presentation" href="<?php echo esc_url( $endpoint_url ); ?>"><?php echo esc_html( $menu_label ); ?></a>
	<?php endforeach; ?>
</div>
