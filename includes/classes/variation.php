<?php

namespace StoreEngine\Classes;

use StoreEngine\Classes\Data\AttributeData;
use StoreEngine\Utils\Helper;

class Variation {

	protected int $id;
	protected string $table;
	protected string $name = '';

	protected array $data     = [
		'sku'        => '',
		'product_id' => '',
		'price'      => null,
	];
	protected array $new_data = [];

	protected array $meta_data        = [];
	protected array $json_meta        = [];
	protected array $add_meta_data    = [];
	protected array $update_meta_data = [];
	protected array $delete_meta_data = [];
	/**
	 * @var AttributeData[]
	 */
	protected array $attributes              = [];
	protected array $new_attributes_term_ids = [];

	public const CACHE_KEY   = 'storeengine_variation_';
	public const CACHE_GROUP = 'storeengine_variations';

	public function __construct( int $id = 0 ) {
		global $wpdb;
		$this->id    = $id;
		$this->table = $wpdb->prefix . Helper::DB_PREFIX . 'product_variations';
	}

	public function get() {
		global $wpdb;

		$has_cache = wp_cache_get( self::CACHE_KEY . $this->get_id(), self::CACHE_GROUP );
		if ( $has_cache ) {
			return $this->set_data( $has_cache );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->get_row( $wpdb->prepare(
			"SELECT *
				FROM
					{$wpdb->prefix}storeengine_product_variations v
				WHERE
					id = %d", $this->get_id()
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( ! $result ) {
			return false;
		}

		$variation_attributes = $wpdb->get_results( $wpdb->prepare(
			"SELECT
						t.term_id AS term_id,
						tt.term_taxonomy_id AS term_taxonomy_id,
						pl.variation_id AS variation_id,
						t.name AS name,
						t.slug AS slug,
						tt.description AS description,
						tt.taxonomy AS taxonomy,
						tt.`count` AS count,
						pl.term_order AS term_order
					FROM
						{$wpdb->prefix}storeengine_variation_term_relations pl
						LEFT JOIN {$wpdb->terms} t ON t.term_id = pl.term_id
						LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = pl.term_id
					WHERE
						pl.variation_id = %d
					ORDER BY
						pl.term_order DESC", $this->get_id()
		) );

		$result->attributes = is_array( $variation_attributes ) ? $variation_attributes : [];

		wp_cache_set( self::CACHE_KEY . $this->get_id(), $result, self::CACHE_GROUP );

		$this->get_metadata_from_db();

		$this->set_data( $result );

		return $this;
	}

	public function get_metadata_from_db() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}storeengine_product_variation_meta WHERE variation_id = %d", $this->get_id()
		) );

		if ( empty( $results ) ) {
			return;
		}

