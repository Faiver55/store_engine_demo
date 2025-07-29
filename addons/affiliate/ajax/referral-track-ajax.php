<?php

namespace StoreEngine\Addons\Affiliate\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Addons\Affiliate\models\ReferralTrack;

class ReferralTrackAjax extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [
			'get_all_clicks'        => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_all_clicks' ],
				'fields'     => [
					'page'     => 'integer',
					'per_page' => 'integer',
					'status'   => 'string',
					'search'   => 'string',
				],
			],
			'get_a_click'           => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_a_click' ],
				'fields'     => [
					'track_id' => 'integer',
				],
			],
			'get_a_referral_clicks' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_a_referral_clicks' ],
				'fields'     => [
					'referral_id' => 'integer',
					'page'        => 'integer',
					'per_page'    => 'integer',
					'status'      => 'string',
					'search'      => 'string',
				],
			],
		];
	}

	public function get_a_click( $payload ) {
		if ( empty( $payload['track_id'] ) ) {
			wp_send_json_error( esc_html__( 'Track ID is required.', 'storeengine' ) );
		}

		$referral = ReferralTrack::get_referral_tracks([
			'track_id' => $payload['track_id'],
		]);

		if ( is_wp_error( $referral ) ) {
			wp_send_json_error( $referral->get_error_message() );
		}

		wp_send_json_success( $referral );
	}

	public function get_a_referral_clicks( $payload ) {
		$referral_id = ( ! empty( $payload['referral_id'] ) ? $payload['referral_id'] : '' );
		$page        = ( ! empty( $payload['page'] ) ? $payload['page'] : 1 );
		$per_page    = ( ! empty( $payload['per_page'] ) ? $payload['per_page'] : 10 );
		$status      = ( ! empty( $payload['status'] ) ? $payload['status'] : 'any' );
		$search      = ( ! empty( $payload['search'] ) ? $payload['search'] : '' );
		$offset      = ( $page - 1 ) * $per_page;

		// Set the x-wp-total header
		header( 'X-WP-TOTAL: ' . ReferralTrack::get_referral_tracks([ 'count' => true ]));
		wp_send_json_success( ReferralTrack::get_clicks_by_referral_id( $referral_id, $offset, $per_page, $status, $search ) );
	}

	public function get_all_clicks( $payload ) {
		$page     = ( ! empty( $payload['page'] ) ? $payload['page'] : 1 );
		$per_page = ( ! empty( $payload['per_page'] ) ? $payload['per_page'] : 10 );
		$status   = ( ! empty( $payload['status'] ) ? $payload['status'] : 'any' );
		$search   = ( ! empty( $payload['search'] ) ? $payload['search'] : '' );
		$offset   = ( $page - 1 ) * $per_page;

		// Set the x-wp-total header
		header( 'X-WP-TOTAL: ' . ReferralTrack::get_referral_tracks_count() );
		wp_send_json_success( ReferralTrack::get_referral_tracks([
			'per_page' => $per_page,
			'status'   => $status,
			'search'   => $search,
			'offset'   => $offset,
		]));
	}
}
