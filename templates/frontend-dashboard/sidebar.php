<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
?>
<div class="storeengine-frontend-dashboard__sidebar" id="storeengine-frontend-dashboard-sidebar">
	<div class="storeengine-frontend-dashboard__user">
		<div class="user-title" id="user-avatar">
			<?php echo get_avatar( get_current_user_id(), 96, '', wp_get_current_user()->display_name, [ 'loading' => 'lazy' ] ); ?>
			<span class="storeengine-dashboard-menu__item-label"><?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
			<span id="user-dropdown-icon" class="storeengine-icon storeengine-icon--angle-right"></span>
		</div>
		<div id="storeengine-collapsible-menu-close-button" class="storeengine-collapsible-menu storeengine-collapsible-menu--close" role="presentation">
			<span class="storeengine-icon storeengine-icon--arrow-left" aria-hidden="true" style="display:flex;font-size:16px"></span>
		</div>
	</div>
	<ul class="storeengine-dashboard-menu storeengine-frontend-dashboard__menu" id="storeengine-dashboard-menu">
		<?php
		/**
		 * @hook - storeengine_frontend_dashboard_menu
		 */
		do_action( 'storeengine/frontend/dashboard_menu' )
		?>
	</ul>
</div>
