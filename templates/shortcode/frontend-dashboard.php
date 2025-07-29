<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="storeengine-frontend-dashboard">
	<div class="storeengine-container">
		<div class="storeengine-row">
			<div class="storeengine-col-lg-12">
				<?php \StoreEngine\Utils\Helper::get_template( 'frontend-dashboard/sidebar.php' ); ?>
				<?php \StoreEngine\Utils\Helper::get_template( 'frontend-dashboard/content.php' ); ?>
			</div>
		</div>
	</div>
</div>
