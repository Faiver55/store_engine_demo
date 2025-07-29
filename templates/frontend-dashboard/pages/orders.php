<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Template;
?>
<div class="kzui-list-table--orders">
	<?php Template::get_template( 'frontend-dashboard/partials/orders.php' ); ?>
</div>
