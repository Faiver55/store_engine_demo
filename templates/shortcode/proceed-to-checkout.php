<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Utils\Template;
?>
<div class="storeengine-proceed-to-checkout-shortcode">
	<?php
		Template::get_template( 'cart/proceed-to-checkout.php' );
	?>
</div>
