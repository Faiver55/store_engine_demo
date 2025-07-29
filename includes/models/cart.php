<?php

namespace StoreEngine\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cart {

	public static function get_carts_by_hash_or_user_id( $hash, $user_id ): array {
		global $wpdb;

		if ( ! $hash && ! $user_id ) {
			return [];
		}

		if ( 0 === $user_id ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_cart WHERE cart_hash = %s ORDER BY `updated_at` DESC;", $hash ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_cart WHERE cart_hash = %s OR user_id=%d ORDER BY `updated_at` DESC;", $hash, $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public static function get_cart_hash_by_user_id( $user_id ) {
		global $wpdb;
		$result = $wpdb->get_var( $wpdb->prepare( "SELECT cart_hash FROM {$wpdb->prefix}storeengine_cart WHERE user_id = %d;", $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $result ) {
			return $result;
		}

		return null;
	}

	public static function update( int $id, string $cart_hash, array $cart_data ): bool {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'storeengine_cart',
			[
				'user_id'   => get_current_user_id(),
				'cart_hash' => $cart_hash,
				'cart_data' => maybe_serialize( $cart_data ),
			],
			[ 'cart_id' => $id ]
		);

		return true;
	}

	public static function delete( int $id ): bool {
		global $wpdb;

		$wpdb->delete( $wpdb->prefix . 'storeengine_cart', [ 'cart_id' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return true;
	}

	public static function create( int $user_id, string $cart_hash, array $cart_data ) {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'storeengine_cart', // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			[
				'user_id'   => $user_id,
				'cart_hash' => $cart_hash,
				'cart_data' => maybe_serialize( $cart_data ),
			],
			[ '%d', '%s', '%s' ]
		);

		return $wpdb->insert_id;
	}
}
