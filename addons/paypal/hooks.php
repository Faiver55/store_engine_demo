<?php

namespace StoreEngine\Addons\Paypal;

use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusTransitionException;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Helper;

class Hooks {
	protected static ?Hooks $instance = null;
	protected GatewayPaypal $gateway;

	public static function init( $gateway ) {
		if ( null === self::$instance ) {
			self::$instance          = new self();
			self::$instance->gateway = $gateway;

			if ( $gateway->is_enabled ) {
				add_filter( 'storeengine/frontend_scripts_payment_method_data', [ self::$instance, 'gateway_javascript_params' ] );
				add_action( 'storeengine/payment_gateway/paypal/save_settings', [ self::$instance, 'setup_paypal_webhooks' ] );
			}
		}
	}

	public function gateway_javascript_params( $payment_method ) {
		if ( $this->gateway->is_available() ) {
			$payment_method['paypal'] = [
				'client_id' => $this->gateway->get_option( 'client_id_' . ( $this->gateway->get_option( 'is_production', true ) ? 'production' : 'sandbox' ), '' ),
			];
		}

		return $payment_method;
	}

	/**
	 * @param Order $order
	 *
	 * @return void
	 * @throws StoreEngineInvalidOrderStatusException
	 * @throws StoreEngineInvalidOrderStatusTransitionException
	 * @throws StoreEngineException
	 */
	public function change_payment_status( Order $order ) {
		if ( 'paypal' !== $order->get_payment_method() ) {
			return;
		}

		$paypal        = PaypalExpressService::init( $this->gateway );
		$paypal_result = $paypal->get_order( $order->get_meta( '_paypal_order_id', true, 'edit' ) );

		if ( ! empty( $paypal_result ) && 'COMPLETED' !== $paypal_result->status ) {
			return;
		}

		$order_context = new OrderContext( $order->get_status() );
		$order_context->proceed_to_next_status( 'payment_initiate', $order );
		$order_context->proceed_to_next_status( 'payment_confirm', $order );
		$order->save();
	}

	/**
	 * @throws StoreEngineException
	 */
	public function setup_paypal_webhooks( $gateway ) {
		PaypalExpressService::init( $gateway )->create_webhook( [
			'url'         => get_site_url() . '/wp-json/storeengine/v1/payment/paypal/webhook',
			'event_types' => [
				[ 'name' => 'BILLING.SUBSCRIPTION.ACTIVATED' ],
				[ 'name' => 'PAYMENT.SALE.COMPLETED' ],
				[ 'name' => 'BILLING.SUBSCRIPTION.PAYMENT.FAILED' ],
			],
		] );
	}
}
