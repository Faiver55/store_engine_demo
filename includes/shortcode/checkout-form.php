<?php

namespace StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

class CheckoutForm {
	public function __construct() {
		add_shortcode( 'storeengine_checkout_form', array( $this, 'render_checkout_form' ) );
	}

	public function render_checkout_form(): string {
		$cart = Helper::cart();
		do_action('storeengine/cart/check_items');
		$is_cart_empty  = $cart->is_cart_empty();
		$cart_sub_total = $cart->get_cart_subtotal();
		$products       = $cart->get_cart_items();

		$order              = Helper::get_recent_draft_order();
		$current_user_email = ( is_user_logged_in() ) ? wp_get_current_user()->user_email : '';
		ob_start();

		Template::get_template('shortcode/checkout-form.php', [
			'is_cart_empty'      => $is_cart_empty,
			'cart_sub_total'     => $cart_sub_total,
			'products'           => $products,
			'product_query'      => null,
			'order'              => $order,
			'current_user_email' => $current_user_email,
			'is_digital_cart'    => $cart->needs_shipping(),
		]);

		return ob_get_clean();
	}



}
