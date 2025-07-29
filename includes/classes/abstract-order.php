<?php

namespace StoreEngine\Classes;

use Exception;
use stdClass;
use StoreEngine\Classes\Cart\ItemTotals;
use StoreEngine\Classes\enums\ProductTaxStatus;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidArgumentException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusException;
use StoreEngine\Classes\Order\AbstractOrderItem;
use StoreEngine\Classes\Order\OrderItemCoupon;
use StoreEngine\Classes\Order\OrderItemFee;
use StoreEngine\Classes\Order\OrderItemProduct;
use StoreEngine\Classes\Order\OrderItemShipping;
use StoreEngine\Classes\Order\OrderItemTax;
use StoreEngine\Classes\Order\PaymentInfo;
use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Classes\PaymentTokens\PaymentToken;
use StoreEngine\Utils\Caching;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\NumberUtil;
use StoreEngine\Utils\TaxUtil;
use WP_Error;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @see \WC_Abstract_Order
 * @see \Abstract_WC_Order_Data_Store_CPT
 * @see \OrdersTableDataStore
 */
abstract class AbstractOrder extends AbstractEntity {
	use ItemTotals;

	protected bool $read_extra_data_separately = false;

	protected string $table = 'storeengine_orders';

	protected string $object_type = 'order';

	protected string $meta_type = 'order';

	protected array $internal_meta_keys = [
		'_total_tax',
		'_cart_tax',
		'_subtotal',
		'_total',
	];

	protected array $meta_key_to_props = [
		'_subtotal'  => 'subtotal',
		'_total'     => 'total',
		'_total_tax' => 'total_tax',
		'_cart_tax'  => 'cart_tax',
	];

	/**
	 * Order core data.
	 *
	 * @var array
	 */
	protected array $data = [
		'status'                      => '',
		'currency'                    => '',
		'type'                        => 'order',
		'tax_amount'                  => '',
		'total_amount'                => '',
		'customer_id'                 => '',
		'order_email'                 => '',
		'date_created_gmt'            => null,
		'date_updated_gmt'            => null,
		'parent_order_id'             => '',
		'payment_method'              => '',
		'payment_method_title'        => '',
		'transaction_id'              => '',
		'ip_address'                  => '',
		'user_agent'                  => '',
		'customer_note'               => '',
		'hash'                        => '',
		// Operational data of an order.
		'operational_id'              => 0,
		'created_via'                 => 'store-checkout',
		'version'                     => STOREENGINE_VERSION,
		'prices_include_tax'          => false,
		'coupon_usages_are_counted'   => 0,
		'download_permission_granted' => 0,
		'cart_hash'                   => '',
		'new_order_email_sent'        => 0,
		'order_key'                   => '',
		'order_stock_reduced'         => 1,
		'date_paid_gmt'               => null,
		'date_completed_gmt'          => null,
		'shipping_tax_amount'         => 0.00,
		'shipping_total_amount'       => 0.00,
		'discount_tax_amount'         => 0.00,
		'discount_total_amount'       => 0.00,
		'recorded_sales'              => 1,
		// C
		'total'                       => 0.0,
		'subtotal'                    => 0.0,
		'total_tax'                   => 0.0,
		'cart_tax'                    => 0.0,
	];

	/**
	 * Order items array.
	 *
	 * @var AbstractOrderItem[]
	 */
	protected array $items = [];

	/**
	 * Order items that need deleting are stored here.
	 *
	 * @var AbstractOrderItem[]
	 */
	protected array $items_to_delete = [];

	protected array $readable_fields = [
		'status',
		'currency',
		'type',
		'tax_amount',
		'total_amount',
		'customer_id',
		'billing_email',
		'date_created_gmt',
		'date_updated_gmt',
		'parent_order_id',
		'payment_method',
		'payment_method_title',
		'transaction_id',
		'ip_address',
		'user_agent',
		'customer_note',
		'hash',
	];

	/**
	 * Mappings of order item types to groups.
	 *
	 * @var array
	 */
	protected array $item_types_to_group = [
		'line_item' => 'line_items',
		'tax'       => 'tax_lines',
		'shipping'  => 'shipping_lines',
		'fee'       => 'fee_lines',
		'coupon'    => 'coupon_lines',
	];

	protected bool $allow_trash = true;

	protected function read_data(): array {
		return $this->read_db_data( $this->get_id() );
	}

