<?php

namespace StoreEngine\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\Customer;
use StoreEngine\Utils\Helper;

class Customers extends AbstractAjaxHandler {
	public function __construct() {
		$this->actions = [
			'create_new_customer' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'create_new_customer' ],
				'fields'     => [
					'first_name' => 'string',
					'last_name'  => 'string',
					'email'      => 'email',
					'password'   => 'string',
					'address_1'  => 'string',
					'address_2'  => 'string',
					'city'       => 'string',
					'state'      => 'string',
					'postcode'   => 'string',
					'country'    => 'string',
					'phone'      => 'string',
				],
			],
			'top_customers'       => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'top_customers' ],
			],
			'customer_login'      => [
				'allow_visitor_action' => true,
				'callback'             => [ $this, 'customer_login_form_handler' ],
				'fields'               => [
					'username'    => 'string',
					'password'    => 'string',
					'remember'    => 'string',
					'redirect_to' => 'url',
				],
			],
		];
	}

	public function create_new_customer( $payload ) {
		if ( empty( $payload['email'] ) || empty( $payload['password'] ) ) {
			wp_send_json_error( esc_html__( 'Email and Password are required', 'storeengine' ) );
		}

		if ( ! is_email( $payload['email'] ) ) {
			wp_send_json_error( esc_html__( 'Invalid email', 'storeengine' ) );
		}

		$customer = new Customer();
		$customer->set_first_name( $payload['first_name'] );
		$customer->set_last_name( $payload['last_name'] );
		$customer->set_display_name( $payload['first_name'] );
		$customer->set_email( $payload['email'] );
		$customer->set_password( $payload['password'] );
		$customer = $customer->save();

		if ( is_wp_error( $customer ) ) {
			wp_send_json_error( $customer->get_error_message() );
		}

		wp_send_json_success( [
			'id'              => $customer->get_id(),
			'user_login'      => $customer->get_username(),
			'user_nicename'   => $customer->get_nicename(),
			'user_email'      => $customer->get_email(),
			'user_url'        => $customer->get_url(),
			'user_registered' => $customer->get_user_registered(),
			'display_name'    => $customer->get_display_name(),
			'first_name'      => $customer->get_first_name(),
			'last_name'       => $customer->get_last_name(),
		] );
	}

	public function top_customers() {
		$customers     = Helper::get_top_customers();
		$top_customers = [];

		foreach ( $customers as $customer ) {
			$top_customers[] = [
				'id'           => $customer->get_id(),
				'avatar'       => get_avatar_url( $customer->get_email(), [ 'size' => 50 ] ),
				'name'         => ( ! empty( $customer->get_first_name() ) && ! empty( $customer->get_last_name() ) ) ? $customer->get_first_name() . ' ' . $customer->get_last_name() : null,
				'billing_name' => $customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name(),
				'total_spent'  => $customer->get_total_spent(),
			];
		}

		wp_send_json_success( $top_customers );
	}

	public function customer_login_form_handler( $payload ) {
		wp_clear_auth_cookie();

		if ( empty( $payload['username'] ) ) {
			wp_send_json_error( esc_html__( 'Username is required', 'storeengine' ) );
		}

		if ( empty( $payload['password'] ) ) {
			wp_send_json_error( esc_html__( 'Password is required', 'storeengine' ) );
		}

		do_action( 'storeengine/shortcode/before_customer_signon' );

		$user = wp_signon( [
			'user_login'    => $payload['username'],
			'user_password' => $payload['password'],
			'remember'      => ! empty( $payload['remember'] ),
		], is_ssl() );

		if ( is_wp_error( $user ) ) {
			wp_send_json_error( $user->get_error_message() );
		}

		wp_set_current_user( $user->ID );

		do_action( 'storeengine/shortcode/after_customer_signon' );

		if ( ! empty( $payload['redirect_to'] ) ) {
			$redirect_to = $payload['redirect_to'];
		} elseif ( $user->has_cap( 'manage_options' ) ) {
			$redirect_to = admin_url();
		} else {
			$redirect_to = Helper::get_dashboard_url();
		}

		/** @noinspection HttpUrlsUsage */
		if ( is_ssl() && str_contains( $redirect_to, 'wp-admin' ) && str_starts_with( $redirect_to, 'http://' ) ) {
			$redirect_to = preg_replace( '|^http://|', 'https://', $redirect_to );
		}

		wp_send_json_success( [
			'message'      => esc_html__( 'You have logged in successfully. Redirecting...', 'storeengine' ),
			'redirect_url' => esc_url( wp_validate_redirect( $redirect_to, home_url() ) ),
		] );
	}
}
