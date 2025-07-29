<?php

namespace StoreEngine\Shortcode;

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductDescription {

	public function __construct() {
		add_shortcode( 'storeengine_product_description', [ $this, 'render' ] );
	}

	public function render( array $attrs ) {
		$attrs = shortcode_atts( [
			'label'      => __( 'Description', 'storeengine' ),
			'product_id' => null,
		], $attrs );

		if ( empty( $attrs['product_id'] ) ) {
			return __( 'Product ID is required', 'storeengine' );
		}

		$product_id = (int) $attrs['product_id'];
		$product    = Helper::get_product( $product_id );
		if ( ! $product ) {
			return __( 'Product not found', 'storeengine' );
		}

		ob_start();
		Template::get_template( 'shortcode/product-description.php', [
			'label'   => $attrs['label'],
			'content' => $product->get_content(),
		] );

		return ob_get_clean();
	}

}