	/**
	 * @param int|string|array{meta_key:string,meta_value:string,format:string}|array{cart_hash:string,customer_id:int} $value
	 * @param string $field
	 *
	 * @return array
	 * @throws StoreEngineException
	 *
	 * @FIXME move raed_db_data, get_by_key & get_by_meta to helper function
	 *       so we can avoid creating 2 instances for getting an order data.
	 */
	protected function read_db_data( $value, string $field = 'id' ): array {
		$allowed_fields = [ 'id', 'order_key', 'meta', 'cart_hash' ];

		if ( ! in_array( $field, $allowed_fields, true ) ) {
			/* translators: %s: Field/column name. */
			StoreEngineInvalidArgumentException::throw( sprintf( __( 'Invalid argument provided. Field %s can not be recognized.', 'storeengine' ), $field ) );
		}
		if ( in_array( $field, [ 'cart_hash', 'meta' ], true ) && ! is_array( $value ) ) {
			/* translators: %s: Argument type. */
			StoreEngineInvalidArgumentException::throw( sprintf( __( 'Invalid argument provided. Value must be array {meta_value, meta_key} to be used with meta field, %s provided.', 'storeengine' ), gettype( $value ) ) );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Query already prepared.
		if ( 'id' === $field ) {
			$result = $this->wpdb->get_row(
				$this->wpdb->prepare(
					"{$this->query()} WHERE o.id = %d AND o.type = %s",
					absint( $value ),
					$this->get_type()
				),
				ARRAY_A
			);
		} elseif ( 'order_key' === $field ) {
			$result = $this->wpdb->get_row(
				$this->wpdb->prepare(
					"{$this->query()} WHERE p.order_key = %s AND o.type = %s",
					$value,
					$this->get_type()
				),
				ARRAY_A
			);
		} elseif ( 'cart_hash' === $field ) {
			list( $cart_hash, $customer_id ) = $value;
			if ( $customer_id ) {
				$result = $this->wpdb->get_row(
					$this->wpdb->prepare(
						"{$this->query()} WHERE o.status = %s AND (o.customer_id = %d OR p.cart_hash = %s) ORDER BY o.id DESC",
						'draft',
						absint( $customer_id ),
						$cart_hash
					), ARRAY_A );
			} else {
				$result = $this->wpdb->get_row(
					$this->wpdb->prepare(
						"{$this->query()} WHERE o.status = %s AND p.cart_hash = %s ORDER BY o.id DESC",
						'draft',
						$cart_hash
					), ARRAY_A );
			}
		} else {
			if ( ! empty( $value[2] ) && is_string( $value[2] ) ) {
				$format = $value[2];
			} else {
				$format = is_float( $value[0] ) ? '%f' : ( is_numeric( $value[0] ) ? '%d' : '%s' );
			}

			$result = $this->wpdb->get_row(
				$this->wpdb->prepare(
					"{$this->query()} WHERE m.meta_value = $format AND m.meta_key = %s AND o.type = %s GROUP BY o.id",
					$value[1], // meta_value
					$value[0], // meta_key
					$this->get_type()
				),
				ARRAY_A
			);
		}

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Query already prepared.

		// Due to the group & concat (in the query), db can return row with null values (in all column) if order not found.

		if ( ! $result || empty( $result['o_id'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$data = [
					'field' => $field,
					'value' => $value,
				];
			} else {
				$data = null;
			}
			if ( $this->wpdb->last_error ) {
				/* translators: %s: Error message. */
				throw new StoreEngineException( sprintf( __( 'Error reading data from database. Error: %s', 'storeengine' ), $this->wpdb->last_error ), 'db-read-error', $data, 500 );
			} else {
				throw new StoreEngineException( __( 'Order not found.', 'storeengine' ), 'error-no-record', $data, 404 );
			}
		}

		$result['id'] = $result['o_id'];

		unset( $result['o_id'], $result['order_id'] );

		if ( ! $this->get_id() ) {
			$this->set_id( $result['id'] );
		}

		if ( ! empty( $result['meta_data'] ) ) {
			$meta_data     = array_values( (array) ( json_decode( $result['meta_data'], false ) ?? [] ) );
			$raw_meta_data = $this->filter_raw_meta_data( $meta_data );
			if ( is_array( $raw_meta_data ) ) {
				$this->init_meta_data( $raw_meta_data );
				if ( ! empty( $this->cache_group ) ) {
					wp_cache_set( $this->get_meta_cache_key(), $raw_meta_data, $this->cache_group );
				}
			}

			foreach ( array_filter( $meta_data, [ $this, 'include_extra_meta_keys' ] ) as $meta ) {
				if ( ! empty( $this->meta_key_to_props[ $meta->meta_key ] ) ) {
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- not running query, just decoding value.
					$result[ $this->meta_key_to_props[ $meta->meta_key ] ] = maybe_unserialize( $meta->meta_value );
				}
			}

			unset( $result['meta_data'] );
		}

		return array_merge(
			[
				'total'     => $this->get_metadata( '_total' ),
				'subtotal'  => $this->get_metadata( '_subtotal' ),
				'total_tax' => $this->get_metadata( '_total_tax' ),
				'cart_tax'  => $this->get_metadata( '_cart_tax' ),
			],
			$result
		);
	}

	/**
	 * @throws StoreEngineException
	 */
	public function get_by_key( string $key ): self {
		try {
			$id = wp_cache_get( 'order:key:' . $key, $this->cache_group );

			if ( false !== $id && false !== wp_cache_get( $id, $this->cache_group ) ) {
				$this->set_id( $id );
				$this->read();

				return $this;
			}

			$data = $this->read_db_data( $key, 'order_key' );

			wp_cache_set( 'order:key:' . $key, $data['id'], $this->cache_group );
			wp_cache_set( $data['id'], $data, $this->cache_group );

			$this->set_id( $data['id'] );
			$this->read();

			return $this;
		} catch ( StoreEngineException $e ) {
			if ( 'error-no-record' === $e->get_wp_error_code() ) {
				$e->set_message( __( 'The order is no longer exists.', 'storeengine' ) );
			}

			throw $e;
		}
	}

	/**
	 * @throws StoreEngineException
	 */
	public function get_by_meta( string $meta_key, $meta_value, ?string $format = null ): self {
		$cache_key = md5( $meta_key . '=' . $meta_value );
		$id        = wp_cache_get( $cache_key, $this->cache_group );

		if ( false !== $id && false !== wp_cache_get( $id, $this->cache_group ) ) {
			$this->set_id( $id );
			$this->read();

			return $this;
		}

		if ( null === $format ) {
			$format = is_float( $meta_value ) ? '%f' : ( is_numeric( $meta_value ) ? '%d' : '%s' );
		}

		$data = $this->read_db_data( [ $meta_key, $meta_value, $format ], 'meta' );
		wp_cache_set( $cache_key, $data['id'], $this->cache_group );
		wp_cache_set( $data['id'], $data, $this->cache_group );

		$this->set_id( $data['id'] );
		$this->read();

		return $this;
	}

	protected function query(): ?string {
		global $wpdb;

		return "
			SELECT
			o.id as o_id,
			o.parent_order_id as parent_order_id,
			o.*,
			p.id as operational_id,
			b.first_name as billing_first_name,
			b.last_name as billing_last_name,
			b.company as billing_company,
			b.address_1 as billing_address_1,
			b.address_2 as billing_address_2,
			b.city as billing_city,
			b.state as billing_state,
			b.postcode as billing_postcode,
			b.country as billing_country,
			o.billing_email as order_email,
			b.email as billing_email,
			b.phone as billing_phone,
			s.first_name as shipping_first_name,
			s.last_name as shipping_last_name,
			s.company as shipping_company,
			s.address_1 as shipping_address_1,
			s.address_2 as shipping_address_2,
			s.city as shipping_city,
			s.state as shipping_state,
			s.postcode as shipping_postcode,
			s.country as shipping_country,
			s.email as shipping_email,
			s.phone as shipping_phone,
			p.*
		FROM {$wpdb->prefix}storeengine_orders o
			LEFT JOIN {$wpdb->prefix}storeengine_order_addresses b ON b.order_id = o.id AND b.address_type = 'billing'
			LEFT JOIN {$wpdb->prefix}storeengine_order_addresses s ON s.order_id = o.id AND s.address_type = 'shipping'
			LEFT JOIN {$wpdb->prefix}storeengine_order_operational_data p ON p.order_id = o.id
			LEFT JOIN {$wpdb->prefix}storeengine_orders_meta m ON m.order_id = o.id
		";
	}

	protected function prepare_for_db( string $context = 'create' ): array {
		$data   = [];
		$format = [];

		$props = [
			'status',
			'currency',
			'type',
			'tax_amount',
			'total_amount',
			'customer_id',
			'order_email',
			'date_created_gmt',
			'date_updated_gmt',
			'parent_order_id',
			'payment_method',
			'payment_method_title',
			'transaction_id',
			'ip_address',
			'user_agent',
			'customer_note',
			'hash',
		];

		if ( 'create' === $context ) {
			$this->set_date_prop( 'date_created_gmt', current_time( 'mysql', 1 ) );
		}

		// Always set.
		$this->set_date_prop( 'date_updated_gmt', current_time( 'mysql', 1 ) );

		foreach ( $props as $prop ) {
			if ( 'update' === $context && 'date_created_gmt' === $prop ) {
				continue;
			}

			$value = $this->{"get_$prop"}( 'edit' );

			if ( $value && is_a( $value, StoreengineDatetime::class ) ) {
				$value = $this->prepare_date_for_db( $value, $prop );
			}

			if ( 'order_email' === $prop ) {
				$prop = 'billing_email'; // Special case.
				// @TODO rename the column as order_email.
			}

			$format[]      = $this->predict_format( $prop, $value );
			$data[ $prop ] = $value;
		}

		return [
			'data'   => apply_filters( 'storeengine/' . $this->object_type . '/db/' . $context, $data, $this ),
			'format' => $format,
		];
	}

	protected function prepare_operational_data_for_db( string $context = 'create' ): array {
		$data   = [];
		$format = [];

		$props = [
			'created_via'                 => 'created_via',
			'storeengine_version'         => 'version',
			'prices_include_tax'          => 'prices_include_tax',
			'coupon_usages_are_counted'   => 'coupon_usages_are_counted',
			'download_permission_granted' => 'download_permission_granted',
			'cart_hash'                   => 'cart_hash',
			'new_order_email_sent'        => 'new_order_email_sent',
			'order_key'                   => 'order_key',
			'order_stock_reduced'         => 'order_stock_reduced',
			'date_paid_gmt'               => 'date_paid_gmt',
			'date_completed_gmt'          => 'date_completed_gmt',
			'shipping_tax_amount'         => 'shipping_tax_amount',
			'shipping_total_amount'       => 'shipping_total_amount',
			'discount_tax_amount'         => 'discount_tax_amount',
			'discount_total_amount'       => 'discount_total_amount',
			'recorded_sales'              => 'recorded_sales',
		];

		if ( 'create' === $context ) {
			$props['order_id'] = 'id';
		}

		foreach ( $props as $key => $prop ) {
			$value = $this->{"get_$prop"}( 'edit' );
			if ( $value && is_a( $value, StoreengineDatetime::class ) ) {
				$value = $this->prepare_date_for_db( $value, $prop );
			}

			$format[]     = $this->predict_format( $key, $value );
			$data[ $key ] = $value;
		}

		return [
			'data'   => apply_filters( 'storeengine/' . $this->object_type . '_operational_data/db/' . $context, $data, $this ),
			'format' => $format,
		];
	}

	/**
	 * @throws StoreEngineException
	 */
	public function create() {
		$this->set_version( STOREENGINE_VERSION );
		$this->set_currency( $this->get_currency() ? $this->get_currency() : Formatting::get_currency() );

		if ( ! $this->get_date_created_gmt( 'edit' ) ) {
			$this->set_date_created_gmt( time() );
		}

		if ( ! $this->get_order_key( 'edit' ) ) {
			$this->set_order_key( self::generate_order_key() );
		}

		parent::create();

		$this->save_items();

		[ 'data' => $data, 'format' => $format ] = $this->prepare_operational_data_for_db( 'create' );

		if ( $this->wpdb->insert( "{$this->wpdb->prefix}storeengine_order_operational_data", $data, $format ) ) {
			$this->set_operational_id( $this->wpdb->insert_id );
		}

		if ( $this->wpdb->last_error ) {
			throw new StoreEngineException( $this->wpdb->last_error, 'db-error-insert-record' );
		}
	}

	/**
	 * @throws StoreEngineException
	 */
	public function update() {
		parent::update();

		$this->save_items();

		[ 'data' => $data, 'format' => $format ] = $this->prepare_operational_data_for_db( 'update' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->update(
			"{$this->wpdb->prefix}storeengine_order_operational_data",
			$data,
			[ 'order_id' => $this->get_id() ],
			$format,
			[ '%d' ]
		);

		if ( $this->wpdb->last_error ) {
			throw new StoreEngineException( $this->wpdb->last_error, 'db-error-update-record' );
		}

		$this->apply_changes();
		$this->clear_cache();
	}

	public function delete( bool $force_delete = false ): bool {
		if ( ! $force_delete && $this->is_trashable() ) {
			return $this->trash();
		}

		if ( $this->get_operational_id( 'edit' ) && $this->get_id() ) {
			$this->wpdb->delete(
				"{$this->wpdb->prefix}storeengine_order_operational_data",
				[ 'order_id' => $this->get_id() ],
				[ '%d' ],
			);

			if ( $this->wpdb->last_error ) {
				throw new StoreEngineException( $this->wpdb->last_error, 'db-error-delete-order_operational_data' );
			}
		}

		return parent::delete( true );
	}

	/**
	 * This method overwrites the base class's clone method to make it a no-op. In base class WC_Data, we are unsetting the meta_id to clone.
	 * It seems like this was done to avoid conflicting the metadata when duplicating products. However, doing that does not seems necessary for orders.
	 * In-fact, when we do that for orders, we lose the capability to clone orders with custom meta data by caching plugins. This is because, when we clone an order object for caching, it will clone the metadata without the ID. Unfortunately, when this cached object with nulled meta ID is retrieved, WC_Data will consider it as a new meta and will insert it as a new meta-data causing duplicates.
	 *
	 * Eventually, we should move away from overwriting the __clone method in base class itself, since it's easily possible to still duplicate the product without having to hook into the __clone method.
	 */
	public function __clone() {
	}

	/**
	 * Get all class data in array format.
	 *
	 * @return array
	 */
	public function get_data(): array {
		return array_merge(
			[ 'id' => $this->get_id() ],
			$this->data,
			[
				'meta_data'      => $this->get_meta_data(),
				'line_items'     => $this->get_line_product_items(),
				'tax_lines'      => $this->get_line_tax_items(),
				'shipping_lines' => $this->get_line_shipping_items(),
				'fee_lines'      => $this->get_line_fee_items(),
				'coupon_lines'   => $this->get_line_coupon_items(),
			]
		);
	}

	/**
	 * Log an error about this order is exception is encountered.
	 *
	 * @param StoreEngineException $e Exception object.
	 * @param string $message Message regarding exception thrown.
	 */
	protected function handle_exception( StoreEngineException $e, string $message = 'Error' ) {
		// @TODO implement error handler.
		Helper::log_error( $e );
	}

	/**
	 * Save all order items which are part of this order.
	 *
	 * @throws StoreEngineException
	 */
	protected function save_items() {
		$items_changed = false;

		foreach ( $this->items_to_delete as $item ) {
			$item->delete();
			$items_changed = true;
		}

		$this->items_to_delete = [];

		// Add/save items.
		foreach ( $this->items as $item_group => $items ) {
			if ( is_array( $items ) ) {
				$items = array_filter( $items );
				foreach ( $items as $item_key => $item ) {
					$item->set_order_id( $this->get_id() );

					$item_id = $item->save();

					// If ID changed (new item saved to DB)...
					if ( $item_id !== $item_key ) {
						$this->items[ $item_group ][ $item_id ] = $item;

						unset( $this->items[ $item_group ][ $item_key ] );

						$items_changed = true;
					}
				}
			}
		}

		if ( $items_changed ) {
			delete_transient( 'storeengine/order_' . $this->get_id() . '_needs_processing' );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Getters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get parent order ID.
	 *
	 * @param string $context View or edit context.
	 *
	 * @return int
	 */
	public function get_parent_order_id( string $context = 'view' ): int {
		return (int) $this->get_prop( 'parent_order_id', $context );
	}

	public function get_parent_id( string $context = 'view' ): int {
		return $this->get_parent_order_id( $context );
	}

	/**
	 * @param string $context
	 *
	 * @return false|Order
	 */
	public function get_parent_order( string $context = 'view' ) {
		$order = Helper::get_order( $this->get_parent_order_id( $context ) );

		if ( $order && ! is_wp_error( $order ) ) {
			return $order;
		}

		return false;
	}

	public function get_operational_id( string $context = 'view' ): int {
		return (int) $this->get_prop( 'operational_id', $context );
	}

	/**
	 * Gets order currency.
	 *
	 * @param string $context View or edit context.
	 *
	 * @return string
	 */
	public function get_currency( string $context = 'view' ): ?string {
		$currency = (string) $this->get_prop( 'currency', $context );
		if ( ! $currency ) {
			/**
			 * In view context, return the default status if no status has been set.
			 *
			 * @param string $status Default status.
			 */
			$currency = apply_filters( 'storeengine/default_order_currency', Formatting::get_currency() );
		}

		return $currency;
	}

	/**
	 * Get order_version.
	 *
	 * @param string $context View or edit context.
	 *
	 * @return ?string
	 */
	public function get_version( string $context = 'view' ): ?string {
		return $this->get_prop( 'version', $context );
	}

	/**
	 * Get prices_include_tax.
	 *
	 * @param string $context View or edit context.
	 *
	 * @return bool
	 */
	public function get_prices_include_tax( string $context = 'view' ): bool {
		return Formatting::string_to_bool( $this->get_prop( 'prices_include_tax', $context ) );
	}

	/**
	 * Get date_created.
	 *
	 * @param string $context View or edit context.
	 *
	 * @return StoreEngineDateTime|NULL object if the date is set or null if there is no date.
	 *
	 * @deprecated
	 * @see self::get_date_created_gmt()
	 */
	public function get_date_created( string $context = 'view' ): ?StoreEngineDateTime {
		return $this->get_date_created_gmt( $context );
	}

	/**
	 * Get date_modified.
	 *
	 * @param string $context View or edit context.
	 *
	 * @return StoreEngineDateTime|NULL object if the date is set or null if there is no date.
	 */
	public function get_date_modified( string $context = 'view' ): ?StoreEngineDateTime {
		return $this->get_date_updated_gmt( $context );
	}

	/**
	 * Get date paid.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return StoreengineDatetime|NULL object if the date is set or null if there is no date.
	 *
	 * @deprecated
	 */
	public function get_date_paid( string $context = 'view' ): ?StoreEngineDateTime {
		return $this->get_date_paid_gmt( $context );
	}

	/**
	 * Placeholder for reminding devs to use the _gmt version.
	 *
	 * @param string $context View or edit context.
	 *
	 * @return StoreEngineDateTime|NULL object if the date is set or null if there is no date.
	 * @deprecated
	 * @see set_date_completed_gmt()
	 */
	public function get_date_completed( string $context = 'view' ): ?StoreEngineDateTime {
		return $this->get_date_completed_gmt( $context );
	}

	/**
	 * Return the order statuses without wc- internal prefix.
	 *
	 * @param string $context View or edit context.
	 *
	 * @return string
	 */
	public function get_status( string $context = 'view' ): ?string {
		$status = $this->get_prop( 'status', $context );

		if ( empty( $status ) && 'view' === $context ) {
			/**
			 * In view context, return the default status if no status has been set.
			 *
			 * @param string $status Default status.
			 */
			$status = apply_filters( 'storeengine/default_order_status', OrderStatus::PAYMENT_PENDING );
		}

		return $status;
	}

	/**
	 * Get discount_total.
	 *
	 * @param string $context View or edit context.
	 *
	 * @return string|float
	 */
	public function get_discount_total( string $context = 'view' ) {
		return $this->get_prop( 'discount_total_amount', $context );
	}

	/**
	 * Get discount_tax.
	 *
	 * @param string $context View or edit context.
	 *
	 * @return string|float
	 */
	public function get_discount_tax( string $context = 'view' ) {
		return $this->get_prop( 'discount_tax_amount', $context );
	}

	/**
	 * Get shipping_total.
	 *
	 * @param string $context View or edit context.
	 *
	 * @return string|float
	 */
	public function get_shipping_total( string $context = 'view' ) {
		return $this->get_prop( 'shipping_total_amount', $context );
	}

	/**
	 * Get shipping_tax.
	 *
	 * @param string $context View or edit context.
	 *
	 * @return string|float
	 */
	public function get_shipping_tax( string $context = 'view' ) {
		return $this->get_prop( 'shipping_tax_amount', $context );
	}

	/**
	 * Gets cart tax amount.
	 *
	 * @param string $context View or edit context.
	 *
	 * @return string|float
	 */
	public function get_cart_tax( string $context = 'view' ) {
		return $this->get_prop( 'cart_tax', $context );
	}

	/**
	 * Gets order grand total including taxes, shipping cost, fees, and coupon discounts. Used in gateways.
	 *
	 * @param string $context View or edit context.
	 *
	 * @return string|float
	 */
	public function get_total( string $context = 'view' ) {
		return $this->get_prop( 'total', $context );
	}

	/**
	 * Get total tax amount. Alias for get_order_tax().
	 *
	 * @param string $context View or edit context.
	 *
	 * @return string|float
	 */
	public function get_total_tax( string $context = 'view' ) {
		return $this->get_prop( 'total_tax', $context );
	}

	/*
	|--------------------------------------------------------------------------
	| Non-CRUD Getters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Gets the total discount amount.
	 *
	 * @param bool $ex_tax Show discount excl any tax.
	 *
	 * @return float
	 */
	public function get_total_discount( bool $ex_tax = true ): float {
		if ( $ex_tax ) {
			$total_discount = (float) $this->get_discount_total();
		} else {
			$total_discount = (float) $this->get_discount_total() + (float) $this->get_discount_tax();
		}

		return apply_filters( 'storeengine/order_get_total_discount', NumberUtil::round( $total_discount, Formatting::get_rounding_precision() ), $this );
	}

	/**
	 * Gets order subtotal. Order subtotal is the price of all items excluding taxes, fees, shipping cost, and coupon discounts.
	 * If sale price is set on an item, the subtotal will include this sale discount. E.g. a product with a regular
	 * price of $100 bought at a 50% discount will represent $50 of the subtotal for the order.
	 *
	 * @return float
	 */
	public function get_subtotal(): float {
		$subtotal = NumberUtil::round( $this->get_cart_subtotal_for_order(), Formatting::get_price_decimals() );

		return apply_filters( 'storeengine/order_get_subtotal', $subtotal, $this );
	}

	/**
	 * Get taxes, merged by code, formatted ready for output.
	 *
	 * @return array
	 */
	public function get_tax_totals(): array {
		$tax_totals = [];

		foreach ( $this->get_line_tax_items() as $key => $tax ) {
			$code = $tax->get_rate_code();

			if ( ! isset( $tax_totals[ $code ] ) ) {
				$tax_totals[ $code ]         = new stdClass();
				$tax_totals[ $code ]->amount = 0;
			}


			$tax_totals[ $code ]->id          = $key;
			$tax_totals[ $code ]->code        = $code;
			$tax_totals[ $code ]->rate_id     = $tax->get_rate_id();
			$tax_totals[ $code ]->is_compound = $tax->is_compound();
			$tax_totals[ $code ]->label       = $tax->get_label();
			$tax_totals[ $code ]->amount     += (float) $tax->get_tax_total() + (float) $tax->get_shipping_tax_total();
			// Add formated amount.
			$tax_totals[ $code ]->formatted_amount = Formatting::price( $tax_totals[ $code ]->amount, [ 'currency' => $this->get_currency() ] );
		}

		if ( apply_filters( 'storeengine/order_hide_zero_taxes', true ) ) {
			$amounts    = array_filter( wp_list_pluck( $tax_totals, 'amount' ) );
			$tax_totals = array_intersect_key( $tax_totals, $amounts );
		}

		return apply_filters( 'storeengine/order_get_tax_totals', $tax_totals, $this );
	}

	/**
	 * Get all valid statuses for this order
	 *
	 * @return array Internal status keys e.g. 'processing'
	 */
	protected function get_valid_statuses(): array {
		return array_keys( OrderStatus::get_order_statuses() );
	}

	/**
	 * Alias for get_customer_id().
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return int
	 */
	public function get_user_id( string $context = 'view' ): int {
		return $this->get_customer_id( $context );
	}

	/**
	 * Get the user associated with the order. False for guests.
	 *
	 * @return WP_User|false
	 */
	public function get_user() {
		return $this->get_user_id() ? get_user_by( 'id', $this->get_user_id() ) : false;
	}

	/**
	 * Gets information about whether coupon counts were updated.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return bool True if coupon counts were updated, false otherwise.
	 */
	public function get_recorded_coupon_usage_counts( string $context = 'view' ): bool {
		return Formatting::string_to_bool( $this->get_prop( 'recorded_coupon_usage_counts', $context ) );
	}

	/**
	 * Get basic order data in array format.
	 *
	 * @return array
	 */
	public function get_base_data(): array {
		return array_merge(
			[ 'id' => $this->get_id() ],
			$this->data
		);
	}

	/**
	 * Get info about the card used for payment in the order.
	 *
	 * @return array
	 */
	public function get_payment_card_info(): array {
		return PaymentInfo::get_card_info( $this );
	}

	/*
	|--------------------------------------------------------------------------
	| Setters
	|--------------------------------------------------------------------------
	|
	| Functions for setting order data. These should not update anything in the
	| database itself and should only change what is stored in the class
	| object. However, for backwards compatibility pre 3.0.0 some of these
	| setters may handle both.
	*/

	/**
	 * Set parent order ID.
	 *
	 * @param int|string $value Value to set.
	 *
	 * @throws StoreEngineException Exception thrown if parent ID does not exist or is invalid.
	 */
	public function set_parent_order_id( $value ) {
		$value = absint( $value );
		if ( $value && $value === $this->get_id() ) {
			$this->error( 'order_invalid_parent_order_id', __( 'Invalid parent ID', 'storeengine' ) );
		}
		$this->set_prop( 'parent_order_id', $value );
	}

	/**
	 * Set parent order ID.
	 *
	 * @param int|string $value Value to set.
	 */
	public function set_operational_id( $value ) {
		$this->set_prop( 'operational_id', absint( $value ) );
	}

	/**
	 * Set order status.
	 *
	 * @param string $new_status Status to change the order to. No internal wc- prefix is required.
	 *
	 * @return array details of change
	 */
	public function set_status( string $new_status ): array {
		$old_status = $this->get_status();

		if ( $new_status === $old_status ) {
			return [
				'from' => $old_status,
				'to'   => $new_status,
			];
		}

		$status_exceptions = [ OrderStatus::AUTO_DRAFT, OrderStatus::TRASH ];

		// If setting the status, ensure it's set to a valid status.
		if ( true === $this->object_read ) {
			// Only allow valid new status.
			if (
				! in_array( $new_status, $this->get_valid_statuses(), true ) &&
				! in_array( $new_status, $status_exceptions, true )
			) {
				$new_status = OrderStatus::DRAFT;
			}

			// If the old status is set but unknown (e.g. draft) assume it's pending for action usage.
			if (
				$old_status &&
				(
					OrderStatus::AUTO_DRAFT === $old_status ||
					(
						! in_array( $old_status, $this->get_valid_statuses(), true ) &&
						! in_array( $old_status, $status_exceptions, true )
					)
				)
			) {
				$old_status = OrderStatus::DRAFT;
			}
		}

		$this->set_prop( 'status', $new_status );

		return [
			'from' => $old_status,
			'to'   => $new_status,
		];
	}

	/**
	 * Set order_version.
	 *
	 * @param string $value Value to set.
	 */
	public function set_version( string $value ) {
		$this->set_prop( 'version', $value );
	}

	/**
	 * Set order_currency.
	 *
	 * @param ?string $value Value to set.
	 *
	 * @throws StoreEngineException Exception may be thrown if value is invalid.
	 */
	public function set_currency( ?string $value = null ) {
		if ( $value && ! in_array( $value, array_keys( Helper::get_currencies() ), true ) ) {
			$this->error( 'order_invalid_currency', __( 'Invalid currency code', 'storeengine' ) );
		}

		$this->set_prop( 'currency', $value ?: Formatting::get_currency() );
	}

	/**
	 * Set prices_include_tax.
	 *
	 * @param bool|string $value Value to set.
	 */
	public function set_prices_include_tax( $value ) {
		$this->set_prop( 'prices_include_tax', Formatting::string_to_bool( $value ) );
	}

	/**
	 * Set date_created.
	 *
	 * @param string|integer|null $date UTC timestamp, or ISO 8601 DateTime. If the DateTime string has no timezone or offset, WordPress site timezone will be assumed. Null if there is no date.
	 */
	public function set_date_created( $date = null ) {
		$this->set_date_created_gmt( $date );
	}

	/**
	 * Set date_modified.
	 *
	 * @param string|integer|null $date UTC timestamp, or ISO 8601 DateTime. If the DateTime string has no timezone or offset, WordPress site timezone will be assumed. Null if there is no date.
	 */
	public function set_date_modified( $date = null ) {
		$this->set_date_updated_gmt( $date );
	}

	/**
	 * Set discount_total.
	 *
	 * @param string|float $value Value to set.
	 */
	public function set_discount_total( $value ) {
		$this->set_prop( 'discount_total_amount', Formatting::format_decimal( $value, false, true ) );
	}

	/**
	 * Set discount_tax.
	 *
	 * @param string|float $value Value to set.
	 */
	public function set_discount_tax( $value ) {
		$this->set_prop( 'discount_tax_amount', Formatting::format_decimal( $value, false, true ) );
	}

	/**
	 * Set shipping_total.
	 *
	 * @param string|float $value Value to set.
	 */
	public function set_shipping_total( $value ) {
		$this->set_prop( 'shipping_total_amount', Formatting::format_decimal( $value, false, true ) );
	}

	/**
	 * Set shipping_tax.
	 *
	 * @param string|float $value Value to set.
	 */
	public function set_shipping_tax( $value ) {
		$this->set_prop( 'shipping_tax_amount', Formatting::format_decimal( $value, false, true ) );
		$this->set_total_tax( (float) $this->get_cart_tax() + (float) $this->get_shipping_tax() );
	}

	/**
	 * Set cart tax.
	 *
	 * @param string|float $value Value to set.
	 */
	public function set_cart_tax( $value ) {
		$this->set_prop( 'cart_tax', Formatting::format_decimal( $value, false, true ) );
		$this->set_total_tax( (float) $this->get_cart_tax() + (float) $this->get_shipping_tax() );
	}

	/**
	 * Sets order tax (sum of cart and shipping tax). Used internally only.
	 *
	 * @param string|float $value Value to set.
	 */
	protected function set_total_tax( $value ) {
		// We round here because this is a total entry, as opposed to line items in other setters.
		$this->set_prop( 'total_tax', Formatting::format_decimal( NumberUtil::round( $value, Formatting::get_price_decimals() ) ) );
	}

	/**
	 * Set total.
	 *
	 * @param string|float|int $value Value to set.
	 */
	public function set_total( $value ) {
		$this->set_prop( 'total', Formatting::format_decimal( $value, Formatting::get_price_decimals() ) );
	}


	/**
	 * Stores information about whether the coupon usage were counted.
	 *
	 * @param bool|string $value True if counted, false if not.
	 *
	 * @return void
	 */
	public function set_recorded_coupon_usage_counts( $value ) {
		$this->set_prop( 'recorded_coupon_usage_counts', Formatting::string_to_bool( $value ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Order Item Handling
	|--------------------------------------------------------------------------
	|
	| Order items are used for products, taxes, shipping, and fees within
	| each order.
	*/

	/**
	 * Remove all line items (products, coupons, shipping, taxes) from the order.
	 *
	 * @param ?string $type Order item type. Default null.
	 */
	public function remove_order_items( ?string $type = null ) {

		/**
		 * Trigger action before removing all order line items. Allows you to track order items.
		 *
		 * @param Order $this The current order object.
		 * @param string $type Order item type. Default null.
		 */
		do_action( 'storeengine/order/remove_order_items', $this, $type );
		if ( ! empty( $type ) ) {
			$this->delete_items( $type );

			$group = $this->type_to_group( $type );

			if ( $group ) {
				unset( $this->items[ $group ] );
			}
		} else {
			$this->delete_items();
			$this->items = [];
		}
		/**
		 * Trigger action after removing all order line items.
		 *
		 * @param Order $this The current order object.
		 * @param string $type Order item type. Default null.
		 */
		do_action( 'storeengine/order/removed_order_items', $this, $type );
	}

	/**
	 * Remove all line items (products, coupons, shipping, taxes) from the order.
	 *
	 * @param ?string $type Order item type. Default null.
	 */
	public function delete_items( ?string $type = null ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- deleting items, no need caching.
		if ( ! empty( $type ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE itemmeta FROM {$wpdb->prefix}storeengine_order_item_meta as itemmeta INNER JOIN {$wpdb->prefix}woocommerce_order_items as items WHERE itemmeta.order_item_id = items.order_item_id AND items.order_id = %d AND items.order_item_type = %s", $this->get_id(), $type ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}storeengine_order_items WHERE order_id = %d AND order_item_type = %s", $this->get_id(), $type ) );
		} else {
			$wpdb->query( $wpdb->prepare( "DELETE itemmeta FROM {$wpdb->prefix}storeengine_order_item_meta as itemmeta INNER JOIN {$wpdb->prefix}woocommerce_order_items as items WHERE itemmeta.order_item_id = items.order_item_id and items.order_id = %d", $this->get_id() ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}storeengine_order_items WHERE order_id = %d", $this->get_id() ) );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- deleting items, no need caching.

		$this->clear_caches();
	}

	/**
	 * Convert a type to a types group.
	 *
	 * @param string $type type to lookup.
	 *
	 * @return string
	 */
	protected function type_to_group( string $type ): ?string {
		$type_to_group = apply_filters( 'storeengine/order_type_to_group', $this->item_types_to_group );

		return $type_to_group[ $type ] ?? '';
	}

	/**
	 * Return an array of items/products within this order.
	 *
	 * @param string|string[] $types Types of line items to get (array or string).
	 * @param bool $force Force read from db.
	 *
	 * @return AbstractOrderItem[]
	 */
	public function get_items( $types = 'line_item', bool $force = false ): array {
		$items = [];
		$types = array_filter( (array) $types );


		foreach ( $types as $type ) {
			$group = $this->type_to_group( $type );

			if ( $group ) {
				if ( $force ) {
					$this->items[ $group ] = null;
					unset( $this->items[ $group ] );
				}

				if ( ! isset( $this->items[ $group ] ) ) {
					$this->items[ $group ] = array_filter( $this->read_items( $type ) );
				}

				// Don't use array_merge here because keys are numeric.
				$items = $items + $this->items[ $group ];
			}
		}

		return apply_filters( 'storeengine/order_get_items', $items, $this, $types );
	}

	/**
	 * @return OrderItemProduct[]
	 */
	public function get_line_product_items(): array {
		return $this->get_items( 'line_item' );
	}

	/**
	 * @return OrderItemTax[]
	 */
	public function get_line_tax_items(): array {
		return $this->get_items( 'tax' );
	}

	/**
	 * @return OrderItemShipping[]
	 */
	public function get_line_shipping_items(): array {
		return $this->get_items( 'shipping' );
	}

	/**
	 * @return OrderItemFee[]
	 */
	public function get_line_fee_items(): array {
		return $this->get_items( 'fee' );
	}

	/**
	 * @return OrderItemCoupon[]
	 */
	public function get_line_coupon_items(): array {
		return $this->get_items( 'coupon' );
	}

	/**
	 * Return array of values for calculations.
	 *
	 * @param string $field Field name to return.
	 *
	 * @return array Array of values.
	 */
	protected function get_values_for_total( string $field ): array {
		return array_map(
			function ( $item ) use ( $field ) {
				$getter = 'get_' . $field;
				if ( method_exists( $item, $getter ) ) {
					return Formatting::add_number_precision( $item->{$getter}(), false );
				}

				return 0;
			},
			array_values( $this->get_items() )
		);
	}

	/**
	 * Return an array of coupons within this order.
	 *
	 * @return ?OrderItemCoupon[]
	 */
	public function get_coupons(): array {
		return $this->get_line_coupon_items();
	}

	/**
	 * Return an array of fees within this order.
	 *
	 * @return ?OrderItemFee[]
	 */
	public function get_fees(): array {
		return $this->get_line_fee_items();
	}

	/**
	 * Return an array of taxes within this order.
	 *
	 * @return OrderItemTax[]
	 */
	public function get_taxes(): array {
		return $this->get_line_tax_items();
	}

	/**
	 * Return an array of shipping costs within this order.
	 *
	 * @return OrderItemShipping[]
	 */
	public function get_shipping_methods(): array {
		return $this->get_line_shipping_items();
	}

	/**
	 * Gets formatted shipping method title.
	 *
	 * @return string
	 */
	public function get_shipping_method(): ?string {
		$names = [];
		foreach ( $this->get_shipping_methods() as $shipping_method ) {
			$names[] = $shipping_method->get_name();
		}

		return apply_filters( 'storeengine/order/shipping_method', implode( ', ', $names ), $this );
	}

	/**
	 * Get used coupon codes only.
	 *
	 * @return array
	 */
	public function get_coupon_codes(): array {
		$coupon_codes = [];
		$coupons      = $this->get_line_coupon_items();

		if ( $coupons ) {
			foreach ( $coupons as $coupon ) {
				$coupon_codes[] = $coupon->get_code();
			}
		}

		return $coupon_codes;
	}

	/**
	 * Gets the count of order items of a certain type.
	 *
	 * @param string $item_type Item type to lookup.
	 *
	 * @return int|string
	 */
	public function get_item_count( string $item_type = '' ) {
		$items = $this->get_items( empty( $item_type ) ? 'line_item' : $item_type );
		$count = 0;

		foreach ( $items as $item ) {
			$count += $item->get_quantity();
		}

		return apply_filters( 'storeengine/get_item_count', $count, $item_type, $this );
	}

	/**
	 * Get an order item object, based on its type.
	 *
	 * @param int|string $item_id ID of item to get.
	 * @param bool $load_from_db Prior to 3.2 this item was loaded direct from WC_Order_Factory, not this object. This param is here for backwards compatibility with that. If false, uses the local items variable instead.
	 *
	 * @return AbstractOrderItem|OrderItemProduct|OrderItemCoupon|OrderItemShipping|OrderItemTax|OrderItemFee|false
	 */
	public function get_item( int $item_id, bool $load_from_db = true ) {
		if ( $load_from_db ) {
			return self::get_order_item( $item_id );
		}

		// Search for item id.
		if ( $this->items ) {
			foreach ( $this->items as $items ) {
				if ( isset( $items[ $item_id ] ) ) {
					return $items[ $item_id ];
				}
			}
		}

		// Load all items of type and cache.
		$type = AbstractOrderItem::get_order_item_type( $item_id );

		if ( ! $type ) {
			return false;
		}

		$items = $this->get_items( $type );

		return ! empty( $items[ $item_id ] ) ? $items[ $item_id ] : false;
	}

	/**
	 * Get key for where a certain item type is stored in _items.
	 *
	 * @param string|AbstractOrderItem $item object Order item (product, shipping, fee, coupon, tax).
	 *
	 * @return string
	 */
	protected function get_items_key( $item ): ?string {
		if ( is_a( $item, OrderItemProduct::class ) ) {
			return 'line_items';
		} elseif ( is_a( $item, OrderItemFee::class ) ) {
			return 'fee_lines';
		} elseif ( is_a( $item, OrderItemShipping::class ) ) {
			return 'shipping_lines';
		} elseif ( is_a( $item, OrderItemTax::class ) ) {
			return 'tax_lines';
		} elseif ( is_a( $item, OrderItemCoupon::class ) ) {
			return 'coupon_lines';
		}

		return apply_filters( 'storeengine/get_items_key', '', $item );
	}

	/**
	 * Remove item from the order.
	 *
	 * @param int|string $item_id Item ID to delete.
	 *
	 * @return false|void
	 */
	public function remove_item( $item_id ) {
		$item      = $this->get_item( absint( $item_id ), false );
		$items_key = $item ? $this->get_items_key( $item ) : false;


		if ( ! $items_key ) {
			return false;
		}

		// Unset and remove later.
		$this->items_to_delete[] = $item;
		unset( $this->items[ $items_key ][ $item->get_id() ] );
	}

	/**
	 * Adds an order item to this order. The order item will not persist until save.
	 *
	 * @param AbstractOrderItem $item Order item object (product, shipping, fee, coupon, tax).
	 *
	 * @return false|void
	 */
	public function add_item( AbstractOrderItem $item ) {
		$items_key = $this->get_items_key( $item );

		if ( ! $items_key ) {
			return false;
		}

		// Make sure existing items are loaded so we can append this new one.
		if ( ! isset( $this->items[ $items_key ] ) ) {
			$this->items[ $items_key ] = $this->get_items( $item->get_type() );
		}

		// Set parent.
		$item->set_order_id( $this->get_id() );

		// Append new row with generated temporary ID.
		$item_id = $item->get_id();

		if ( $item_id ) {
			$this->items[ $items_key ][ $item_id ] = $item;
		} else {
			$this->items[ $items_key ][ 'new:' . $items_key . count( $this->items[ $items_key ] ) ] = $item;
		}
	}

	/**
	 * Check and records coupon usage tentatively so that counts validation is correct. Display an error if coupon usage limit has been reached.
	 *
	 * If you are using this method, make sure to `release_held_coupons` in case an Exception is thrown.
	 *
	 * @param string $billing_email Billing email of order.
	 *
	 * @throws Exception When not able to apply coupon.
	 */
	public function hold_applied_coupons( string $billing_email ) {
		$held_keys          = [];
		$held_keys_for_user = [];
		$error              = null;

		try {
			foreach ( Helper::cart()->get_coupons() as $coupon ) {
				// Hold coupon for when global coupon usage limit is present.
				if ( 0 < $coupon->get_usage_limit() ) {
					$held_key = $this->hold_coupon( $coupon );
					if ( $held_key ) {
						$held_keys[ $coupon->get_id() ] = $held_key;
					}
				}

				// Hold coupon for when usage limit per customer is enabled.
				if ( 0 < $coupon->get_usage_limit_per_user() ) {
					$user_alias = '';
					if ( ! isset( $user_ids_and_emails ) ) {
						$user_alias          = get_current_user_id() ? wp_get_current_user()->ID : sanitize_email( $billing_email );
						$user_ids_and_emails = $this->get_billing_and_current_user_aliases( $billing_email );
					}

					$held_key_for_user = $this->hold_coupon_for_users( $coupon, $user_ids_and_emails, $user_alias );

					if ( $held_key_for_user ) {
						$held_keys_for_user[ $coupon->get_id() ] = $held_key_for_user;
					}
				}
			}
		} catch ( Exception $e ) {
			$error = $e;
		} finally {
			// Even in case of error, we will save keys for whatever coupons that were held so our data remains accurate.
			// We save them in bulk instead of one by one for performance reasons.
			if ( 0 < count( $held_keys_for_user ) || 0 < count( $held_keys ) ) {
				$this->set_coupon_held_keys( $held_keys, $held_keys_for_user );
			}
			if ( $error instanceof Exception ) {
				throw $error;
			}
		}
	}

	/**
	 * Add/Update list of meta keys that are currently being used by this order to hold a coupon.
	 * This is used to figure out what all meta entries we should delete when order is cancelled/completed.
	 *
	 * @param array    $held_keys          Array of coupon_code => meta_key.
	 * @param array    $held_keys_for_user Array of coupon_code => meta_key for held coupon for user.
	 *
	 * @return void
	 */
	public function set_coupon_held_keys( $held_keys, $held_keys_for_user ) {
		if ( is_array( $held_keys ) && 0 < count( $held_keys ) ) {
			$this->update_meta_data( '_coupon_held_keys', $held_keys );
		}
		if ( is_array( $held_keys_for_user ) && 0 < count( $held_keys_for_user ) ) {
			$this->update_meta_data( '_coupon_held_keys_for_users', $held_keys_for_user );
		}
	}

	/**
	 * Return array of coupon_code => meta_key for coupon which have usage limit and have tentative keys.
	 * Pass $coupon_id if key for only one of the coupon is needed.
	 *
	 * @param int|string $coupon_id If passed, will return held key for that coupon.
	 *
	 * @return array|string Key value pair for coupon code and meta key name. If $coupon_id is passed, returns meta_key for only that coupon.
	 */
	public function get_coupon_held_keys( $coupon_id = null ) {
		$held_keys = $this->get_meta( '_coupon_held_keys' );
		if ( $coupon_id ) {
			return $held_keys[ $coupon_id ] ?? null;
		}
		return $held_keys;
	}

	/**
	 * Return array of coupon_code => meta_key for coupon which have usage limit per customer and have tentative keys.
	 *
	 * @param int|string $coupon_id If passed, will return held key for that coupon.
	 *
	 * @return mixed
	 */
	public function get_coupon_held_keys_for_users( $coupon_id = null ) {
		$held_keys_for_user = $this->get_meta( '_coupon_held_keys_for_users' );
		if ( $coupon_id ) {
			return $held_keys_for_user[ $coupon_id ] ?? null;
		}
		return $held_keys_for_user;
	}

	/**
	 * Release all coupons held by this order.
	 *
	 * @param bool $save  Whether to delete keys from DB right away. Could be useful to pass `false` if you are building a bulk request.
	 */
	public function release_held_coupons( $save = true ) {
		$coupon_held_keys = $this->get_coupon_held_keys();
		if ( is_array( $coupon_held_keys ) ) {
			foreach ( $coupon_held_keys as $coupon_id => $meta_key ) {
				delete_post_meta( $coupon_id, $meta_key );
			}
		}
		$this->delete_meta_data( '_coupon_held_keys' );

		$coupon_held_keys_for_users = $this->get_coupon_held_keys_for_users();
		if ( is_array( $coupon_held_keys_for_users ) ) {
			foreach ( $coupon_held_keys_for_users as $coupon_id => $meta_key ) {
				delete_post_meta( $coupon_id, $meta_key );
			}
		}
		$this->delete_meta_data( '_coupon_held_keys_for_users' );

		if ( $save ) {
			$this->save_meta_data();
		}
	}

	/**
	 * Hold coupon if a global usage limit is defined.
	 *
	 * @param Coupon $coupon Coupon object.
	 *
	 * @return string    Meta key which indicates held coupon.
	 * @throws Exception When can't be held.
	 */
	private function hold_coupon( Coupon $coupon ): ?string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		return '';
	}

	/**
	 * Hold coupon if usage limit per customer is defined.
	 *
	 * @param Coupon $coupon Coupon object.
	 * @param array $user_ids_and_emails Array of user Id and emails to check for usage limit.
	 * @param string $user_alias User ID or email to use to record current usage.
	 *
	 * @return string    Meta key which indicates held coupon.
	 * @throws Exception When coupon can't be held.
	 */
	private function hold_coupon_for_users( Coupon $coupon, array $user_ids_and_emails, string $user_alias ): ?string {
		return '';
	}

	/**
	 * Helper method to get all aliases for current user and provide billing email.
	 *
	 * @param string $billing_email Billing email provided in form.
	 *
	 * @return array     Array of all aliases.
	 */
	private function get_billing_and_current_user_aliases( string $billing_email ): array {
		$emails = [ $billing_email ];
		if ( get_current_user_id() ) {
			$emails[] = wp_get_current_user()->user_email;
		}

		$emails   = array_unique( array_map( fn( $email ) => strtolower( sanitize_email( $email ) ), $emails ) );
		$user_ids = Helper::get_user_ids_for_billing_email( $emails );

		return array_merge( $user_ids, $emails );
	}

	/**
	 * Apply a coupon to the order and recalculate totals.
	 *
	 * @param string|Coupon $raw_coupon Coupon code or object.
	 *
	 * @return true|WP_Error True if applied, error if not.
	 */
	public function apply_coupon( $raw_coupon ) {
		if ( is_a( $raw_coupon, Coupon::class ) ) {
			$coupon = $raw_coupon;
		} elseif ( is_string( $raw_coupon ) ) {
			$code   = Formatting::format_coupon_code( $raw_coupon );
			$coupon = new Coupon( $code );

			if ( strtolower($coupon->get_code()) !== $code ) {
				return new WP_Error( 'invalid_coupon', __( 'Invalid coupon code', 'storeengine' ) );
			}
		} else {
			return new WP_Error( 'invalid_coupon', __( 'Invalid coupon', 'storeengine' ) );
		}

		// Check to make sure coupon is not already applied.
		$applied_coupons = $this->get_line_coupon_items();
		foreach ( $applied_coupons as $applied_coupon ) {
			if ( strtolower($applied_coupon->get_code()) === strtolower($coupon->get_code()) ) {
				return new WP_Error( 'duplicate_coupon', __( 'Coupon code already applied!', 'storeengine' ) );
			}
		}

		$discounts = new Discounts( $this );
		$applied   = $discounts->apply_coupon( $coupon );

		if ( is_wp_error( $applied ) ) {
			return $applied;
		}

		/**
		 * Check specific for guest checkouts here as well since WC_Cart handles that separately in check_customer_coupons.
		 *
		 * @see \WC_Coupon
		 * @see \WC_Abstract_Order
		 *
		 * @todo implement get_usage_by_email & get_usage_limit_per_user and handle
		 *       validation for "Coupon usage limit has been reached." error.
		 */

		/**
		 * Action to signal that a coupon has been applied to an order.
		 *
		 * @param Coupon $coupon The applied coupon object.
		 * @param Order $this The current order object.
		 */
		do_action( 'storeengine/order/applied_coupon', $coupon, $this );

		$this->set_coupon_discount_amounts( $discounts );
		$this->save();

		// Recalculate totals and taxes.
		$this->recalculate_coupons();

		// @TODO update usage count. Record usage so counts and validation is correct.

		return true;
	}

	/**
	 * Remove a coupon from the order and recalculate totals.
	 *
	 * Coupons affect line item totals, but there is no relationship between
	 * coupon and line total, so to remove a coupon we need to work from the
	 * line subtotal (price before discount) and re-apply all coupons in this
	 * order.
	 *
	 * Manual discounts are not affected; those are separate and do not affect
	 * stored line totals.
	 *
	 * @param  ?string $code Coupon code.
	 *
	 * @return bool TRUE if coupon was removed, FALSE otherwise.
	 */
	public function remove_coupon( ?string $code ): bool {
		$coupons = $this->get_line_coupon_items();
		$code    = Formatting::format_coupon_code( $code );

		// Remove the coupon line.
		foreach ( $coupons as $item_id => $coupon ) {
			if ( $coupon->get_code() === $code ) {
				$this->remove_item( $item_id );
				// @TODO decrease coupon usage count if increased.
				$this->recalculate_coupons();

				return true;
			}
		}

		return false;
	}

	/**
	 * Apply all coupons in this order again to all line items.
	 * This method is public since WooCommerce 3.8.0.
	 */
	public function recalculate_coupons() {
		// Reset line item totals.
		foreach ( $this->get_line_product_items() as $item ) {
			$item->set_total( $item->get_subtotal() );
			$item->set_total_tax( $item->get_subtotal_tax() );
		}

		$discounts = new Discounts( $this );

		foreach ( $this->get_line_coupon_items() as $coupon_item ) {
			$coupon_code = $coupon_item->get_code();
			$coupon_id   = Coupon::get_by_code( $coupon_code );

			// If we have a coupon ID (loaded via wc_get_coupon_id_by_code) we can simply load the new coupon object using the ID.
			if ( $coupon_id ) {
				$coupon_object = new Coupon( $coupon_id );
			} else {
				// If we do not have a coupon ID (was it virtual? has it been deleted?) we must create a temporary coupon using what data we have stored during checkout.
				$coupon_object = $this->get_temporary_coupon( $coupon_item );

				// If there is no coupon amount (maybe dynamic?), set it to the given **discount** amount so the coupon's same value is applied.
				if ( ! $coupon_object->get_amount() ) {

					// If the order originally had prices including tax, remove the discount + discount tax.
					if ( $this->get_prices_include_tax() ) {
						$coupon_object->settings['coupon_amount'] = (float) $coupon_item->get_discount() + (float) $coupon_item->get_discount_tax();
					} else {
						$coupon_object->settings['coupon_amount'] = (float) $coupon_item->get_discount();
					}

					$coupon_object->settings['coupon_type'] = 'fixedAmount';
				}
			}

			/**
			 * Allow developers to filter this coupon before it gets re-applied to the order.
			 */
			$coupon_object = apply_filters( 'storeengine/order_recalculate_coupons_coupon_object', $coupon_object, $coupon_code, $coupon_item, $this );

			if ( $coupon_object ) {
				$discounts->apply_coupon( $coupon_object, false );
			}
		}

		$this->set_coupon_discount_amounts( $discounts );
		$this->set_item_discount_amounts( $discounts );

		// Recalculate totals and taxes.
		$this->calculate_totals( true );
	}

	/**
	 * Get a coupon object populated from order line item metadata, to be used when reapplying coupons
	 * if the original coupon no longer exists.
	 *
	 * @param OrderItemCoupon $coupon_item The order item corresponding to the coupon to reapply.
	 *
	 * @returns Coupon Coupon object populated from order line item metadata, or empty if no such metadata exists (should never happen).
	 */
	private function get_temporary_coupon( OrderItemCoupon $coupon_item ): Coupon {
		$coupon_object = new Coupon();

		// @TODO check coupon info & set coupon info.

		$coupon_settings = $coupon_item->get_meta( 'coupon_settings' );
		if ( $coupon_settings ) {
			$coupon_object->settings = $coupon_settings;
		}

		return $coupon_object;
	}

	/**
	 * After applying coupons via the WC_Discounts class, update line items.
	 *
	 * @param Discounts $discounts Discounts class.
	 */
	protected function set_item_discount_amounts( Discounts $discounts ) {
		$item_discounts = $discounts->get_discounts_by_item();
		$tax_location   = $this->get_tax_location();
		$tax_location   = array(
			$tax_location['country'],
			$tax_location['state'],
			$tax_location['postcode'],
			$tax_location['city'],
		);

		if ( $item_discounts ) {
			foreach ( $item_discounts as $item_id => $amount ) {
				$item = $this->get_item( $item_id, false );

				// If the prices include tax, discounts should be taken off the tax inclusive prices like in the cart.
				if ( $this->get_prices_include_tax() && TaxUtil::is_tax_enabled() && ProductTaxStatus::TAXABLE === $item->get_tax_status() ) {
					$taxes = Tax::calc_tax( $amount, $this->get_tax_rates( $item->get_tax_class(), $tax_location ), true );

					// Use unrounded taxes so totals will be re-calculated accurately, like in cart.
					$amount = $amount - array_sum( $taxes );
				}

				$item->set_total( max( 0, (float) $item->get_total() - $amount ) );
			}
		}
	}

	/**
	 * After applying coupons via the WC_Discounts class, update or create coupon items.
	 *
	 * @param Discounts $discounts Discounts class.
	 */
	protected function set_coupon_discount_amounts( Discounts $discounts ) {
		$coupons           = $this->get_line_coupon_items();
		$coupon_code_to_id = Helper::list_pluck( $coupons, 'get_id', 'get_code' );
		$all_discounts     = $discounts->get_discounts();
		$coupon_discounts  = $discounts->get_discounts_by_coupon();
		$tax_location      = $this->get_tax_location();
		$tax_location      = [
			$tax_location['country'],
			$tax_location['state'],
			$tax_location['postcode'],
			$tax_location['city'],
		];

		if ( $coupon_discounts ) {
			foreach ( $coupon_discounts as $coupon_code => $amount ) {
				$item_id = $coupon_code_to_id[ $coupon_code ] ?? 0;


				if ( ! $item_id ) {
					$coupon_item = new OrderItemCoupon();
					$coupon_item->set_code( $coupon_code );

					// Add coupon data.
					$coupon_id = Coupon::get_by_code( $coupon_code );
					$coupon    = new Coupon( (string) $coupon_id );

					// @TODO check coupon_info and get_short_info
					$coupon_item->add_meta_data( 'coupon_settings', $coupon->get_settings() );
				} else {
					$coupon_item = $this->get_item( $item_id, false );
				}

				$discount_tax = 0;

				// Work out how much tax has been removed as a result of the discount from this coupon.
				foreach ( $all_discounts[ $coupon_code ] as $item_id => $item_discount_amount ) {
					$item = $this->get_item( $item_id, false );

					if ( ! $item || ProductTaxStatus::TAXABLE !== $item->get_tax_status() || ! TaxUtil::is_tax_enabled() ) {
						continue;
					}

					$taxes = array_sum( Tax::calc_tax( $item_discount_amount, $this->get_tax_rates( $item->get_tax_class(), $tax_location ), $this->get_prices_include_tax() ) );
					if ( ! TaxUtil::tax_round_at_subtotal() ) {
						$taxes = Formatting::round_tax_total( $taxes );
					}

					$discount_tax += $taxes;

					if ( $this->get_prices_include_tax() ) {
						$amount = $amount - $taxes;
					}
				}

				$coupon_item->set_discount( $amount );
				$coupon_item->set_discount_tax( $discount_tax );

				$this->add_item( $coupon_item );
			}
		}
	}

	/**
	 * Add a product line item to the order. This is the only line item type with
	 * its own method because it saves looking up order amounts (costs are added up for you).
	 *
	 * @param int|\StoreEngine\Classes\Price $price_id
	 * @param int $quantity
	 * @param array $args Args for the added product.
	 *
	 * @return int return newly created order-item id.
	 */
	public function add_product( $price, int $quantity = 1, array $args = [] ): int {
		if ( is_numeric( $price ) ) {
			$price = new \StoreEngine\Classes\Price( $price );
		}

		$variation_id = $args['variation_id'] ?? 0;
		$variation    = false;
		$_price       = $price->get_price();

		if ( 0 < $variation_id ) {
			$variation = Helper::get_product_variation( $variation_id );

			if ( ! $variation ) {
				return -1;
			}

			$_price = $_price + (float) $variation->get_price();
		}

		// Calculate item total.
		$total = Formatting::get_price_excluding_tax(
			$_price,
			$price->get_id(),
			$price->get_product_id(),
			[
				'qty'   => $quantity,
				'order' => $args['order'] ?? $this,
			]
		);

		// Parse.
		$args = wp_parse_args( $args, [
			// Product
			'name'         => $args['name'] ?? $price->get_product_title(),
			'tax_class'    => '',
			'product_id'   => $price->get_product_id(),
			'variation_id' => $args['variation_id'] ?? 0,
			'variation'    => $args['variation'] ?? [],
			// Type
			'product_type' => get_post_meta( $price->get_product_id(), '_storeengine_product_type', true ) ?? '',
			// Price
			'price_id'     => $price->get_id(),
			'price'        => $_price,
			'subtotal'     => $total,
			'total'        => $total,
			'quantity'     => $quantity,
			'fee'          => true,
		] );

		// Unset unknown prop for order-item
		$add_fee = $args['fee'];
		unset( $args['fee'] );


		if ( array_key_exists( 'order', $args ) ) {
			unset( $args['order'] );
		}

		$item = new OrderItemProduct();

		$item->set_props( $args );
		$item->add_meta_data( '_price_settings', $price->get_settings(), true );

		if ( $variation ) {
			foreach ( $variation->get_attributes() as $attribute ) {
				$item->add_meta_data( $attribute->taxonomy, $attribute->slug, true );
			}
		}

		$item->set_backorder_meta();
		$item->set_order_id( $this->get_id() );
		$item->save();
		$this->add_item( $item );

		if ( $add_fee && $price->has_setup_fee() && $price->get_setup_fee_price() ) {
			$this->add_fee( $price->get_setup_fee_name(), $price->get_setup_fee_price() );
		}

		delete_transient( 'storeengine/order_' . $this->get_id() . '_needs_processing' );

		return $item->get_id();
	}

	/**
	 * @param string $name
	 * @param $amount
	 * @param string $tax_class
	 *
	 * @return int|WP_Error
	 */
	public function add_fee( string $name, $amount, string $tax_class = '' ) {
		$fees = $this->get_fees();

		if ( $fees ) {
			foreach ( $fees as $fee ) {
				$exisitng_hash = strtolower( trim( $fee->get_name( 'edit' ) . $fee->get_amount( 'edit' ) ) );
				$new_hash      = strtolower( trim( $name . $amount ) );
				if ( $exisitng_hash === $new_hash ) {
					return new WP_Error( 'duplicate-fee', __( 'Fee already applied.', 'storeengine' ) );
				}
			}
		}

		$item = new OrderItemFee();
		$item->set_props( [
			'order_id'  => $this->get_id(),
			'name'      => trim( $name ),
			'tax_class' => trim( $tax_class ),
			'amount'    => $amount,
			'total'     => $amount,
		] );

		$item->set_order_id( $this->get_id() );
		$item->save();
		$this->add_item( $item );

		return $item->get_id();
	}

	/*
	|--------------------------------------------------------------------------
	| Payment Token Handling
	|--------------------------------------------------------------------------
	|
	| Payment tokens are hashes used to take payments by certain gateways.
	|
	*/

	/**
	 * Add a payment token to an order
	 *
	 * @param PaymentToken|null|string $token Payment token object.
	 *
	 * @return boolean|int The new token ID or false if it failed.
	 */
	public function add_payment_token( $token ) {
		if ( empty( $token ) || ! ( $token instanceof PaymentToken ) ) {
			return false;
		}

		$token_ids   = $this->get_payment_tokens();
		$token_ids[] = $token->get_id();

		$this->add_meta_data( '_payment_tokens', $token_ids, true );

		$order_id = $this->get_id();
		$token_id = $token->get_id();
		/**
		 * Fires after payment token added to order.
		 *
		 * @param int $order_id Order id.
		 * @param int $token_id Token id.
		 * @param PaymentToken $token Token object.
		 * @param array $token_ids All token ids if current order.
		 */
		do_action( 'storeengine/order/payment_token_added', $order_id, $token_id, $token, $token_ids );

		return $token_id;
	}

	/**
	 * Returns a list of all payment tokens associated with the current order.
	 *
	 * @param string $context
	 *
	 * @return array An array of payment token objects
	 */
	public function get_payment_tokens( string $context = 'view' ): array {
		return array_filter( (array) $this->get_meta( '_payment_tokens', true, $context ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Calculations.
	|--------------------------------------------------------------------------
	|
	| These methods calculate order totals and taxes based on the current data.
	|
	*/

	/**
	 * Calculate shipping total.
	 *
	 * @return float
	 */
	public function calculate_shipping() {
		$shipping_total = 0;

		foreach ( $this->get_shipping_methods() as $shipping ) {
			$shipping_total += (float) $shipping->get_total();
		}

		$this->set_shipping_total( $shipping_total );
		$this->save();

		return $this->get_shipping_total();
	}

	/**
	 * Get all tax classes for items in the order.
	 *
	 * @return array
	 */
	public function get_items_tax_classes() {
		$found_tax_classes = [];
		$valid_statuses    = [ ProductTaxStatus::TAXABLE, ProductTaxStatus::SHIPPING ];

		foreach ( $this->get_items() as $item ) {
			if ( is_callable( [ $item, 'get_tax_status' ] ) && in_array( $item->get_tax_status(), $valid_statuses, true ) ) {
				$found_tax_classes[] = $item->get_tax_class();
			}
		}

		return array_unique( $found_tax_classes );
	}

	/**
	 * Get tax location for this order.
	 *
	 * @param array $args array Override the location.
	 *
	 * @return array
	 */
	protected function get_tax_location( array $args = [] ) {
		$tax_based_on = TaxUtil::tax_based_on();

		if ( 'shipping' === $tax_based_on && ! $this->get_shipping_country() ) {
			$tax_based_on = 'billing';
		}

		$args = wp_parse_args(
			$args,
			[
				'country'  => 'billing' === $tax_based_on ? $this->get_billing_country() : $this->get_shipping_country(),
				'state'    => 'billing' === $tax_based_on ? $this->get_billing_state() : $this->get_shipping_state(),
				'postcode' => 'billing' === $tax_based_on ? $this->get_billing_postcode() : $this->get_shipping_postcode(),
				'city'     => 'billing' === $tax_based_on ? $this->get_billing_city() : $this->get_shipping_city(),
			]
		);

		/**
		 * Filters whether apply base tax for local pickup shipping method or not.
		 *
		 * @param boolean $apply_base_tax Apply_base_tax Whether apply base tax for local pickup. Default true.
		 */
		$apply_base_tax = true === apply_filters( 'storeengine/apply_base_tax_for_local_pickup', true );

		/**
		 * Filters local pickup shipping methods.
		 *
		 * @param string[] $local_pickup_methods Local pickup shipping method IDs.
		 */
		$local_pickup_methods = apply_filters( 'storeengine/local_pickup_methods', [ 'local_pickup' ] );

		$shipping_method_ids = array_map( fn( $item ) => $item->get_method_id(), $this->get_shipping_methods() );

		// Set shop base address as a tax location if order has local pickup shipping method.
		if ( $apply_base_tax && count( array_intersect( $shipping_method_ids, $local_pickup_methods ) ) > 0 ) {
			$tax_based_on = 'base';
		}

		// Default to base.
		if ( 'base' === $tax_based_on || empty( $args['country'] ) ) {
			$args['country']  = Countries::init()->get_base_country();
			$args['state']    = Countries::init()->get_base_state();
			$args['postcode'] = Countries::init()->get_base_postcode();
			$args['city']     = Countries::init()->get_base_city();
		}

		return apply_filters( 'storeengine/order_get_tax_location', $args, $this );
	}

	/**
	 * Public wrapper for exposing get_tax_location() method, enabling 3rd parties to get the tax location for an order.
	 *
	 * @param array $args array Override the location.
	 *
	 * @return array
	 */
	public function get_taxable_location( array $args = [] ): array {
		return $this->get_tax_location( $args );
	}

	/**
	 * Get tax rates for an order. Use order's shipping or billing address, defaults to base location.
	 *
	 * @param string $tax_class Tax class to get rates for.
	 * @param array $location_args Location to compute rates for. Should be in form: array( country, state, postcode, city).
	 * @param ?Customer $customer Only used to maintain backward compatibility for filter `woocommerce-matched_rates`.
	 *
	 * @return mixed|void Tax rates.
	 */
	protected function get_tax_rates( string $tax_class, array $location_args = [], ?Customer $customer = null ) {
		$tax_location = $this->get_tax_location( $location_args );
		$tax_location = array(
			$tax_location['country'],
			$tax_location['state'],
			$tax_location['postcode'],
			$tax_location['city'],
		);

		return Tax::get_rates_from_location( $tax_class, $tax_location, $customer );
	}

	/**
	 * Calculate taxes for all line items and shipping, and store the totals and tax rows.
	 *
	 * If by default the taxes are based on the shipping address and the current order doesn't
	 * have any, it would use the billing address rather than using the Shopping base location.
	 *
	 * Will use the base country unless customer addresses are set.
	 *
	 * @param array $args Pass things like location.
	 */
	public function calculate_taxes( array $args = [] ) {
		/**
		 * Fires before tax calculation on order.
		 *
		 * @param array $args Pass things like location.
		 * @param Order $this Order object.
		 */
		do_action( 'storeengine/order/before_calculate_taxes', $args, $this );

		$calculate_tax_for  = $this->get_tax_location( $args );
		$shipping_tax_class = Helper::get_settings( 'shipping_tax_class', '' );

		if ( 'inherit' === $shipping_tax_class ) {
			$found_classes      = array_intersect( array_merge( array( '' ), Tax::get_tax_class_slugs() ), $this->get_items_tax_classes() );
			$shipping_tax_class = count( $found_classes ) ? current( $found_classes ) : false;
		}

		$is_vat_exempt = apply_filters( 'storeengine/order_is_vat_exempt', 'yes' === $this->get_meta( 'is_vat_exempt' ), $this );

		// Trigger tax recalculation for all items.
		foreach ( $this->get_items( [ 'line_item', 'fee' ] ) as $item ) {
			if ( ! $is_vat_exempt ) {
				$item->calculate_taxes( $calculate_tax_for );
			} else {
				$item->set_taxes( false );
			}

			if ( $item->get_changes() ) {
				$item->save();
			}
		}

		foreach ( $this->get_shipping_methods() as $item_id => $item ) {
			if ( false !== $shipping_tax_class && ! $is_vat_exempt ) {
				$item->calculate_taxes( array_merge( $calculate_tax_for, array( 'tax_class' => $shipping_tax_class ) ) );
			} else {
				$item->set_taxes( false );
			}
			if ( $item->get_changes() ) {
				$item->save();
			}
		}


		$this->update_taxes();
	}

	/**
	 * Calculate fees for all line items.
	 *
	 * @return float Fee total.
	 */
	public function get_total_fees(): float {
		return array_reduce(
			$this->get_fees(),
			function ( $carry, $item ) {
				return $carry + (float) $item->get_total();
			},
			0.0
		);
	}

	/**
	 * Update tax lines for the order based on the line item taxes themselves.
	 */
	public function update_taxes() {
		$cart_taxes     = [];
		$shipping_taxes = [];
		$existing_taxes = $this->get_taxes();
		$saved_rate_ids = [];

		foreach ( $this->get_items( [ 'line_item', 'fee' ] ) as $item_id => $item ) {
			$taxes = $item->get_taxes();
			foreach ( $taxes['total'] as $tax_rate_id => $tax ) {
				$tax_amount = (float) $this->round_line_tax( $tax, false );

				$cart_taxes[ $tax_rate_id ] = isset( $cart_taxes[ $tax_rate_id ] ) ? (float) $cart_taxes[ $tax_rate_id ] + $tax_amount : $tax_amount;
			}
		}

		foreach ( $this->get_shipping_methods() as $item_id => $item ) {
			$taxes = $item->get_taxes();
			foreach ( $taxes['total'] as $tax_rate_id => $tax ) {
				$tax_amount = (float) $tax;

				if ( ! TaxUtil::tax_round_at_subtotal() ) {
					$tax_amount = Formatting::round_tax_total( $tax_amount );
				}

				$shipping_taxes[ $tax_rate_id ] = isset( $shipping_taxes[ $tax_rate_id ] ) ? (float) $shipping_taxes[ $tax_rate_id ] + $tax_amount : $tax_amount;
			}
		}

		foreach ( $existing_taxes as $tax ) {
			// Remove taxes which no longer exist for cart/shipping.
			if ( ( ! array_key_exists( $tax->get_rate_id(), $cart_taxes ) && ! array_key_exists( $tax->get_rate_id(), $shipping_taxes ) ) || in_array( $tax->get_rate_id(), $saved_rate_ids, true ) ) {
				$this->remove_item( $tax->get_id() );
				continue;
			}
			$saved_rate_ids[] = $tax->get_rate_id();
			$tax->set_rate( $tax->get_rate_id() );
			$tax->set_tax_total( isset( $cart_taxes[ $tax->get_rate_id() ] ) ? $cart_taxes[ $tax->get_rate_id() ] : 0 );
			$tax->set_label( Tax::get_rate_label( $tax->get_rate_id() ) );
			$tax->set_shipping_tax_total( ! empty( $shipping_taxes[ $tax->get_rate_id() ] ) ? $shipping_taxes[ $tax->get_rate_id() ] : 0 );
			$tax->save();
		}

		$new_rate_ids = wp_parse_id_list( array_diff( array_keys( $cart_taxes + $shipping_taxes ), $saved_rate_ids ) );

		// New taxes.
		foreach ( $new_rate_ids as $tax_rate_id ) {
			$item = new OrderItemTax();
			$item->set_rate( $tax_rate_id );
			$item->set_tax_total( $cart_taxes[ $tax_rate_id ] ?? 0 );
			$item->set_shipping_tax_total( ! empty( $shipping_taxes[ $tax_rate_id ] ) ? $shipping_taxes[ $tax_rate_id ] : 0 );
			$this->add_item( $item );
		}

		$this->set_shipping_tax( array_sum( $shipping_taxes ) );
		$this->set_cart_tax( array_sum( $cart_taxes ) );
		$this->save();
	}

	/**
	 * Helper function.
	 * If you add all items in this order in cart again, this would be the cart subtotal (assuming all other settings are same).
	 *
	 * @return float Cart subtotal.
	 */
	protected function get_cart_subtotal_for_order(): float {
		return Formatting::remove_number_precision( $this->get_rounded_items_total( $this->get_values_for_total( 'subtotal' ) ) );
	}

	/**
	 * Helper function.
	 * If you add all items in this order in cart again, this would be the cart total (assuming all other settings are same).
	 *
	 * @return float Cart total.
	 */
	protected function get_cart_total_for_order(): float {
		return Formatting::remove_number_precision( $this->get_rounded_items_total( $this->get_values_for_total( 'total' ) ) );
	}

	/**
	 * Calculate totals by looking at the contents of the order. Stores the totals and returns the orders final total.
	 *
	 * @param bool $and_taxes Calc taxes if true.
	 *
	 * @return float calculated grand total.
	 */
	public function calculate_totals( bool $and_taxes = true ) {
		/**
		 * Fires before calculate totals on Order.
		 *
		 * @param bool $and_taxes Calc taxes if true.
		 * @param Order $this Order object.
		 */
		do_action( 'storeengine/order/before_calculate_totals', $and_taxes, $this );

		$fees_total        = 0;
		$shipping_total    = 0;
		$cart_subtotal_tax = 0;
		$cart_total_tax    = 0;

		$cart_subtotal = $this->get_cart_subtotal_for_order();
		$cart_total    = (float) $this->get_cart_total_for_order();

		// Sum shipping costs.
		foreach ( $this->get_shipping_methods() as $shipping ) {
			$shipping_total += NumberUtil::round( $shipping->get_total(), Formatting::get_price_decimals() );
		}

		$this->set_shipping_total( $shipping_total );

		// Sum fee costs.
		foreach ( $this->get_fees() as $item ) {
			$fee_total = (float) $item->get_total();

			if ( 0 > $fee_total ) {
				$max_discount = NumberUtil::round( $cart_total + $fees_total + $shipping_total, Formatting::get_price_decimals() ) * - 1;

				if ( $fee_total < $max_discount && 0 > $max_discount ) {
					$item->set_total( $max_discount );
				}
			}
			$fees_total += (float) $item->get_total();
		}

		// Calculate taxes for items, shipping, discounts. Note; this also triggers save().
		if ( $and_taxes ) {
			$this->calculate_taxes();
		}

		// Sum taxes again so we can work out how much tax was discounted. This uses original values, not those possibly rounded to 2dp.
		foreach ( $this->get_items() as $item ) {
			$taxes = $item->get_taxes();

			foreach ( $taxes['total'] as $tax ) {
				$cart_total_tax += (float) $tax;
			}

			foreach ( $taxes['subtotal'] as $tax ) {
				$cart_subtotal_tax += (float) $tax;
			}
		}

		$this->set_discount_total( NumberUtil::round( $cart_subtotal - $cart_total, Formatting::get_price_decimals() ) );
		$this->set_discount_tax( Formatting::round_tax_total( $cart_subtotal_tax - $cart_total_tax ) );
		$this->set_total( NumberUtil::round( $cart_total + $fees_total + (float) $this->get_shipping_total() + (float) $this->get_cart_tax() + (float) $this->get_shipping_tax(), Formatting::get_price_decimals() ) );

		/**
		 * Fires after calculate totals on Order.
		 *
		 * @param bool $and_taxes Calc taxes if true.
		 * @param Order $this Order object.
		 */
		do_action( 'storeengine/order/after_calculate_totals', $and_taxes, $this );

		$this->save();

		return $this->get_total();
	}

	/**
	 * Get item subtotal - this is the cost before discount.
	 *
	 * @param object $item Item to get total from.
	 * @param bool $inc_tax (default: false).
	 * @param bool $round (default: true).
	 *
	 * @return float
	 */
	public function get_item_subtotal( object $item, bool $inc_tax = false, bool $round = true ): float {
		$subtotal = 0;

		if ( is_callable( array( $item, 'get_subtotal' ) ) && $item->get_quantity() ) {
			if ( $inc_tax ) {
				$subtotal = ( (float) $item->get_subtotal() + (float) $item->get_subtotal_tax() ) / $item->get_quantity();
			} else {
				$subtotal = ( (float) $item->get_subtotal() ) / $item->get_quantity();
			}

			$subtotal = $round ? NumberUtil::round( $subtotal, Formatting::get_price_decimals() ) : $subtotal;
		}

		return apply_filters( 'storeengine/order_amount_item_subtotal', $subtotal, $this, $item, $inc_tax, $round );
	}

	/**
	 * Get line subtotal - this is the cost before discount.
	 *
	 * @param object $item Item to get total from.
	 * @param bool $inc_tax (default: false).
	 * @param bool $round (default: true).
	 *
	 * @return float
	 */
	public function get_line_subtotal( object $item, bool $inc_tax = false, bool $round = true ): float {
		$subtotal = 0;

		if ( is_callable( array( $item, 'get_subtotal' ) ) ) {
			if ( $inc_tax ) {
				$subtotal = (float) $item->get_subtotal() + (float) $item->get_subtotal_tax();
			} else {
				$subtotal = (float) $item->get_subtotal();
			}

			$subtotal = $round ? NumberUtil::round( $subtotal, Formatting::get_price_decimals() ) : $subtotal;
		}

		return apply_filters( 'storeengine/order_amount_line_subtotal', $subtotal, $this, $item, $inc_tax, $round );
	}

	/**
	 * Calculate item cost - useful for gateways.
	 *
	 * @param object $item Item to get total from.
	 * @param bool $inc_tax (default: false).
	 * @param bool $round (default: true).
	 *
	 * @return float
	 */
	public function get_item_total( object $item, bool $inc_tax = false, bool $round = true ): float {
		$total = 0;

		if ( is_callable( array( $item, 'get_total' ) ) && $item->get_quantity() ) {
			if ( $inc_tax ) {
				$total = ( (float) $item->get_total() + (float) $item->get_total_tax() ) / $item->get_quantity();
			} else {
				$total = ( (float) $item->get_total() ) / $item->get_quantity();
			}

			$total = $round ? NumberUtil::round( $total, Formatting::get_price_decimals() ) : $total;
		}

		return apply_filters( 'storeengine/order_amount_item_total', $total, $this, $item, $inc_tax, $round );
	}

	/**
	 * Calculate line total - useful for gateways.
	 *
	 * @param object $item Item to get total from.
	 * @param bool $inc_tax (default: false).
	 * @param bool $round (default: true).
	 *
	 * @return float
	 */
	public function get_line_total( object $item, bool $inc_tax = false, bool $round = true ): float {
		$total = 0;

		if ( is_callable( array( $item, 'get_total' ) ) ) {
			// Check if we need to add line tax to the line total.
			$total = $inc_tax ? (float) $item->get_total() + (float) $item->get_total_tax() : (float) $item->get_total();

			// Check if we need to round.
			$total = $round ? NumberUtil::round( $total, Formatting::get_price_decimals() ) : $total;
		}

		return apply_filters( 'storeengine/order_amount_line_total', $total, $this, $item, $inc_tax, $round );
	}

	/**
	 * Get item tax - useful for gateways.
	 *
	 * @param mixed $item Item to get total from.
	 * @param bool $round (default: true).
	 *
	 * @return float
	 */
	public function get_item_tax( $item, bool $round = true ): float {
		$tax = 0;

		if ( is_callable( array( $item, 'get_total_tax' ) ) && $item->get_quantity() ) {
			$tax = $item->get_total_tax() / $item->get_quantity();
			$tax = $round ? Formatting::round_tax_total( $tax ) : $tax;
		}

		return apply_filters( 'storeengine/order_amount_item_tax', $tax, $item, $round, $this );
	}

	/**
	 * Get line tax - useful for gateways.
	 *
	 * @param mixed $item Item to get total from.
	 *
	 * @return float
	 */
	public function get_line_tax( $item ): float {
		return apply_filters( 'storeengine/order_amount_line_tax', is_callable( array(
			$item,
			'get_total_tax',
		) ) ? Formatting::round_tax_total( $item->get_total_tax() ) : 0, $item, $this );
	}

	/**
	 * Gets line subtotal - formatted for display.
	 *
	 * @param object $item Item to get total from.
	 * @param string $tax_display Incl or excl tax display mode.
	 *
	 * @return string
	 */
	public function get_formatted_line_subtotal( object $item, string $tax_display = '' ): ?string {
		$tax_display = $tax_display ? $tax_display : Helper::get_settings( 'tax_display_cart', 'excl' );

		if ( 'excl' === $tax_display ) {
			$ex_tax_label = $this->get_prices_include_tax() ? 1 : 0;

			$subtotal = Formatting::price(
				$this->get_line_subtotal( $item ),
				[
					'ex_tax_label' => $ex_tax_label,
					'currency'     => $this->get_currency(),
				]
			);
		} else {
			$subtotal = Formatting::price( $this->get_line_subtotal( $item, true ), [ 'currency' => $this->get_currency() ] );
		}

		return apply_filters( 'storeengine/order_formatted_line_subtotal', $subtotal, $item, $this );
	}

	/**
	 * Gets order total - formatted for display.
	 *
	 * @return string
	 */
	public function get_formatted_order_total(): ?string {
		$formatted_total = Formatting::price( $this->get_total(), [ 'currency' => $this->get_currency() ] );

		return apply_filters( 'storeengine/get_formatted_order_total', $formatted_total, $this );
	}

	/**
	 * Gets subtotal - subtotal is shown before discounts, but with localised taxes.
	 *
	 * @param bool $compound (default: false).
	 * @param string $tax_display (default: the tax_display_cart value).
	 *
	 * @return string
	 */
	public function get_subtotal_to_display( $compound = false, $tax_display = '' ): ?string {
		$tax_display = $tax_display ?: Helper::get_settings( 'tax_display_cart', 'excl' );
		$subtotal    = (float) $this->get_cart_subtotal_for_order();

		if ( ! $compound ) {
			if ( 'incl' === $tax_display ) {
				$subtotal_taxes = 0;
				foreach ( $this->get_items() as $item ) {
					$subtotal_taxes += self::round_line_tax( (float) $item->get_subtotal_tax(), false );
				}
				$subtotal += Formatting::round_tax_total( $subtotal_taxes );
			}

			$subtotal = Formatting::price( $subtotal, [ 'currency' => $this->get_currency() ] );

			if ( 'excl' === $tax_display && $this->get_prices_include_tax() && TaxUtil::is_tax_enabled() ) {
				$subtotal .= ' <small class="tax_label">' . Countries::init()->ex_tax_or_vat() . '</small>';
			}
		} else {
			if ( 'incl' === $tax_display ) {
				return '';
			}

			// Add Shipping Costs.
			$subtotal += (float) $this->get_shipping_total();

			// Remove non-compound taxes.
			foreach ( $this->get_taxes() as $tax ) {
				if ( $tax->is_compound() ) {
					continue;
				}
				$subtotal = $subtotal + (float) $tax->get_tax_total() + (float) $tax->get_shipping_tax_total();
			}

			// Remove discounts.
			$subtotal = $subtotal - (float) $this->get_total_discount();
			$subtotal = Formatting::price( $subtotal, [ 'currency' => $this->get_currency() ] );
		}

		return apply_filters( 'storeengine/order_subtotal_to_display', $subtotal, $compound, $this );
	}

	/**
	 * Gets shipping (formatted).
	 *
	 * @param string $tax_display Excl or incl tax display mode.
	 *
	 * @return string
	 */
	public function get_shipping_to_display( string $tax_display = '' ) {
		$tax_display = $tax_display ?: Helper::get_settings( 'tax_display_cart', 'excl' );

		if ( 0 < abs( (float) $this->get_shipping_total() ) ) {
			if ( 'excl' === $tax_display ) {

				// Show shipping excluding tax.
				$shipping = Formatting::price( $this->get_shipping_total(), [ 'currency' => $this->get_currency() ] );

				if ( (float) $this->get_shipping_tax() > 0 && $this->get_prices_include_tax() ) {
					$shipping .= apply_filters( 'storeengine/order_shipping_to_display_tax_label', '&nbsp;<small class="tax_label">' . Countries::init()->ex_tax_or_vat() . '</small>', $this, $tax_display );
				}
			} else {

				// Show shipping including tax.
				$shipping = Formatting::price( (float) $this->get_shipping_total() + (float) $this->get_shipping_tax(), [ 'currency' => $this->get_currency() ] );

				if ( (float) $this->get_shipping_tax() > 0 && ! $this->get_prices_include_tax() ) {
					$shipping .= apply_filters( 'storeengine/order_shipping_to_display_tax_label', '&nbsp;<small class="tax_label">' . Countries::init()->inc_tax_or_vat() . '</small>', $this, $tax_display );
				}
			}

			/* translators: %s: method */
			$shipping .= apply_filters( 'storeengine/order_shipping_to_display_shipped_via', '&nbsp;<small class="shipped_via">' . sprintf( __( 'via %s', 'storeengine' ), $this->get_shipping_method() ) . '</small>', $this );
		} elseif ( $this->get_shipping_method() ) {
			$shipping = $this->get_shipping_method();
		} else {
			$shipping = __( 'Free!', 'storeengine' );
		}

		return apply_filters( 'storeengine/order_shipping_to_display', $shipping, $this, $tax_display );
	}

	/**
	 * Get the discount amount (formatted).
	 *
	 * @param string $tax_display Excl or incl tax display mode.
	 *
	 * @return string
	 */
	public function get_discount_to_display( string $tax_display = '' ): ?string {
		$tax_display = $tax_display ? $tax_display : Helper::get_settings( 'tax_display_cart', 'excl' );

		/**
		 * Filter the discount amount to display.
		 */
		return apply_filters( 'storeengine/order_discount_to_display', Formatting::price( $this->get_total_discount( 'excl' === $tax_display ), [ 'currency' => $this->get_currency() ] ), $this );
	}

	/**
	 * Add total row for subtotal.
	 *
	 * @param array $total_rows Reference to total rows array.
	 * @param string $tax_display Excl or incl tax display mode.
	 */
	protected function add_order_item_totals_subtotal_row( &$total_rows, $tax_display ) {
		$subtotal = $this->get_subtotal_to_display( false, $tax_display );

		if ( $subtotal ) {
			$total_rows['cart_subtotal'] = [
				'type'  => 'subtotal',
				'label' => __( 'Subtotal:', 'storeengine' ),
				'value' => $subtotal,
			];
		}
	}

	/**
	 * Add total row for discounts.
	 *
	 * @param array $total_rows Reference to total rows array.
	 * @param string $tax_display Excl or incl tax display mode.
	 */
	protected function add_order_item_totals_discount_row( array &$total_rows, string $tax_display ) {
		if ( $this->get_total_discount() > 0 ) {
			$total_rows['discount'] = [
				'type'  => 'discount',
				'label' => __( 'Discount:', 'storeengine' ),
				'value' => '-' . $this->get_discount_to_display( $tax_display ),
			];
		}
	}

	/**
	 * Add total row for shipping.
	 *
	 * @param array $total_rows Reference to total rows array.
	 * @param string $tax_display Excl or incl tax display mode.
	 */
	protected function add_order_item_totals_shipping_row( array &$total_rows, string $tax_display ) {
		if ( $this->get_shipping_method() ) {
			$total_rows['shipping'] = [
				'type'  => 'shipping',
				'label' => __( 'Shipping:', 'storeengine' ),
				'value' => $this->get_shipping_to_display( $tax_display ),
				'meta'  => $this->get_shipping_method(),
			];
		}
	}

	/**
	 * Add total row for fees.
	 *
	 * @param array $total_rows Reference to total rows array.
	 * @param string $tax_display Excl or incl tax display mode.
	 */
	protected function add_order_item_totals_fee_rows( array &$total_rows, string $tax_display ) {
		$fees = $this->get_fees();

		if ( $fees ) {
			foreach ( $fees as $id => $fee ) {
				if ( apply_filters( 'storeengine/get_order_item_totals_excl_free_fees', empty( $fee->get_total() ) && empty( $fee->get_total_tax() ), $id ) ) {
					continue;
				}
				$total_rows[ 'fee_' . $fee->get_id() ] = [
					'type'  => 'fee',
					'label' => $fee->get_name() . ':',
					'value' => Formatting::price( 'excl' === $tax_display ? (float) $fee->get_total() : (float) $fee->get_total() + (float) $fee->get_total_tax(), [ 'currency' => $this->get_currency() ] ),
				];
			}
		}
	}

	/**
	 * Add total row for taxes.
	 *
	 * @param array $total_rows Reference to total rows array.
	 * @param string $tax_display Excl or incl tax display mode.
	 */
	protected function add_order_item_totals_tax_rows( array &$total_rows, string $tax_display ) {
		// Tax for tax exclusive prices.
		if ( 'excl' === $tax_display && TaxUtil::is_tax_enabled() ) {
			if ( 'itemized' === Helper::get_settings( 'tax_total_display' ) ) {
				foreach ( $this->get_tax_totals() as $code => $tax ) {
					$total_rows[ sanitize_title( $code ) ] = [
						'type'  => 'tax',
						'label' => $tax->label . ':',
						'value' => $tax->formatted_amount,
					];
				}
			} else {
				$total_rows['tax'] = [
					'type'  => 'tax',
					'label' => Countries::init()->tax_or_vat() . ':',
					'value' => Formatting::price( $this->get_total_tax(), [ 'currency' => $this->get_currency() ] ),
				];
			}
		}
	}

	/**
	 * Add total row for grand total.
	 *
	 * @param array $total_rows Reference to total rows array.
	 * @param string $tax_display Excl or incl tax display mode.
	 */
	protected function add_order_item_totals_total_row( array &$total_rows, string $tax_display ) {
		$total_rows['order_total'] = array(
			'type'  => 'total',
			'label' => __( 'Total:', 'storeengine' ),
			'value' => $this->get_formatted_order_total( $tax_display ),
		);
	}

	/**
	 * Get totals for display on pages and in emails.
	 *
	 * @param mixed $tax_display Excl or incl tax display mode.
	 *
	 * @return array
	 */
	public function get_order_item_totals( string $tax_display = '' ) {
		$tax_display = $tax_display ? $tax_display : Helper::get_settings( 'tax_display_cart', 'excl' );
		$total_rows  = [];

		$this->add_order_item_totals_subtotal_row( $total_rows, $tax_display );
		$this->add_order_item_totals_discount_row( $total_rows, $tax_display );
		$this->add_order_item_totals_shipping_row( $total_rows, $tax_display );
		$this->add_order_item_totals_fee_rows( $total_rows, $tax_display );
		$this->add_order_item_totals_tax_rows( $total_rows, $tax_display );
		$this->add_order_item_totals_total_row( $total_rows, $tax_display );

		return apply_filters( 'storeengine/get_order_item_totals', $total_rows, $this, $tax_display );
	}

	/*
	|--------------------------------------------------------------------------
	| Conditionals
	|--------------------------------------------------------------------------
	|
	| Checks if a condition is true or false.
	|
	*/

	/**
	 * Checks the order status against a passed in status.
	 *
	 * @param array|string $status Status to check.
	 *
	 * @return bool
	 */
	public function has_status( $status ): bool {
		return apply_filters( 'storeengine/order_has_status', ( is_array( $status ) && in_array( $this->get_status(), $status, true ) ) || $this->get_status() === $status, $this, $status );
	}

	/**
	 * Check whether this order has a specific shipping method or not.
	 *
	 * @param string $method_id Method ID to check.
	 *
	 * @return bool
	 */
	public function has_shipping_method( string $method_id ): bool {
		foreach ( $this->get_shipping_methods() as $shipping_method ) {
			if ( strpos( $shipping_method->get_method_id( 'edit' ), $method_id ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns true if the order contains a free product.
	 *
	 * @return bool
	 */
	public function has_free_item(): bool {
		foreach ( $this->get_items() as $item ) {
			if ( ! $item->get_total() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get order title.
	 *
	 * @return string Order title.
	 */
	public function get_title( string $context = 'view' ): ?string {
		$title = $this->get_meta( 'title', true, $context );

		return $title ?: __( 'Order', 'storeengine' );
	}


	// ------------------>

	public function add_tax( $tax_rate_id ) {
		if ( $tax_rate_id && apply_filters( 'storeengine/cart/remove_taxes_zero_rate_id', 'zero-rated' ) === $tax_rate_id ) {
			return;
		}

		$item = new OrderItemTax();
		$item->set_props( [
			'rate_id'            => $tax_rate_id,
			'order_id'           => $this->get_id(),
			'tax_total'          => Helper::cart()->get_tax_amount( $tax_rate_id ),
			'shipping_tax_total' => Helper::cart()->get_shipping_tax_amount( $tax_rate_id ),
			'rate_code'          => Tax::get_rate_code( $tax_rate_id ),
			'label'              => Tax::get_rate_label( $tax_rate_id ),
			'compound'           => Tax::is_compound( $tax_rate_id ),
			'rate_percent'       => Tax::get_rate_percent_value( $tax_rate_id ),
		] );

		/**
		 * Fires after adding tax on Order.
		 *
		 * @param OrderItemTax $item ItemTax object.
		 * @param int $tax_rate_id Tax rate id.
		 * @param Order $this Order instance.
		 */
		do_action( 'storeengine/order/checkout/create_order_tax_item', $item, $tax_rate_id, $this );

		$this->add_item( $item );
	}

	//---

	public function clear_items() {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"DELETE items, meta
       			FROM {$wpdb->prefix}storeengine_order_items AS items
				LEFT JOIN {$wpdb->prefix}storeengine_order_item_meta AS meta
				ON items.order_item_id = meta.order_item_id
				WHERE items.order_id = %d",
			$this->get_id()
		) );
	}

	public function calculate( $and_taxes = true ) {
		$this->calculate_totals( $and_taxes );
	}

	protected function items_query(): ?string {
		global $wpdb;

		return "
			SELECT
		        items.order_item_id,
		        items.order_item_name
			FROM {$wpdb->prefix}storeengine_order_items AS items
			LEFT JOIN {$wpdb->prefix}storeengine_order_item_meta AS meta
		        ON items.order_item_id = meta.order_item_id
			WHERE items.order_id = %d AND items.order_item_type = %s
			GROUP BY items.order_item_id;";
	}

	/**
	 * Read order items of a specific type from the database for this order.
	 *
	 * @param string $type Order item type.
	 *
	 * @return array
	 */
	public function read_items( string $type ): array {
		global $wpdb;

		// When the order is not yet saved, we cannot get the items from DB. Trying to do so will risk reading items of different orders that were saved incorrectly.
		if ( 0 === $this->get_id() ) {
			return [];
		}

		// Get from cache if available.
		$items = 0 < $this->get_id() ? wp_cache_get( 'order-items-' . $this->get_id(), 'orders' ) : false;

		if ( false === $items ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- adding items into cache.
			$items = $wpdb->get_results(
				$wpdb->prepare( "SELECT order_item_type, order_item_id, order_id, order_item_name FROM {$wpdb->prefix}storeengine_order_items WHERE order_id = %d ORDER BY order_item_id;", $this->get_id() )
			);
			foreach ( $items as $item ) {
				wp_cache_set( 'item-' . $item->order_item_id, $item, 'order-items' );
			}
			if ( 0 < $this->get_id() ) {
				wp_cache_set( 'order-items-' . $this->get_id(), $items, 'orders' );
			}
		}

		$items = wp_list_filter( $items, [ 'order_item_type' => $type ] );

		if ( ! empty( $items ) ) {
			$items = array_map(
				[ Order::class, 'get_order_item' ],
				array_combine( wp_list_pluck( $items, 'order_item_id' ), $items )
			);
		} else {
			$items = [];
		}

		return $items;
	}

	/**
	 * Get order item.
	 *
	 * @param int|string|AbstractOrderItem $item_id Order item ID to get.
	 *
	 * @return AbstractOrderItem|false if not found
	 */
	public static function get_order_item( $item_id = 0 ) {
		if ( is_numeric( $item_id ) ) {
			$item_type = AbstractOrderItem::get_order_item_type( absint( $item_id ) );
			$id        = $item_id;
		} elseif ( $item_id instanceof AbstractOrderItem ) {
			$item_type = $item_id->get_type();
			$id        = $item_id->get_id();
		} elseif ( is_object( $item_id ) && ! empty( $item_id->order_item_id ) && ! empty( $item_id->order_item_type ) ) {
			$id        = $item_id->order_item_id;
			$item_type = $item_id->order_item_type;
		} else {
			$item_type = false;
			$id        = false;
		}

		if ( $id && $item_type ) {
			$classname = false;
			switch ( $item_type ) {
				case 'line_item':
				case 'product':
					$classname = OrderItemProduct::class;
					break;
				case 'coupon':
					$classname = OrderItemCoupon::class;
					break;
				case 'fee':
					$classname = OrderItemFee::class;
					break;
				case 'shipping':
					$classname = OrderItemShipping::class;
					break;
				case 'tax':
					$classname = OrderItemTax::class;
					break;
			}

			$classname = apply_filters( 'storeengine/get_order_item_classname', $classname, $item_type, $id );

			if ( $classname && class_exists( $classname ) ) {
				try {
					return new $classname( $id );
				} catch ( Exception $e ) {
					return false;
				}
			}
		}

		return false;
	}

	public function get_order_email( string $context = 'view' ) {
		return $this->get_prop( 'order_email', $context );
	}

	public function set_order_email( ?string $value = '' ) {
		$value = $value ?? '';

		if ( $value && ! is_email( $value ) ) {
			$this->error( 'order_email', __( 'Invalid order email address.', 'storeengine' ) );
		}

		$this->set_prop( 'order_email', sanitize_email( $value ) );
	}

	/**
	 * Get customer_id.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return int
	 */
	public function get_customer_id( string $context = 'view' ): int {
		return (int) $this->get_prop( 'customer_id', $context );
	}

	public function get_customer() {
		if ( $this->get_customer_id() ) {
			$customer = new Customer( $this->get_customer_id() );

			return $customer->get_id() ? $customer : false;
		}

		return false;
	}

	/**
	 * @throws StoreEngineInvalidOrderStatusException
	 */
	public function get_status_title(): string {
		$order_status = new OrderContext( $this->get_status() );

		return $order_status->get_order_status_title();
	}

	// operational_data methods ----------


	public function get_coupon_usages_are_counted( string $context = 'view' ): bool {
		return Formatting::string_to_bool( $this->get_prop( 'coupon_usages_are_counted', $context ) );
	}

	public function get_download_permission_granted( string $context = 'view' ): bool {
		return Formatting::string_to_bool( $this->get_prop( 'download_permission_granted', $context ) );
	}

	public function get_cart_hash( string $context = 'view' ) {
		return $this->get_prop( 'cart_hash', $context );
	}

	public function get_new_order_email_sent( string $context = 'view' ): bool {
		return Formatting::string_to_bool( $this->get_prop( 'new_order_email_sent', $context ) );
	}

	public function get_order_key( string $context = 'view' ) {
		$value = $this->get_prop( 'order_key', $context );

		if ( $value ) {
			return $value;
		}

		return self::generate_order_key();
	}

	public function get_order_stock_reduced( string $context = 'view' ): bool {
		return Formatting::string_to_bool( $this->get_prop( 'order_stock_reduced', $context ) );
	}

	public function get_date_created_gmt( string $context = 'view' ): ?StoreengineDatetime {
		return $this->get_prop( 'date_created_gmt', $context );
	}

	public function get_date_updated_gmt( string $context = 'view' ): ?StoreengineDatetime {
		return $this->get_prop( 'date_updated_gmt', $context );
	}

	public function get_date_paid_gmt( string $context = 'view' ) {
		return $this->get_prop( 'date_paid_gmt', $context );
	}

	public function get_date_completed_gmt( string $context = 'view' ) {
		return $this->get_prop( 'date_completed_gmt', $context );
	}

	public function get_shipping_tax_amount( string $context = 'view' ) {
		return $this->get_prop( 'shipping_tax_amount', $context );
	}

	public function get_shipping_total_amount( string $context = 'view' ) {
		return $this->get_prop( 'shipping_total_amount', $context );
	}

	public function get_discount_tax_amount( string $context = 'view' ) {
		return $this->get_prop( 'discount_tax_amount', $context );
	}

	public function get_discount_total_amount( string $context = 'view' ) {
		return $this->get_prop( 'discount_total_amount', $context );
	}

	public function get_recorded_sales( string $context = 'view' ): bool {
		return Formatting::string_to_bool( $this->get_prop( 'recorded_sales', $context ) );
	}

	// ----------

	public function set_coupon_usages_are_counted( $value ) {
		$this->set_prop( 'coupon_usages_are_counted', Formatting::string_to_bool( $value ) );
	}

	public function set_download_permission_granted( $value ) {
		$this->set_prop( 'download_permission_granted', Formatting::string_to_bool( $value ) );
	}

	public function set_cart_hash( $value ) {
		$this->set_prop( 'cart_hash', $value );
	}

	public function set_new_order_email_sent( $value ) {
		$this->set_prop( 'new_order_email_sent', Formatting::string_to_bool( $value ) );
	}

	public function set_order_key( $value ) {
		$this->set_prop( 'order_key', $value );
	}

	public function set_order_stock_reduced( $value ) {
		$this->set_prop( 'order_stock_reduced', Formatting::string_to_bool( $value ) );
	}

	public function set_date_created_gmt( $value ) {
		$this->set_date_prop( 'date_created_gmt', $value );
	}

	public function set_date_updated_gmt( $value ) {
		$this->set_date_prop( 'date_updated_gmt', $value );
	}

	public function set_date_paid_gmt( $value ) {
		$this->set_date_prop( 'date_paid_gmt', $value );
	}

	/**
	 * Set date paid.
	 *
	 * @param string|integer|null $date UTC timestamp, or ISO 8601 DateTime. If the DateTime string has no timezone or offset, WordPress site timezone will be assumed. Null if their is no date.
	 *
	 * @deprecated
	 * @see set_date_paid_gmt
	 */
	public function set_date_paid( $date = null ) {
		$this->set_date_prop( 'set_date_paid_gmt', $date );
	}

	public function set_date_completed_gmt( $value ) {
		$this->set_date_prop( 'date_completed_gmt', $value );
	}

	/**
	 * Placeholder for reminding devs to use the _gmt version.
	 *
	 * @param $value
	 *
	 * @return void
	 * @deprecated
	 * @see set_date_completed_gmt()
	 */
	public function set_date_completed( $value ) {
		$this->set_date_completed_gmt( $value );
	}

	public function set_shipping_tax_amount( $value ) {
		$this->set_prop( 'shipping_tax_amount', $value );
	}

	public function set_shipping_total_amount( $value ) {
		$this->set_prop( 'shipping_total_amount', $value );
	}

	public function set_discount_tax_amount( $value ) {
		$this->set_prop( 'discount_tax_amount', $value );
	}

	public function set_discount_total_amount( $value ) {
		$this->set_prop( 'discount_total_amount', $value );
	}

	public function set_recorded_sales( $value ) {
		$this->set_prop( 'recorded_sales', Formatting::string_to_bool( $value ) );
	}

	/**
	 * Generate an order key with prefix.
	 *
	 * @param int $length By default, generates a 13 digit secret.
	 *                     Length can't be less than 9 or greater than 91 due to db restrain.
	 *
	 * @return string The order key.
	 */
	public static function generate_order_key( int $length = 13 ): string {
		if ( 9 > $length || 91 < $length ) {
			$length = 13;
		}

		$key = wp_generate_password( $length, false );

		return 'se_' . apply_filters( 'storeengine/generate_order_key', 'order_' . $key );
	}

	public function clear_cache() {
		parent::clear_cache();
		wp_cache_delete( 'order:draft:' . Helper::get_cart_hash_from_cookie(), $this->cache_group );
		wp_cache_delete( 'order:key:' . $this->get_order_key( 'edit' ), $this->cache_group );
		wp_cache_delete( Caching::get_cache_prefix( 'orders' ) . 'refunds' . $this->get_id(), $this->cache_group );

		$this->clear_caches();
	}

	protected function clear_caches() {
		clean_post_cache( $this->get_id() );

		if ( $this->get_customer_id() ) {
			global $wpdb;
			delete_user_meta( $this->get_customer_id(), '_money_spent_' . rtrim( $wpdb->get_blog_prefix(), '_' ) );
			delete_user_meta( $this->get_customer_id(), '_order_count_' . rtrim( $wpdb->get_blog_prefix(), '_' ) );
			delete_user_meta( $this->get_customer_id(), '_last_order_' . rtrim( $wpdb->get_blog_prefix(), '_' ) );
		}

		Caching::get_transient_version( 'orders' );
		Caching::invalidate_cache_group( 'orders' );

		wp_cache_delete( 'order-items-' . $this->get_id(), 'orders' );
	}
}
