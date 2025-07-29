<?php

namespace StoreEngine\Addons\MigrationTool\Migration\Woocommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Attribute;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Product\SimpleProduct as StoreEngineProduct;
use StoreEngine\Classes\Price;
use StoreEngine\Classes\Variation;
use StoreEngine\Database;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WC_Product_Variable;

class ProductMigration {

	protected ?int $wc_product_id = null;
	protected StoreEngineProduct $se_product;

	protected int $product_id;

	public static function get_product_by_wc_id( int $wc_product_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare(
			"
			SELECT p.ID FROM {$wpdb->postmeta} m
			INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
			WHERE p.post_type = 'storeengine_product' AND meta_key = %s AND meta_value = %d",
			'_wc_to_se_pid',
			$wc_product_id
		) );
	}

	public static function get_variation_by_wc_id( int $wc_variation_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT variation_id
					FROM
						{$wpdb->prefix}storeengine_product_variation_meta
					WHERE
						meta_key = '_wc_variation_id'
						AND meta_value = %d", $wc_variation_id ) );
	}

	public function __construct( int $wc_product_id ) {
		$this->wc_product_id = $wc_product_id;
		$this->product_id    = self::get_product_by_wc_id( $wc_product_id );
		$this->se_product    = new StoreEngineProduct( $this->wc_product_id );
	}

	public function is_exists(): bool {
		return $this->product_id > 0;
	}

	public function migrate(): ?int {
		if ( $this->is_exists() ) {
			return $this->product_id;
		} else {
			return $this->add_product();
		}
	}

	protected function add_product(): ?int {
		// Restore WC Table info in wpdb.
		WC()->wpdb_table_fix();

		$wc_product  = wc_get_product( $this->wc_product_id );
		$unsupported = [
			'external',
			'grouped',
			'variable_grouped',
			'subscription',
			'subscription_variation',
			'variable-subscription',
		];

		if ( ! $wc_product || $wc_product->is_type( $unsupported ) ) {
			// Restore SE Table info in wpdb.
			Database::init()->register_database_table_name();

			return null;
		}

		$sale_price    = (float) $wc_product->get_sale_price() ?? 0;
		$regular_price = (float) $wc_product->get_regular_price() ?? 0;
		$thumbnail_id  = $wc_product->get_image_id();
		$gallery_id    = $wc_product->get_gallery_image_ids();
		$wc_parent     = $wc_product->get_parent_id( 'edit' );

		/** @var \WC_Product_Download[] $downloads */
		$downloads          = $wc_product->get_downloads();
		$downloadable_files = [];

		foreach ( $downloads as $download ) {
			$downloadable_files[] = array_merge( $download->get_data(), [ 'enabled' => true ] );
		}

		// WC default weight unit is kg & default dimension unit is cm.
		$weight_unit    = get_option( 'woocommerce_weight_unit', 'kg' );
		$dimension_unit = get_option( 'woocommerce_dimension_unit', 'cm' );

		// Get date.
		$pub_date     = $wc_product->get_date_created( 'edit' ) ? $wc_product->get_date_created( 'edit' )->getOffsetTimestamp() : current_time( 'timestamp' );
		$pub_date_gmt = $wc_product->get_date_created( 'edit' ) ? $wc_product->get_date_created( 'edit' )->getTimestamp() : time();

		// Prepare data.
		$data = [
			'name'                  => $wc_product->get_title(),
			'author_id'             => get_post_field( 'post_author', $wc_product->get_id() ),
			'parent_id'             => $wc_parent ? self::get_product_by_wc_id( $wc_parent ) : 0,
			'content'               => $wc_product->get_description(),
			'excerpt'               => $wc_product->get_short_description(),
			'status'                => get_post_status( $wc_product->get_id() ),
			'hide'                  => 'visible' !== $wc_product->get_catalog_visibility(),
			'shipping_type'         => $wc_product->is_virtual() ? 'digital' : 'physical',
			'digital_auto_complete' => $wc_product->is_virtual(),
			'downloadable_files'    => $downloadable_files,
			'weight'                => (float) $wc_product->get_weight(),
			'weight_unit'           => $weight_unit,
			'length'                => (float) $wc_product->get_length( 'edit' ),
			'width'                 => (float) $wc_product->get_width( 'edit' ),
			'height'                => (float) $wc_product->get_height( 'edit' ),
			'dimension_unit'        => $dimension_unit,
			'tax_status'            => $wc_product->get_tax_status(),
			'crosssell_ids'         => $this->handle_linked_products( $wc_product->get_cross_sell_ids() ),
			'upsell_ids'            => $this->handle_linked_products( $wc_product->get_upsell_ids() ),
			'published_date'        => $pub_date ? gmdate( 'Y-m-d H:i:s', $pub_date ) : null,
			'published_date_gmt'    => $pub_date_gmt ? gmdate( 'Y-m-d H:i:s', $pub_date_gmt ) : null,
		];

		// Restore SE Table info in wpdb.
		Database::init()->register_database_table_name();

		foreach ( $data as $key => $value ) {
			$setter = 'set_' . $key;

			if ( method_exists( $this->se_product, $setter ) ) {
				$this->se_product->{$setter}( $value );
			}
		}

		$this->se_product->set_metadata( [ 'product_gallery_ids' => $gallery_id ] );

		$this->se_product->save();

		if ( $wc_product->is_type( 'variable' ) && $wc_product instanceof WC_Product_Variable ) {
			$this->import_variations( $wc_product );
		} else {
			$this->add_price( $sale_price, $regular_price );
		}

		update_post_meta( $this->se_product->get_id(), '_thumbnail_id', $thumbnail_id );
		update_post_meta( $this->se_product->get_id(), '_wc_to_se_pid', $this->wc_product_id );

		$this->copy_terms( 'product_tag', 'storeengine_product_tag' );
		$this->copy_terms( 'product_cat', 'storeengine_product_category' );

		return $this->se_product->get_id();
	}

	protected function import_variations( WC_Product_Variable $wc_product ) {
		global $wpdb;
		$variation_data = [];
		$attribute_data = [];

		// Restore WC Table info in wpdb.
		WC()->wpdb_table_fix();

		foreach ( $wc_product->get_available_variations() as $wc_variation ) {
			$attributes = [];
			foreach ( $wc_variation['attributes'] as $wc_taxonomy => $value ) {
				if ( ! $value ) {
					// SE doesn't work without empty attribute.
					continue;
				}

				$wc_taxonomy = str_replace( 'attribute_', '', $wc_taxonomy );
				$taxonomy    = str_replace( 'pa_', '', $wc_taxonomy );

				if ( strlen( $taxonomy ) > 25 ) {
					$taxonomy = rtrim( trim( substr( $taxonomy, 0, 25 ) ), '-_' );
					if ( taxonomy_exists( $taxonomy ) ) {
						$taxonomy = rtrim( trim( substr( $taxonomy, 0, 23 ) ), '-_' ) . '-' . 2;
						$i        = 3;
						while ( taxonomy_exists( $taxonomy ) ) {
							$taxonomy = rtrim( trim( substr( $taxonomy, 0, 23 ) ), '-_' ) . '-' . $i;
							$i ++;
						}
					}
				}

				if ( ! isset( $attribute_data[ $taxonomy ] ) ) {
					$attribute_data[ $taxonomy ] = [
						'id'          => 0,
						'wc_taxonomy' => $wc_taxonomy,
						'values'      => [ $value ],
						'label'       => wc_attribute_label( $wc_taxonomy ),
					];
					$attributes[ $taxonomy ]     = $value;
					continue;
				}

				if ( ! in_array( $value, $attribute_data[ $taxonomy ]['values'], true ) ) {
					$attribute_data[ $taxonomy ]['values'][] = $value;
				}

				$attributes[ $taxonomy ] = $value;
			}

			$variation_data[] = [
				'variation_id'  => $wc_variation['variation_id'],
				'image_id'      => $wc_variation['image_id'] ?? null,
				'attributes'    => $attributes,
				'price'         => $wc_variation['display_price'],
				'regular_price' => $wc_variation['display_regular_price'],
			];
		}

		// Restore SE Table info in wpdb.
		Database::init()->register_database_table_name();

		$taxonomies             = array_keys( $attribute_data );
		$taxonomies_placeholder = implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) );

		$db_attributes = $wpdb->get_results(
			$wpdb->prepare( "SELECT attribute_id, attribute_name
			FROM {$wpdb->prefix}storeengine_attribute_taxonomies
			WHERE attribute_name IN ($taxonomies_placeholder)", ...$taxonomies )
		);

		foreach ( $db_attributes as $db_attribute ) {
			$attribute_data[ $db_attribute->attribute_name ] = array_merge(
				$attribute_data[ $db_attribute->attribute_name ],
				[ 'id' => (int) $db_attribute->attribute_id ]
			);
		}

		foreach ( $attribute_data as $taxonomy => $attribute ) {
			if ( $attribute['id'] ) {
				continue;
			}

			$label         = Formatting::slug_to_words( sanitize_text_field( $attribute['label'] ) );
			$new_attribute = new Attribute();
			$new_attribute->set_name( sanitize_title( $taxonomy ) );
			$new_attribute->set_label( Formatting::entity_decode_utf8( $label ) );
			$new_attribute->set_orderby( 'term_id' );
			$new_attribute->save();

			$attribute_data[ $taxonomy ]['id'] = $new_attribute->get_id();

			foreach ( $attribute['values'] as $value ) {
				$new_attribute->add_term( $value );
			}
		}

		$with_prefix_taxonomies = array_map( fn( $taxonomy ) => Helper::get_attribute_taxonomy_name( $taxonomy ), $taxonomies );
		$terms                  = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT t.term_id, t.`name`, tt.taxonomy
				FROM {$wpdb->term_taxonomy} tt
				INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				WHERE taxonomy IN ($taxonomies_placeholder)
				",
				...$with_prefix_taxonomies
			)
		);

		$term_ids = [];
		foreach ( $terms as $term ) {
			$term_ids[ strtolower( $term->name ) ] = (int) $term->term_id;
		}

		// attr -> 28.29
		// taxonomy 32
		$created_prices = [];
		foreach ( $variation_data as $variation_datum ) {
			$new_variation = new Variation();
			$price         = new Price( $created_prices[ $variation_datum['price'] ] ?? 0 );
			$price->set_product_id( $this->se_product->get_id() );
			$price->set_price( $variation_datum['price'] );
			if ( $variation_datum['price'] < $variation_datum['regular_price'] ) {
				$price->set_compare_price( $variation_datum['regular_price'] );
			}

			$price->set_name( __( 'Onetime', 'storeengine' ) );
			$price->save();

			$new_variation->set_price_id( $price->get_id() );
			$new_variation->set_price( 0 );
			$new_variation->set_product_id( $this->se_product->get_id() );
			$new_variation->set_featured_image( $variation_datum['image_id'] );
			$new_variation->add_metadata( '_wc_variation_id', $variation_datum['variation_id'] );

			$new_attribute_term_ids = [];
			foreach ( $variation_datum['attributes'] as $taxonomy => $attribute ) {
				if ( isset( $term_ids[ strtolower( $attribute ) ] ) ) {
					$new_attribute_term_ids[] = $term_ids[ strtolower( $attribute ) ];
					continue;
				}

				$name     = Formatting::slug_to_words( sanitize_text_field( $attribute ) );
				$new_term = wp_insert_term(
					Formatting::entity_decode_utf8( $name ),
					Helper::get_attribute_taxonomy_name( $taxonomy ),
					[ 'slug' => sanitize_title( $attribute ) ]
				);

				if ( is_wp_error( $new_term ) ) {
					$terms = get_terms( [
						'slug'     => strtolower( $attribute ),
						'taxonomy' => Helper::get_attribute_taxonomy_name( $taxonomy ),
					] );
					if ( is_wp_error( $terms ) ) {
						throw StoreEngineException::from_wp_error( $new_term );
					} else {
						$term     = reset( $terms );
						$new_term = [ 'term_id' => $term->term_id ];
					}
				}

				$term_ids[ strtolower( $attribute ) ] = $new_term['term_id'];
				$new_attribute_term_ids[]             = $new_term['term_id'];
			}

			$new_variation->set_attributes( $new_attribute_term_ids );
			$new_variation->save();
			$created_prices[ $variation_datum['price'] ] = $price->get_id();
		}

		$product = $this->se_product;
		$product->set_type( 'variable' );

		foreach ( $attribute_data as $taxonomy => $attribute_datum ) {
			wp_set_object_terms( $product->get_id(), array_map( fn( $val ) => $term_ids[ strtolower( $val ) ], $attribute_datum['values'] ), Helper::get_attribute_taxonomy_name( $taxonomy ) );
		}

		$product->set_attributes_order( array_map( fn( $taxonomy ) => Helper::get_attribute_taxonomy_name( $taxonomy ), $taxonomies ) );

		$product->save();
	}

	protected function add_price( float $sale_price, float $regular_price ): ?int {
		if ( empty( $this->se_product->get_id() ) ) {
			return null;
		}

		$price = new Price();
		$price->set_product_id( $this->se_product->get_id() );
		$price->set_name( 'Regular Price' );
		$price->set_type( 'onetime' );

		if ( $sale_price ) {
			$price->set_price( $sale_price );
			$price->set_compare_price( $regular_price );
		} else {
			$price->set_price( $regular_price );
		}

		$price->save();

		return $price->get_id();
	}

	/**
	 * @param int[] $linked_ids
	 *
	 * @return int[]
	 */
	protected function handle_linked_products( array $linked_ids ): array {
		return array_map( function ( $id ) {
			$se_product_id = self::get_product_by_wc_id( $id );

			return $se_product_id ?: ( new ProductMigration( $id ) )->migrate();
		}, $linked_ids );
	}

	protected function copy_terms( string $from_taxonomy, string $to_taxonomy ): void {
		if ( empty( $this->se_product->get_id() ) || empty( $this->wc_product_id ) ) {
			return;
		}

		$terms = wp_get_post_terms( $this->wc_product_id, $from_taxonomy );

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( $existing_term = term_exists( $term->slug, $to_taxonomy ) ) {
					wp_set_object_terms( $this->se_product->get_id(), (int) $existing_term['term_id'], $to_taxonomy, true );
				} else {
					$name    = Formatting::slug_to_words( $term->name );
					$term_id = wp_insert_term(
						Formatting::entity_decode_utf8( $name ),
						$to_taxonomy,
						[
							'slug'        => $term->slug,
							'description' => $term->description,
						]
					);

					if ( is_wp_error( $term_id ) ) {
						continue;
					}

					wp_set_object_terms( $this->se_product->get_id(), $term_id, $to_taxonomy, true );
				}
			}
		}
	}
}
