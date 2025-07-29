<?php

namespace StoreEngine\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\classes\AbstractProduct;
use StoreEngine\Classes\Product\VariableProduct;
use StoreEngine\Classes\Variation;
use StoreEngine\Utils\Helper;
use WP_Post;
use WP_Query;
use WP_REST_Request;

class Product {

	public static function init() {
		$self = new self();

		add_filter( 'rest_prepare_' . Helper::PRODUCT_POST_TYPE, [ $self, 'extend_product_rest_response' ] );
		add_action( 'rest_insert_storeengine_product', [ $self, 'save_attributes' ], 10, 2 );
	}

	public function extend_product_rest_response( $item ) {
		$item->data['prices'] = Helper::get_prices_array_by_product_id( $item->data['id'] );

		$product    = Helper::get_product( $item->data['id'] );
		$attributes = [];
		foreach ( $product->get_attributes() as $taxonomy => $terms ) {
			$taxonomyKey  = Helper::strip_attribute_taxonomy_name( $taxonomy );
			$attributes[] = [
				'label' => $taxonomyKey,
				'ids'   => array_map( fn( $term ) => $term->term_id, $terms ),
			];
		}
		$item->data['attributes']   = $attributes;
		$item->data['product_type'] = $product->get_type();
		$item->data['variants']     = array_map( function ( $variant ) {
			$data       = [];
			$taxonomies = [];
			foreach ( $variant->get_attributes() as $attribute ) {
				if ( ! taxonomy_exists( $attribute->taxonomy ) ) {
					continue;
				}
				$data[] = [
					'label' => get_taxonomy( $attribute->taxonomy )->label,
					'value' => $attribute->name,
				];

				$taxonomies[ Helper::strip_attribute_taxonomy_name( $attribute->taxonomy ) ] = $attribute->term_id;
			}

			return [
				'id'                => $variant->get_id(),
				'taxonomies'        => $taxonomies,
				'data'              => $data,
				'featured_image_id' => $variant->get_featured_image(),
				'pricing_id'        => $variant->get_pricing_id(),
				'price'             => $variant->get_price(),
				'sku'               => $variant->get_sku(),
			];
		}, 'variable' === $product->get_type() ? $product->get_variants() : [] );
		$item->data['integrations'] = array_map( fn( $integration ) => [
			'id'             => $integration->integration->get_id(),
			'product_id'     => $integration->price->get_product_id(),
			'price_id'       => $integration->price->get_id(),
			'integration_id' => $integration->integration->get_integration_id(),
			'provider'       => $integration->integration->get_provider(),
			'course_ids'     => 'storeengine/course-bundle' === $integration->integration->get_provider() ? get_post_meta( $integration->integration->get_integration_id(), 'academy_course_bundle_courses_ids', true ) ?? [] : [],
		], Helper::get_integrations_by_product_id( $item->data['id'] ) );

		return $item;
	}

