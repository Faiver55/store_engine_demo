<?php

namespace StoreEngine\models;

use StoreEngine\Addons\Subscription\Classes\SubscriptionCollection;
use StoreEngine\Classes\AbstractModel;
use StoreEngine\Classes\ProductFactory;
use StoreEngine\Utils\Helper;
use WP_Error;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @deprecated - Use `Order` entity or `OrderCollection` class instead.
 *
 * @see \StoreEngine\Classes\Order
 * @see \StoreEngine\Classes\OrderCollection
 */
class Order extends AbstractModel {

	protected string $table       = 'storeengine_orders';
	protected string $primary_key = 'id';
	public array $data;

	public function __construct( $order_id = null ) {
		parent::__construct();
		if ( null !== $order_id ) {
			$this->data = $this->get_by_primary_key( $order_id );
		}
	}

	public function get_order_by_hash( $order_hash = null ) {
		$order_hash = $order_hash ?? Helper::get_cart_hash_from_cookie();
		if ( ! $order_hash ) {
			return new WP_Error( 'failed-to-fetch', __( 'No hash set in cookie', 'storeengine' ) );
		}
		global $wpdb;
		$order = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_orders WHERE hash = %s AND status !='draft'", $order_hash ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! $order ) {
			return [];
		}

		$order                          = $order[0];
		$order['meta']                  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_orders_meta WHERE order_id = %d", $order[0]['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order['purchase_items']        = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_order_product_lookup WHERE order_id = %d", $order[0]['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order['order_billing_address'] = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_order_addresses WHERE order_id = %d", $order[0]['id'] ) )[0]; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $order;
	}


	public function save( $args = [] ) {
		$defaults = [
			'status'               => '',
			'currency'             => '',
			'type'                 => '',
			'tax_amount'           => '',
			'total_amount'         => '',
			'customer_id'          => is_user_logged_in() ? get_current_user_id() : '',
			'billing_email'        => '',
			'date_created_gmt'     => current_time( 'mysql' ),
			'date_updated_gmt'     => current_time( 'mysql' ),
			'parent_order_id'      => '',
			'payment_method'       => '',
			'payment_method_title' => '',
			'transaction_id'       => '',
			'ip_address'           => Helper::get_user_ip(),
			'user_agent'           => ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : null,
			'hash'                 => Helper::get_cart_hash_from_cookie(),

		];

		$order_args = array_filter( $args, fn( $value ) => ! is_array( $value ) );
		$data       = wp_parse_args( $order_args, $defaults );
		$inserted   = $this->wpdb->insert(
			$this->table,
			$data,
			[
				'%s',
				'%s',
				'%s',
				'%f',
				'%f',
				'%d',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			]
		);
		if ( ! $inserted ) {
			return new WP_Error( 'failed-to-insert', $this->wpdb->last_error );
		}
		$order_id           = $this->wpdb->insert_id;
		$meta_insert_result = $this->order_meta_insert( $order_id, $args['meta'] );
		if ( is_wp_error( $meta_insert_result ) ) {
			return $meta_insert_result;
		}
		$lookup_insert_result = $this->order_items_and_lookup_insert( $order_id, $args['purchase_items'] );
		if ( is_wp_error( $lookup_insert_result ) ) {
			return $lookup_insert_result;
		}
		$address_insert_result = $this->order_billing_address_insert( $order_id, $args['order_billing_address'] );
		if ( is_wp_error( $address_insert_result ) ) {
			return $address_insert_result;
		}
		$inserted_order = $this->get_by_primary_key( $order_id );
		do_action( 'storeengine/models/after_create_order', $inserted_order );

		return $inserted_order;
	}

	public function order_meta_insert( $order_id, $items ) {
		if ( is_array( $items ) && count( $items ) > 0 ) {
			foreach ( $items as $key => $item ) {
				$this->wpdb->insert( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					$this->wpdb->prefix . 'storeengine_orders_meta',
					[
						'order_id'   => $order_id,
						'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_value' => $item, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					],
					[ '%d', '%s', '%s' ]
				);

				if ( ! $this->wpdb->insert_id ) {
					return new WP_Error( 'failed-to-insert', $this->wpdb->last_error, 'storeengine' );
				}
			}
		}

		return false;
	}

