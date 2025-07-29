<?php

namespace StoreEngine\Classes;

use StoreEngine\Utils\Helper;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Customers {

	public function get_customers( array $query ) {
		// @XXX Why getting only user's with orders.
		//      Why not listing customer registered directly without purchase history.
		$users     = get_users( array_merge( $query, array(
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'storeengine_total_orders',
					'value'   => 0,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			),
		) ) );
		$customers = array();
		foreach ( $users as $user ) {
			$customer = new Customer();
			$customer->set_data( $user );
			$customers[] = $customer;
		}

		return $customers;
	}

	/**
	 * Get top customers based total spent.
	 *
	 * @return Customer[]
	 */
	public function get_top_customers(): array {
		$users = get_users( [
			'meta_key'   => 'storeengine_total_spent', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'    => 'meta_value_num',
			'order'      => 'DESC',
			'number'     => 10,
			'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => 'storeengine_total_spent',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				],
			],
		] );

		$top_customers = array();
		foreach ( $users as $user ) {
			$customer = new Customer();
			$customer->set_data( $user );
			$top_customers[] = $customer;
		}

		return $top_customers;
	}

	public function get_new_customers_count( string $start_date, string $end_date ) {
		// @TODO replace with WP_User_Query or direct sql for performance.
		$users = get_users( [
			'fields'     => 'ID', // For performance improvement (temp see above todo).
			'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => 'storeengine_total_orders',
					'value'   => 0,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				],
			],
			'date_query' => [
				[
					'before'    => $end_date,
					'after'     => $start_date,
					'inclusive' => true,
				],
			],
		] );

		return count( $users );
	}
}
