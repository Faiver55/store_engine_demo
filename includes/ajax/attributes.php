<?php

namespace StoreEngine\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\Attribute;
use StoreEngine\Utils\Helper;

class Attributes extends AbstractAjaxHandler {
	public function __construct() {
		$this->actions = array(
			'get_product_attributes'         => [
				'callback' => [ $this, 'get_product_attributes' ],
				'fields'   => [
					'page'     => 'int',
					'per_page' => 'int',
					'status'   => 'string',
					'search'   => 'string',
				],
			],
			'create_product_attribute'       => [
				'callback' => [ $this, 'create_product_attribute' ],
				'fields'   => [
					'attribute_name'    => 'string',
					'attribute_label'   => 'string',
					'attribute_orderby' => 'string',
					'attribute_public'  => 'boolean',
				],
			],
			'update_product_attribute'       => [
				'callback' => [ $this, 'update_product_attribute' ],
				'fields'   => [
					'attribute_id'      => 'int',
					'attribute_label'   => 'string',
					'attribute_name'    => 'string',
					'attribute_orderby' => 'string',
					'attribute_public'  => 'int',
				],
			],
			'delete_product_attribute'       => [
				'callback' => [ $this, 'delete_product_attribute' ],
				'fields'   => [
					'attribute_id' => 'int',
				],
			],
			'delete_bulk_product_attributes' => [
				'callback' => [ $this, 'delete_bulk_product_attributes' ],
				'fields'   => [
					'attribute_ids' => 'string',
				],
			],
			// attribute term
			'get_product_attribute_terms'    => [
				'callback' => [ $this, 'get_product_attribute_terms' ],
				'fields'   => [
					'attribute_id' => 'int',
					'search'       => '',
				],
			],
			'create_product_attribute_term'  => [
				'callback' => [ $this, 'create_product_attribute_term' ],
				'fields'   => [
					'attribute_id' => 'int',
					'id'           => 'int',
					'name'         => 'string',
					'slug'         => 'string',
					'description'  => 'string',
				],
			],
			'update_product_attribute_term'  => [
				'callback' => [ $this, 'update_product_attribute_term' ],
				'fields'   => [
					'attribute_id' => 'int',
					'term_id'      => 'int',
					'name'         => 'string',
					'slug'         => 'string',
					'description'  => 'string',
				],
			],
			'delete_product_attribute_term'  => [
				'callback' => [ $this, 'delete_product_attribute_term' ],
				'fields'   => [
					'attribute_id' => 'int',
					'term_id'      => 'int',
				],
			],
		);
	}

	public function get_product_attributes( array $payload ) {
		$page     = $payload['page'] ?? 1;
		$per_page = $payload['per_page'] ?? 10;
		$search   = $payload['search'] ?? '';

		$attributes = Helper::get_product_attributes( [
			'per_page' => $per_page,
			'page'     => $page,
			'search'   => $search,
		] );

		wp_send_json_success( [
			'totalItems' => Helper::get_total_product_attributes_count( $search ),
			'attributes' => array_map( fn( $attribute ) => [
				'attribute_id'      => $attribute->get_id(),
				'attribute_name'    => $attribute->get_name(),
				'attribute_label'   => $attribute->get_label(),
				'attribute_orderby' => $attribute->get_orderby(),
				'attribute_type'    => $attribute->get_type(),
				'attribute_public'  => $attribute->is_public(),
				'attribute_terms'   => implode( ', ', array_map( fn( $term ) => $term->name, $attribute->get_terms() ) ),
			], $attributes ),
		] );
	}

