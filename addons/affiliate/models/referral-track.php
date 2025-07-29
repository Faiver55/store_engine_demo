<?php

namespace StoreEngine\Addons\Affiliate\models;

use WP_Error;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReferralTrack {

	public static function get_referral_tracks( $args ) {
		global $wpdb;

		$defaults = [
			'track_id' => null,
			'count'    => false,
			'offset'   => 0,
			'per_page' => 10,
			'status'   => 'any',
			'search'   => '',
		];

		$args = wp_parse_args( $args, $defaults );

		if ( $args['count'] ) {
			return $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}storeengine_affiliate_referrals_tracks;" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		$query = "SELECT
				track_id,
				created_at,
				referral_ip,
				status
				FROM
					{$wpdb->prefix}storeengine_affiliate_referrals_tracks";

		if ( $args['track_id'] ) {
			$query .= $wpdb->prepare(' WHERE track_id = %d', $args['track_id']);
			$track  = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			return $track ?? [];
		}

		if ( 'any' !== $args['status'] ) {
			$query .= $wpdb->prepare( ' WHERE status = %s AND referral_ip LIKE %s', $args['status'], '%' . $wpdb->esc_like( $args['search'] ) . '%' );
		} else {
			$query .= $wpdb->prepare( ' WHERE referral_ip LIKE %s', '%' . $wpdb->esc_like( $args['search'] ) . '%' );
		}

		$tracks = $wpdb->get_results( $wpdb->prepare( "{$query} ORDER BY created_at DESC LIMIT %d, %d", $args['offset'], $args['per_page'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- query prepared above.

		return $tracks ?? [];
	}

	public static function get_clicks_by_referral_id( $referral_id = null, $offset = 0, $per_page = 10, $status = 'any', $search = '' ) {
		global $wpdb;

		if ( ! $referral_id ) {
			return new WP_Error( 'failed-to-fetch', __( 'No referral id provided', 'storeengine' ) );
		}

		$query = "SELECT track_id, created_at, referral_ip, status FROM {$wpdb->prefix}storeengine_affiliate_referrals_tracks";

		if ( 'any' !== $status ) {
			$query .= $wpdb->prepare( ' WHERE referral_id = %d AND status = %s AND referral_ip LIKE %s', $referral_id, $status, '%' . $wpdb->esc_like( $search ) . '%' );
		} else {
			$query .= $wpdb->prepare( ' WHERE referral_id = %d AND referral_ip LIKE %s', $referral_id, '%' . $wpdb->esc_like( $search ) . '%' );
		}

		$tracks = $wpdb->get_results( $wpdb->prepare( "{$query} ORDER BY created_at DESC LIMIT %d, %d;", $offset, $per_page ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Query prepared above.

		return $tracks ?? [];
	}

	public static function save( $args = [] ) {
		global $wpdb;

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			"{$wpdb->prefix}storeengine_affiliate_referrals_tracks",
			[
				'referral_id' => $args['referral_id'],
				'referral_ip' => $args['referral_ip'],
				'status'      => $args['status'],
			],
			[ '%d', '%s', '%s' ]
		);
		if ( ! $inserted ) {
			return new WP_Error( 'failed-to-insert', $wpdb->last_error );
		}
		$track_id = $wpdb->insert_id;

		return self::get_referral_tracks([
			'track_id' => $track_id,
		]);
	}

	public static function update( int $id, array $args ) {
		global $wpdb;

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"{$wpdb->prefix}storeengine_affiliate_referrals_tracks",
			$args,
			[ 'track_id' => $id ],
			[ '%s' ]
		);

		if ( ! $updated ) {
			return new WP_Error( 'failed-to-update', $wpdb->last_error );
		}

		return $updated;
	}
}
