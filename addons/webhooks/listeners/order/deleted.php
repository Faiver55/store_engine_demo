<?php

namespace StoreEngine\Addons\Webhooks\Listeners\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class Deleted extends Created {

	public static function dispatch( $deliver_callback, $webhook ) {
		add_action( 'storeengine/api/after_delete_order',
			function ( $prev_order, $order_id, $force ) use ( $deliver_callback, $webhook ) {
				if ( $force ) {
					return;
				}
				$data       = self::get_payload( $prev_order );
				$data['id'] = $order_id;
				call_user_func_array( $deliver_callback, [ $webhook, $data ] );
			},
			10,
			4
		);
	}
}
