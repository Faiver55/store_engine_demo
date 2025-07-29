<?php

namespace StoreEngine\Addons\Webhooks\Listeners\Coupon;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

use StoreEngine\Addons\Webhooks\Classes\AbstractListener;
use StoreEngine\Utils\Helper;

class Updated extends AbstractListener {

	public static function dispatch( $deliver_callback, $webhook ) {
		add_action(
			'rest_insert_' . Helper::COUPON_POST_TYPE,
			function ( $post, $request, $creating ) use ( $deliver_callback, $webhook ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInImplementedInterfaceBeforeLastUsed
				if ( ! $creating && empty( get_post_meta( $post->ID, '_wp_trash_meta_status', true ) ) ) {
					call_user_func_array( $deliver_callback, [ $webhook, self::get_payload( $post ) ] );
				}
			},
			10,
			3
		);
	}

	public static function get_payload( $post ) {
		$data = (array) $post;

		$data['meta'] = self::prepare_meta_data( array_map( fn( $value ) => maybe_unserialize( $value[0] ), get_post_meta( $post->ID ) ) );

		return $data;
	}
}
