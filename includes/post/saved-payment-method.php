<?php

namespace StoreEngine\Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Academy\Mpdf\Tag\P;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\AbstractPostHandler;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\PaymentTokens\PaymentTokens;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

class SavedPaymentMethod extends AbstractAjaxHandler {
	public function __construct() {
		$this->actions = [
			'payment_method/add'         => [
				'callback' => [ $this, 'add_payment_method' ],
				'fields'   => [ 'payment_method' => 'string' ],
			],
			'payment_method/delete'      => [
				'callback' => [ $this, 'delete_payment_method' ],
				'fields'   => [ 'token_id' => 'string' ],
			],
			'payment_method/set_default' => [
				'callback' => [ $this, 'set_default_payment_method' ],
				'fields'   => [ 'token_id' => 'string' ],
			],
		];
	}

	/**
	 * @param array $payload
	 *
	 * @return void
	 */
	protected function add_payment_method( array $payload ) {
		try {
			if ( empty( $payload['payment_method'] ) ) {
				throw new StoreEngineException( __( 'Payment method is required.', 'storeengine' ), 'payment_method_required' );
			}

			$gateway = Helper::get_payment_gateways()->get_available_payment_gateway( $payload['payment_method'] );

			if ( ! $gateway->supports( 'add_payment_method' ) && ! $gateway->supports( 'tokenization' ) ) {
				throw new StoreEngineException( __( 'Invalid payment gateway.', 'storeengine' ), 'invalid_payment_gateway' );
			}

			$gateway->validate_fields();

			$result = $gateway->add_payment_method( Formatting::clean( wp_unslash( $_POST ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			// @TODO add a notification handler.
			// result.found ? 'updated' : 'added'..
			// 'Payment method successfully added.'

			if ( ! empty( $result['redirect'] ) ) {
				wp_send_json_success( $result['redirect'] ); //phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			}
		} catch ( StoreEngineException $e ) {
			wp_send_json_error( [
				'message' => $e->getMessage(),
				'code'    => $e->get_wp_error_code(),
			] );
		}
	}

	protected function delete_payment_method( array $payload ) {
		$token = PaymentTokens::get( $payload['token_id'] );

		if ( is_null( $token ) || get_current_user_id() !== $token->get_user_id() ) {
			throw new StoreEngineException( __( 'Invalid payment method.', 'storeengine' ), 'invalid_payment_method' );
		} else {
			PaymentTokens::delete( $payload['token_id'] );
			// @TODO add a notification handler.
			// __( 'Payment method deleted.', 'storeengine' )
		}

		wp_safe_redirect( Helper::get_account_endpoint_url( 'payment-methods' ) );
		exit();
	}

	protected function set_default_payment_method( array $payload ) {
		$token = PaymentTokens::get( absint( $payload['token_id'] ) );

		if ( is_null( $token ) || get_current_user_id() !== $token->get_user_id() ) {
			throw new StoreEngineException( __( 'Invalid payment method.', 'storeengine' ), 'invalid_payment_method' );
		} else {
			PaymentTokens::set_users_default( $token->get_user_id(), absint( $payload['token_id'] ) );
			// @TODO add a notification handler.
			// __( 'This payment method was successfully set as your default.', 'storeengine' )
		}

		wp_safe_redirect( Helper::get_account_endpoint_url( 'payment-methods' ) );
		exit();
	}
}

// End of file saved-payment-method.php.
