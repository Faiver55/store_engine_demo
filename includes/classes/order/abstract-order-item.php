<?php

namespace StoreEngine\Classes\Order;

use Exception;
use StoreEngine\Classes\AbstractEntity;
use StoreEngine\Classes\AbstractWpdb;
use StoreEngine\Classes\enums\ProductTaxStatus;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\Tax;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\TaxUtil;

/**
 * @see \WC_Order_Item
 */
//abstract class AbstractOrderItem extends AbstractWpdb {
abstract class AbstractOrderItem extends AbstractEntity {

	protected bool $read_extra_data_separately = false;

	protected string $table = 'storeengine_order_items';

	protected string $meta_type = 'order_item';

	protected int $order_id = 0;

	protected string $object_type = 'order_item';

	protected array $data = [
		'order_id' => 0,
		'name'     => '',
	];

	protected string $primary_key = 'order_item_id';

	protected array $readable_fields = [ 'order_item_id id', 'order_id', 'order_item_name name' ];

	/**
	 * Create DB Record.
	 *
	 * @throws StoreEngineException
	 */
	public function create() {
		$data = [
			'order_item_name' => $this->get_name(),
			'order_item_type' => $this->get_type(),
			'order_id'        => $this->get_order_id(),
		];

		if ( $this->wpdb->insert( $this->table, $data, [ '%s', '%s', '%d' ] ) ) {
			$this->set_id( $this->wpdb->insert_id );
			$this->save_item_data();
			$this->save_meta_data();
			$this->apply_changes();
			$this->clear_cache();
		}

		if ( $this->wpdb->last_error ) {
			throw new StoreEngineException( $this->wpdb->last_error, 'db-error-insert-record' );
		}
	}

	/**
	 * Update DB Record.
	 *
	 * @throws StoreEngineException
	 */
	public function update() {
		if ( ! $this->get_id() ) {
			return;
		}

		$this->save_meta_data();

		$changes = $this->get_changes();

		if ( array_intersect( [ 'name', 'order_id' ], array_keys( $changes ) ) ) {
			$data = [
				'order_item_name' => $this->get_name(),
				'order_item_type' => $this->get_type(),
				'order_id'        => $this->get_order_id(),
			];

			$this->wpdb->update( $this->table, $data, [ $this->primary_key => $this->get_id() ], [
				'%s',
				'%s',
				'%d',
			], [ '%d' ] );
			if ( $this->wpdb->last_error ) {
				throw new StoreEngineException( $this->wpdb->last_error, 'db-error-update-record' );
			}
		}

		$this->save_item_data();
		$this->save_meta_data();
		$this->apply_changes();
		$this->clear_cache();
	}

