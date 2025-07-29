<?php

namespace StoreEngine;

use StoreEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Frontend {

	public static function init() {
		$self = new self();
		$self->dispatch_hooks();
	}

	public function dispatch_hooks() {
		if ( StoreEngine\Utils\Helper::get_settings( 'enable_floating_cart', true ) ) {
			Frontend\FloatingCart::init();
		}

		Frontend\Template::init();
		Frontend\Coupon::init();
		Frontend\Comments::init();
		add_action( 'init', array( $this, 'init_frontend' ) );
	}

	public function init_frontend() {
		if ( StoreEngine\Utils\Helper::is_request( 'frontend' ) ) {
			StoreEngine::init()->load_cart();
		}

		$user = wp_get_current_user();

		if ( $user && in_array( 'storeengine_customer', (array) $user->roles, true ) ) {
			add_filter( 'show_admin_bar', '__return_false' );
		}
	}

}
