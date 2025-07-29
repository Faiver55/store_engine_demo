<?php

namespace StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

class CartListTable {
	public function __construct() {
		add_shortcode( 'storeengine_cart_list_table', array( $this, 'render_cart_list_table' ) );
	}

	public function render_cart_list_table( $atts ) {
		$attributes = shortcode_atts( [
			'empty_message' => __( 'Your cart is currently empty.', 'storeengine' ),
		], $atts );

		Helper::cart();
		do_action( 'storeengine/cart/check_items' );

		ob_start();
		Template::get_template(
			'shortcode/cart-list-table.php',
			$attributes
		);

		return ob_get_clean();
	}
}
