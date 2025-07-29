<?php

namespace StoreEngine\Classes;

use StoreEngine\Classes\Data\AttributeData;
use StoreEngine\Classes\Data\IntegrationRepositoryData;
use StoreEngine\Classes\enums\ProductTaxStatus;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\TaxUtil;

/**
 * @TODO convert into entity class.
 */
class AbstractProduct {

	protected int $id;

	protected array $data = [
		'post_author'       => 0,
		'post_parent'       => 0,
		'post_date'         => '',
		'post_date_gmt'     => '',
		'post_content'      => '',
		'post_title'        => '',
		'post_excerpt'      => '',
		'post_type'         => 'storeengine_product',
		'post_status'       => 'publish',
		'comment_status'    => '',
		'ping_status'       => '',
		'post_name'         => '',
		'post_modified'     => '',
		'post_modified_gmt' => '',
		'comment_count'     => 0,
	];

	protected array $meta_data = [
		'product_hide'                    => false,
		'product_type'                    => 'simple',
		'product_shipping_type'           => 'digital',
		'product_digital_auto_complete'   => true,
		'product_physical_weight'         => '',
		'product_physical_weight_unit'    => '',
		'product_physical_length'         => '',
		'product_physical_width'          => '',
		'product_physical_height'         => '',
		'product_physical_dimension_unit' => '',
		'product_gallery_ids'             => [],
		'product_downloadable_files'      => [],
		'product_attributes_order'        => [],
		'product_tax_status'              => ProductTaxStatus::TAXABLE,
		'product_tax_class'               => '',
		'upsell_ids'                      => [],
		'crosssell_ids'                   => [],
	];

	protected array $serialized_data = [
		'product_gallery_ids',
		'product_downloadable_files',
		'product_attributes_order',
		'upsell_ids',
		'crosssell_ids',
	];


	protected array $new_data = [];

	protected array $new_meta_data = [];

	public const CACHE_KEY = 'storeengine_product_';

	public const CACHE_GROUP = 'storeengine_products';


	public function __construct( int $id = 0 ) {
		$this->id = $id;
		$this->get(); // load if id is present.
	}

	public function get() {
		if ( ! $this->id ) {
			return false;
		}

		$product = get_post( $this->id, ARRAY_A );

		if ( ! $product || 'storeengine_product' !== $product['post_type'] ) {
			$this->id = 0;
		} else {
			$this->set_data( $product );

			$post_metas = get_metadata( 'post', $this->id );
			$post_metas = array_map( fn( $meta ) => $meta[0], $post_metas );

			$this->set_metadata( $post_metas );
		}

		return $this;
	}

	public function set_data( array $data ) {
		$this->id   = $data['ID'];
		$this->data = array_intersect_key( $data, array_flip( array_keys( $this->data ) ) );
	}

