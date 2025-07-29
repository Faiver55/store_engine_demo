<?php

namespace StoreEngine\Addons\Webhooks\Listeners\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class Updated extends Created {

	public static function dispatch( $deliver_callback, $webhook ) {
		add_action(
			'storeengine/api/after_update_order',
			function ( $order ) use ( $deliver_callback, $webhook ) {
				call_user_func_array( $deliver_callback, [ $webhook, self::get_payload( $order ) ] );
			},
			10,
			4
		);
	}
}
