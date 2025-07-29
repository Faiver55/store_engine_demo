<?php

namespace StoreEngine\Classes;

use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Database;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

class Attribute {

	protected int $id;
	protected string $table;

	protected array $data     = [
		'attribute_name'    => '',
		'attribute_label'   => '',
		'attribute_type'    => 'select',
		'attribute_orderby' => '',
		'attribute_public'  => 0,
	];
	protected array $new_data = [];

	public const CACHE_KEY   = 'storeengine_attribute_';
	public const CACHE_GROUP = 'storeengine_attributes';

	public function __construct( int $id = 0 ) {
		global $wpdb;
		$this->id    = $id;
		$this->table = $wpdb->prefix . Helper::DB_PREFIX . 'attribute_taxonomies';
	}

	public function get() {
		global $wpdb;

		$has_cache = wp_cache_get( self::CACHE_KEY . $this->get_id(), self::CACHE_GROUP );
		if ( $has_cache ) {
			return $this->set_data( $has_cache );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $this->table WHERE attribute_id = %d", $this->get_id()
		) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( ! $result ) {
			return false;
		}
		wp_cache_set( self::CACHE_KEY . $this->get_id(), $result, self::CACHE_GROUP );

		return $this->set_data( $result );
	}

	public function set_data( $data ): Attribute {
		$this->id                        = $data->attribute_id;
		$this->data['attribute_name']    = $data->attribute_name;
		$this->data['attribute_label']   = $data->attribute_label;
		$this->data['attribute_type']    = $data->attribute_type;
		$this->data['attribute_orderby'] = $data->attribute_orderby;
		$this->data['attribute_public']  = $data->attribute_public;

		return $this;
	}

