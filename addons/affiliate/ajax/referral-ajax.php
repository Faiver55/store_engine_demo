<?php

namespace StoreEngine\Addons\Affiliate\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Addons\Affiliate\models\Referral;

class ReferralAjax extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [
			'get_all_referrals' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_all_referrals' ],
				'fields'     => [
					'page'     => 'absint',
					'per_page' => 'integer',
					'search'   => 'string',
				],
			],
			'get_an_referral'   => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_an_referral' ],
				'fields'     => [
					'referral_id' => 'integer',
				],
			],
			'add_an_referral'   => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'add_an_referral' ],
				'fields'     => [
					'affiliate_id'     => 'integer',
					'referral_post_id' => 'integer',
				],
			],
		];
	}

	public function add_an_referral( $payload ) {
		$args = [
			'affiliate_id'     => ! empty( $payload['affiliate_id'] ) ? $payload['affiliate_id'] : null,
			'referral_post_id' => ! empty( $payload['referral_post_id'] ) ? $payload['referral_post_id'] : 0,
		];

		if ( empty( array_filter( array_values( $args ) ) ) ) {
			wp_send_json_error( esc_html__( 'Missing required fields.', 'storeengine' ) );
		}

		$ret = Referral::save( $args );

		if ( is_wp_error( $ret ) ) {
			wp_send_json_error( $ret->get_error_message() );
		}

		wp_send_json_success( $ret );
	}

	public function get_an_referral( $payload ) {
		if ( ! empty( $payload['referral_id'] ) ) {
			wp_send_json_error( esc_html__( 'Referral ID is required.', 'storeengine' ) );
		}

		$referral = Referral::get_referrals([
			'referral_id' => $payload['referral_id'],
		]);

		if ( is_wp_error( $referral ) ) {
			wp_send_json_error( $referral->get_error_message() );
		}

		wp_send_json_success( $referral );
	}

	public function get_all_referrals( $payload ) {
		$page     = ! empty( $payload['page'] ) ? $payload['page'] : 1;
		$per_page = ! empty( $payload['per_page'] ) ? $payload['per_page'] : 10;
		$search   = ! empty( $payload['search'] ) ? $payload['search'] : '';
		$offset   = ( $page - 1 ) * $per_page;

		// Set the x-wp-total header
		header( 'X-WP-TOTAL: ' . Referral::get_referrals([ 'count' => true ]));
		wp_send_json_success( Referral::get_referrals([
			'offset'   => $offset,
			'per_page' => $per_page,
			'search'   => $search,
		]));
	}
}
