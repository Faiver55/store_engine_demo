<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="academy-widget-enroll__continue">
	<a class="academy-btn academy-btn--bg-purple" href="<?php echo esc_url( \Academy\Helper::get_frontend_dashboard_endpoint_url( 'enrolled-courses' ) ); ?>">
		<?php esc_html_e( 'View Courses', 'storeengine' ); ?>
	</a>
</div>
