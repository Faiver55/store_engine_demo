<?php

namespace StoreEngine\Addons\Webhooks\Listeners\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

use StoreEngine\Utils\Helper;
use StoreEngine\Addons\Webhooks\Classes\AbstractListener;

class Restored extends AbstractListener {
	use Traits\Product;
	public static function dispatch( $deliver_callback, $webhook ) {
		add_action( 'transition_post_status', function ( $new_status, $old_status, $post ) use ( $deliver_callback, $webhook ) {
			if ( Helper::PRODUCT_POST_TYPE === $post->post_type && 'trash' === $old_status && 'trash' !== $new_status ) {
				call_user_func_array( $deliver_callback, [ $webhook, self::get_payload( $post ) ] );
			}
		}, 10, 3 );
	}
}
