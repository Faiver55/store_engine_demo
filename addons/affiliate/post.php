<?php
namespace StoreEngine\Addons\Affiliate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Affiliate\Post\Settings;
use StoreEngine\Addons\Affiliate\Post\Affiliate;

class Post {
	public static function init() {
		$self = new self();
		$self->dispatch_hooks();
	}
	public function dispatch_hooks() {
		( new Settings() )->dispatch_actions();
		( new Affiliate() )->dispatch_actions();
	}
}
