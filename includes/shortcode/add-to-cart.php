<?php

namespace StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

class AddToCart {
	public function __construct() {
		add_shortcode( 'storeengine_add_to_cart', array( $this, 'render' ) );
	}

	public function render( array $attrs ) {
		$attrs = shortcode_atts( [
			'label'           => '',
			'product_id'      => null,
			'price_id'        => null,
			'variation_id'    => 0,
			'direct_checkout' => 'true',
			'quantity'        => 1,
			'show_quantity'   => 'false',
			'disabled'        => 'false',
		], $attrs );

		if ( empty( $attrs['product_id'] ) ) {
			return __( 'Product ID is required', 'storeengine' );
		}

		if ( $attrs['variation_id'] ) {
			$variation = Helper::get_product_variation( (int) $attrs['variation_id'] );
			if ( ! $variation ) {
				return __( 'Variation not found', 'storeengine' );
			}
			$attrs['variation_id'] = $variation->get_id();
		}

		$product_id = (int) $attrs['product_id'];
		$product    = Helper::get_product( $product_id );
		if ( ! $product ) {
			return __( 'Product not found', 'storeengine' );
		}

		$label           = $attrs['label'];
		$direct_checkout = Formatting::string_to_bool( $attrs['direct_checkout'] );
		if ( empty( $label ) ) {
			if ( $direct_checkout ) {
				$label = __( 'Buy now', 'storeengine' );
			} else {
				$label = __( 'Add to cart', 'storeengine' );
			}
		}

		$price_id = $attrs['price_id'];
		$prices   = [];
		if ( ! $price_id ) {
			$prices = $product->get_prices();
			if ( count( $prices ) === 1 ) {
				$price_id = $prices[0]->get_id();
			}
		}

		ob_start();
		Template::get_template( 'shortcode/add-to-cart.php', [
			'product'         => $product,
			'price_id'        => $price_id,
			'variation_id'    => $attrs['variation_id'],
			'direct_checkout' => $direct_checkout,
			'quantity'        => $attrs['quantity'],
			'show_quantity'   => Formatting::string_to_bool( $attrs['show_quantity'] ),
			'disabled'        => Formatting::string_to_bool( $attrs['disabled'] ),
			'label'           => $label,
			'prices'          => ! $price_id ? $prices : [],
		] );

		return ob_get_clean();
	}
}