	public function set_metadata( array $data ) {
		$outsider_metadata = array_filter( $data, fn( $value, $key ) => 0 !== strpos( $key, '_storeengine_' ), ARRAY_FILTER_USE_BOTH );

		foreach ( $this->meta_data as $meta_key => $meta_data ) {
			$db_meta_key = '_storeengine_' . $meta_key;
			if ( ! array_key_exists( $db_meta_key, $data ) ) {
				continue;
			}

			$post_meta = $data[ $db_meta_key ];

			if ( in_array( $meta_key, $this->serialized_data, true ) ) {
				$this->meta_data[ $meta_key ] = $post_meta ? maybe_unserialize( $post_meta ) : [];
			} else {
				$this->meta_data[ $meta_key ] = $post_meta;
			}
		}

		foreach ( $outsider_metadata as $meta_key => $meta_data ) {
			$this->meta_data[ $meta_key ] = $meta_data;
		}
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_parent_id(): int {
		return (int) $this->get_prop( 'post_parent' );
	}

	public function get_type() {
		return $this->get_meta_prop( 'product_type', 'simple' );
	}

	public function get_name() {
		return $this->get_prop( 'post_title' );
	}

	public function get_content() {
		return $this->get_prop( 'post_content' );
	}

	public function get_slug() {
		return $this->get_prop( 'post_name' );
	}

	public function get_status() {
		return $this->get_prop( 'post_status' );
	}

	public function get_shipping_type() {
		return $this->get_meta_prop( 'product_shipping_type' );
	}

	public function auto_complete_digital(): bool {
		return Formatting::string_to_bool( $this->get_meta_prop( 'product_digital_auto_complete' ) ) && 'digital' === $this->get_shipping_type();
	}

	public function get_attributes_order() {
		$data = $this->get_meta_prop( 'product_attributes_order', '[]' );

		if ( ! is_array( $data ) ) {
			$data = maybe_unserialize( $data );
		}

		if ( ! is_array( $data ) ) {
			$data = json_decode( $data, true );
		}

		return $data;
	}

	protected function get_prop( string $name, $default = '' ) {
		if ( array_key_exists( $name, $this->new_data ) ) {
			return $this->new_data[ $name ];
		}

		return $this->data[ $name ] ?? $default;
	}

	protected function get_meta_prop( string $name, $default = '' ) {
		if ( array_key_exists( $name, $this->new_meta_data ) ) {
			return $this->new_meta_data[ $name ];
		}

		return empty( $this->meta_data[ $name ] ) ? $default : $this->meta_data[ $name ];
	}

	/**
	 * @return Price[]
	 */
	public function get_prices(): array {
		if ( 0 === $this->id ) {
			return [];
		}

		$where = [
			[
				'key'   => 'product_id',
				'value' => $this->id,
			],
		];

		if ( ! Helper::get_addon_active_status( 'subscription' ) ) {
			$where[] = [
				'key'     => 'price_type',
				'value'   => 'subscription',
				'compare' => '!=',
			];
		}

		$query = new PriceCollection( [ 'where' => $where ] );

		return array_values( $query->get_results() );
	}

	/**
	 * @return IntegrationRepositoryData[]
	 * @throws StoreEngineException
	 */
	public function get_integrations(): array {
		if ( 0 === $this->id ) {
			return [];
		}

		$has_cache = wp_cache_get( self::CACHE_KEY . $this->id . '_integrations', self::CACHE_GROUP );
		if ( $has_cache && is_array( $has_cache ) ) {
			return array_map( fn( $result ) => new IntegrationRepositoryData(
				( new Integration() )->set_data( $result ),
				new Price( $result->price_id )
			), $has_cache );
		}

		global $wpdb;
		$integrations = [];
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results(
			$wpdb->prepare( "
				SELECT
					i.id,
					i.product_id,
					i.price_id,
					i.provider,
					i.integration_id,
					i.variation_id,
					i.created_at,
					i.updated_at,
					pr.price_name,
					pr.price_type,
					pr.price,
					pr.compare_price,
					pr.settings,
					pr.`order`
					FROM {$wpdb->prefix}storeengine_integrations i
                 JOIN {$wpdb->prefix}storeengine_product_price pr ON pr.id = i.price_id
         		 WHERE i.product_id = %d ORDER BY pr.`order`", $this->get_id()
			)
		);

		if ( ! $results ) {
			return $integrations;
		}

		/**
		 * @TODO prepare data and store ids in cache
		 * @see get_prices()
		 */
		wp_cache_set( self::CACHE_KEY . $this->id . '_integrations', $results, self::CACHE_GROUP );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery

		foreach ( $results as $result ) {
			$integrations[] = new IntegrationRepositoryData( ( new Integration() )->set_data( $result ), new Price( $result->price_id ) );
		}


		return $integrations;
	}

	public function get_weight(): float {
		return (float) $this->get_meta_prop( 'product_physical_weight', 0 );
	}

	public function get_weight_unit(): ?string {
		return $this->get_meta_prop( 'product_physical_weight_unit', 'g' );
	}

	public function get_length(): float {
		return (float) $this->get_meta_prop( 'product_physical_length', 0 );
	}

	public function get_width(): float {
		return (float) $this->get_meta_prop( 'product_physical_width', 0 );
	}

	public function get_height(): float {
		return (float) $this->get_meta_prop( 'product_physical_height', 0 );
	}

	public function get_dimension_unit(): ?string {
		return $this->get_meta_prop( 'product_physical_dimension_unit', 'mm' );
	}

	public function get_dimensions(): array {
		return [
			'length' => $this->get_length(),
			'width'  => $this->get_width(),
			'height' => $this->get_height(),
		];
	}

	public function get_formatted_dimensions(): string {
		return apply_filters( 'storeengine/product/dimensions', Formatting::format_dimensions( $this->get_dimensions( false ) ), $this );
	}

	public function get_catalog_visibility(): string {
		// @TODO: Need to implement this feature with taxonomy.
		return $this->is_hide() ? 'hidden' : 'visible';
	}

	public function get_product_gallery() {
		return $this->get_meta_prop( 'product_gallery_ids' );
	}

	public function get_upsell_ids() {
		return $this->get_meta_prop( 'upsell_ids' );
	}

	public function get_crosssell_ids() {
		return $this->get_meta_prop( 'crosssell_ids' );
	}

	public function get_upsell_products(): array {
		$ids = $this->get_upsell_ids();
		if ( ! is_array( $ids ) ) {
			$ids = [];
		}

		return array_filter( array_map( fn( $id ) => Helper::get_product( absint( $id ) ), array_unique( $ids ) ) );
	}

	public function get_crosssell_products(): array {
		$ids = $this->get_crosssell_ids();
		if ( ! is_array( $ids ) ) {
			$ids = [];
		}

		return array_filter( array_map( fn( $id ) => Helper::get_product( absint( $id ) ), array_unique( $ids ) ) );
	}

	public function get_downloadable_files() {
		return $this->get_meta_prop( 'product_downloadable_files', [] );
	}

	public function get_published_date() {
		return $this->get_prop( 'post_date' );
	}

	public function get_published_date_gmt() {
		return $this->get_prop( 'post_date_gmt' );
	}

	public function get_updated_date() {
		return $this->get_prop( 'post_modified' );
	}

	public function get_updated_date_gmt() {
		return $this->get_prop( 'post_modified_gmt' );
	}

	public function is_downloadable(): bool {
		return ! empty( $this->get_downloadable_files() );
	}

	public function is_hide(): bool {
		return Formatting::string_to_bool( $this->get_meta_prop( 'product_hide' ) );
	}

	/**
	 * @return AttributeData[][]
	 */
	public function get_attributes(): array {
		$cache_key = self::CACHE_KEY . $this->get_id() . '_attributes';
		$has_cache = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( $has_cache ) {
			return $has_cache;
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT t.term_id, tt.term_taxonomy_id, t.name, t.slug, tt.description, tt.taxonomy, tt.count, tr.term_order
				FROM {$wpdb->term_relationships} tr
				JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				JOIN $wpdb->terms t ON tt.term_id = t.term_id
				WHERE tr.object_id = %d AND tt.taxonomy LIKE %s ORDER BY tr.term_order
				",
				$this->get_id(),
				'se_pa_%'
			)
		);

		if ( ! $results ) {
			return [];
		}

		$attributes = [];

		foreach ( $results as $result ) {
			if ( ! taxonomy_exists( $result->taxonomy ) ) {
				continue;
			}

			$attributes[ $result->taxonomy ][] = ( new AttributeData() )->set_data( $result );
		}

		$ordered_attributes = [];
		$attributes_order   = $this->get_attributes_order();

		foreach ( $attributes_order as $taxonomy ) {
			if ( isset( $attributes[ $taxonomy ] ) ) {
				$ordered_attributes[ $taxonomy ] = $attributes[ $taxonomy ];
			}
		}

		wp_cache_set( $cache_key, $ordered_attributes, self::CACHE_GROUP );

		return $ordered_attributes;
	}

