<?php

namespace StoreEngine\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VariableProduct {

	public static function init() {
		$self = new self();
		add_filter('storeengine/frontend/cart/add_item', [ $self, 'include_variation_data' ]);
	}

	public function include_variation_data( $cart_item_data ) {
	}
}
