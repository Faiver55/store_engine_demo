<?php

namespace StoreEngine\Classes;

use StoreEngine\Classes\Product\SimpleProduct;
use StoreEngine\Classes\Product\VariableProduct;

class ProductFactory {

	/**
	 * @param int $product_id
	 *
	 * @return false|SimpleProduct|VariableProduct
	 */
	public function get_product( int $product_id = 0 ) {
		$product_type = get_post_meta( $product_id, '_storeengine_product_type', true ) ?? 'simple';
		$classname    = $this->get_product_classname( $product_id, $product_type );

		return ( new $classname( $product_id ) );
	}

	public function get_product_by_price_id( int $price_id ) {
		global $wpdb;
		$product_id = $wpdb->get_var( $wpdb->prepare( "SELECT product_id FROM {$wpdb->prefix}storeengine_product_price WHERE id = %d", $price_id ) );
		if ( ! $product_id ) {
			return false;
		}

		return $this->get_product( $product_id );
	}

	public static function get_product_classname( $product_id, $product_type ) {
		$class_names = apply_filters( 'storeengine/product_classes', [
			'simple'   => SimpleProduct::class,
			'variable' => VariableProduct::class,
		] );

		$classname = apply_filters( 'storeengine/product/get_classname', $class_names[ $product_type ] ?? null, $product_id, $product_type );

		if ( ! $classname || ! class_exists( $classname ) ) {
			$classname = SimpleProduct::class;
		}

		return $classname;
	}
}
