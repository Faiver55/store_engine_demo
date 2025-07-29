<?php
/**
 * @var $logout_redirect_url string Logout redirect url.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="storeengine-logged-in-message">
	<?php
	echo sprintf(
	/* translators: %1$s: User name, %2$s: Opening <a> tag, %3$s: Closing </a> tag */
		esc_html__( 'You are Logged in as %1$s (%2$sLogout%3$s)', 'storeengine' ),
		esc_html( wp_get_current_user()->display_name ),
		'<a href="' . esc_url( storeengine_logout_url( $logout_redirect_url ) ) . '">',
		'</a>'
	);
	?>
</div>