	private function order_items_and_lookup_insert( $order_id, $order_item_details ) {
		foreach ( $order_item_details as $single_item ) {
			$this->wpdb->insert(
				$this->wpdb->prefix . 'storeengine_order_items',
				[
					'order_item_name' => get_the_title( $single_item['product_id'] ),
					'order_item_type' => 'product',
					'order_id'        => $order_id,
				],
				[ '%s', '%s', '%d' ]
			);

			$product_model     = new Product();
			$price_expiry_date = $product_model->get_price_expiry_date( $single_item['price_id'] );

			$result = $this->wpdb->insert(
				$this->wpdb->prefix . 'storeengine_order_product_lookup',
				[
					'order_item_id'         => $this->wpdb->insert_id,
					'order_id'              => $order_id,
					'product_id'            => $single_item['product_id'],
					'variation_id'          => $single_item['variation_id'] ?? 0,
					'price_id'              => $single_item['price_id'],
					'price'                 => $single_item['price'],
					'customer_id'           => is_user_logged_in() ? get_current_user_id() : '',
					'date_created'          => current_time( 'mysql' ),
					'product_qty'           => $single_item['product_qty'],
					'product_net_revenue'   => /*TODO: its needs to be calculated*/ 0,
					'product_gross_revenue' => /*TODO: its needs to be calculated*/ 0,
					'coupon_amount'         => $single_item['coupon_amount'] ?? 0,
					'tax_amount'            => $single_item['tax_amount'] ?? 0,
					'shipping_amount'       => $single_item['shipping_amount'] ?? 0,
					'shipping_tax_amount'   => $single_item['shipping_tax_amount'] ?? 0,
					'shipping_status'       => $single_item['shipping_status'] ?? '',
					'expire_date'           => $price_expiry_date ?? '0000-00-00 00:00:00',
				],
				[
					'%d', // order_item_id
					'%d', // order_id
					'%d', // product_id
					'%d', // validation_id
					'%d', // price_id
					'%f', // price
					'%d', // customer_id
					'%s', // date_created
					'%d', // product_qty
					'%f', // product_net_revenue
					'%f', // product_gross_revenue
					'%f', // coupon_amount
					'%f', // tax_amount
					'%f', // shipping_amount
					'%f', // shipping_tax_amount
					'%s', // shipping_status
					'%s', // expire_date
				]
			);
		}//end foreach

		if ( false === $result ) {
			return new WP_Error( 'failed-to-insert', $this->wpdb->last_error, 'storeengine' );
		}

		// There might be a bug present exception handling is not done properly

		return true;
	}

	public function order_billing_address_insert( $order_id, $billing_address ) {
		$this->wpdb->insert(
			$this->wpdb->prefix . 'storeengine_order_addresses',
			[

				'order_id'     => $order_id,
				'address_type' => 'Billing Address',
				'first_name'   => $billing_address['first_name'],
				'last_name'    => $billing_address['last_name'],
				'company'      => $billing_address['company'],
				'address_1'    => $billing_address['address_1'],
				'address_2'    => $billing_address['address_2'],
				'city'         => $billing_address['city'],
				'state'        => $billing_address['state'],
				'postcode'     => $billing_address['postcode'],
				'country'      => $billing_address['country'],
				'email'        => $billing_address['email'],
				'phone'        => $billing_address['phone'],

			],
			[
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			]
		);

		if ( ! $this->wpdb->insert_id ) {
			return new WP_Error( 'failed-to-insert', $this->wpdb->last_error, 'storeengine' );
		}

		return true;
	}

