<?php

namespace StoreEngine\Addons\Affiliate\Models;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Commission {
	public static function get_commission( $args = [] ) {
		global $wpdb;

		$defaults = [
			'commission_id' => null,
			'order_id'      => null,
			'count'         => false,
			'offset'        => 0,
			'per_page'      => 10,
			'status'        => 'any',
			'search'        => '',
		];

		$args = wp_parse_args( $args, $defaults );

		$query = "SELECT
        c.commission_id,
        c.affiliate_id,
        u.display_name,
        u.user_email,
        c.created_at,
        c.order_id,
        o.total_amount,
        a.commission_rate,
        c.commission_amount,
        c.status
    FROM
        {$wpdb->prefix}storeengine_affiliate_commissions c
    LEFT JOIN
        {$wpdb->prefix}storeengine_affiliates a ON c.affiliate_id = a.affiliate_id
    LEFT JOIN
        {$wpdb->prefix}storeengine_orders o ON c.order_id = o.id
    LEFT JOIN
        {$wpdb->prefix}users u ON a.user_id = u.ID";

		if ( $args['commission_id'] ) {
			$query .= $wpdb->prepare(' WHERE commission_id = %d', $args['commission_id']);

			$commission = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

			return $commission ?? [];
		}

		if ( $args['order_id'] ) {
			$commissions = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_affiliate_commissions WHERE order_id = %d", $args['order_id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			return $commissions ?? [];
		}

		if ( $args['count'] ) {
			return $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}storeengine_affiliate_commissions;" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		if ( 'any' !== $args['status'] ) {
			$query .= $wpdb->prepare( ' WHERE c.status = %s AND u.display_name LIKE %s', $args['status'], '%' . $wpdb->esc_like( $args['search'] ) . '%' );
		} else {
			$query .= $wpdb->prepare( ' WHERE u.display_name LIKE %s', '%' . $wpdb->esc_like( $args['search'] ) . '%' );
		}

		$all_commissions = $wpdb->get_results( $wpdb->prepare( "{$query} ORDER BY c.created_at DESC LIMIT %d, %d", $args['offset'], $args['per_page'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- query prepared above.

		return $all_commissions ?? [];
	}

	public static function save( $args = [] ) {
		$order_id = $args['order_id'];

		global $wpdb;

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'storeengine_affiliate_commissions',
			[
				'affiliate_id'      => $args['affiliate_id'],
				'order_id'          => $order_id,
				'commission_amount' => $args['commission_amount'],
				'status'            => 'pending',
			],
			[
				'%d',
				'%d',
				'%f',
				'%s',
			]
		);
		if ( ! $inserted ) {
			return new WP_Error( 'failed-to-insert', $wpdb->last_error );
		}
		$commission_id = $wpdb->insert_id;

		return self::get_commission([
			'commission_id' => $commission_id,
		]);
	}

	public static function update( int $id, array $args ) {
		global $wpdb;

		$updated = $wpdb->update( "{$wpdb->prefix}storeengine_affiliate_commissions", $args, [ 'commission_id' => $id ], [ '%s' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $updated ) {
			return new WP_Error( 'failed-to-update', $wpdb->last_error );
		}

		return $updated;
	}
}
