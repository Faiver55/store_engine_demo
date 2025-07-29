<?php

namespace StoreEngine\Models;

use StoreEngine\classes\AbstractModel;
use StoreEngine\Utils\Helper;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @deprecated - Use `Variation` entity class instead.
 */
class Variation extends AbstractModel {
	protected string $table       = Helper::DB_PREFIX . 'product_variations';
	protected string $primary_key = 'id';

	public function get_variant_by_id( $variation_id ) {
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table is hardcoded.
		return $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $variation_id ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table is hardcoded.
	}

	public function get_product_variants( $product_id ) {
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table is hardcoded.
		return $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE product_id = %d", $product_id ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table is hardcoded.
	}

	public function get_all( $offset = 0, $per_page = 10, $status = 'any', $search = '' ) {
		global $wpdb;
		$query = "SELECT * FROM {$this->table}";

		$query .= $wpdb->prepare( ' WHERE name LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' );
		$query .= ' ORDER BY id DESC LIMIT %d, %d';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		$variants = $wpdb->get_results( $wpdb->prepare( $query, $offset, $per_page ), ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- prepared above.

		return $variants ?? null;
	}

	public function get_variants_count() {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table is hardcoded.
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table}" ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table is hardcoded.
	}

	public function save( array $args = [] ) {
		if ( empty( $args['product_id'] ) || empty( $args['name'] ) ) {
			return false;
		}

		$variation_id = $this->wpdb->insert(
			$this->table,
			[
				'sku'           => $args['sku'],
				'product_id'    => $args['product_id'],
				'name'          => $args['name'],
				'regular_price' => $args['regular_price'],
				'sale_price'    => $args['sale_price'],
				'stock'         => $args['stock'],

			],
			[
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			]
		);

		if ( ! $variation_id ) {
			return new WP_Error( 'failed-to-insert', $this->wpdb->last_error, 'storeengine' );
		}

		return $variation_id;
	}

	public function update( int $id, array $args ) {
		if ( ! $id ) {
			return new WP_Error( 'failed-to-update', __( 'No ID provided', 'storeengine' ) );
		}

		return (bool) $this->wpdb->update(
			$this->table,
			$args,
			[ 'id' => $id ],
			[
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			],
			[ '%d' ]
		);
	}

	public function delete( ?int $id = null ) {
		if ( ! $id ) {
			return new WP_Error( 'failed-to-delete', __( 'No ID provided', 'storeengine' ) );
		}

		return (bool) $this->wpdb->delete( $this->table, [ 'id' => $id ] );
	}
}
