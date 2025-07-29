<?php
namespace StoreEngine\Addons\Affiliate\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Affiliate {
	public static function get_settings_saved_data() {
		$settings = get_option( STOREENGINE_AFFILIATE_SETTINGS_NAME );
		if ( $settings ) {
			return json_decode( $settings, true );
		}
		return [];
	}

	public static function get_settings_default_data() {
		return apply_filters( 'storeengine/affiliate/settings/affiliate_settings_default_data', [
			// Referral Tracking Settings
			'allow_referral_tracking'    => true,
			'referral_type'              => 'last',
			'referral_tracking_length'   => 30,
			// Commission Settings
			'commission_type'            => 'percentage',
			'commission_rate'            => 10,
			'allow_auto_commission'      => false,
			'allow_zero_commission'      => true,            // Withdraw settings
			'minimum_withdraw_amount'    => 10,
			'is_enabled_paypal_withdraw' => false,
			'is_enabled_echeck_withdraw' => false,
			'is_enabled_bank_withdraw'   => false,
		] );
	}

	public static function save_settings( $form_data = false ) {
		$default_data  = self::get_settings_default_data();
		$saved_data    = self::get_settings_saved_data();
		$settings_data = wp_parse_args( $saved_data, $default_data );
		if ( $form_data ) {
			$settings_data = wp_parse_args( $form_data, $settings_data );
		}
		// if settings already saved, then update it
		if ( count( $saved_data ) ) {
			return update_option( STOREENGINE_AFFILIATE_SETTINGS_NAME, wp_json_encode( $settings_data ) );
		}

		return add_option( STOREENGINE_AFFILIATE_SETTINGS_NAME, wp_json_encode( $settings_data ) );
	}
}
