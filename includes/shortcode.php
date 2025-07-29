<?php

namespace StoreEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Shortcode {

	public static function init() {
		$self = new self();
		$self->dispatch_shortcode();
	}

	public function dispatch_shortcode() {
		new Shortcode\Products();
		new Shortcode\ProductsSidebar();
		new Shortcode\ProductsArchive();
		new Shortcode\Login();
		new Shortcode\FrontendDashboard();
		new Shortcode\ArchiveHeaderFilter();
		new Shortcode\SingleProduct();
		new Shortcode\ProceedToCheckout();
		new Shortcode\ContinueShopping();
		new Shortcode\CartListTable();
		new Shortcode\CartSubTotalTable();
		new Shortcode\ApplyCouponForm();
		new Shortcode\CheckoutForm();
		new Shortcode\OrderSummary();
		new Shortcode\ThankyouOrderInfo();
		new Shortcode\ThankyouPaymentInstructions();
		new Shortcode\OrderDetails();
		new Shortcode\OrderDownloads();
		new Shortcode\OrderBillingAddress();
		new Shortcode\OrderShippingAddress();
		new Shortcode\ProductDescription();
		new Shortcode\AddToCart();
	}
}