	/**
	 * Get order ID by order item ID.
	 *
	 * @param int $item_id Item ID.
	 *
	 * @return int
	 */
	public function get_order_id_by_order_item_id( int $item_id ): int {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT order_id FROM {$wpdb->prefix}storeengine_order_items WHERE order_item_id = %d",
				$item_id
			)
		);
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Clear meta cache.
	 *
	 * @param int $item_id Item ID.
	 * @param int|null $order_id Order ID. If not set, it will be loaded using the item ID.
	 */
	protected function clear_caches( int $item_id, ?int $order_id ) {
		wp_cache_delete( 'item-' . $item_id, 'order-items' );

		if ( ! $order_id ) {
			$order_id = $this->get_order_id_by_order_item_id( $item_id );
		}
		if ( $order_id ) {
			wp_cache_delete( 'order-items-' . $order_id, 'orders' );
		}
	}

	/**
	 * Get the order item type based on Item ID.
	 *
	 * @param int|string $item_id Item ID.
	 *
	 * @return string|null Order item type or null if no order item entry found.
	 */
	public static function get_order_item_type( $item_id ): ?string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT order_item_type FROM {$wpdb->prefix}storeengine_order_items WHERE order_item_id = %d LIMIT 1;",
				absint( $item_id )
			)
		);
	}


	/**
	 * Merge changes with data and clear.
	 * Overrides WC_Data::apply_changes.
	 * array_replace_recursive does not work well for order items because it merges taxes instead
	 * of replacing them.
	 */
	public function apply_changes() {
		$this->data    = array_replace( $this->data, $this->changes );
		$this->changes = [];
	}

	/*
	|--------------------------------------------------------------------------
	| Getters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get order ID this meta belongs to.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return int
	 */
	public function get_order_id( string $context = 'view' ): int {
		return (int) $this->get_prop( 'order_id', $context );
	}

	/**
	 * Get order item name.
	 *
	 * @param string $context What the value is for. Valid values are 'view' and 'edit'.
	 *
	 * @return string
	 */
	public function get_name( string $context = 'view' ): string {
		return $this->get_prop( 'name', $context );
	}

	/**
	 * Get quantity.
	 *
	 * @return int
	 */
	public function get_quantity(): int {
		return 1;
	}

	/**
	 * Get tax status.
	 *
	 * @return string
	 */
	public function get_tax_status(): string {
		return ProductTaxStatus::TAXABLE;
	}

	/**
	 * Get tax class.
	 *
	 * @return string
	 */
	public function get_tax_class(): string {
		return '';
	}

	/**
	 * Get parent order object.
	 *
	 * @return Order|false
	 */
	public function get_order(): Order {
		$oder = Helper::get_order( $this->get_order_id() );

		return $oder && ! is_wp_error( $oder ) ? $oder : false;
	}

	/*
	|--------------------------------------------------------------------------
	| Setters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Set order ID.
	 *
	 * @param int|string $value Order ID.
	 */
	public function set_order_id( $value ) {
		$this->set_prop( 'order_id', absint( $value ) );
	}

	/**
	 * Set order item name.
	 *
	 * @param string $value Item name.
	 */
	public function set_name( string $value ) {
		$this->set_prop( 'name', wp_check_invalid_utf8( $value ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Other Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Check if an attribute is included in the attributes area of a variation name.
	 *
	 * @param string $attribute Attribute value to check for.
	 * @param string $name Product name to check in.
	 *
	 * @return bool
	 */
	protected static function is_attribute_in_product_name( $attribute, $name ): bool {
		$is_in_name = stristr( $name, ' ' . $attribute . ',' ) || 0 === stripos( strrev( $name ), strrev( ' ' . $attribute ) );

		return apply_filters( 'storeengine/is_attribute_in_product_name', $is_in_name, $attribute, $name );
	}

	/**
	 * Wrapper for get_formatted_meta_data that includes all metadata by default. See https://github.com/woocommerce/woocommerce/pull/30948
	 *
	 * @param string $hideprefix Meta data prefix, (default: _).
	 * @param bool $include_all Include all meta data, this stop skip items with values already in the product name.
	 *
	 * @return array
	 */
	public function get_all_formatted_metadata( string $hideprefix = '_', bool $include_all = true ): array {
		return $this->get_formatted_metadata( $hideprefix, $include_all );
	}

	/**
	 * Expands things like term slugs before return.
	 *
	 * @param string $hideprefix Meta data prefix, (default: _).
	 * @param bool $include_all Include all meta data, this stop skip items with values already in the product name.
	 *
	 * @return array
	 */
	public function get_formatted_metadata( string $hideprefix = '_', bool $include_all = false ): array {
		$formatted_meta    = [];
		$meta_data         = $this->get_meta_data();
		$hideprefix_length = ! empty( $hideprefix ) ? strlen( $hideprefix ) : 0;
		$order_item_name   = $this->get_name();

		try {
			$product = is_callable( [ $this, 'get_product' ] ) ? $this->get_product() : false;
		} catch ( Exception $e ) {
			$product = false;
		}

		foreach ( $meta_data as $meta ) {
			if ( empty( $meta->id ) || '' === $meta->value || ! is_scalar( $meta->value ) || ( $hideprefix_length && substr( $meta->key, 0, $hideprefix_length ) === $hideprefix ) ) {
				continue;
			}

			$meta->key     = rawurldecode( (string) $meta->key );
			$meta->value   = rawurldecode( (string) $meta->value );
			$display_value = $meta->value;
			$display_key   = trim( $meta->key );

			if ( taxonomy_exists( $meta->key . '-' ) ) {
				$display_key = get_taxonomy( $display_key )->label;
				$term        = get_term_by( 'slug', $meta->value, $meta->key );

				if ( ! is_wp_error( $term ) && is_object( $term ) && $term->name ) {
					$display_value = $term->name;
				}
			} else {
				$display_key = ucwords( str_replace( [ '_', '-' ], ' ', Helper::strip_attribute_taxonomy_name( $display_key ) ) );
			}


			// Skip items with values already in the product details area of the product name.
			if ( ! $include_all && $product && $product->is_type( 'variable' ) && self::is_attribute_in_product_name( $display_value, $order_item_name ) ) {
				continue;
			}

			$formatted_meta[ $meta->id ] = [
				'key'           => $meta->key,
				'value'         => $meta->value,
				'display_key'   => wp_kses_post( apply_filters( 'storeengine/order_item_display_meta_key', $display_key, $meta, $this ) ),
				'display_value' => wp_kses_post( make_clickable( apply_filters( 'storeengine/order_item_display_meta_value', trim( $display_value ), $meta, $this ) ) ),
			];
		}

		return apply_filters( 'storeengine/order_item_get_formatted_meta_data', $formatted_meta, $this );
	}

	/**
	 * Calculate item taxes.
	 *
	 * @param array $calculate_tax_for Location data to get taxes for. Required.
	 *
	 * @return bool  True if taxes were calculated.
	 */
	public function calculate_taxes( array $calculate_tax_for = [] ): bool {
		if ( ! isset( $calculate_tax_for['country'], $calculate_tax_for['state'], $calculate_tax_for['postcode'], $calculate_tax_for['city'] ) ) {
			return false;
		}

		if ( '0' !== $this->get_tax_class() && ProductTaxStatus::TAXABLE === $this->get_tax_status() && TaxUtil::is_tax_enabled() ) {
			$calculate_tax_for['tax_class'] = $this->get_tax_class();
			$tax_rates                      = Tax::find_rates( $calculate_tax_for );
			$taxes                          = Tax::calc_tax( $this->get_total(), $tax_rates, false );

			if ( method_exists( $this, 'get_subtotal' ) ) {
				$subtotal_taxes = Tax::calc_tax( $this->get_subtotal(), $tax_rates, false );
				$this->set_taxes( [
					'total'    => $taxes,
					'subtotal' => $subtotal_taxes,
				] );
			} else {
				$this->set_taxes( array( 'total' => $taxes ) );
			}
		} else {
			$this->set_taxes( false );
		}

		/**
		 * Fires after calculating taxes for specific order item.
		 *
		 * @param self $this Order item object.
		 * @param array $calculate_tax_for Holds the address data.
		 */
		do_action( 'storeengine/order_item/after_calculate_taxes', $this, $calculate_tax_for );

		return true;
	}
}
