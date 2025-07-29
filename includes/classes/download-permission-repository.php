<?php

namespace StoreEngine\Classes;

use StoreEngine\Utils\Helper;

class DownloadPermissionRepository {

	protected string $table;

	public const CACHE_KEY = 'storeengine_download_permission_repository_';

	protected int $page;
	protected int $limit;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . Helper::DB_PREFIX . 'downloadable_product_permissions';
	}

	/**
	 * @param int $order_id
	 * @return DownloadPermission[]
	 */
	public function get_by_order( int $order_id ): array {
		$cache_key = self::CACHE_KEY . 'order_' . $order_id;
		$has_cache = wp_cache_get( $cache_key, DownloadPermission::CACHE_GROUP );
		if ( $has_cache ) {
			return $has_cache;
		}

		$objects = [];
		global $wpdb;
		//phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $this->table WHERE order_id = %d", $order_id )
		);
		//phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $results ) {
			return [];
		}

		foreach ( $results as $result ) {
			$objects[] = ( new DownloadPermission() )->set_data( $result );
		}
		wp_cache_set($cache_key, $objects, DownloadPermission::CACHE_GROUP);

		return $objects;
	}

	public function with_pagination( int $page = 1, int $per_page = 10 ): self {
		$this->page  = max( 1, $page );
		$this->limit = $per_page;
		return $this;
	}

	public function get_by_customer_id( int $customer_id ) {
		$offset    = ( $this->page - 1 ) * $this->limit;
		$cache_key = self::CACHE_KEY . 'customer_' . $customer_id . '_' . $offset;
		$has_cache = wp_cache_get( $cache_key, DownloadPermission::CACHE_GROUP );
		if ( $has_cache ) {
			return $has_cache;
		}

		$objects = [];

		global $wpdb;
		//phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM $this->table WHERE user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d", $customer_id, $this->limit, $offset )
		);
		//phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $results ) {
			return [];
		}

		foreach ( $results as $result ) {
			$objects[] = ( new DownloadPermission() )->set_data( $result );
		}
		wp_cache_set($cache_key, $objects, DownloadPermission::CACHE_GROUP);

		return $objects;
	}

	public function total_count_by_customer_id( int $customer_id ) {
		$cache_key = self::CACHE_KEY . 'customer_count';
		$has_cache = wp_cache_get( $cache_key, DownloadPermission::CACHE_GROUP );
		if ( $has_cache ) {
			return $has_cache;
		}

		global $wpdb;
		//phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM $this->table WHERE user_id = %d", $customer_id )
		);
		//phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		wp_cache_set($cache_key, $count, DownloadPermission::CACHE_GROUP);

		return $count;
	}

}
