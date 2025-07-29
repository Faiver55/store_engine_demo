<?php
declare( strict_types=1 );

namespace StoreEngine\Classes\Order;

use StoreEngine\Classes\AbstractOrder;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\StringUtil;

/**
 * Class PaymentInfo.
 */
class PaymentInfo {
	/**
	 * This array must contain all the names of the files in the CardIcons directory (without extension),
	 * except 'unknown'.
	 */
	private const KNOWN_CARD_BRANDS = array(
		'amex',
		'diners',
		'discover',
		'interac',
		'jcb',
		'mastercard',
		'visa',
	);

	/**
	 * Get info about the card used for payment on an order.
	 *
	 * @param AbstractOrder $order The order in question.
	 *
	 * @return array
	 */
	public static function get_card_info( AbstractOrder $order ): array {
		$method = $order->get_payment_method();

		// @XXX support for wc-payment plugin.
		if ( 'woocommerce_payments' === $method ) {
			$info = self::get_wcpay_card_info( $order );
		} else {
			/**
			 * Filter to allow payment gateways to provide payment card info for an order.
			 *
			 * @param array|null        $info  The card info.
			 * @param AbstractOrder $order The order.
			 */
			$info = apply_filters( 'storeengine/order_payment_card_info', [], $order );

			if ( ! is_array( $info ) ) {
				$info = [];
			}
		}

		$defaults = [
			'payment_method' => $method,
			'brand'          => '',
			'icon'           => '',
			'last4'          => '',
		];
		$info     = wp_parse_args( $info, $defaults );

		if ( empty( $info['icon'] ) ) {
			$info['icon'] = self::get_card_icon( $info['brand'] );
		}

		return $info;
	}

	/**
	 * Generate a CSS-compatible SVG icon of a card brand.
	 *
	 * @param string $brand The brand of the card.
	 *
	 * @return string
	 */
	private static function get_card_icon( ?string $brand ): string {
		$brand = strtolower( (string) $brand );

		if ( ! in_array( $brand, self::KNOWN_CARD_BRANDS, true ) ) {
			$brand = 'unknown';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return base64_encode( file_get_contents( __DIR__ . "/card-icons/{$brand}.svg" ) );
	}

	/**
	 * Get info about the card used for payment on an order, when the payment gateway is WooPayments.
	 * This adds support for woocommerce-payments plugin.
	 *
	 * @see https://docs.stripe.com/api/charges/object#charge_object-payment_method_details
	 *
	 * @param AbstractOrder $order The order in question.
	 *
	 * @return array
	 */
	private static function get_wcpay_card_info( AbstractOrder $order ): array {
		if ( 'woocommerce_payments' !== $order->get_payment_method() ) {
			return [];
		}

		// For testing purposes: if WooCommerce Payments development mode is enabled, an order meta item with
		// key '_wcpay_payment_details' will be used if it exists as a replacement for the call to the Stripe
		// API's 'get intent' endpoint. The value must be the JSON encoding of an array simulating the
		// "payment_details" part of the response from the endpoint.
		$stored_payment_details = defined( 'WCPAY_DEV_MODE' ) ? $order->get_meta( '_wcpay_payment_details' ) : '';
		$payment_details        = json_decode( $stored_payment_details, true );

		if ( ! $payment_details ) {
			if ( ! class_exists( '\WC_Payments' ) ) {
				return [];
			}

			$payment_method_id = $order->get_meta( '_payment_method_id' );
			if ( ! $payment_method_id ) {
				return [];
			}

			try {
				$payment_details = \WC_Payments::get_payments_api_client()->get_payment_method( $payment_method_id );
			} catch ( \Throwable $ex ) {
				// @TODO implement error logger.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( sprintf( '%s - retrieving info for payment method %s for order %s: %s', StringUtil::class_name_without_namespace( static::class ), $payment_method_id, $order->get_id(), $ex->getMessage() ) );
				}

				return [];
			}
		}

		$card_info = [];

		if ( isset( $payment_details['type'], $payment_details[ $payment_details['type'] ] ) ) {
			$details = $payment_details[ $payment_details['type'] ];
			switch ( $payment_details['type'] ) {
				case 'card':
				default:
					$card_info['brand'] = $details['brand'] ?? '';
					$card_info['last4'] = $details['last4'] ?? '';
					break;
				case 'card_present':
				case 'interac_present':
					$card_info['brand']        = $details['brand'] ?? '';
					$card_info['last4']        = $details['last4'] ?? '';
					$card_info['account_type'] = $details['receipt']['account_type'] ?? '';
					$card_info['aid']          = $details['receipt']['dedicated_file_name'] ?? '';
					$card_info['app_name']     = $details['receipt']['application_preferred_name'] ?? '';
					break;
			}
		}

		return array_map( 'sanitize_text_field', $card_info );
	}
}
