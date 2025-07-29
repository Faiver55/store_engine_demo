<?php
namespace StoreEngine\Addons\Subscription\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Register {
	protected static array $controllers = [
		Controllers\Items::class,
		Controllers\Item::class,
		Controllers\Create::class,
		Controllers\Edit::class,
		Controllers\Delete::class,
	];
	public static function init() {
		add_action( 'rest_api_init', function () {
			foreach ( self::$controllers as $controller ) {
				$controller::init();
			}
		});
	}
}