	public function create_product_attribute( array $payload ) {
		if ( empty( $payload['attribute_label'] ) ) {
			wp_send_json_error( __( 'Please, provide an attribute name.', 'storeengine' ) );
		}

		// Set the attribute slug.
		if ( empty( $payload['attribute_name'] ) ) {
			$slug = Helper::sanitize_taxonomy_name( $payload['attribute_label'] );
		} else {
			$slug = Helper::strip_attribute_taxonomy_name( Helper::sanitize_taxonomy_name( $payload['attribute_name'] ) );
		}

		// Taxonomy max length is 32.
		// se_pa_ length 6 (remain 32-6-1 = 25)

		// Validate slug.
		if ( strlen( $slug ) > 25 ) {
			/* translators: %s: attribute slug */
			wp_send_json_error( sprintf( __( 'Slug "%s" is too long (25 characters max). Shorten it, please.', 'storeengine' ), $slug ), array( 'status' => 400 ) );
		} elseif ( Helper::check_if_attribute_name_is_reserved( $slug ) ) {
			/* translators: %s: attribute slug */
			wp_send_json_error( sprintf( __( 'Slug "%s" is not allowed because it is a reserved term. Change it, please.', 'storeengine' ), $slug ), array( 'status' => 400 ) );
		} elseif ( taxonomy_exists( Helper::get_attribute_taxonomy_name( $slug ) ) ) {
			/* translators: %s: attribute slug */
			wp_send_json_error( sprintf( __( 'Slug "%s" is already in use. Change it, please.', 'storeengine' ), $slug ) );
		}

		if ( empty( $payload['attribute_orderby'] ) || ! in_array( $payload['attribute_orderby'], [ 'name', 'custom-ordering', 'numeric-name', 'term_id' ], true ) ) {
			$payload['attribute_orderby'] = 'custom-ordering';
		}

		$attribute = new Attribute();
		$attribute->set_name( $slug );
		$attribute->set_label( $payload['attribute_label'] );
		$attribute->set_orderby( $payload['attribute_orderby'] );
		$attribute->set_public( $payload['attribute_public'] ?? false );

		try {
			$attribute->save();
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}

		$attribute->get();

		wp_send_json_success( [
			'attribute_id'      => $attribute->get_id(),
			'attribute_name'    => $attribute->get_name(),
			'attribute_label'   => $attribute->get_label(),
			'attribute_orderby' => $attribute->get_orderby(),
			'attribute_type'    => $attribute->get_type(),
			'attribute_public'  => $attribute->is_public(),
		] );
	}

