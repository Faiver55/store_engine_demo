<?php
namespace StoreEngine\Addons\Email;

use StoreEngine\Addons\Email\Ajax\Admin;
use StoreEngine\Addons\Email\Ajax\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class Ajax {
	public function __construct() {
		$this->dispatch_hooks();
	}

	private function dispatch_hooks() {
		( new Settings() )->dispatch_actions();
		( new Admin() )->dispatch_actions();
	}
}
