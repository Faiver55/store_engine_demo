<?php

namespace StoreEngine\Utils\Traits;

use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Classes\Customer as CustomerClass;
use StoreEngine\Classes\Customers;
use StoreEngine\Classes\Order as OrderClass;
use StoreEngine\Classes\Orders;
use StoreEngine\Classes\Subscriptions;
use WP_User_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Customer {

	/**
	 * Get Customer by id.
	 *
	 * @param int|null $id Customer id.
	 * @param bool $in_session
	 *
	 * @return false|CustomerClass
	 */
	public static function get_customer( ?int $id = null, bool $in_session = false ) {
		if ( null === $id ) {
			$id = get_current_user_id();
		}

		return new CustomerClass( $id, $in_session );
	}

	public static function get_customers( $args = array() ): array {
		$args = wp_parse_args( $args, array(
			'number'  => 10,
			'orderby' => 'ID',
			'order'   => 'DESC',
		) );

		return ( new Customers() )->get_customers( $args );
	}


	/**
	 * Get orders of customer.
	 *
	 * @param int $customer_id [Optional] Customer id. Default to zero (current user).
	 *
	 * @return Subscription[]
	 */
	public static function get_customer_subscriptions( int $customer_id = 0, int $page = 1, int $per_page = 10 ): array {
		return ( new Subscriptions( $page, $per_page ) )->get( [
			'customer_id' => [
				'condition' => '=',
				'formatter' => '%d',
				'value'     => 0 !== $customer_id ? $customer_id : get_current_user_id(),
			],
			'type'        => [
				'condition' => '=',
				'formatter' => '%s',
				'value'     => 'subscription',
			],
		] );
	}

	/**
	 * Get top customers.
	 *
	 * @return CustomerClass[]
	 */
	public static function get_top_customers(): array {
		return ( new Customers() )->get_top_customers();
	}

	public static function get_new_customers_count( $start_date, $end_date ): int {
		return ( new Customers() )->get_new_customers_count( $start_date, $end_date );
	}

	/**
	 * Get all user ids who have `billing_email` set to any of the email passed in array.
	 *
	 * @param string[] $emails List of emails to check against.
	 *
	 * @return array
	 */
	public static function get_user_ids_for_billing_email( array $emails ): array {
		$emails      = array_unique( array_map( fn( $email ) => strtolower( sanitize_email( $email ) ), $emails ) );
		$users_query = new WP_User_Query( [
			'fields'     => 'ID',
			'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => 'billing_email',
					'value'   => $emails,
					'compare' => 'IN',
				],
			],
		] );

		return array_unique( $users_query->get_results() );
	}
}
