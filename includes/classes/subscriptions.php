<?php

namespace StoreEngine\Classes;

use StoreEngine\Addons\Subscription\Classes\Subscription;

class Subscriptions extends Orders {

	public function get( $conditions = [] ): array {
		global $wpdb;

		$conditions = array_merge( [
			'status' => [
				'condition' => '!=',
				'formatter' => '%s',
				'value'     => 'draft',
			],
			'type'   => [
				'condition' => '=',
				'formatter' => '%s',
				'value'     => 'subscription',
			],
		], $conditions);

		$offset = ( $this->page - 1 ) * $this->limit;

		// @TODO cache (must be same as Abstract Entity (order object format) to reduce multiple query.

		$sql_conditions = $this->generate_conditions( $conditions );
		$results = $wpdb->get_results($wpdb->prepare("{$this->query()} WHERE {$sql_conditions} GROUP BY o.id ORDER BY o.id DESC LIMIT %d OFFSET %d;", $this->limit, $offset), ARRAY_A); // phpcs:ignore

		if ( ! $results ) {
			return [];
		}

		$orders = [];

		foreach ( $results as $result ) {
			if ( ! empty( $result['o_id'] ) ) {
				$order    = new Subscription( $result['o_id'] );
				$orders[] = $order;
			}
		}

		return $orders;
	}

	public function get_total_orders_count( $conditions = [] ) {
		global $wpdb;

		$conditions = array_merge( [
			'status' => [
				'condition' => '!=',
				'formatter' => '%s',
				'value'     => 'draft',
			],
			'type'   => [
				'condition' => '=',
				'formatter' => '%s',
				'value'     => 'subscription',
			],
		], $conditions);

		$sql_conditions = $this->generate_conditions( $conditions );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- query prepared above.
		$result = $wpdb->get_row("SELECT COUNT(DISTINCT o.id) as order_totals FROM {$wpdb->prefix}storeengine_orders o
				LEFT JOIN {$wpdb->prefix}storeengine_order_addresses b ON b.order_id = o.id AND b.address_type = 'billing'
				LEFT JOIN {$wpdb->prefix}storeengine_order_addresses s ON s.order_id = o.id AND s.address_type = 'shipping'
				LEFT JOIN {$wpdb->prefix}storeengine_order_operational_data p ON p.order_id = o.id
				LEFT JOIN {$wpdb->prefix}storeengine_orders_meta m ON m.order_id = o.id WHERE $sql_conditions;");
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $result ) {
			return 0;
		}

		return $result->order_totals;
	}

}