	public function is_in_stock(): bool {
		// @TODO: Need to implement this feature.
		return true;
	}

	/**
	 * Checks if a product is virtual (has no shipping).
	 *
	 * @return bool
	 */
	public function is_virtual(): bool {
		return apply_filters( 'storeengine/product/is_virtual', 'digital' === $this->get_shipping_type(), $this );
	}

	/**
	 * Returns whether the product is visible in the catalog.
	 *
	 * @return bool
	 */
	public function is_visible(): bool {
		return apply_filters( 'storeengine/product/is_visible', $this->is_visible_core(), $this->get_id() );
	}

	protected function is_visible_core(): bool {
		$visible = 'visible' === $this->get_catalog_visibility() || ( is_search() && 'search' === $this->get_catalog_visibility() ) || ( ! is_search() && 'catalog' === $this->get_catalog_visibility() );

		if ( 'trash' === $this->get_status() ) {
			$visible = false;
		} elseif ( 'publish' !== $this->get_status() && ! current_user_can( 'edit_post', $this->get_id() ) ) {
			$visible = false;
		}

		if ( $this->get_parent_id() ) {
			$parent_product = Helper::get_product( $this->get_parent_id() );

			if ( $parent_product && 'publish' !== $parent_product->get_status() && ! current_user_can( 'edit_post', $parent_product->get_id() ) ) {
				$visible = false;
			}
		}

		if ( 'yes' === get_option( 'storeengine/hide_out_of_stock_items' ) && ! $this->is_in_stock() ) {
			$visible = false;
		}

		return $visible;
	}

