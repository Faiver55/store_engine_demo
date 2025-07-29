<?php

namespace StoreEngine\Classes\Cache;

use StoreEngine\Utils\Helper;

/**
 * @deprecated
 * @use \StoreEngine\Utils\Caching
 */
class OrderCache {
	private const KEY   = 'storeengine_order_cache_';
	private const GROUP = 'storeengine_order_cache';

	public static function set( $id, $data ) {
		wp_cache_set( self::KEY . $id, $data, self::GROUP );
	}

	public static function set_by_key( $key, $data ) {
		wp_cache_set( self::KEY . $key, $data, self::GROUP );
	}

	public static function set_draft_order( $data ) {
		wp_cache_set( self::KEY . 'draft_' . Helper::get_cart_hash_from_cookie(), $data, self::GROUP );
	}

	public static function set_items( $id, $data ) {
		wp_cache_set( self::KEY . $id . 'items', $data, self::GROUP );
	}

	public static function set_coupons( $id, $data ) {
		wp_cache_set( self::KEY . $id . 'coupons', $data, self::GROUP );
	}

	public static function set_refunds( $id, $data ) {
		wp_cache_set( self::KEY . $id . 'refunds', $data, self::GROUP );
	}

	public static function get( int $id ) {
		return wp_cache_get( self::KEY . $id, self::GROUP );
	}

	public static function get_by_key( string $key ) {
		return wp_cache_get( self::KEY . $key, self::GROUP );
	}

	public static function get_draft_order( $cart_hash = null ) {
		$cart_hash = ! $cart_hash ? Helper::get_cart_hash_from_cookie() : $cart_hash;

		return wp_cache_get( self::KEY . 'draft_' . $cart_hash, self::GROUP );
	}

	public static function get_items( $id ) {
		return wp_cache_get( self::KEY . $id . 'items', self::GROUP );
	}

	public static function get_coupons( $id ) {
		return wp_cache_get( self::KEY . $id . 'coupons', self::GROUP );
	}

	public static function get_refunds( $id ) {
		return wp_cache_get( self::KEY . $id . 'refunds', self::GROUP );
	}

	public static function delete( $id ) {
		wp_cache_delete( self::KEY . $id, self::GROUP );
	}

	public static function delete_by_key( string $key ) {
		wp_cache_delete( self::KEY . $key, self::GROUP );
	}

	public static function delete_draft_order() {
		wp_cache_delete( self::KEY . 'draft_' . Helper::get_cart_hash_from_cookie(), self::GROUP );
	}

	public static function delete_items( $id ) {
		wp_cache_delete( self::KEY . $id . 'items', self::GROUP );
	}

	public static function delete_coupons( $id ) {
		wp_cache_delete( self::KEY . $id . 'coupons', self::GROUP );
	}

	public static function delete_refunds( $id ) {
		wp_cache_delete( self::KEY . $id . 'refunds', self::GROUP );
	}

	public static function flush() {
		wp_cache_flush_group( self::GROUP );
	}
}