	public function get_order_hash( $order_id ) {
		global $wpdb;

		$order_hash = $wpdb->get_results( $wpdb->prepare( "SELECT hash FROM {$wpdb->prefix}storeengine_orders WHERE id = %d", $order_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $order_hash[0]['hash'];
	}

	public function order_meta_update( $order_id, $items ) {
		if ( is_array( $items ) && count( $items ) > 0 ) {
			foreach ( $items as $key => $item ) {
				if ( is_array( $item ) ) {
					$item = wp_json_encode( $item );
				}
				global $wpdb;
				$have_meta = $wpdb->get_results( $wpdb->prepare( "SELECT order_id, meta_key  FROM {$wpdb->prefix}storeengine_orders_meta WHERE order_id=%d AND meta_key=%s", intval( $order_id ), $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				if ( count( $have_meta ) === 0 ) {
					$this->wpdb->insert( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						$this->wpdb->prefix . 'storeengine_orders_meta',
						[
							'order_id'   => $order_id,
							'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
							'meta_value' => $item, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
						],
						[ '%d', '%s', '%s' ]
					);
				} else {
					$this->wpdb->update( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						$this->wpdb->prefix . 'storeengine_orders_meta',
						[
							'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
							'meta_value' => $item, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
						],
						[
							'order_id' => $order_id,
							'meta_key' => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						],
						[ '%s', '%s' ],
						[ '%d', '%s' ]
					);
				}//end if
			}//end foreach
			return true;
		}//end if
		return false;
	}

	public function update( int $id, array $args ) {
		$order_table_data = [
			'status'               => $args['status'],
			'currency'             => $args['currency'],
			'type'                 => $args['type'],
			'tax_amount'           => $args['tax_amount'],
			'total_amount'         => $args['total_amount'],
			'customer_id'          => $args['customer_id'],
			'billing_email'        => $args['billing_email'],
			'date_updated_gmt'     => current_time( 'mysql' ),
			'parent_order_id'      => $args['parent_order_id'],
			'payment_method'       => $args['payment_method'],
			'payment_method_title' => $args['payment_method_title'],
			'transaction_id'       => $args['transaction_id'],
			'ip_address'           => Helper::get_user_ip(),
			'user_agent'           => ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : null,
		];

		$this->wpdb->update(
			$this->wpdb->prefix . 'storeengine_orders',
			$order_table_data,
			[ 'id' => $id ],
			[
				'%s', // status
				'%s', // currency
				'%s', // type
				'%f', // tax_amount
				'%f', // total_amount
				'%d', // customer_id
				'%s', // billing_email
				'%s', // date_updated_gmt
				'%d', // parent_order_id
				'%s', // payment_method
				'%s', // payment_method_title
				'%s', // transaction_id
				'%s',  // ip_address
				'%s', // user_agent
			],
			[ '%d' ]
		);

		$meta_update_result        = $this->order_meta_update( $id, $args['meta'] );
		$order_items_update_result = $this->order_items_and_lookup_update( $id, $args['purchase_items'] );
		$address_update_result     = $this->order_billing_address_update( $id, $args['order_billing_address'] );
		if ( is_wp_error( $meta_update_result ) ) {
			return $meta_update_result->get_error_message();
		}
		if ( is_wp_error( $order_items_update_result ) ) {
			return $order_items_update_result->get_error_message();
		}
		if ( is_wp_error( $address_update_result ) ) {
			return $address_update_result->get_error_message();
		}

		$updated_order = $this->get_by_primary_key( $id );
		do_action( 'storeengine/models/after_update_order', $updated_order );

		return $updated_order;
	}

	private function order_items_and_lookup_update( int $order_id, $purchase_items ) {
		global $wpdb;
		$exist_order_items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_order_items  WHERE order_id=%d", $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( count( $exist_order_items ) === 0 ) {
			return new WP_Error( 'failed-to-update', __( 'No order items found with the provided ID', 'storeengine' ) );
		}
		foreach ( $purchase_items as $key => $item ) {
			$this->wpdb->update(
				$this->wpdb->prefix . 'storeengine_order_items',
				[
					'order_item_name' => get_the_title( $item->product_id ),
					'order_item_type' => 'product',
					'order_id'        => $order_id,
				],
				[
					'order_item_id' => $exist_order_items[ $key ]->order_item_id,
					'order_id'      => $order_id,
				],
				[ '%s', '%s', '%d' ],
				[ '%d', '%d' ]
			);
			$item = (object) $item;
			$this->wpdb->update(
				$this->wpdb->prefix . 'storeengine_order_product_lookup',
				[
					'order_item_id'         => $exist_order_items[ $key ]->order_item_id,
					'order_id'              => $order_id,
					'product_id'            => $item->product_id,
					'variation_id'          => $item->variation_id,
					'price_id'              => $item->price_id,
					'customer_id'           => is_user_logged_in() ? get_current_user_id() : null,
					'product_qty'           => $item->product_qty,
					'product_net_revenue'   => $item->product_net_revenue ?? 0,
					'product_gross_revenue' => $item->product_gross_revenue ?? 0,
					'coupon_amount'         => $item->coupon_amount,
					'tax_amount'            => $item->tax_amount ?? 0,
					'shipping_amount'       => $item->shipping_amount ?? 0,
					'shipping_tax_amount'   => $item->shipping_tax_amount ?? 0,
				],
				[
					'order_item_id' => $exist_order_items[ $key ]->order_item_id,
					'order_id'      => $order_id,
				],
				[
					'%d',
					'%d',
					'%d',
					'%d',
					'%d',
					'%d',
					'%s',
					'%d',
					'%f',
					'%f',
					'%f',
					'%f',
					'%f',
					'%f',
				],
				[
					'%d',
					'%d',
				]
			);
		}//end foreach

		return true;
	}

	public function order_billing_address_update( $order_id, $billing_address ) {
		global $wpdb;

		$result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix }storeengine_order_addresses  WHERE order_id=%d", $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( count( $result ) === 0 ) {
			return new WP_Error( 'failed-to-update', __( 'No order billing address found with the provided ID', 'storeengine' ) );
		}

		$billing_address = (array) $billing_address;

		$this->wpdb->update(
			$this->wpdb->prefix . 'storeengine_order_addresses',
			[
				'address_type' => 'Billing Address',
				'first_name'   => $billing_address['first_name'],
				'last_name'    => $billing_address['last_name'],
				'company'      => $billing_address['company'],
				'address_1'    => $billing_address['address_1'],
				'address_2'    => $billing_address['address_2'],
				'city'         => $billing_address['city'],
				'state'        => $billing_address['state'],
				'postcode'     => $billing_address['postcode'],
				'country'      => $billing_address['country'],
				'email'        => $billing_address['email'],
				'phone'        => $billing_address['phone'],
			],
			[
				'order_id' => $order_id,
				'id'       => 0,
			],
			[
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			],
			[
				'%d',
				'%d',
			]
		);

		$this->wpdb->update(
			$this->wpdb->prefix . 'storeengine_order_addresses',
			[
				'address_type' => 'shipping_address',
				'first_name'   => $shipping_address['first_name'],
				'last_name'    => $shipping_address['last_name'],
				'company'      => $shipping_address['company'],
				'address_1'    => $shipping_address['address_1'],
				'address_2'    => $shipping_address['address_2'],
				'city'         => $shipping_address['city'],
				'state'        => $shipping_address['state'],
				'postcode'     => $shipping_address['postcode'],
				'country'      => $shipping_address['country'],
				'email'        => $shipping_address['email'],
				'phone'        => $shipping_address['phone'],
			],
			[
				'order_id' => $order_id,
				'id'       => $shipping_address_id,
			],
			[
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			],
			[
				'%d',
				'%d',
			]
		);

		return true;
	}

	public function get_by_primary_key( $key_value ) {
		global $wpdb;

		$order_data = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_orders WHERE id = %d LIMIT 1;", $key_value ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $order_data ) {
			return [];
		}

		$order_data         = $order_data[0];
		$order_data['meta'] = [];

		$metas                               = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_orders_meta WHERE order_id = %d", $key_value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order_data['purchase_items']        = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_order_product_lookup WHERE order_id = %d", $key_value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$order_data['order_billing_address'] = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_order_addresses WHERE order_id = %d", $key_value ) )[0]; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( $metas as $meta_data ) {
			$order_data['meta'][ $meta_data->meta_key ] = $meta_data->meta_value; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- this is not a meta query.
		}


