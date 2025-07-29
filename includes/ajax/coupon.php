<?php

namespace StoreEngine\Ajax;

if ( ! defined('ABSPATH') ) {
	exit;
}

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\Helper;

class Coupon extends AbstractAjaxHandler {


	public function __construct() {
		$this->actions = [
			'apply_coupon_form'     => [
				'callback'             => [ $this, 'apply_coupon_form' ],
				'allow_visitor_action' => true,
				'fields'               => [
					'coupon_code' => 'string',
				],
			],
			'remove_applied_coupon' => [
				'callback'             => [ $this, 'remove_applied_coupon' ],
				'allow_visitor_action' => true,
				'fields'               => [
					'coupon_code' => 'string',
				],
			],
			'verify_coupon'         => [
				'callback' => [ $this, 'verify_coupon' ],
				'fields'   => [
					'coupon_code'    => 'string',
					'subtotal'       => 'float',
					'total_quantity' => 'integer',
				],
			],
		];
	}

	protected function apply_coupon_form( $payload ) {
		if ( empty( $payload['coupon_code'] ) ) {
			wp_send_json_error( esc_html__( 'Please enter a coupon code.', 'storeengine' ) );
		}

		$cart = Helper::cart();
		if ( $cart->is_coupon_applied($payload['coupon_code']) ) {
			wp_send_json_error(esc_html__('Coupon is already using!', 'storeengine'));
		}
		$result = $cart->apply_coupon($payload['coupon_code']);
		if ( is_wp_error($result) ) {
			wp_send_json_error($result->get_error_message());
		}

		wp_send_json_success();
	}

	protected function remove_applied_coupon( $payload ) {
		if ( empty( $payload['coupon_code'] ) ) {
			wp_send_json_error( esc_html__( 'Invalid request.', 'storeengine' ) );
		}

		$cart   = Helper::cart();
		$result = $cart->remove_coupon($payload['coupon_code']);

		if ( is_wp_error($result) ) {
			wp_send_json_error($result);
		}

		wp_send_json_success( __( 'Coupon has been removed.', 'storeengine' ) );
	}

	protected function verify_coupon( $payload ) {
		if ( empty($payload['coupon_code']) ) {
			wp_send_json_error(esc_html__('Coupon code is required', 'storeengine'));
		}

		if ( empty($payload['total_quantity']) ) {
			wp_send_json_error(esc_html__('Total quantity is required', 'storeengine'));
		}
		if ( empty($payload['subtotal']) ) {
			wp_send_json_error(esc_html__('Subtotal is required', 'storeengine'));
		}

		$coupon_code = $payload['coupon_code'];
		$coupon      = new \StoreEngine\classes\Coupon($coupon_code);

		remove_action('storeengine/validate_coupon', [ \StoreEngine\Frontend\Coupon::class, 'check_cart_minimum_requirement' ]);
		$is_valid = $coupon->validate_coupon();
		add_action('storeengine/validate_coupon', [ \StoreEngine\Frontend\Coupon::class, 'check_cart_minimum_requirement' ]);
		if ( ! is_wp_error($is_valid) ) {
			try {
				\StoreEngine\Frontend\Coupon::check_minimum_requirements($coupon, $payload['total_quantity'], $payload['subtotal']);
			} catch ( StoreEngineException $e ) {
				$is_valid = $e->toWpError();
			}
		}
		if ( is_wp_error($is_valid) ) {
			wp_send_json_error([
				'message' => $is_valid->get_error_message(),
			]);
		}

		wp_send_json_success([
			'coupon_code'   => $coupon->get_code(),
			'coupon_id'     => $coupon->get_id(),
			'coupon_type'   => $coupon->get_discount_type(),
			'coupon_amount' => $coupon->get_amount(),
		]);
	}
}
