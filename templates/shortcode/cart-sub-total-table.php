<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Utils\Template;
?>

<div class="storeengine-cart-sub-total-table-shortcode">
	<?php Template::get_template( 'cart/cart-total-table.php' ); ?>
</div>
