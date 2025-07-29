<?php

namespace StoreEngine\Addons\Affiliate\Models;

use StoreEngine\Classes\AbstractModel;
use StoreEngine\Addons\Affiliate\Helper as HelperAddon;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\Helper;
use WP_Error;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Referral {

	public static function get_referrals( $args = [] ) {
		global $wpdb;

		$defaults = [
			'referral_id' => null,
			'count'       => false,
			'offset'      => 0,
			'per_page'    => 10,
			'search'      => '',
		];

		$args = wp_parse_args( $args, $defaults );

		if ( $args['count'] ) {
			return $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}storeengine_affiliate_referrals;" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		$query = "SELECT
				r.referral_id,
				u.display_name, u.user_email,
				r.referral_code,
				r.referral_post_id,
				r.created_at,
				r.click_counts
			FROM
				{$wpdb->prefix}storeengine_affiliate_referrals r
			LEFT JOIN
				{$wpdb->prefix}storeengine_affiliates a ON r.affiliate_id = a.affiliate_id
			LEFT JOIN
				{$wpdb->prefix}users u ON a.user_id = u.ID";

		if ( $args['referral_id'] ) {
			$query .= $wpdb->prepare( ' WHERE referral_id = %d', $args['referral_id'] );

			$referral = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

			$referral = $referral ? self::modify_referral_url( [ $referral ] ) : [];

			return $referral ? reset( $referral ) : null;
		}

		$query .= $wpdb->prepare( ' WHERE u.display_name LIKE %s', '%' . $wpdb->esc_like( $args['search'] ) . '%' );

		$referrals = $wpdb->get_results( $wpdb->prepare( "{$query} ORDER BY r.created_at DESC LIMIT %d, %d", $args['offset'], $args['per_page'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- query prepared above.

		return self::modify_referral_url( $referrals );
	}

	public static function modify_referral_url( $query_result = [] ) {
		if ( empty( $query_result ) ) {
			return [];
		}

		foreach ( $query_result as &$result ) {
			if ( isset( $result['referral_post_id'] ) ) {
				$result['referral_url'] = self::create_link( $result['referral_code'], $result['referral_post_id'] );
				unset( $result['referral_post_id'] );
			}
		}

		return $query_result;
	}

	public static function create_link( $referral_code, $referral_post_id ) {
		$permalink_structure        = get_option( 'permalink_structure' );
		$url_parameter_prefix_style = empty( $permalink_structure ) ? '&' : '?';

		$referral_query_string = sprintf( '%s%s=%s', $url_parameter_prefix_style, STOREENGINE_AFFILIATE_COOKIE_KEY, $referral_code );
		return esc_url( get_permalink( $referral_post_id ) . $referral_query_string );
	}

	public static function save( $args = [] ) {
		global $wpdb;

		try {
			$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				"{$wpdb->prefix}storeengine_affiliate_referrals",
				[
					'affiliate_id'     => $args['affiliate_id'],
					'referral_code'    => HelperAddon::generate_random_code( 'referrals' ),
					'referral_post_id' => $args['referral_post_id'],
					'click_counts'     => 0,
				],
				[
					'%d',
					'%s',
					'%d',
					'%d',
				]
			);

			if ( ! $inserted ) {
				return new WP_Error( 'failed-to-insert', $wpdb->last_error );
			}

			return self::get_referrals([ 'referral_id' => $wpdb->insert_id ]);
		} catch ( StoreEngineException $e ) {
			return $e->toWpError();
		}
	}

	public static function update( int $id, array $args ) {
		global $wpdb;

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"{$wpdb->prefix}storeengine_affiliate_referrals",
			$args,
			[ 'referral_id' => $id ],
			[ '%d' ]
		);

		if ( ! $updated ) {
			return new WP_Error( 'failed-to-update', $wpdb->last_error );
		}

		return $updated;
	}
}
