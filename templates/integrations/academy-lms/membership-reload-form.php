<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="academy-widget-enroll__enroll-form">
	<a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>">
		<button type="submit" class="academy-btn academy-btn--bg-purple"><?php esc_html_e( 'Enroll Now', 'storeengine' ); ?></button>
	</a>
</div>
