<?php

namespace StoreEngine\Addons\Affiliate\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Addons\Affiliate\models\AffiliateReport;

class AffiliateReportAjax extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [
			'get_all_affiliate_reports' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_all_affiliate_reports' ],
			],
			'get_an_affiliate_report'   => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_an_affiliate_report' ],
				'fields'     => [
					'report_id' => 'integer',
				],
			],
			'add_an_affiliate_report'   => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'add_an_affiliate_report' ],
				'fields'     => [
					'affiliate_id' => 'integer',
					'referral_id'  => 'integer',
				],
			],
		];
	}

	public function add_an_affiliate_report( $payload ) {
		if ( empty( $payload['affiliate_id'] ) ) {
			wp_send_json_error( esc_html__( 'Affiliate ID is required.', 'storeengine' ) );
		}

		if ( empty( $payload['referral_id'] ) ) {
			wp_send_json_error( esc_html__( 'Referral ID is required.', 'storeengine' ) );
		}

		$report = AffiliateReport::save( $payload );

		if ( is_wp_error( $report ) ) {
			wp_send_json_error( $report );
		}

		wp_send_json_success( $report );
	}

	public function get_an_affiliate_report( $payload ) {
		if ( empty( $payload['report_id'] ) ) {
			wp_send_json_error( esc_html__( 'Report ID is required.', 'storeengine' ) );
		}

		$report = AffiliateReport::get_affiliate_reports( $payload['report_id'] );

		if ( is_wp_error( $report ) ) {
			wp_send_json_error( $report );
		}

		wp_send_json_success( $report );
	}

	public function get_all_affiliate_reports() {
		wp_send_json_success( AffiliateReport::get_affiliate_reports() );
	}
}
