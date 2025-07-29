<?php
namespace StoreEngine\Addons\MigrationTool;

use StoreEngine\Addons\MigrationTool\Ajax\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class Ajax {
	public function __construct() {
		$this->dispatch_hooks();
	}

	private function dispatch_hooks() {
		( new Integration() )->dispatch_actions();
	}
}
