<?php
/**
 * @var string $empty_message
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Utils\Template;
?>

<div class="storeengine-cart-list-table-shortcode">
	<?php Template::get_template( 'cart/cart-list-table.php', [
	'empty_message' => $empty_message,
] ); ?>
</div>
