<?php

namespace StoreEngine\Utils\traits;

use StoreEngine\Payment\Gateways\PaymentGateway;
use StoreEngine\Payment_Gateways;

trait Gateway {

	public static function get_payment_gateways(): Payment_Gateways {
		return Payment_Gateways::init();
	}

	public static function get_payment_gateway( string $id ): ?PaymentGateway {
		return self::get_payment_gateways()->get_gateway( $id );
	}

	/**
	 * Get payment gateway class by order data.
	 *
	 * @param int|\StoreEngine\Classes\Order $order Order instance.
	 * @return PaymentGateway|bool
	 */
	public static function get_payment_gateway_by_order( $order ) {
		if ( self::get_payment_gateways() ) {
			$payment_gateways = self::get_payment_gateways()->payment_gateways();
		} else {
			$payment_gateways = [];
		}

		if ( ! is_object( $order ) ) {
			$order = self::get_order( absint( $order ) );
		}

		return is_a( $order, '\StoreEngine\Classes\Order' ) && $order->get_payment_method() && isset( $payment_gateways[ $order->get_payment_method() ] ) ? $payment_gateways[ $order->get_payment_method() ] : false;
	}
}
