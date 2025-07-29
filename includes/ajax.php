<?php
namespace  StoreEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Ajax\Addons;
use StoreEngine\Ajax\Attributes;
use StoreEngine\Ajax\Cart;
use StoreEngine\Ajax\Checkout;
use StoreEngine\Ajax\Coupon;
use StoreEngine\Ajax\Customers;
use StoreEngine\Ajax\Dashboard;
use StoreEngine\Ajax\Order;
use StoreEngine\Ajax\Posts;
use StoreEngine\Ajax\Product;
use StoreEngine\Ajax\Settings;
use StoreEngine\Ajax\Shipping;
use StoreEngine\Ajax\Tools;

class Ajax {
	public static function init() {
		$self = new self();
		$self->dispatch_hooks();
	}

	public function dispatch_hooks() {
		( new Product() )->dispatch_actions();
		( new Posts() )->dispatch_actions();
		( new Settings() )->dispatch_actions();
		( new Customers() )->dispatch_actions();
		( new Cart() )->dispatch_actions();
		( new Coupon() )->dispatch_actions();
		( new Checkout() )->dispatch_actions();
		( new Attributes() )->dispatch_actions();
		( new Addons() )->dispatch_actions();
		( new Tools() )->dispatch_actions();
		( new Order() )->dispatch_actions();
		( new Shipping() )->dispatch_actions();
		( new Dashboard() )->dispatch_actions();
	}
}
