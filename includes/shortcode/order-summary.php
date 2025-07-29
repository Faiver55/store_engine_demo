<?php

namespace StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\CartItem;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

class OrderSummary {

	public function __construct() {
		add_shortcode( 'storeengine_order_summary', array( $this, 'render_checkout_order_summary' ) );
	}

	public function render_checkout_order_summary( $atts ) {
		$attributes = shortcode_atts( [
			'dummy' => false,
		], $atts );
		$dummy      = Formatting::string_to_bool( $attributes['dummy'] );

		if ( $dummy ) {
			$cart_item = new CartItem();
			$cart_item->set_data( [
				'name'          => 'T-shirt',
				'price_name'    => 'Premium cotton',
				'price_type'    => 'onetime',
				'quantity'      => 2,
				'price'         => 12.00,
				'compare_price' => 15.00,
				'line_subtotal' => 24.00,
				'price_html'    => Formatting::price( 24.00 ),
			] );
			$cart_items = [ $cart_item ];
			add_filter( 'storeengine/get_cart_item_data', [ __CLASS__, 'add_dummy_item_data' ] );
		} else {
			$cart_items = Helper::cart()->get_cart_items();
		}


		ob_start();
		Template::get_template( 'shortcode/order-summary.php', [
			'cart_items' => $cart_items,
		] );
		$output = ob_get_clean();

		if ( $dummy ) {
			remove_filter( 'storeengine/get_cart_item_data', [ __CLASS__, 'add_dummy_item_data' ] );
		}

		return $output;
	}

	public static function add_dummy_item_data(): array {
		return [
			[
				'label' => _x( 'Color', 'A dummy label for editor preview - Cart item data.', 'storeengine' ),
				'value' => _x( 'Black', 'A dummy value for editor preview - Cart item data.', 'storeengine' ),
			],
			[
				'label' => _x( 'Size', 'A dummy label for editor preview - Cart item data.', 'storeengine' ),
				'value' => _x( 'XL', 'A dummy value for editor preview - Cart item data.', 'storeengine' ),
			],
		];
	}

}