	/**
	 * Checks if a product needs shipping.
	 *
	 * @return bool
	 */
	public function needs_shipping(): bool {
		return apply_filters( 'storeengine/product/needs_shipping', ! $this->is_virtual(), $this );
	}

	public function set_name( string $name ) {
		$this->new_data['post_title'] = $name;
	}

	public function set_type( string $value ) {
		$this->new_meta_data['product_type'] = $value;
	}

	public function is_type( $type ): bool {
		return is_array( $type ) ? in_array( $this->get_type(), $type, true ) : $type === $this->get_type();
	}

	public function set_author_id( int $author ) {
		$this->new_data['post_author'] = $author;
	}

	public function set_parent( int $id ) {
		$this->new_data['post_parent'] = $id;
	}

	public function set_content( string $content ) {
		$this->new_data['post_content'] = $content;
	}

	public function set_excerpt( string $value ) {
		$this->new_data['post_excerpt'] = $value;
	}

	public function set_status( string $value ) {
		$this->new_data['post_status'] = $value;
	}

	public function set_shipping_type( string $value ) {
		$this->new_meta_data['product_shipping_type'] = $value;
	}

	public function set_digital_auto_complete( $value ) {
		$this->new_meta_data['product_digital_auto_complete'] = Formatting::string_to_bool( $value );
	}

	public function set_hide( $value ) {
		$this->new_meta_data['product_hide'] = Formatting::string_to_bool( $value );
	}

	public function set_attributes_order( array $value ) {
		$this->new_meta_data['product_attributes_order'] = $value;
	}

	public function set_slug( string $value ) {
		$this->new_data['post_name'] = $value;
	}

	public function set_published_date( string $value ) {
		$this->new_data['post_date'] = $value;
	}

	public function set_published_date_gmt( string $value ) {
		$this->new_data['post_date_gmt'] = $value;
	}

	public function set_updated_date( string $value ) {
		$this->new_data['post_modified'] = $value;
	}

	public function set_updated_date_gmt( string $value ) {
		$this->new_data['post_modified_gmt'] = $value;
	}

	public function set_weight( float $value ) {
		$this->new_meta_data['product_physical_weight'] = $value;
	}

	public function set_weight_unit( string $value ) {
		$this->new_meta_data['product_physical_weight_unit'] = $value;
	}

	public function set_length( float $value ) {
		$this->new_meta_data['product_physical_length'] = $value;
	}

	public function set_width( float $value ) {
		$this->new_meta_data['product_physical_width'] = $value;
	}

	public function set_height( float $value ) {
		$this->new_meta_data['product_physical_height'] = $value;
	}

	public function set_dimension_unit( string $value ) {
		$this->new_meta_data['product_physical_dimension_unit'] = $value;
	}

	public function set_downloadable_files( array $value ) {
		$this->new_meta_data['product_downloadable_files'] = $value;
	}

	public function set_upsell_ids( array $value ) {
		$this->new_meta_data['upsell_ids'] = $value;
	}

	public function set_crosssell_ids( array $value ) {
		$this->new_meta_data['crosssell_ids'] = $value;
	}

	public function get_tax_status() {
		return $this->get_meta_prop( 'product_tax_status' );
	}

	public function get_tax_class( string $context = 'view' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return $this->get_meta_prop( 'product_tax_class' );
	}

	public function get_purchase_note( string $context = 'view' ) {
		return $this->get_meta_prop( 'purchase_note' );
	}