	public function update_product_attribute( array $payload ) {
		if ( empty( $payload['attribute_id'] ) ) {
			wp_send_json_error( __( 'Invalid request.', 'storeengine' ) );
		}

		$attribute = Helper::get_product_attribute( $payload['attribute_id'] );

		if ( ! $attribute ) {
			wp_send_json_error( __( 'Attribute not found', 'storeengine' ) );
		}

		if ( empty( $payload['attribute_label'] ) ) {
			wp_send_json_error( __( 'Please, provide an attribute name.', 'storeengine' ) );
		}

		// Set the attribute slug.
		if ( empty( $payload['attribute_name'] ) ) {
			$slug = Helper::sanitize_taxonomy_name( $payload['attribute_label'] );
		} else {
			$slug = Helper::strip_attribute_taxonomy_name( Helper::sanitize_taxonomy_name( $payload['attribute_name'] ) );
		}

		// Taxonomy max length is 32.
		// se_pa_ length 6 (remain 32-6-1 = 25)

		// Validate slug.
		if ( strlen( $slug ) > 25 ) {
			/* translators: %s: attribute slug */
			wp_send_json_error( sprintf( __( 'Slug "%s" is too long (25 characters max). Shorten it, please.', 'storeengine' ), $slug ), array( 'status' => 400 ) );
		} elseif ( Helper::check_if_attribute_name_is_reserved( $slug ) ) {
			/* translators: %s: attribute slug */
			wp_send_json_error( sprintf( __( 'Slug "%s" is not allowed because it is a reserved term. Change it, please.', 'storeengine' ), $slug ), array( 'status' => 400 ) );
		} elseif ( $slug !== $attribute->get_name() && taxonomy_exists( Helper::get_attribute_taxonomy_name( $slug ) ) ) {
			/* translators: %s: attribute slug */
			wp_send_json_error( sprintf( __( 'Slug "%s" is already in use. Change it, please.', 'storeengine' ), $slug ) );
		}

		if ( empty( $payload['attribute_orderby'] ) || ! in_array( $payload['attribute_orderby'], [ 'name', 'custom-ordering', 'numeric-name', 'term_id' ], true ) ) {
			$payload['attribute_orderby'] = 'custom-ordering';
		}

		$old_slug = $attribute->get_name();
		$attribute->set_name( $slug );
		$attribute->set_label( $payload['attribute_label'] );
		$attribute->set_orderby( $payload['attribute_orderby'] );
		$attribute->set_public( $payload['attribute_public'] );

		try {
			global $wpdb;

			// Save new data.
			$attribute->save();

			$new_taxonomy = Helper::get_attribute_taxonomy_name( $slug );
			$old_taxonomy = Helper::get_attribute_taxonomy_name( $old_slug );

			// Update taxonomies in the wp term taxonomy table.
			$wpdb->update( $wpdb->term_taxonomy, [ 'taxonomy' => $new_taxonomy ], [ 'taxonomy' => $old_taxonomy ] );
			wp_cache_flush_group( $old_taxonomy . '_relationships' );
			wp_cache_set_terms_last_changed();

			// Update prefix in attribute order.
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_value FROM $wpdb->postmeta WHERE meta_key = '_storeengine_product_attributes_order' AND meta_value LIKE %s", '%' . $old_taxonomy . '%' )  );

			foreach ( $results as $meta ) {
				$meta_value = maybe_unserialize( $meta->meta_value );
				if ( ! is_array( $meta_value ) ) {
					$meta_value = json_decode( $meta->meta_value, true );
				}

				foreach ( $meta_value as $k => $v ) {
					if ( $old_taxonomy === $v ) {
						$meta_value[ $k ] = $new_taxonomy;
					}
				}

				$wpdb->update(
					$wpdb->postmeta,
					[ 'meta_value' => maybe_serialize( $meta_value ) ],
					[ 'meta_id' => $meta->meta_id ],
				);
			}

			wp_send_json_success( [
				'attribute_id'      => $attribute->get_id(),
				'attribute_name'    => $attribute->get_name(),
				'attribute_label'   => $attribute->get_label(),
				'attribute_orderby' => $attribute->get_orderby(),
				'attribute_type'    => $attribute->get_type(),
				'attribute_public'  => $attribute->is_public(),
			] );
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	public function delete_product_attribute( array $payload ) {
		if ( ! isset( $payload['attribute_id'] ) || $payload['attribute_id'] <= 0 ) {
			wp_send_json_error( __( 'Invalid attribute id', 'storeengine' ) );
		}

		$attribute = Helper::delete_product_attribute( $payload['attribute_id'] );

		if ( ! $attribute ) {
			wp_send_json_error( __( 'Attribute could not deleted', 'storeengine' ) );
		}

		wp_send_json_success();
	}

	public function delete_bulk_product_attributes( array $payload ) {
		$attribute_ids = $payload['attribute_ids'] ?? '';
		$attribute_ids = explode( ',', $attribute_ids );

		if ( empty( $attribute_ids ) ) {
			wp_send_json_error( __( 'Attribute IDs not found', 'storeengine' ) );
		}

		foreach ( $attribute_ids as $attribute_id ) {
			$attribute = Helper::get_product_attribute( $attribute_id );
			if ( $attribute ) {
				$attribute->delete();
			}
		}

		wp_send_json_success();
	}

	public function get_product_attribute_terms( array $payload ) {
		$attribute = Helper::get_product_attribute( $payload['attribute_id'] );

		if ( ! $attribute ) {
			wp_send_json_error( __( 'Invalid Attribute.', 'storeengine' ) );
		}

		$args = [];
		if ( isset( $payload['search'] ) ) {
			$args['search'] = $payload['search'];
		}
		$terms = $attribute->get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			wp_send_json_error( $terms->get_error_message() );
		}

		wp_send_json_success( $terms );
	}

	public function create_product_attribute_term( array $payload ) {
		$attribute = Helper::get_product_attribute( $payload['attribute_id'] );

		if ( ! $attribute ) {
			wp_send_json_error( __( 'Invalid Attribute.', 'storeengine' ) );
		}

		$term = $attribute->add_term( $payload['name'], [
			'slug'        => $payload['slug'] ?? '',
			'description' => $payload['description'] ?? '',
		] );

		if ( is_wp_error( $term ) ) {
			wp_send_json_error( $term->get_error_message() );
		}

		wp_send_json_success( $term );
	}

	public function update_product_attribute_term( array $payload ) {
		$attribute = Helper::get_product_attribute( $payload['attribute_id'] );

		if ( ! $attribute ) {
			wp_send_json_error( __( 'Invalid Attribute.', 'storeengine' ) );
		}

		$term = $attribute->update_term( $payload['term_id'], $payload['name'], [
			'slug'        => $payload['slug'],
			'description' => $payload['description'],
		] );

		if ( is_wp_error( $term ) ) {
			wp_send_json_error( $term->get_error_message() );
		}

		wp_send_json_success( $term );
	}

	public function delete_product_attribute_term( array $payload ) {
		$attribute = Helper::get_product_attribute( $payload['attribute_id'] );

		if ( ! $attribute ) {
			wp_send_json_error( __( 'Invalid Attribute.', 'storeengine' ) );
		}

		$result = $attribute->delete_term( $payload['term_id'] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'Term deleted', 'storeengine' ) );
	}
}