		foreach ( $results as $result ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$this->meta_data[ $result->meta_key ] = $result->meta_value;
		}
	}

	public function save() {
		$this->save_core();
		$this->save_add_metadata();
		$this->save_update_metadata();
		$this->save_attributes();
		$this->save_delete_metadata();
	}

	protected function save_core() {
		if ( empty( $this->new_data ) ) {
			return;
		}

		global $wpdb;
		$data      = array_merge( $this->data, $this->new_data );
		$formatter = [ '%s', '%d', '%s', '%f', '%d' ];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( 0 === $this->get_id() ) {
			$wpdb->insert(
				$this->table,
				$data,
				$formatter
			);
			$this->id = $wpdb->insert_id;
		} else {
			$wpdb->update( $this->table, $data, [ 'id' => $this->get_id() ], $formatter, [ '%d' ] );
			wp_cache_delete( self::CACHE_KEY . $this->get_id(), self::CACHE_GROUP );
			wp_cache_flush_group( AbstractProduct::CACHE_GROUP );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		wp_cache_delete( self::CACHE_KEY . $this->get_id(), self::CACHE_GROUP );
		wp_cache_flush_group( AbstractProduct::CACHE_GROUP );
	}

	protected function save_add_metadata() {
		if ( empty( $this->add_meta_data ) ) {
			return;
		}

		global $wpdb;
		$values = [];
		foreach ( $this->add_meta_data as $key => $value ) {
			if ( in_array( $key, $this->json_meta, true ) ) {
				$value = wp_json_encode( $value );
			}

			$type = in_array( gettype( $value ), [
				'integer',
				'boolean',
			], true ) ? '%d' : ( in_array( gettype( $value ), [ 'double', 'float' ], true ) ? '%f' : '%s' );

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$values[] = $wpdb->prepare( "( %d, %s, $type )", $this->get_id(), $key, $value );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		}
		$values = implode( ', ', $values );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "INSERT INTO {$wpdb->prefix}storeengine_product_variation_meta (variation_id, meta_key, meta_value) VALUES $values" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	protected function save_update_metadata() {
		if ( empty( $this->update_meta_data ) ) {
			return;
		}

		global $wpdb;
		$values = [];
		foreach ( $this->update_meta_data as $key => $value ) {
			if ( in_array( $key, $this->json_meta, true ) ) {
				$value = wp_json_encode( $value );
			}

			$type = in_array( gettype( $value ), [
				'integer',
				'boolean',
			], true ) ? '%d' : ( in_array( gettype( $value ), [ 'double', 'float' ], true ) ? '%f' : '%s' );

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$values[] = $wpdb->prepare( "WHEN variation_id = %d AND meta_key = %s THEN $type", $this->get_id(), $key, $value );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		}
		$values = implode( ' ', $values );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}storeengine_product_variation_meta
							SET meta_value = CASE
								$values
							END
							WHERE variation_id = %d", $this->get_id() ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	protected function save_attributes() {
		if ( empty( $this->attributes ) && empty( $this->new_attributes_term_ids ) ) {
			return;
		}

		$term_ids = array_map( fn( $attribute ) => $attribute->term_id, $this->attributes );

		$removed_items = array_values( array_diff( $term_ids, $this->new_attributes_term_ids ) );
		$new_items     = array_values( array_diff( $this->new_attributes_term_ids, $term_ids ) );

		global $wpdb;
		if ( ! empty( $removed_items ) ) {
			$removed_placeholders = array_fill( 0, count( $removed_items ), '%d' );
			$removed_placeholders = implode( ',', $removed_placeholders );

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}storeengine_variation_term_relations WHERE variation_id = %d AND term_id IN (" . $removed_placeholders . ')', $this->get_id(), ...$removed_items
			) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			wp_cache_delete( self::CACHE_KEY . $this->get_id(), self::CACHE_GROUP );
			wp_cache_flush_group( AbstractProduct::CACHE_GROUP );
		}

		if ( ! empty( $new_items ) ) {
			$values = [];
			foreach ( $new_items as $item ) {
				$values[] = $wpdb->prepare( '(%d, %d)', $this->get_id(), $item );
			}
			$values = implode( ', ', $values );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- We've prepared values above.
			$wpdb->query( "INSERT INTO {$wpdb->prefix}storeengine_variation_term_relations (variation_id, term_id) VALUES {$values}" );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			wp_cache_flush_group( self::CACHE_GROUP );
		}

		// update order.
		$update_query = "UPDATE {$wpdb->prefix}storeengine_variation_term_relations SET term_order = CASE ";
		$values       = [];

		foreach ( $this->new_attributes_term_ids as $index => $new_attribute_term_id ) {
			$update_query .= 'WHEN variation_id = %d AND term_id = %d THEN %d ';
			$values[]      = $this->get_id();
			$values[]      = $new_attribute_term_id;
			$values[]      = $index;
		}
		$update_query .= 'END WHERE variation_id = %d';
		$values[]      = $this->get_id();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( $update_query, ...$values ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

		$this->get();
	}

	protected function save_delete_metadata() {
		if ( empty( $this->delete_meta_data ) ) {
			return;
		}

		$placeholders = array_fill( 0, count( $this->delete_meta_data ), '%s' );
		$placeholders = implode( ',', $placeholders );

		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamically prepared.
		$values = $wpdb->prepare( $placeholders, $this->delete_meta_data );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}storeengine_product_variation_meta WHERE variation_id = %d AND meta_key IN $values", $this->get_id()
		) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		wp_cache_delete( self::CACHE_KEY . $this->get_id(), self::CACHE_GROUP );
	}

	public function delete(): bool {
		if ( 0 === $this->get_id() ) {
			return false;
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		$res = (bool) $wpdb->delete( $this->table, [ 'id' => $this->get_id() ], [ '%d' ] );
		if ( $res ) {
			$wpdb->delete( $wpdb->prefix . 'storeengine_product_variation_meta', [ 'variation_id' => $this->get_id() ], [ '%d' ] );
			$wpdb->delete( $wpdb->prefix . 'storeengine_variation_term_relations', [ 'variation_id' => $this->get_id() ], [ '%d' ] );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		wp_cache_delete( self::CACHE_KEY . $this->id, self::CACHE_GROUP );
		wp_cache_flush_group( AbstractProduct::CACHE_GROUP );

		return $res;
	}

	public function set_data( $data ): Variation {
		$this->id                 = (int) $data->id;
		$this->data['sku']        = $data->sku;
		$this->data['product_id'] = (int) $data->product_id;
		$this->data['price']      = $data->price ? (float) $data->price : null;
		$attributes               = $data->attributes;

		if ( ! is_array( $attributes ) ) {
			return $this;
		}

		usort( $attributes, fn( $a, $b ) => $a->term_order <=> $b->term_order );
		$this->attributes = [];
		foreach ( $attributes as $attribute ) {
			$this->attributes[] = ( new AttributeData() )->set_data( $attribute );
		}
		$this->name = implode( ' / ', wp_list_pluck( $this->attributes, 'name' ) );

		return $this;
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_name(): string {
		return $this->name;
	}

	public function get_sku() {
		return $this->get_prop( 'sku' );
	}

	public function get_product_id() {
		return $this->get_prop( 'product_id', 0 );
	}

	public function get_price() {
		return $this->get_prop( 'price', null );
	}

	public function get_metadata( string $name ) {
		return $this->get_prop_metadata( $name );
	}

	public function get_pricing_id() {
		return $this->get_prop_metadata( '_pricing_id', null );
	}

	public function get_featured_image() {
		return $this->get_prop_metadata( '_featured_image_id', null );
	}

	public function get_attributes(): array {
		return $this->attributes;
	}

	public function set_product_id( int $value ) {
		$this->new_data['product_id'] = $value;
	}

	public function set_sku( string $value ) {
		$this->new_data['sku'] = $value;
	}

	/**
	 * @param float|null $value
	 *
	 * @return void
	 */
	public function set_price( ?float $value ) {
		$this->new_data['price'] = $value;
	}

	public function set_price_id( $value ) {
		$this->set_prop_metadata( '_pricing_id', $value );
	}

	public function set_featured_image( ?int $attachment_id ) {
		$this->set_prop_metadata( '_featured_image_id', $attachment_id );
	}

	public function add_metadata( string $name, $value ) {
		$this->add_meta_data[ $name ] = $value;
	}

	public function update_metadata( string $name, $value ) {
		$this->update_meta_data[ $name ] = $value;
	}

	public function remove_metadata( string $name ) {
		$this->delete_meta_data[ $name ] = $name;
	}

	public function set_id( int $id ) {
		$this->id = $id;
	}

	public function set_attributes( array $term_ids ) {
		$this->new_attributes_term_ids = $term_ids;
	}

	public function set_metadata( array $data ) {
		$this->meta_data = $data;
	}

	protected function get_prop_metadata( string $name, $default = '' ) {
		if ( array_key_exists( $name, $this->update_meta_data ) ) {
			return $this->update_meta_data[ $name ];
		}

		if ( array_key_exists( $name, $this->add_meta_data ) ) {
			return $this->add_meta_data[ $name ];
		}

		return $this->meta_data[ $name ] ?? $default;
	}

	protected function get_prop( string $name, $default = '' ) {
		if ( array_key_exists( $name, $this->new_data ) ) {
			return $this->new_data[ $name ];
		}

		return $this->data[ $name ] ?? $default;
	}

	protected function set_prop_metadata( string $name, $value ) {
		if ( array_key_exists( $name, $this->meta_data ) ) {
			$this->update_meta_data[ $name ] = $value;
		} else {
			$this->add_meta_data[ $name ] = $value;
		}
	}
}
