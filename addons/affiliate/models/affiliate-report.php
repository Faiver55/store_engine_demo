<?php

namespace StoreEngine\Addons\Affiliate\Models;

use StoreEngine\Classes\AbstractModel;
use StoreEngine\Utils\Helper;
use WP_Error;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AffiliateReport {

	public static function get_affiliate_reports( $report_id = null, $affiliate_id = null ) {
		global $wpdb;

		if ( $report_id ) {
			$report = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_affiliate_report WHERE report_id = %d", $report_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $report ?? [];
		}

		if ( $affiliate_id ) {
			$report = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_affiliate_report WHERE affiliate_id = %d", $affiliate_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $report ?? [];
		}

		$all_reports = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}storeengine_affiliate_report;", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $all_reports ?? [];
	}

	public static function save( $args = [] ) {
		global $wpdb;
		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"{$wpdb->prefix}storeengine_affiliate_report",
			[
				'affiliate_id'      => $args['affiliate_id'],
				'referral_id'       => $args['referral_id'],
				'total_clicks'      => 0,
				'total_sales'       => 0,
				'total_commissions' => 0,
				'current_balance'   => 0,
			],
			[
				'%d',
				'%d',
				'%d',
				'%f',
				'%f',
				'%f',
			]
		);
		if ( ! $inserted ) {
			return new WP_Error( 'failed-to-insert', $wpdb->last_error );
		}
		$report_id = $wpdb->insert_id;

		return self::get_affiliate_reports( $report_id );
	}

	public static function update( int $id, array $args, string $by = 'report_id' ) {
		global $wpdb;

		$where = [ $by => $id ];

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"{$wpdb->prefix}storeengine_affiliate_report",
			$args,
			$where,
			[ '%d' ]
		);

		if ( ! $updated ) {
			return new WP_Error( 'failed-to-update', $wpdb->last_error );
		}

		return $updated;
	}

	public static function get_current_balance( int $affiliate_id ) {
		if ( ! $affiliate_id ) {
			return new WP_Error( 'failed-to-fetch', __( 'No affiliate id provided', 'storeengine' ) );
		}

		global $wpdb;

		$current_balance = $wpdb->get_var( $wpdb->prepare( "SELECT current_balance FROM {$wpdb->prefix}storeengine_affiliate_report WHERE affiliate_id = %d", $affiliate_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return (float) ( $current_balance ?? 0 );
	}

}
