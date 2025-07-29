<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Utils\Template;
?>
<div class="storeengine-continue-shopping-shortcode"><?php Template::get_template( 'cart/continue-shopping.php' ); ?></div>