		if ( 'subscription' === $order_data['type'] ) {
			$order_data['billing_periods'] = $this->get_billing_periods_for_subscription( $order_data['id'] );
		}

		return $order_data;
	}

	public function get_order_id_from_hash( $hash ) {
		global $wpdb;

		$order_id = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}storeengine_orders WHERE hash = %s", $hash ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $order_id[0]['id'];
	}

	public function delete( ?int $id = null ) {
		if ( ! $id ) {
			return new WP_Error( 'failed-to-delete', __( 'No ID provided', 'storeengine' ) );
		}

		$order = $this->get_by_primary_key( $id );
		if ( is_wp_error( $order ) ) {
			return new WP_Error( 'failed-to-delete', __( 'No order found with the provided ID', 'storeengine' ) );
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$is_delete_order                 = $wpdb->delete( $wpdb->prefix . 'storeengine_orders', [ 'id' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$is_delete_order_meta            = $wpdb->delete( $wpdb->prefix . 'storeengine_orders_meta', [ 'order_id' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$is_delete_order_items           = $wpdb->delete( $wpdb->prefix . 'storeengine_order_items', [ 'order_id' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$is_delete_order_product_lookup  = $wpdb->delete( $wpdb->prefix . 'storeengine_order_product_lookup', [ 'order_id' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$is_delete_order_billing_address = $wpdb->delete( $wpdb->prefix . 'storeengine_order_addresses', [ 'order_id' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $is_delete_order && $is_delete_order_meta && $is_delete_order_items && $is_delete_order_product_lookup && $is_delete_order_billing_address ) {
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			return $is_delete_order;
		}
		// Something went wrong. Rollback.
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// return wp error with db error
		return new WP_Error( 'failed-to-delete', $wpdb->last_error );
	}

	public function get_all_orders( $per_page, $offset, $order_status, $type = 'order' ) {
		global $wpdb;

		if ( ! in_array( $type, [ 'order', 'refund_order' ], true ) ) {
			return [];
		}

		$args        = [ $type, 'draft', 'subscription' ];
		$order_query = '';

		if ( $order_status && 'any' !== $order_status ) {
			$order_query .= ' AND status = %s';
			$args[]       = $order_status;
		}

		$order_query .= ' ORDER BY date_created_gmt DESC';

		if ( $per_page && $offset ) {
			$order_query .= ' LIMIT %d, %d';
			$args[]       = $offset;
			$args[]       = $per_page;
		}

		$orders = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_orders WHERE type = %s AND status != %s AND type != %s {$order_query};", $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- query build above.

		foreach ( $orders as $key => $order ) {
			// Fetch meta data.
			$purchase_items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_order_product_lookup WHERE order_id = %d", $order['id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			// Prepare meta data for output.
			$orders[ $key ]['meta']                  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_orders_meta WHERE order_id = %d", $order['id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$orders[ $key ]['purchase_items']        = $this->prepare_purchase_items_for_response( $purchase_items );
			$orders[ $key ]['order_billing_address'] = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_order_addresses WHERE order_id = %d", $order['id'] ), ARRAY_A )[0]; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		return $orders;
	}

	public function prepare_purchase_items_for_response( $purchase_items ) {
		foreach ( $purchase_items as $key => $item ) {
			$purchase_items[ $key ]['product_type'] = get_post_meta( $item['product_id'], '_storeengine_product_type', true );
			$purchase_items[ $key ]['product_name'] = get_the_title( $item['product_id'] );
		}

		return $purchase_items;
	}


	public function get_orders_by_customer_id( int $customer_id ): array {
		global $wpdb;

		$all_order = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}storeengine_orders WHERE customer_id = %d AND status !='draft' AND type!='subscription'", $customer_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $all_order ) {
			return [];
		}

		$orders = [];
		foreach ( $all_order as $order ) {
			$orders[] = $this->get_by_primary_key( $order['id'] );
		}

		return $orders;
	}

	/**
	 * @param int $customer_id
	 *
	 * @return array
	 *
	 * @deprecated
	 * @see SubscriptionCollection
	 */
	public function get_subscriptions_by_customer_id( int $customer_id ): array {
		global $wpdb;

		$all_order = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_orders WHERE customer_id = %d AND type = 'subscription'", $customer_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$orders = [];
		foreach ( $all_order as $order ) {
			$orders[] = $this->get_by_primary_key( $order['id'] );
		}

		return $orders;
	}


	public function update_order_billing_details_by_key( $order_id, $key, $value ): bool {
		global $wpdb;

		$wpdb->update( $wpdb->prefix . 'storeengine_order_addresses', [ $key => $value ], [ 'order_id' => $order_id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return true;
	}

	public function get_purchase_items( $order_id ) {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_order_product_lookup WHERE order_id = %d", $order_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}


	public function get_orders_with_non_zero_payment(): array {
		global $wpdb;
		$orders = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}storeengine_orders WHERE total_amount > 0", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( $orders as $key => $order ) {
			$orders_meta                             = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_orders_meta WHERE order_id = %d", $order['id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$purchase_items                          = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_order_product_lookup WHERE order_id = %d", $order['id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$shipping_address                        = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_order_addresses WHERE order_id = %d", $order['id'] ), ARRAY_A )[0]; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$orders[ $key ]['meta']                  = $orders_meta;
			$orders[ $key ]['purchase_items']        = $purchase_items;
			$orders[ $key ]['order_billing_address'] = $shipping_address;
		}

		return $orders;
	}

	public static function add_order_meta( $order_id, $meta_key, $meta_value ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$wpdb->prefix . 'storeengine_orders_meta',
			[
				'order_id'   => $order_id,
				'meta_key'   => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $meta_value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			],
			[ '%d', '%s', '%s' ]
		);
	}

	public static function get_order_meta( $order_id, $meta_key ) {
		global $wpdb;
		$meta = $wpdb->get_results( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}storeengine_orders_meta WHERE order_id = %d AND meta_key = %s", $order_id, $meta_key ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		if ( ! $meta ) {
			return [];
		}

		return $meta[0]['meta_value'];
	}

	public static function update_order_meta( $order_id, $meta_key, $meta_value ) {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$wpdb->prefix . 'storeengine_orders_meta',
			[ 'meta_value' => $meta_value ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			[
				'order_id' => $order_id,
				'meta_key' => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			],
			[ '%s' ],
			[ '%d', '%s' ]
		);
	}

	public static function delete_order_meta( $order_id, $meta_key ) {
		global $wpdb;
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$wpdb->prefix . 'storeengine_orders_meta',
			[
				'order_id' => $order_id,
				'meta_key' => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			],
			[ '%d', '%s' ]
		);
	}


	public function get_subscription_orders(): array {
		global $wpdb;
		$orders = $wpdb->get_results( "SELECT id FROM {$wpdb->prefix}storeengine_orders WHERE type = 'subscription' AND status='active'", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $orders ) {
			return [];
		}
		$subscription_orders = [];
		foreach ( $orders as $order ) {
			$subscription_orders[] = $this->get_by_primary_key( $order['id'] );
		}

		return $subscription_orders;
	}


	public function get_subscription_orders_by_customer_id( int $customer_id ): array {
		global $wpdb;
		$all_order = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}storeengine_orders WHERE customer_id = %d AND status !='draft' AND type='subscription'", $customer_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! $all_order ) {
			return [];
		}

		$orders = [];
		foreach ( $all_order as $order ) {
			$orders[] = $this->get_by_primary_key( $order['id'] );
		}

		return $orders;
	}

	public function get_total_spent_by_customer_id( int $customer_id ) {
		global $wpdb;
		$total_spent = $wpdb->get_results( $wpdb->prepare( "SELECT SUM(total_amount) as total_spent FROM {$wpdb->prefix}storeengine_orders WHERE customer_id = %d", $customer_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $total_spent[0]['total_spent'];
	}

	public function cancel_subscription( $order_data ) {
		if ( 'subscription' !== $order_data['type'] ) {
			return new WP_Error( 'failed-to-cancel', __( 'This is not a subscription order', 'storeengine' ) );
		}

		do_action( 'storeengine/models/before_cancel_subscription', $order_data );
		$this->update( $order_data['id'], [ 'status' => 'cancelled' ] );

		return true;
	}

	public function get_renewal_subscriptions( int $current_time ) {
		global $wpdb;
		$order_ids = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT order_id
						FROM {$wpdb->prefix}storeengine_orders_meta om
						WHERE om.meta_key = 'next_payment_date'
						AND om.meta_value < %d;", $current_time ), ARRAY_A );
		if ( ! $order_ids ) {
			return [];
		}

		$subscriptions = [];
		foreach ( $order_ids as $order_id ) {
			$subscriptions[] = $this->get_by_primary_key( $order_id['order_id'] );
		}

		return $subscriptions;
	}

	public function order_meta_delete( $id, string $string ) {
		global $wpdb;
		$result = $wpdb->get_results( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}storeengine_orders_meta WHERE order_id = %d AND meta_key = %s", $id, $string ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $result ) {
			return new WP_Error( 'failed-to-delete', __( 'No meta found with the provided key', 'storeengine' ) );
		}

		return true;
	}

	public function products_purchased_by_customer( $id ): array {
		global $wpdb;
		$products = $wpdb->get_results( $wpdb->prepare( "SELECT product_id, SUM(product_qty) as total_qty FROM {$wpdb->prefix}storeengine_order_product_lookup WHERE order_id IN (SELECT id FROM {$wpdb->prefix}storeengine_orders WHERE customer_id = %d) GROUP BY product_id", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$formatted_products = [];
		foreach ( $products as $index => $product ) {
			$formatted_products[ $index ]['product_id']     = $product['product_id'];
			$formatted_products[ $index ]['product_name']   = get_the_title( $product['product_id'] );
			$formatted_products[ $index ]['total_quantity'] = $product['total_qty'];
		}

		return $formatted_products;
	}

	public function get_order_items( $order_id ) {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_order_product_lookup WHERE order_id = %d", $order_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public function is_subscription_order( $order_id ) {
		global $wpdb;
		$order = $wpdb->get_results( $wpdb->prepare( "SELECT type FROM {$wpdb->prefix}storeengine_orders WHERE id = %d", $order_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return 'subscription' === $order[0]['type'];
	}

	public function get_subscription_by_order_id( $order_id ): array {
		$subscription = $this->get_by_primary_key( $order_id );

		if ( 'subscription' !== $subscription['type'] || 'renewed' === $subscription['status'] ) {
			return [];
		}

		return $subscription;
	}


	public function get_billing_periods_for_subscription( $subscription_id ): array {
		global $wpdb;
		$billing_periods      = $wpdb->get_results( $wpdb->prepare( "SELECT id,total_amount,date_created_gmt,status  FROM {$wpdb->prefix}storeengine_orders WHERE parent_order_id = %d AND status = 'renewed'", $subscription_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$billing_periods_data = [];
		foreach ( $billing_periods as $key => $renewed ) {
			$billing_periods_data[ $key ]['id']           = $renewed['id'];
			$billing_periods_data[ $key ]['amount']       = Helper::currency_format( $renewed['total_amount'] );
			$billing_periods_data[ $key ]['renewal_date'] = $renewed['date_created_gmt'];
			$billing_periods_data[ $key ]['status']       = $renewed['status'];
		}

		return $billing_periods_data;
	}

	public function get_order_meta_and_lookup_details( array $args = [] ) {
		global $wpdb;

		$default = [
			'product_id' => $product_id ?? 0,
			'order_id'   => $order_id ?? 0,
		];

		$data = wp_parse_args( $args, $default );

		// @FIXME sort & limit to return last item.
		$result = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}storeengine_order_product_lookup WHERE order_id=%d AND product_id=%d",
				$data['order_id'],
				$data['product_id'],
			),
			ARRAY_A
		);

		if ( $result ) {
			$result = current( $result );
		}

		return $result;
	}

	public function update_shipping_status( int $order_id, int $product_id, string $shipping_status ) {
		global $wpdb;

		return $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'storeengine_order_product_lookup',
			array(
				'shipping_status' => $shipping_status,
			),
			array(
				'order_id'   => $order_id,
				'product_id' => $product_id,
			),
			array(
				'%s',
			),
			array(
				'%d',
				'%d',
			),
		);
	}

	public function get_single_product_shipping_status( $product_id, $order_id ) {
		global $wpdb;

		$result = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT shipping_status FROM {$wpdb->prefix}order_product_lookup WHERE product_id=%d AND order_id=%d",
				$product_id,
				$order_id,
			)
		);

		return $result ? current( $result ) : [];
	}

	public function is_expired( $customer_id, $price_id ): bool {
		global $wpdb;

		$expired = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT expire_date FROM {$wpdb->prefix}storeengine_order_product_lookup WHERE customer_id=%d AND price_id=%d",
				$customer_id,
				$price_id
			)
		);

		if ( ! $expired ) {
			return false;
		}

		$expired = current( $expired );

		if ( '0000-00-00 00:00:00' === $expired->expire_date ) {
			return true;
		}

		// compare current time with expire date in this '0000-00-00 00:00:00' format

		return strtotime( $expired->expire_date ) < time();
	}

	public function get_order_by_meta( $meta_key, $meta_value ): array {
		global $wpdb;
		$order_id = $wpdb->get_results( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}storeengine_orders_meta WHERE meta_key = %s AND meta_value = %s", $meta_key, $meta_value ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $order_id ) {
			return [];
		}

		return $this->get_by_primary_key( $order_id[0]['order_id'] );
	}
}