	public function save() {
		global $wpdb;
		$data      = array_merge( $this->data, $this->new_data );
		$formatter = [ '%s', '%s', '%s', '%s', '%d' ];

		$taxonomy = Database::register_attribute_taxonomy( $this->get_name(), $this->get_label(), $this->is_public() );

		// Early bail if taxonomy can't be registered.
		if ( is_wp_error( $taxonomy ) ) {
			throw StoreEngineException::from_wp_error( $taxonomy );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( 0 === $this->get_id() ) {
			$wpdb->insert( $this->table, $data, $formatter );
			$this->id = $wpdb->insert_id;
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $this->table, $data, [ 'attribute_id' => $this->get_id() ], $formatter, [ '%d' ] );
			wp_cache_delete( self::CACHE_KEY . $this->id, self::CACHE_GROUP );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		wp_cache_flush_group( self::CACHE_GROUP );
		wp_cache_flush_group( AbstractProduct::CACHE_GROUP );
		wp_cache_flush_group( $taxonomy->name . '_relationships' );
		wp_cache_set_terms_last_changed();
	}

	public function delete(): bool {
		if ( 0 === $this->get_id() ) {
			return false;
		}

		$taxonomy      = $this->get_taxonomy_name();
		$terms         = $this->get_terms();
		$deleted_terms = [];
		foreach ( $terms as $term ) {
			$result = wp_delete_term( $term->term_id, $taxonomy );
			if ( ! is_wp_error( $result ) ) {
				$deleted_terms[] = $term->term_id;
			}
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		if ( ! empty( $deleted_terms ) ) {
			$deleted_terms_placeholders = implode( ',', array_fill( 0, count( $deleted_terms ), '%d' ) );
			$variation_ids              = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT variation_id FROM {$wpdb->prefix}storeengine_variation_term_relations WHERE term_id in ($deleted_terms_placeholders)",
					...$deleted_terms
				)
			);

			$this->delete_variation( array_map( fn( $v ) => $v->variation_id, $variation_ids ) );

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}storeengine_variation_term_relations WHERE term_id in ($deleted_terms_placeholders)",
					...$deleted_terms,
				)
			);
		}


		$res = $wpdb->delete( $this->table, [ 'attribute_id' => $this->id ], [ '%d' ] );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		if ( ! $res ) {
			return false;
		}

		wp_cache_delete( self::CACHE_KEY . $this->id, self::CACHE_GROUP );
		wp_cache_flush_group( self::CACHE_GROUP );
		wp_cache_flush_group( AbstractProduct::CACHE_GROUP );

		return true;
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_name() {
		return $this->get_prop( 'attribute_name' );
	}

	public function get_taxonomy_name(): string {
		return Helper::get_attribute_taxonomy_name( $this->get_name() );
	}

	public function get_label() {
		return $this->get_prop( 'attribute_label' );
	}

	public function get_type() {
		return $this->get_prop( 'attribute_type' );
	}

	public function get_orderby() {
		return $this->get_prop( 'attribute_orderby' );
	}

	public function get_terms( $args = [] ) {
		$args  = wp_parse_args( $args, [
			'taxonomy'   => $this->get_taxonomy_name(),
			'hide_empty' => false,
			'orderby'    => $this->get_orderby(),
		] );
		$terms = get_terms( $args );

		return is_wp_error( $terms ) ? [] : $terms;
	}

	public function get_count() {
		return wp_count_terms( $this->get_taxonomy_name() );
	}

	public function add_term( string $name, array $args = [] ) {
		$args = wp_parse_args( $args, [
			'slug'        => '',
			'description' => '',
			'parent'      => 0,
		] );

		return wp_insert_term( $name, $this->get_taxonomy_name(), $args );
	}

	public function update_term( int $term_id, string $name, array $args = [] ) {
		$args = wp_parse_args( $args, [
			'slug'        => '',
			'description' => '',
			'parent'      => 0,
		] );

		return wp_update_term(
			$term_id,
			$this->get_taxonomy_name(),
			[
				'name'        => $name,
				'slug'        => $args['slug'],
				'description' => $args['description'],
				'parent'      => $args['parent'],
			]
		);
	}

	public function delete_term( int $term_id ) {
		global $wpdb;

		$result = wp_delete_term( $term_id, $this->get_taxonomy_name() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$variation_ids = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT variation_id FROM {$wpdb->prefix}storeengine_variation_term_relations WHERE term_id = %d",
				$term_id
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}storeengine_variation_term_relations WHERE term_id = %d",
				$term_id
			)
		);

		$this->delete_variation( array_map( fn( $v ) => $v->variation_id, $variation_ids ) );

		return true;
	}

	public function is_public(): bool {
		return Formatting::string_to_bool( $this->get_prop( 'attribute_public', false ) );
	}

	public function set_name( string $value ) {
		$this->new_data['attribute_name'] = $value;
	}

	public function set_label( string $value ) {
		$this->new_data['attribute_label'] = $value;
	}

	public function set_type( string $value ) {
		$this->new_data['attribute_type'] = $value;
	}

	public function set_orderby( string $value ) {
		$this->new_data['attribute_orderby'] = $value;
	}

	public function set_public( bool $value ) {
		$this->new_data['attribute_public'] = $value;
	}

	protected function get_prop( string $name, $default = '' ) {
		if ( array_key_exists( $name, $this->new_data ) ) {
			return $this->new_data[ $name ];
		}

		return $this->data[ $name ] ?? $default;
	}

	/**
	 * @param array $variation_ids
	 *
	 * @return void
	 */
	private function delete_variation( array $variation_ids ): void {
		global $wpdb;

		if ( empty( $variation_ids ) ) {
			return;
		}

		$variation_ids_placeholders = implode( ',', array_fill( 0, count( $variation_ids ), '%d' ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}storeengine_product_variations WHERE id in ($variation_ids_placeholders)",
				...$variation_ids,
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}storeengine_product_variation_meta WHERE variation_id in ($variation_ids_placeholders)",
				...$variation_ids,
			)
		);
	}

}
