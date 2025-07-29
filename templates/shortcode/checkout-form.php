<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Utils\Template;

?>
<div class="storeengine-checkout-form-shortcode">
	<?php
	Template::get_template( 'checkout/form.php', [
		'order'    => $order,
		'products' => $products,
	] );
	?>
</div>
