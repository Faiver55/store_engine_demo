<?php

namespace StoreEngine\Classes;

use StoreEngine\Utils\Formatting;

class Attributes extends AbstractWpdb {

	private int $page;
	private int $limit;

	public function __construct( int $page = 1, int $per_page = 10 ) {
		$this->page  = max( 1, $page );
		$this->limit = $per_page ?: 10;

		parent::__construct();

		global $wpdb;
		$this->table = "{$wpdb->prefix}storeengine_attribute_taxonomies";
	}

	public function get( $search = '' ) {
		$cache_key = Attribute::CACHE_KEY . md5( wp_json_encode( [
			'search' => $search,
			'page'   => $this->page,
			'limit'  => $this->limit,
		] ) );
		$has_cache = wp_cache_get( $cache_key, Attribute::CACHE_GROUP );
		if ( $has_cache ) {
			return $has_cache;
		}

		global $wpdb;
		$query = "SELECT * FROM $this->table";
		if ( ! empty( $search ) ) {
			$query .= $wpdb->prepare( ' WHERE attribute_label LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' );
		}
		$query .= ' ORDER BY attribute_id DESC';

		if ( $this->limit > 0 ) {
			$offset = ( $this->limit * $this->page ) - $this->limit;
			$query .= $wpdb->prepare( ' LIMIT %d, %d', $offset, $this->limit );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- Prepared query below.
		$results = $wpdb->get_results( $query );
		// phpcs:enable phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		if ( empty( $results ) ) {
			return [];
		}

		$attributes = [];
		foreach ( $results as $result ) {
			$attributes[] = ( new Attribute() )->set_data( $result );
		}
		wp_cache_set( $cache_key, $attributes, Attribute::CACHE_GROUP );

		return $attributes;
	}

	public function get_all_names() {
		$cache_key = Attribute::CACHE_KEY . 'all_names';
		$has_cache = wp_cache_get( $cache_key, Attribute::CACHE_GROUP );
		if ( $has_cache ) {
			return $has_cache;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results("SELECT attribute_name, attribute_label, attribute_public FROM {$wpdb->prefix}storeengine_attribute_taxonomies");

		if ( empty( $results ) ) {
			return [];
		}

		$names = [];
		foreach ( $results as $result ) {
			$names[ $result->attribute_name ] = [
				'label'  => $result->attribute_label,
				'public' => Formatting::string_to_bool( $result->attribute_public ),
			];
		}

		wp_cache_set( $cache_key, $names, Attribute::CACHE_GROUP );

		return $names;
	}

	public function get_total_count( string $search = '' ): int {
		$cache_key = Attribute::CACHE_KEY . 'count' . $search;
		$has_cache = wp_cache_get( $cache_key, Attribute::CACHE_GROUP );
		if ( $has_cache ) {
			return (int) $has_cache;
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $search ) {
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $this->table WHERE attribute_label LIKE %s", $search ) );
		} else {
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $this->table" );
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		wp_cache_set( $cache_key, $count, Attribute::CACHE_GROUP );

		return $count;
	}

}