	public function save_attributes( WP_Post $post, WP_REST_Request $request ) {
		$unformatted_attributes = $request->get_param( 'attributes' );
		if ( ! is_array( $unformatted_attributes ) ) {
			return;
		}

		$attributes = [];
		foreach ( $unformatted_attributes as $unformatted_attribute ) {
			$attributes[ $unformatted_attribute['label'] ] = $unformatted_attribute['ids'];
		}

		$product                          = Helper::get_product( $post->ID );
		$unformatted_existence_attributes = $product->get_attributes();
		$existence_attributes             = [];
		foreach ( $unformatted_existence_attributes as $taxonomy => $terms ) {
			$taxonomyKey                          = Helper::strip_attribute_taxonomy_name( $taxonomy );
			$existence_attributes[ $taxonomyKey ] = array_map( fn( $term ) => $term->term_id, $terms );
		}

		if ( $attributes !== $existence_attributes ) {
			$product->set_attributes_order( array_map( fn( $taxonomy ) => Helper::get_attribute_taxonomy_name( $taxonomy ), array_keys( $attributes ) ) );

			foreach ( $attributes as $key => $value ) {
				wp_set_object_terms( $post->ID, array_map( fn( $val ) => (int) sanitize_text_field( $val ), $value ), Helper::get_attribute_taxonomy_name( sanitize_text_field( $key ) ) );
			}

			// update the order.
			$update_values = [];
			$update_cases  = [];

			foreach ( $attributes as $taxonomy => $terms ) {
				foreach ( $terms as $order => $term_id ) {
					$update_cases[]  = "WHEN tr.term_taxonomy_id = $term_id THEN $order";
					$update_values[] = $term_id;
				}
			}

			// Execute bulk UPDATE if there are items to update
			if ( ! empty( $update_cases ) ) {
				global $wpdb;
				$update_query = "UPDATE {$wpdb->term_relationships} tr
							INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
							SET tr.term_order = CASE " . implode( ' ', $update_cases ) . ' END
							WHERE tr.object_id = %d
							AND tr.term_taxonomy_id IN
							(' . implode( ',', array_fill( 0, count( $update_values ), '%d' ) ) . ')';
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( $wpdb->prepare( $update_query, $post->ID, ...$update_values ) );
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			}
			wp_cache_flush_group( AbstractProduct::CACHE_GROUP );
		}


		// variation saving.
		$variants = $request->get_param( 'variants' );
		if ( 'variable' === $product->get_type() && empty( $variants ) ) {
			$variations = $product instanceof VariableProduct ? $product->get_variants() : [];
			foreach ( $variations as $variation ) {
				$variation->delete();
			}
		}

		if ( ! is_array( $variants ) || empty( $variants ) ) {
			$product->set_type( 'simple' );
			$product->save();

			return;
		}

		$product->set_type( 'variable' );
		$product->save();

		$variations           = $product instanceof VariableProduct ? $product->get_variants() : [];
		$new_variations_data  = [];
		$edit_variations_data = [];
		foreach ( $variants as $variant ) {
			if ( ! isset( $variant['taxonomies'] ) || ! is_array( $variant['taxonomies'] ) ) {
				continue;
			}

			if ( isset( $variant['id'] ) ) {
				$edit_variations_data[ $variant['id'] ] = $variant;
			} else {
				$new_variations_data[] = $variant;
			}
		}

		foreach ( $variations as $variation ) {
			if ( isset( $edit_variations_data[ $variation->get_id() ] ) ) {
				$this->save_variation_data( $variation, $product->get_id(), $edit_variations_data[ $variation->get_id() ] );
			} else {
				$variation->delete();
			}
		}

		if ( ! empty( $new_variations_data ) ) {
			foreach ( $new_variations_data as $new_variation_data ) {
				$this->save_variation_data( new Variation(), $product->get_id(), $new_variation_data );
			}
		}
	}

	private function save_variation_data( Variation $variation, int $product_id, array $data ) {
		$variation->set_product_id( $product_id );
		$price = isset( $data['price'] ) && is_numeric( $data['price'] ) ? (float) sanitize_text_field( $data['price'] ) : null;
		$variation->set_price( $price );
		$pricing_id = (int) sanitize_text_field( $data['pricing_id'] ?? 0 );
		$variation->set_price_id( $pricing_id > 0 ? $pricing_id : null );
		$featured_image_id = (int) sanitize_text_field( $data['featured_image_id'] ?? 0 );
		$variation->set_featured_image( $featured_image_id > 0 ? $featured_image_id : null );
		$variation->set_sku( sanitize_text_field( $data['sku'] ) );
		$term_ids = array_map( fn( $term_id ) => (int) sanitize_text_field( $term_id ), $data['taxonomies'] );
		$term_ids = array_values( $term_ids );
		$variation->set_attributes( $term_ids );
		$variation->save();
	}
}
