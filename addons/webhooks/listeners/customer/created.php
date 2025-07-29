<?php

namespace StoreEngine\Addons\Webhooks\Listeners\Customer;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

use StoreEngine\Addons\Webhooks\Classes\AbstractListener;

class Created extends AbstractListener {

	public static function dispatch( $deliver_callback, $webhook ) {
		add_action(
			'user_register',
			function ( $user_id ) use ( $deliver_callback, $webhook ) {
				$user = get_userdata( $user_id );
				if ( in_array( 'storeengine_customer', $user->roles, true ) ) {
					call_user_func_array( $deliver_callback, [ $webhook, self::get_payload( $user ) ] );
				}
			}
		);
	}

	public static function get_payload( $user ) {
		return (array) $user;
	}
}
