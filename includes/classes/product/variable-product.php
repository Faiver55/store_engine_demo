<?php

namespace StoreEngine\Classes\Product;

use StoreEngine\classes\AbstractProduct;
use StoreEngine\Classes\Variation;


class VariableProduct extends AbstractProduct {

	/**
	 * @return Variation[]
	 */
	public function get_variants(): array {
		$cache_key = self::CACHE_KEY . $this->get_id() . '_variants';
		$has_cache = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( $has_cache ) {
			return $has_cache;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT
					*
				FROM
					{$wpdb->prefix}storeengine_product_variations
				WHERE
					product_id = %d", $this->get_id()
		) );

		if ( ! $results ) {
			return [];
		}

		$variation_ids = wp_list_pluck( $results, 'id' );
		$placeholders  = array_fill( 0, count( $variation_ids ), '%d' );
		$placeholders  = implode( ',', $placeholders );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$variation_ids = $wpdb->prepare( "($placeholders)", $variation_ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$variation_metadata_db = $wpdb->get_results( "
				SELECT * FROM {$wpdb->prefix}storeengine_product_variation_meta WHERE variation_id IN $variation_ids"
		);

		$variation_metadata = [];
		foreach ( $variation_metadata_db as $variation_meta ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$variation_metadata[ (int) $variation_meta->variation_id ][ $variation_meta->meta_key ] = $variation_meta->meta_value;
		}

		$variation_attributes = $wpdb->get_results(
			"SELECT
						t.term_id AS term_id,
						tt.term_taxonomy_id AS term_taxonomy_id,
						pl.variation_id AS variation_id,
						t.name AS name,
						t.slug AS slug,
						tt.description AS description,
						tt.taxonomy AS taxonomy,
						tt.count AS count,
						pl.term_order AS term_order
					FROM
						{$wpdb->prefix}storeengine_variation_term_relations pl
						LEFT JOIN {$wpdb->terms} t ON t.term_id = pl.term_id
						LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = pl.term_id
					WHERE
						pl.variation_id IN $variation_ids
						AND t.term_id <> ''
					ORDER BY
						pl.term_order DESC"
		);

		if ( is_array( $variation_attributes ) ) {
			$variation_attributes_list = [];
			foreach ( $variation_attributes as $variation_attribute ) {
				$variation_attributes_list[ $variation_attribute->variation_id ][] = $variation_attribute;
			}

			foreach ( $results as &$result ) {
				$result->attributes = $variation_attributes_list[ $result->id ] ?? [];
			}
		}

		$variants = [];

		foreach ( $results as $result ) {
			$variation = ( new Variation() )->set_data( $result );
			$variation->set_metadata( $variation_metadata[ (int) $result->id ] ?? [] );
			$variants[] = $variation;
		}

		wp_cache_set( $cache_key, $variants, self::CACHE_GROUP );

		return $variants;
	}

	/**
	 * @return Variation[]
	 */
	public function get_available_variants(): array {
		return array_values( array_filter( $this->get_variants(), fn( $v ) => $v->get_price() !== null ) );
	}
}
