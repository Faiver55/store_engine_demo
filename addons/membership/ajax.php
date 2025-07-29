<?php
namespace StoreEngine\Addons\Membership;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Membership\Ajax\AccessGroups;

class Ajax {
	public static function init() {
		$self = new self();
		$self->dispatch_hooks();
	}

	public function dispatch_hooks() {
		( new AccessGroups() )->dispatch_actions();
	}

}
