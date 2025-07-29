<?php

namespace StoreEngine\Addons\Affiliate\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Addons\Affiliate\models\Payout;
use StoreEngine\Addons\Affiliate\models\AffiliateReport;

class PayoutAjax extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [
			'get_all_payouts'                => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_all_payouts' ],
				'fields'     => [
					'page'     => 'absint',
					'per_page' => 'integer',
					'status'   => 'string',
					'search'   => 'string',
				],
			],
			'get_a_payout'                   => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_a_payout' ],
				'fields'     => [
					'payout_id' => 'absint',
				],
			],
			'add_a_payout'                   => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'add_a_payout' ],
				'fields'     => [
					'affiliate_id'   => 'absint',
					'payout_amount'  => 'float',
					'payment_method' => 'string',
				],
			],
			'update_a_payouts_status'        => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'update_a_payouts_status' ],
				'fields'     => [
					'payout_id' => 'absint',
					'status'    => 'string',
				],
			],
			'get_affiliates_current_balance' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_affiliates_current_balance' ],
				'fields'     => [
					'affiliate_id' => 'absint',
				],
			],
		];
	}

	public function update_a_payouts_status( $payload ) {
		$payout_id = ! empty( $payload['payout_id'] ) ? $payload['payout_id'] : '';
		$status    = ! empty( $payload['status'] ) ? $payload['status'] : '';

		if ( ! $payout_id ) {
			wp_send_json_error( esc_html__( 'Payout ID is required.', 'storeengine' ) );
		}
		if ( ! $status ) {
			wp_send_json_error( esc_html__( 'Payout status is required.', 'storeengine' ) );
		}

		if ( 'completed' === $status ) {
			$this->update_commission_report( $payout_id );
		}

		$ret = Payout::update( $payout_id, [ 'status' => $status ] );

		if ( is_wp_error( $ret ) ) {
			wp_send_json_error( $ret->get_error_message() );
		}

		do_action('storeengine/addons/affiliate/update_payout_status', $payout_id, $status );

		wp_send_json_success( $ret );
	}

	public function add_a_payout( $payload ) {
		$args = [
			'affiliate_id'   => ! empty( $payload['affiliate_id'] ) ? $payload['affiliate_id'] : '',
			'payout_amount'  => ! empty( $payload['payout_amount'] ) ? $payload['payout_amount'] : '',
			'payment_method' => ! empty( $payload['payment_method'] ) ? $payload['payment_method'] : '',
		];

		if ( empty( array_filter( array_values( $args ) ) ) ) {
			wp_send_json_error( esc_html__( 'Missing required fields.', 'storeengine' ) );
		}

		if ( ! in_array( $payload['payment_method'], [ 'PayPal', 'Bank Transfer', 'Stripe', 'Check Payment', 'E-Check' ], true ) ) {
			wp_send_json_error( esc_html__( 'Invalid payment method.', 'storeengine' ) );
		}

		if ( $payload['payout_amount'] <= 0 ) {
			wp_send_json_error( esc_html__( 'Invalid amount. Amount must be greater then zero.', 'storeengine' ) );
		}

		$ret = ( new Payout() )->save( $args );

		if ( is_wp_error( $ret ) ) {
			wp_send_json_error( $ret->get_error_message() );
		}

		wp_send_json_success( $ret );
	}

	public function get_a_payout( $payload ) {
		if ( ! empty( $payload['payout_id'] ) ) {
			wp_send_json_error( esc_html__( 'Payout ID missing.', 'storeengine' ) );
		}

		$payout = new Payout();
		$ret    = Payout::get_payouts([
			'payout_id' => $payload['payout_id'],
		]);

		if ( is_wp_error( $ret ) ) {
			wp_send_json_error( $ret->get_error_message() );
		}

		wp_send_json_success( $ret );
	}

	public function get_all_payouts( $payload ) {
		$page     = ! empty( $payload['page'] ) ? $payload['page'] : 1;
		$per_page = ! empty( $payload['per_page'] ) ? $payload['per_page'] : 10;
		$status   = ! empty( $payload['status'] ) ? $payload['status'] : 'any';
		$search   = ! empty( $payload['search'] ) ? $payload['search'] : '';
		$offset   = ( $page - 1 ) * $per_page;
		$payout   = new Payout();

		// Set the x-wp-total header
		header( 'X-WP-TOTAL: ' . Payout::get_payouts([ 'count' => true ]) );
		wp_send_json_success( Payout::get_payouts([
			'offset'   => $offset,
			'per_page' => $per_page,
			'status'   => $status,
			'search'   => $search,
		]));
	}

	public function get_affiliates_current_balance( $payload ) {
		if ( $payload['affiliate_id'] ) {
			wp_send_json_success( AffiliateReport::get_current_balance( $payload['affiliate_id'] ) );
		}

		wp_send_json_error( esc_html__( 'Affiliate ID missing.', 'storeengine' ) );
	}

	protected function update_commission_report( $payout_id ) {
		if ( ! $payout_id ) {
			return false;
		}

		$payout_row       = Payout::get_payouts([ 'payout_id' => $payout_id ]);
		$affiliate_report = AffiliateReport::get_affiliate_reports([ 'affiliate_id' => $payout_row['affiliate_id'] ]);

		return AffiliateReport::update( $payout_row['affiliate_id'], [
			'current_balance' => $affiliate_report['current_balance'] - $payout_row['payout_amount'],
		] );
	}
}
