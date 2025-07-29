<?php

namespace StoreEngine\Addons\Webhooks\Listeners\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

use StoreEngine\Utils\Helper;
use StoreEngine\Addons\Webhooks\Classes\AbstractListener;

class Deleted extends AbstractListener {
	use Traits\Product;
	public static function dispatch( $deliver_callback, $webhook ) {
		add_action(
			'rest_delete_' . Helper::PRODUCT_POST_TYPE,
			function ( $post ) use ( $deliver_callback, $webhook ) {
				call_user_func_array( $deliver_callback, [ $webhook, self::get_payload( $post ) ] );
			},
			10,
			3
		);
	}
}
