<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="storeengine-frontend-dashboard__content">
	<?php
	/**
	 * @hook -'storeengine/frontend/dashboard_content
	 */
	do_action( 'storeengine/frontend/dashboard_content' )
	?>
</div>
