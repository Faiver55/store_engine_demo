<?php

namespace StoreEngine\Addons\Affiliate\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Addons\Affiliate\models\Affiliate;
use StoreEngine\Addons\Affiliate\Models\Referral;
use StoreEngine\Utils\Helper;

class AffiliateAjax extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [
			'get_all_users'               => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_all_users' ],
			],
			'get_all_affiliates'          => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_all_affiliates' ],
				'fields'     => [
					'page'     => 'integer',
					'per_page' => 'integer',
					'status'   => 'string',
					'search'   => 'string',
				],
			],
			'get_an_affiliate'            => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_an_affiliate' ],
				'fields'     => [ 'affiliate_id' => 'integer' ],
			],
			'add_an_affiliate_by_id'      => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'add_an_affiliate_by_id' ],
				'fields'     => [
					'user_id' => 'integer',
				],
			],
			'add_an_affiliate'            => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'add_an_affiliate' ],
				'fields'     => [
					'first_name' => 'string',
					'last_name'  => 'string',
					'email'      => 'string',
					'password'   => 'string',
				],
			],
			'update_an_affiliates_status' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'update_an_affiliates_status' ],
				'fields'     => [
					'affiliate_id' => 'integer',
					'status'       => 'string',
				],
			],
		];
	}

	public function update_an_affiliates_status( $payload ) {
		$affiliate_id = ! empty( $payload['affiliate_id'] ) ? $payload['affiliate_id'] : '';
		$status       = ! empty( $payload['status'] ) ? $payload['status'] : '';

		if ( ! $affiliate_id ) {
			wp_send_json_error( esc_html__( 'Affiliate ID is required.', 'storeengine' ) );
		}
		if ( ! $status ) {
			wp_send_json_error( esc_html__( 'Affiliate status is required.', 'storeengine' ) );
		}

		$ret = Affiliate::update( $affiliate_id, [ 'status' => $status ] );

		if ( is_wp_error( $ret ) ) {
			wp_send_json_error( $ret->get_error_message() );
		}

		do_action( 'storeengine/addons/affiliate/update_status', $affiliate_id, $status );

		wp_send_json_success( $ret );
	}

	public function add_an_affiliate( $payload ) {
		$args = [
			'first_name' => ! empty( $payload['first_name'] ) ? $payload['first_name'] : '',
			'last_name'  => ! empty( $payload['last_name'] ) ? $payload['last_name'] : '',
			'email'      => ! empty( $payload['email'] ) ? $payload['email'] : '',
			'password'   => ! empty( $payload['password'] ) ? $payload['password'] : '',
		];

		if ( empty( array_filter( array_values( $args ) ) ) ) {
			wp_send_json_error( esc_html__( 'Missing required fields.', 'storeengine' ) );
		}

		$inserted = Affiliate::save( $args );
		if ( is_wp_error( $inserted ) ) {
			wp_send_json_error( $inserted->get_error_message() );
		}

		$ref = Referral::save([
			'affiliate_id'     => $inserted['affiliate_id'],
			'referral_post_id' => Helper::get_settings('shop_page'),
		]);

		$inserted = array_merge($inserted, array_intersect_key(
			$ref, array_flip(
				[ 'referral_code', 'referral_url' ]
			)
		));

		wp_send_json_success( $inserted );
	}

	public function add_an_affiliate_by_id( $payload ) {
		if ( empty( $payload['user_id'] ) ) {
			wp_send_json_error( esc_html__( 'User ID is required.', 'storeengine' ) );
		}

		$inserted = Affiliate::save( [ 'user_id' => $payload['user_id'] ] );

		if ( is_wp_error( $inserted ) ) {
			wp_send_json_error( $inserted->get_error_message() );
		}

		$ref = Referral::save([
			'affiliate_id'     => $inserted['affiliate_id'],
			'referral_post_id' => Helper::get_settings('shop_page'),
		]);

		$inserted = array_merge($inserted, array_intersect_key(
			$ref, array_flip(
				[ 'referral_code', 'referral_url' ]
			)
		));

		wp_send_json_success( $inserted );
	}

	public function get_an_affiliate( $payload ) {
		if ( ! empty( $payload['affiliate_id'] ) ) {
			wp_send_json_error( esc_html__( 'Affiliate ID is required.', 'storeengine' ) );
		}

		$affiliate = Affiliate::get_affiliates( [ 'affiliate_id' => $payload['affiliate_id'] ] );

		if ( is_wp_error( $affiliate ) ) {
			wp_send_json_error( $affiliate->get_error_message() );
		}

		wp_send_json_success( $affiliate );
	}

	public function get_all_affiliates( $payload ) {
		$page     = ! empty( $payload['page'] ) ? $payload['page'] : 1;
		$per_page = ! empty( $payload['per_page'] ) ? $payload['per_page'] : 10;
		$status   = ! empty( $payload['status'] ) ? $payload['status'] : 'any';
		$search   = ! empty( $payload['search'] ) ? $payload['search'] : '';
		$offset   = ( $page - 1 ) * $per_page;

		header( 'X-WP-TOTAL: ' . Affiliate::get_affiliates( [ 'count' => true ] ) );
		$affiliates = Affiliate::get_affiliates( [
			'offset'   => $offset,
			'per_page' => $per_page,
			'status'   => $status,
			'search'   => $search,
		] );
		wp_send_json_success(Referral::modify_referral_url( $affiliates ) );
	}

	public function get_all_users() {
		$users    = [];
		$wp_users = get_users();

		foreach ( $wp_users as $user ) {
			$users[] = [
				'value' => $user->ID,
				'label' => $user->display_name,
				'email' => $user->user_email,
				'hash'  => md5( $user->user_email ),
			];
		}

		if ( ! $users ) {
			wp_send_json_error( esc_html__( 'No users found.', 'storeengine' ) );
		}

		wp_send_json_success( $users );
	}
}
