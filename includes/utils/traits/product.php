<?php

namespace StoreEngine\Utils\traits;

use StoreEngine\Classes\AbstractProduct;
use StoreEngine\Classes\Data\AttributeData;
use StoreEngine\Classes\Price;
use StoreEngine\classes\ProductFactory;
use StoreEngine\Classes\Product\SimpleProduct;
use StoreEngine\Classes\Variation;
use StoreEngine\Utils\Helper;

trait Product {

	public static function get_product( int $product_id ) {
		$product = ( new ProductFactory() )->get_product( $product_id );

		return $product->get_id() ? $product : false;
	}

	public static function get_product_by_price_id( int $price_id ) {
		$product = ( new ProductFactory() )->get_product_by_price_id( $price_id );

		return $product instanceof AbstractProduct && $product->get_id() ? $product : false;
	}

	/**
	 * @param array $args Arguments for get_posts().
	 *
	 * @return AbstractProduct[]
	 */
	public static function get_products( array $args = array() ): array {
		$args['post_type'] = Helper::DB_PREFIX . 'product';
		$args              = wp_parse_args( $args, [
			's'              => '',
			'posts_per_page' => 10,
			'paged'          => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'post_status'    => 'publish',
		] );

		$cache_key = 'storeengine_products_' . md5( wp_json_encode( $args ) );
		$has_cache = wp_cache_get( $cache_key, AbstractProduct::CACHE_GROUP );
		if ( $has_cache ) {
			return $has_cache;
		}

		$posts = get_posts( $args );
		if ( empty( $posts ) ) {
			return [];
		}
		$posts = array_map( fn( $post ) => (array) $post, $posts );

		$post_ids     = array_map( fn( $post ) => $post['ID'], $posts );
		$placeholders = array_fill( 0, count( $posts ), '%d' );
		$placeholders = implode( ',', $placeholders );

		global $wpdb;
		//phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- we're preparing dynamic placeholder above.
		$meta_data = $wpdb->get_results( $wpdb->prepare( "
			SELECT post_id, meta_key, meta_value
				FROM $wpdb->postmeta
        	WHERE post_id IN ($placeholders)", ...$post_ids
		) );
		//phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		$meta_data_by_posts = [];
		foreach ( $meta_data as $meta ) {
			//phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$meta_data_by_posts[ $meta->post_id ][ $meta->meta_key ] = $meta->meta_value;
		}

		$products = [];
		foreach ( $posts as $post ) {
			$product = new SimpleProduct();
			$product->set_data( $post );
			$product->set_metadata( $meta_data_by_posts[ $post['ID'] ] );
			$products[] = $product;
		}
		wp_cache_set( $cache_key, $products, AbstractProduct::CACHE_GROUP );

		return $products;
	}

	public static function get_price( int $price_id ) {
		return ( new Price( $price_id ) );
	}

	public static function get_prices_array_by_product_id( int $product_id ): array {
		$product = self::get_product( $product_id );
		if ( ! $product ) {
			return [];
		}

		return self::get_prices_array_from_product( $product );
	}

	/**
	 * @param AbstractProduct $product
	 *
	 * @return array
	 */
	public static function get_prices_array_from_product( AbstractProduct $product ): array {
		$prices = [];
		foreach ( $product->get_prices() as $price ) {
			$prices[] = [
				'id'            => $price->get_id(),
				'price_name'    => $price->get_name(),
				'price_type'    => $price->get_type(),
				'price'         => $price->get_price(),
				'compare_price' => $price->get_compare_price(),
				'product_id'    => $price->get_product_id(),
				'settings'      => $price->get_settings(),
				'order'         => $price->get_menu_order(),
			];
		}

		return $prices;
	}

	public static function get_product_variation( int $id ) {
		return ( new Variation( $id ) )->get();
	}

	public static function get_product_list( int $integration_id, string $provider, string $search = '' ): array {
		$products = get_posts( [
			'post_type'      => 'storeengine_product',
			'posts_per_page' => 10,
			'post_status'    => 'publish',
			's'              => $search,
		] );

		if ( empty( $products ) ) {
			return [];
		}

		$product_ids           = array_map( fn( $product ) => $product->ID, $products );
		$product_ids_formatter = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );

		global $wpdb;
		$prices = $wpdb->get_results(
			$wpdb->prepare( "SELECT
				p.id,
				p.product_id,
				price_name,
				price_type,
				price,
				compare_price,
				`order`
			FROM
				{$wpdb->prefix}storeengine_product_price p
			WHERE
				NOT EXISTS ( SELECT 1 FROM {$wpdb->prefix}storeengine_integrations i
				WHERE i.price_id = p.id AND i.integration_id = %d AND i.provider = %s)
				AND p.product_id IN ($product_ids_formatter)", $integration_id, $provider, ...$product_ids )
		);

		$product_prices = [];
		foreach ( $prices as $price ) {
			$product_prices[ (int) $price->product_id ][] = $price;
		}

		return array_map( function ( $product ) use ( $product_prices ) {
			return [
				'label'  => $product->post_title,
				'value'  => $product->ID,
				'prices' => $product_prices[ $product->ID ] ?? [],
			];
		}, $products );
	}

	public static function get_variation_attributes( int $product_id, int $price_id = 0 ): array {
		$product = self::get_product( $product_id );
		if ( ! $product || 'variable' !== $product->get_type() ) {
			return [];
		}
		$variation_attributes = [];

		$variant_with_attributes = [];
		foreach ( $product->get_available_variants() as $variant ) {
			if ( 0 < $price_id && (int) $variant->get_pricing_id() !== $price_id ) {
				continue;
			}
			$variant_with_attributes[] = $variant->get_attributes();
		}
		/** @var AttributeData[] $variant_with_attributes */
		$variant_with_attributes = array_merge( ...$variant_with_attributes );

		foreach ( $variant_with_attributes as $variant_with_attribute ) {
			$attribute_taxonomy = get_taxonomy( $variant_with_attribute->taxonomy );
			if ( ! $attribute_taxonomy ) {
				continue;
			}
			if ( ! isset( $variation_attributes[ $variant_with_attribute->taxonomy ] ) ) {
				$variation_attributes[ $variant_with_attribute->taxonomy ] = [
					'name'     => $attribute_taxonomy->label,
					'taxonomy' => $variant_with_attribute->taxonomy,
					'options'  => [
						[
							'id'          => $variant_with_attribute->term_id,
							'name'        => $variant_with_attribute->name,
							'slug'        => $variant_with_attribute->slug,
							'description' => $variant_with_attribute->description,
						],
					],
				];
			} else {
				$variation_attributes[ $variant_with_attribute->taxonomy ]['options'][] = [
					'id'          => $variant_with_attribute->term_id,
					'name'        => $variant_with_attribute->name,
					'slug'        => $variant_with_attribute->slug,
					'description' => $variant_with_attribute->description,
				];
				$variation_attributes[ $variant_with_attribute->taxonomy ]['options']   = array_values( array_unique( $variation_attributes[ $variant_with_attribute->taxonomy ]['options'], SORT_REGULAR ) );
			}
		}

		return array_values( $variation_attributes );
	}

}
