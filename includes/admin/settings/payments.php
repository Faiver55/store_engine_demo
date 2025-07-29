<?php

namespace StoreEngine\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @deprecated
 */
class Payments {

	/**
	 * @deprecated
	 */
	public static function get_settings_saved_data() {
		$settings = get_option( STOREENGINE_PAYMENTS_SETTINGS_NAME );
		$settings = json_decode( $settings, true );
		$settings = is_array( $settings ) ? $settings : [];

		$parsed_settings = [];

		$default_settings = self::get_settings_default_data();

		foreach ( $settings as $setting ) {
			if ( empty( $setting['title'] ) ) {
				$setting['title'] = $default_settings[ $setting['type'] ]['title'] ?? '';
			}

			if ( empty( $setting['index'] ) ) {
				$setting['index'] = $default_settings[ $setting['type'] ]['index'] ?? 0;
			}

			$parsed_settings[ $setting['type'] ] = $setting;
		}


		return array_values( $parsed_settings );
	}

	/**
	 * @deprecated
	 */
	public static function get_settings_default_data() {
		// @TODO make separate gateway class
		return apply_filters( 'storeengine/admin/payments_settings_default_data', [
			'bank_transfer'    => [
				'type'         => 'bank_transfer',
				'is_enabled'   => false,
				'title'        => __( 'Bank Transfer', 'storeengine' ),
				'description'  => __( 'Take payments in person via BACS. More commonly known as direct bank/wire transfer.', 'storeengine' ),
				'instructions' => __( 'Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order will not be shipped until the funds have cleared in our account.', 'storeengine' ),
				'accounts'     => [],
				'index'        => 0,
			],
			'check_payment'    => [
				'type'         => 'check_payment',
				'is_enabled'   => false,
				'title'        => __( 'Check Payment', 'storeengine' ),
				'description'  => __( 'Take payments in person via checks. This offline gateway can also be useful to test purchases.', 'storeengine' ),
				'instructions' => __( 'Please send a check to Store Name, Store Street, Store Town, Store State / County, Store Postcode.', 'storeengine' ),
				'index'        => 1,
			],
			'cash_on_delivery' => [
				'type'         => 'cash_on_delivery',
				'is_enabled'   => false,
				'title'        => __( 'Cash on delivery', 'storeengine' ),
				'description'  => __( 'Have your customers pay with cash (or by other means) upon delivery.', 'storeengine' ),
				'instructions' => __( 'Pay with cash upon delivery.', 'storeengine' ),
				'index'        => 2,
			],
			'paypal'           => [
				'type'                  => 'paypal',
				'title'                 => 'Paypal',
				'description'           => __( 'Accept PayPal, Pay Later and alternative payment types.', 'storeengine' ),
				'instructions'          => __( 'Continue with PayPal, Pay Later and alternative payment types.', 'storeengine' ),
				'is_enabled'            => false,
				'is_enabled_sandbox'    => false,
				'sandbox_client_id'     => '',
				'sandbox_client_secret' => '',
				'live_client_id'        => '',
				'live_client_secret'    => '',
				'index'                 => 3,
			],
			'stripe'           => [
				'type'                 => 'stripe',
				'title'                => __( 'Stripe', 'storeengine' ),
				'description'          => __( 'Accept debit and credit cards in 135+ currencies, methods such as SEPA, and one-touch checkout with Apple Pay.', 'storeengine' ),
				'is_enabled'           => false,
				'is_enabled_test_mode' => false,
				'test_publishable_key' => '',
				'test_secret_key'      => '',
				'live_publishable_key' => '',
				'live_secret_key'      => '',
				'index'                => 4,
			],
		] );
	}

	/**
	 * @deprecated
	 */
	public static function save_settings( $form_data = false ) {
		$default_data  = self::get_settings_default_data();
		$saved_data    = self::get_settings_saved_data();
		$settings_data = ! empty( $saved_data ) ? $saved_data : $default_data;

		if ( $form_data && is_array( $form_data ) ) {
			foreach ( $form_data as $form_data_item ) {
				$type  = $form_data_item['type'];
				$found = false;
				// Update the existing settings data
				foreach ( $settings_data as &$existingItem ) {
					if ( $existingItem['type'] === $type ) {
						foreach ( $form_data_item as $key => $value ) {
							if ( array_key_exists( $key, $existingItem ) ) {
								$existingItem[ $key ] = $value;
							}
						}
						$found = true;
						break;
					}
				}
				// If the type is not found in the existing settings data, add it as a new item
				if ( ! $found ) {
					$settings_data[] = $form_data_item;
				}
			}
		}//end if

		usort( $settings_data, function ( $a, $b ) {
			return $a['index'] - $b['index'];
		} );

		return update_option( STOREENGINE_PAYMENTS_SETTINGS_NAME, wp_json_encode( $settings_data ) );
	}

	/**
	 * @deprecated
	 */
	public static function disable_payment_method( $type ): bool {
		$settings = self::get_settings_saved_data();
		foreach ( $settings as &$setting ) {
			if ( $setting['type'] === $type ) {
				$setting['is_enabled'] = false;
				break;
			}
		}

		return update_option( STOREENGINE_PAYMENTS_SETTINGS_NAME, wp_json_encode( $settings ) );
	}
}
