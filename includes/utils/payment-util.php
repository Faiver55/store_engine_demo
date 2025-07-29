<?php
/**
 * A class of utilities for dealing with payment.
 */

namespace StoreEngine\Utils;

use StoreEngine\Classes\PaymentTokens\PaymentToken;
use StoreEngine\Classes\PaymentTokens\PaymentTokens;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PaymentUtil {
	protected static array $cc_types = [];

	public static function credit_card_type_labels(): array {
		if ( null === self::$cc_types ) {
			self::$cc_types = apply_filters( 'storeengine/credit_card_type_labels', [
				'mastercard'       => _x( 'MasterCard', 'Name of credit card', 'storeengine' ),
				'visa'             => _x( 'Visa', 'Name of credit card', 'storeengine' ),
				'discover'         => _x( 'Discover', 'Name of credit card', 'storeengine' ),
				'american express' => _x( 'American Express', 'Name of credit card', 'storeengine' ),
				'cartes bancaires' => _x( 'Cartes Bancaires', 'Name of credit card', 'storeengine' ),
				'diners'           => _x( 'Diners', 'Name of credit card', 'storeengine' ),
				'jcb'              => _x( 'JCB', 'Name of credit card', 'storeengine' ),
			] );
		}

		return self::$cc_types;
	}

	/**
	 * Get a nice name for credit card providers.
	 *
	 * @param string $type Provider Slug/Type.
	 *
	 * @return string
	 */
	public static function get_credit_card_type_label( string $type ): string {
		self::credit_card_type_labels();
		// Normalize.
		$type = strtolower( $type );
		$type = str_replace( '-', ' ', $type );
		$type = str_replace( '_', ' ', $type );


		/**
		 * Fallback to title case, uppercasing the first letter of each word.
		 */
		return apply_filters( 'storeengine/get_credit_card_type_label', ( array_key_exists( $type, self::$cc_types ) ? self::$cc_types[ $type ] : ucwords( $type ) ) );
	}

	/**
	 * Get My Account > Payment methods columns.
	 *
	 * @since 2.6.0
	 * @return array
	 */
	public static function get_account_payment_methods_columns(): array {
		return apply_filters(
			'storeengine/account_payment_methods_columns',
			[
				'method'  => __( 'Method', 'storeengine' ),
				'expires' => __( 'Expires', 'storeengine' ),
				'actions' => '&nbsp;',
			]
		);
	}

	/**
	 * Get My Account > Payment methods types
	 *
	 * @since 2.6.0
	 * @return array
	 */
	public static function get_account_payment_methods_types(): array {
		return apply_filters(
			'storeengine/payment_methods_types',
			[
				'cc'     => __( 'Credit card', 'storeengine' ),
				'echeck' => __( 'eCheck', 'storeengine' ),
			]
		);
	}

	/**
	 * Get customer saved payment methods list.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array
	 */
	public static function get_customer_saved_methods_list( $customer_id ) {
		return apply_filters( 'storeengine/saved_payment_methods_list', [], $customer_id );
	}

	/**
	 * Callback for storeengine/payment_methods_list_item filter to add token id
	 * to the generated list.
	 *
	 * @param array $list_item The current list item for the saved payment method.
	 * @param PaymentToken $token     The token for the current list item.
	 *
	 * @return array The list item with the token id added.
	 */
	public static function include_token_id_with_payment_methods( array $list_item, PaymentToken $token ) {
		$list_item['tokenId'] = $token->get_id();
		$brand                = ! empty( $list_item['method']['brand'] ) ?
			strtolower( $list_item['method']['brand'] ) :
			'';
		if ( ! empty( $brand ) && esc_html__( 'Credit card', 'storeengine' ) !== $brand ) {
			$list_item['method']['brand'] = self::get_credit_card_type_label( $brand );
		}
		return $list_item;
	}

	/**
	 * Get enabled payment gateways.
	 *
	 * @return array
	 */
	public static function get_enabled_payment_gateways(): array {
		return array_filter(Helper::get_payment_gateways()->payment_gateways(), fn ( $payment_gateway ) => $payment_gateway->is_enabled() );
	}

	/**
	 * Returns enabled saved payment methods for a customer and the default method if there are multiple.
	 *
	 * @return array
	 */
	public static function get_saved_payment_methods(): array {
		if ( ! is_user_logged_in() ) {
			return [];
		}

		add_filter( 'storeengine/payment_methods_list_item', [ self::class, 'include_token_id_with_payment_methods' ], 10, 2 );

		$enabled_payment_gateways = self::get_enabled_payment_gateways();
		$_saved_payment_methods   = self::get_customer_saved_methods_list( get_current_user_id() );
		$payment_methods          = [
			'enabled' => [],
			'default' => null,
		];

		// Filter out payment methods that are not enabled.
		foreach ( $_saved_payment_methods as $payment_method_group => $saved_payment_methods ) {
			$payment_methods['enabled'][ $payment_method_group ] = array_values(
				array_filter(
					$saved_payment_methods,
					function ( $saved_payment_method ) use ( $enabled_payment_gateways, &$payment_methods ) {
						if ( true === $saved_payment_method['is_default'] && null === $payment_methods['default'] ) {
							$payment_methods['default'] = $saved_payment_method;
						}
						return in_array( $saved_payment_method['method']['gateway'], array_keys( $enabled_payment_gateways ), true );
					}
				)
			);
		}

		remove_filter( 'storeengine/payment_methods_list_item', [ self::class, 'include_token_id_with_payment_methods' ], 10, 2 );

		return $payment_methods;
	}

	/**
	 * Returns the default payment method for a customer.
	 *
	 * @return string
	 */
	public static function get_default_payment_method(): string {
		$saved_payment_methods = self::get_saved_payment_methods();
		// A saved payment method exists, set as default.
		if ( $saved_payment_methods && ! empty( $saved_payment_methods['default'] ) ) {
			return $saved_payment_methods['default']['method']['gateway'] ?? '';
		}

		$order = Helper::get_recent_draft_order( 0, null, false );
		// If payment method is already stored in session, use it.
		if ( $order && $order->get_payment_method() ) {
			return $order->get_payment_method();
		}

		// If no saved payment method exists, use the first enabled payment method.
		$enabled_payment_gateways = self::get_enabled_payment_gateways();
		$first_key                = array_key_first( $enabled_payment_gateways );
		$first_payment_method     = $enabled_payment_gateways[ $first_key ];
		return $first_payment_method->id ?? '';
	}
}
