<?php

namespace StoreEngine;

use StoreEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


class API {

	public static function init() {
		$self = new self();

		API\Product::init();
		API\Orders::init();
		API\cart::init();
		API\Payment::init();
		API\Analytics::init();
		API\Customer::init();
		API\Settings::init();
		API\Taxes::init();
		API\Shipping::init();

		add_filter( 'rest_api_init', [ $self, 'api_init' ] );
		add_filter( 'user_has_cap', array( $self, 'update_user_cap' ), 10, 3 );
	}

	public function api_init() {
		// @TODO move to specific api where cart is needed.
		StoreEngine::init()->load_cart();
	}

	public function update_user_cap( $all_caps, $cap, $args ) {
		if ( isset( $all_caps['edit_posts'] ) && $all_caps['edit_posts'] ) {
			$all_caps['edit_post_meta'] = true;
		}

		return $all_caps;
	}
}
