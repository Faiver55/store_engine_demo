<?php

namespace StoreEngine\Schedules;

use StoreEngine;
use StoreEngine\Classes\Order\OrderItemProduct;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Order {

	public static function init() {
		// Schedule lookup table update.
		add_action( 'storeengine/checkout/after_place_order', [ __CLASS__, 'set_product_lookup_schedule' ] );
		add_action( 'storeengine/api/after_create_order', [ __CLASS__, 'set_product_lookup_schedule' ] );
		add_action( 'storeengine/api/after_update_order', [ __CLASS__, 'set_product_lookup_schedule' ] );
		add_action( 'storeengine/api/order/add_order_item', [ __CLASS__, 'set_product_lookup_schedule' ] );
		add_action( 'storeengine/api/order/update_order_item', [ __CLASS__, 'set_product_lookup_schedule' ] );
		add_action( 'storeengine/api/order/delete_order_item', [ __CLASS__, 'set_remove_lookup_schedule' ], 10, 3 );

		// Update lookup table.
		add_action( 'storeengine/store_product_lookup', [ __CLASS__, 'store_product_lookup' ] );
		add_action( 'storeengine/remove_product_lookup_data', [ __CLASS__, 'remove_product_lookup_data' ], 10, 2 );
	}

	public static function set_product_lookup_schedule( $order ) {
		if ( ! StoreEngine::init()->queue()->get_next( 'storeengine/store_product_lookup', [ $order->get_id() ] ) ) {
			StoreEngine::init()->queue()->schedule_single( time() + 1, 'storeengine/store_product_lookup', [ $order->get_id() ] );
		}
	}

	public static function set_remove_lookup_schedule( $order, $line_item, $line_item_id ) {
		if ( 'line_item' !== $line_item->get_type() ) {
			return;
		}

		if ( ! $line_item instanceof OrderItemProduct ) {
			return;
		}

		$args = [ $order->get_id(), $line_item_id ];

		if ( ! StoreEngine::init()->queue()->get_next( 'storeengine/remove_product_lookup_data', $args ) ) {
			StoreEngine::init()->queue()->schedule_single( time() + 1, 'storeengine/remove_product_lookup_data', $args );
		}
	}

	public static function store_product_lookup( $order_id ) {
		global $wpdb;

		$order = Helper::get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$order_items = $order->get_line_product_items();

		if ( empty( $order_items ) ) {
			return;
		}

		$defaults     = [
			'order_item_id'         => 0,
			'order_id'              => $order->get_id(),
			'product_id'            => 0,
			'variation_id'          => 0,
			'price_id'              => 0,
			'price'                 => 0.00,
			'customer_id'           => 0,
			'date_created'          => null,
			'product_qty'           => 1,
			'product_net_revenue'   => 0.00,
			'product_gross_revenue' => 0.00,
			'coupon_amount'         => 0.00,
			'tax_amount'            => 0.00,
			'shipping_amount'       => 0.00,
			'shipping_tax_amount'   => 0.00,
		];
		$formats      = [
			'%d', // order_item_id
			'%d', // order_id
			'%d', // product_id
			'%d', // variation_id
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
		];
		$values       = [];
		$placeholders = [];

		$total_qty = array_sum( array_map( fn( $item ) => $item->get_quantity(), $order_items ) );

		foreach ( $order_items as $order_item ) {
			$item_coupon_amount    = Formatting::format_decimal( ( $order_item->get_quantity( 'edit' ) / $total_qty ) * $order->get_discount_total( 'edit' ) );
			$product_net_revenue   = Formatting::format_decimal( $order_item->get_total( 'edit' ) );
			$product_gross_revenue = Formatting::format_decimal( $product_net_revenue + $order_item->get_total_tax( 'edit' ) );
			$value                 = array_merge( $defaults, [
				'order_id'              => $order->get_id(),
				'order_item_id'         => $order_item->get_id(),
				'product_id'            => $order_item->get_product_id(),
				'price_id'              => $order_item->get_price_id(),
				'variation_id'          => $order_item->get_variation_id(),
				'customer_id'           => $order->get_customer_id(),
				'price'                 => $order_item->get_price(),
				'product_gross_revenue' => $product_gross_revenue,
				'product_net_revenue'   => $product_net_revenue,
				'coupon_amount'         => $item_coupon_amount,
				'tax_amount'            => $order_item->get_total_tax( 'edit' ),
				'product_qty'           => $order_item->get_quantity( 'edit' ),
				'date_created'          => current_time( 'mysql' ),
			] );
			$values                = array_merge( $values, array_values( $value ) );
			$placeholders[]        = '(' . implode( ',', $formats ) . ')';
		}

		$placeholders = implode( ', ', $placeholders );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Placeholders prepared above.
		$wpdb->query(
			$wpdb->prepare(
				"
				INSERT INTO {$wpdb->prefix}storeengine_order_product_lookup
				    (
				     `order_item_id`,
				     `order_id`,
				     `product_id`,
				     `variation_id`,
				     `price_id`,
				     `price`,
				     `customer_id`,
				     `date_created`,
				     `product_qty`,
				     `product_net_revenue`,
				     `product_gross_revenue`,
				     `coupon_amount`,
				     `tax_amount`,
				     `shipping_amount`,
				     `shipping_tax_amount`
				    )
				    VALUES {$placeholders}
				ON DUPLICATE KEY UPDATE
					`order_id` = VALUES(`order_id`),
					`product_id` = VALUES(`product_id`),
					`variation_id` = VALUES(`variation_id`),
					`price_id` = VALUES(`price_id`),
					`price` = VALUES(`price`),
					`customer_id` = VALUES(`customer_id`),
					`date_created` = VALUES(`date_created`),
					`product_qty` = VALUES(`product_qty`),
					`product_net_revenue` = VALUES(`product_net_revenue`),
					`product_gross_revenue` = VALUES(`product_gross_revenue`),
					`coupon_amount` = VALUES(`coupon_amount`),
					`tax_amount` = VALUES(`tax_amount`),
					`shipping_amount` = VALUES(`shipping_amount`),
					`shipping_tax_amount` = VALUES(`shipping_tax_amount`);
				",
				$values
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	public static function remove_product_lookup_data( $order_id, $line_item_id ) {
		global $wpdb;

		$wpdb->delete(
			$wpdb->prefix . 'storeengine_order_product_lookup',
			[
				'order_item_id' => $line_item_id,
				'order_id'      => $order_id,
			],
			[ '%d', '%d' ]
		);

		$order = Helper::get_order( $order_id );

		if ( $order && ! is_wp_error( $order ) ) {
			self::set_product_lookup_schedule( $order );
		}
	}
}
