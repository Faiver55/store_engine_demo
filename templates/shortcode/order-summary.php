<?php
/**
 * @var \StoreEngine\Classes\CartItem[] $cart_items
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Utils\Template;

?>
<div class="storeengine-order-summary-shortcode">
	<?php
	Template::get_template( 'checkout/order-summary.php', [
		'cart_items' => $cart_items,
	] );
	?>
</div>
