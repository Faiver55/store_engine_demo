<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>


<div class="storeengine-frontend-dashboard-page storeengine-frontend-dashboard-page--dashboard">
	<?php
	\StoreEngine\Utils\Helper::get_template( 'frontend-dashboard/partials/subscriptions.php' );
	?>
	<?php
	\StoreEngine\Utils\Helper::get_template( 'frontend-dashboard/partials/orders.php' );
	?>
</div>
