<?php

namespace StoreEngine\Addons\Webhooks\Listeners\Product\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

use StoreEngine\Utils\Helper;

trait Product {
	public static function meta_data( int $id ) : array {
		$post_metas = get_metadata('post', $id);
		if ( ! is_array( $post_metas ) ) {
			return [];
		}

		$data = [];
		foreach ( $post_metas as $key => $value ) {
			if ( 0 === strpos( $key, '_storeengine_') ) {
				$key          = str_replace( '_storeengine_', '', $key );
				$data[ $key ] = $value[0];
			}
		}
		return $data;
	}

	public static function get_payload( $post ) {
		$product      = Helper::get_product( absint( $post->ID ) );
		$data         = (array) $post;
		$data['meta'] = self::meta_data( $post->ID );

		foreach ( $product->get_prices() as $price ) {
			$data['prices'] = [
				'name'                  => $price->get_name(),
				'price'                 => $price->get_price(),
				'compare_price'         => $price->get_compare_price(),
				'order_no'              => $price->get_menu_order(),
				'menu_order'            => $price->get_menu_order(),
				'is_setup_fee'          => $price->is_setup_fee(),
				'get_setup_fee_name'    => $price->get_setup_fee_name(),
				'get_setup_fee_price'   => $price->get_setup_fee_price(),
				'get_setup_fee_type'    => $price->get_setup_fee_type(),
				'is_trial'              => $price->is_trial(),
				'trial_days'            => $price->get_trial_days(),
				'is_expire'             => $price->is_expire(),
				'expire_days'           => $price->get_expire_days(),
				'payment_duration'      => $price->get_payment_duration(),
				'payment_duration_type' => $price->get_payment_duration_type(),
				'is_upgradeable'        => $price->is_upgradeable(),
			];
		}
		return $data;
	}
}