	/**
	 * @param ?string $value
	 *
	 * @return void
	 */
	public function set_tax_status( ?string $value ) {
		// Set default if empty.
		if ( empty( $status ) ) {
			$status = ProductTaxStatus::TAXABLE;
		}

		$status = strtolower( $status );

		$tax_options = [
			ProductTaxStatus::TAXABLE,
			ProductTaxStatus::SHIPPING,
			ProductTaxStatus::NONE,
		];

		if ( ! in_array( $status, $tax_options, true ) ) {
			throw new StoreEngineException( __( 'Invalid product tax status.', 'storeengine' ), 'product_invalid_tax_status' );
		}

		$this->new_meta_data['product_tax_status'] = $value;
	}

	public function set_tax_class( string $class ) {
		$class         = sanitize_title( $class );
		$class         = 'standard' === $class ? '' : $class;
		$valid_classes = $this->get_valid_tax_classes();

		if ( ! in_array( $class, $valid_classes, true ) ) {
			$class = '';
		}

		$this->new_meta_data['product_tax_class'] = $class;
	}

	public function set_purchase_note( string $purchase_note ) {
		$this->new_meta_data['product_purchase_note'] = $purchase_note;
	}

	/**
	 * Return an array of valid tax classes
	 *
	 * @return array valid tax classes
	 */
	protected function get_valid_tax_classes(): array {
		return Tax::get_tax_class_slugs();
	}

	/**
	 * Returns whether the product-pricing is taxable.
	 *
	 * @return bool
	 */
	public function is_taxable(): bool {
		/**
		 * Filters whether a product is taxable.
		 *
		 * @param bool $taxable Whether the product is taxable.
		 * @param AbstractProduct $price Product object.
		 */
		return apply_filters( 'storeengine/product/is_taxable', ProductTaxStatus::TAXABLE === $this->get_tax_status() && TaxUtil::is_tax_enabled(), $this );
	}

	/**
	 * Returns whether the product-pricing shipping is taxable.
	 *
	 * @return bool
	 */
	public function is_shipping_taxable(): bool {
		return $this->needs_shipping() && ( ProductTaxStatus::TAXABLE === $this->get_tax_status() || ProductTaxStatus::TAXABLE === $this->get_tax_status() );
	}

	public function get_metadata( string $name, bool $single = true ) {
		if ( array_key_exists( $name, $this->meta_data ) ) {
			return $this->meta_data[ $name ];
		}

		if ( strpos( $name, '_storeengine_' ) === 0 ) {
			return $this->get_meta_prop( str_replace( '_storeengine_', '', $name ), false );
		}

		return get_post_meta( $this->id, $name, $single );
	}

	public function update_metadata( string $name, $value ) {
		if ( 0 === strpos( $name, '_storeengine_' ) ) {
			$this->new_meta_data[ str_replace( '_storeengine_', '', $name ) ] = $value;
		} else {
			$this->meta_data[ $name ] = $value;
		}

		return update_post_meta( $this->id, $name, $value );
	}

	public function save() {
		// setup default data.
		if ( empty( $this->new_meta_data ) ) {
			foreach ( $this->meta_data as $name => $value ) {
				if ( '' === $value || [] === $value ) {
					continue;
				}

				$this->new_meta_data[ $name ] = $value;
			}
		}

		if ( empty( $this->new_meta_data ) && empty( $this->new_data ) ) {
			return;
		}

		$this->save_core_data();
		$this->save_meta_data();
		wp_cache_flush_group( self::CACHE_GROUP );
		wp_cache_set_last_changed( self::CACHE_GROUP );
	}

	protected function save_core_data() {
		if ( 0 === $this->id ) {
			$product_id = wp_insert_post( array_merge( $this->data, $this->new_data ), true );

			if ( is_wp_error( $product_id ) ) {
				throw StoreEngineException::from_wp_error( $product_id );
			}

			$this->id = $product_id;
		} else {
			$updated = wp_update_post( array_merge( $this->data, $this->new_data, array( 'ID' => $this->id ) ), true );
			if ( is_wp_error( $updated ) ) {
				throw StoreEngineException::from_wp_error( $updated );
			}
		}
	}

	protected function save_meta_data() {
		$new_meta_data = $this->new_meta_data;
		if ( 0 === count( $new_meta_data ) ) {
			return;
		}

		foreach ( $new_meta_data as $meta_key => $new_meta_datum ) {
			update_post_meta( $this->id, '_storeengine_' . $meta_key, $new_meta_datum );
		}
	}
}
