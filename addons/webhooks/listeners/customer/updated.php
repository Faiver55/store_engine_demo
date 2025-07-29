<?php

namespace StoreEngine\Addons\Webhooks\Listeners\Customer;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class Updated extends Created {

	public static function dispatch( $deliver_callback, $webhook ) {
		add_action(
			'profile_update',
			function ( $user_id ) use ( $deliver_callback, $webhook ) {
				$user = get_userdata( $user_id );
				if ( in_array( 'storeengine_customer', $user->roles, true ) ) {
					call_user_func_array( $deliver_callback, [ $webhook, self::get_payload( $user ) ] );
				}
			},
			10,
			4
		);
	}
}
