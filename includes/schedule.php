<?php
namespace StoreEngine;

use StoreEngine\Schedules\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schedule {

	public static function init() {
		Order::init();
	}
}
