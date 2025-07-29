<?php
/**
 * Dashboard top bar.
 *
 * @var string $page_title
 * @var string $path
 * @var string $sub_path
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Base URL paths
$dashboard_url    = '/dashboard/';
$current_url      = ( isset( $_SERVER['REQUEST_URI'] ) ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
$has_value_action = has_action( 'storeengine/templates/frontend-dashboard/topbar/breadcrumbs' );
?>

<div class="storeengine-topbar storeengine-topbar-tabs">
	<div class="storeengine-topbar__entry-left">
		<div id="storeengine-collapsible-menu-open-button" class="storeengine-collapsible-menu storeengine-collapsible-menu--open" role="presentation">
			<span id="storeengine-collapsible-menu-open-icon" class="storeengine-icon storeengine-icon--arrow-right"></span>
		</div>
		<p class="storeengine-topbar-heading">
			<span><?php echo esc_html( get_the_title() ); ?></span>
			<?php
			if ( $current_url !== $dashboard_url ) :
				echo ' <i class="storeengine-icon storeengine-icon--arrow-right" aria-hidden="true"></i> ';
				printf( ! $has_value_action ? '%s' : '<span>%s</span>', esc_html( $page_title ) );
			endif;

			do_action( 'storeengine/templates/frontend-dashboard/topbar/breadcrumbs', $path, $sub_path );
			?>
		</p>
		<?php
			do_action( 'storeengine/templates/frontend-dashboard/topbar/after_heading', $path, $sub_path )
		?>
	</div>
	<div class="storeengine-topbar__entry-right">
		<?php
			do_action( 'storeengine/templates/frontend-dashboard/topbar/right_content', $path, $sub_path )
		?>
	</div>
</div>
