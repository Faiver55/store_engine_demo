<?php
namespace  StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;
use StoreEngine\Models\Cart as CartModel;

class CartSubTotalTable {
	public function __construct() {
		add_shortcode( 'storeengine_cart_sub_total_table', array( $this, 'render_cart_sub_total_table' ) );
	}
	public function render_cart_sub_total_table() {
		ob_start();
		Template::get_template( 'shortcode/cart-sub-total-table.php'  );
		return ob_get_clean();
	}
}
