<?php

namespace StoreEngine\Addons\Affiliate\Post;

use StoreEngine\Classes\AbstractPostHandler;
use StoreEngine\Utils\Helper;
use StoreEngine\Addons\Affiliate\Models\Affiliate as AffiliateModel;
use StoreEngine\Addons\Affiliate\Models\Referral;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Affiliate extends AbstractPostHandler {
	public function __construct() {
		$this->actions = [
			'apply_for_affiliation'  => [
				'callback'             => [ $this, 'apply_for_affiliation' ],
				'allow_visitor_action' => false,
			],
			'register_for_affiliate' => [
				'callback'             => [ $this, 'register_for_affiliate' ],
				'allow_visitor_action' => true,
				'fields'               => [
					'first_name' => 'string',
					'last_name'  => 'string',
					'email'      => 'string',
					'password'   => 'string',
				],
			],
		];
	}

	public function apply_for_affiliation() {
		$affiliate = AffiliateModel::save( [ 'user_id' => get_current_user_id() ] );
		if ( is_wp_error( $affiliate ) ) {
			wp_die( esc_html( $affiliate->get_error_message() ), esc_html__( 'Error', 'storeengine' ), [
				'back_link' => true,
			] );
		}
		Referral::save([
			'affiliate_id'     => $affiliate['affiliate_id'],
			'referral_post_id' => Helper::get_settings('shop_page'),
		]);
		wp_safe_redirect( Helper::sanitize_referer_url( wp_get_referer() ) );
	}

	public function register_for_affiliate( $payload ) {
		if ( ! isset($payload['first_name'], $payload['last_name'], $payload['email'], $payload['password']) ) {
			wp_die( esc_html__( 'All fields are required.', 'storeengine' ), esc_html__( 'Error', 'storeengine' ), [
				'back_link' => true,
			] );
		}

		$user = get_user_by( 'email', $payload['email'] );

		if ( $user ) {
			$payload['user_id'] = $user->ID;
		}

		$inserted = AffiliateModel::save( $payload );

		if ( is_wp_error( $inserted ) ) {
			wp_die( esc_html( $inserted->get_error_message() ), esc_html__( 'Error', 'storeengine' ), [
				'back_link' => true,
			] );
		}

		Referral::save([
			'affiliate_id'     => $inserted['affiliate_id'],
			'referral_post_id' => Helper::get_settings('shop_page'),
		]);

		$redirect_url = add_query_arg( 'registration_success', 'true', Helper::sanitize_referer_url( wp_get_referer() ) );

		wp_safe_redirect( $redirect_url );
		exit;
	}
}
