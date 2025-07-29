<?php
namespace StoreEngine\Ajax;

use StoreEngine\Admin\Menu;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class Dashboard extends AbstractAjaxHandler {
	public function __construct() {
		$this->actions = [
			'get_admin_menu_items' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_admin_menu_items' ],
			],
		];
	}

	protected function get_admin_menu_items() {
		// @FIXME json-encoding 2 times.
		$menu_items = wp_json_encode( Menu::get_menu_lists() );
		wp_send_json_success( $menu_items );
	}
}
