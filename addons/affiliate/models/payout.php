<?php

namespace StoreEngine\Addons\Affiliate\models;

use StoreEngine\Classes\AbstractModel;
use StoreEngine\Addons\Affiliate\Helper as HelperAddon;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\Helper;
use WP_Error;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Payout {
	public static function get_payouts( $args = [] ) {
		global $wpdb;

		// Set default values for the arguments
		$defaults = [
			'payout_id' => null,
			'user_id'   => null,
			'offset'    => 0,
			'per_page'  => 10,
			'count'     => false,
			'status'    => 'any',
			'search'    => '',
		];

		// Merge the passed arguments with the defaults
		$args = wp_parse_args( $args, $defaults );

		if ( $args['count'] ) {
			return $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}storeengine_affiliate_payouts;" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		$query = "SELECT
        p.payout_id,
        p.affiliate_id,
        u.display_name,
        u.user_email,
        p.payment_method,
        p.payout_amount,
        p.transaction_id,
        p.created_at,
        p.status
    FROM
        {$wpdb->prefix}storeengine_affiliate_payouts p
    LEFT JOIN
        {$wpdb->prefix}storeengine_affiliates a ON p.affiliate_id = a.affiliate_id
    LEFT JOIN
        {$wpdb->prefix}users u ON a.user_id = u.ID";

		if ( $args['payout_id'] ) {
			$query .= $wpdb->prepare( ' WHERE p.payout_id = %d', $args['payout_id'] );
			$payout = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

			return $payout ?? [];
		}

		if ( $args['user_id'] ) {
			$query  .= $wpdb->prepare( ' WHERE a.user_id = %d', $args['user_id'] );
			$payouts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$query, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				ARRAY_A
			);
			return $payouts ?? [];
		}

		if ( 'any' !== $args['status'] ) {
			$query .= $wpdb->prepare( ' WHERE p.status = %s AND u.display_name LIKE %s', $args['status'], '%' . $wpdb->esc_like( $args['search'] ) . '%' );
		} else {
			$query .= $wpdb->prepare( ' WHERE u.display_name LIKE %s', '%' . $wpdb->esc_like( $args['search'] ) . '%' );
		}

		$payouts = $wpdb->get_results( $wpdb->prepare( "{$query} ORDER BY p.created_at DESC LIMIT %d, %d", $args['offset'], $args['per_page'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- query prepared above.

		return $payouts ?? [];
	}

	public static function save( $args = [] ) {
		global $wpdb;

		try {
			$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				"{$wpdb->prefix}storeengine_affiliate_payouts",
				[
					'affiliate_id'   => $args['affiliate_id'],
					'payout_amount'  => $args['payout_amount'],
					'payment_method' => $args['payment_method'],
					'transaction_id' => HelperAddon::generate_random_code( 'payouts', 12 ),
					'status'         => 'pending',
				],
				[
					'%d',
					'%f',
					'%s',
					'%s',
					'%s',
				]
			);

			if ( ! $inserted ) {
				return new WP_Error( 'failed-to-insert', esc_html( $wpdb->last_error ) );
			}

			$payout_id = $wpdb->insert_id;

			return self::get_payouts([
				'payout_id' => $payout_id,
			]);
		} catch ( StoreEngineException $e ) {
			return $e->toWpError();
		}
	}

	public static function update( int $id, array $args ) {
		global $wpdb;

		$updated = $wpdb->update( "{$wpdb->prefix}storeengine_affiliate_payouts", $args, [ 'payout_id' => $id ], [ '%s' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $updated ) {
			return new WP_Error( 'failed-to-update', $wpdb->last_error );
		}

		return $updated;
	}
}
