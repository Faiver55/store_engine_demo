<?php
namespace StoreEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Post\Dashboard;
use StoreEngine\Post\SavedPaymentMethod;

class Post {
	public static function init() {
		$self = new self();
		$self->dispatch_hooks();
	}
	public function dispatch_hooks() {
		( new Dashboard() )->dispatch_actions();
		( new SavedPaymentMethod() )->dispatch_actions();
	}
}
