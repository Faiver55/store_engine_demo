<?php

namespace StoreEngine\Addons\Affiliate\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Addons\Affiliate\Settings\Affiliate as AffiliateSettings;

class Settings extends AbstractAjaxHandler {
	public function __construct() {
		$this->actions = [
			'update_affiliate_settings' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'update_affiliate_settings' ],
				'fields'     => [
					// Referral Tracking Settings
					'allow_referral_tracking'    => 'boolean',
					'referral_type'              => 'string',
					'referral_tracking_length'   => 'integer',
					// Commission Settings
					'commission_type'            => 'string',
					'commission_rate'            => 'integer',
					'allow_auto_commission'      => 'boolean',
					'allow_zero_commission'      => 'boolean',
					// Withdraw settings
					'minimum_withdraw_amount'    => 'float',
					'is_enabled_paypal_withdraw' => 'boolean',
					'is_enabled_echeck_withdraw' => 'boolean',
					'is_enabled_bank_withdraw'   => 'boolean',
				],
			],
		];
	}

	protected function update_affiliate_settings( $payload ) {
		$default = AffiliateSettings::get_settings_default_data();
		$args    = [
			// Referral Tracking Settings
			'allow_referral_tracking'    => $payload['allow_referral_tracking'] ?? $default['allow_referral_tracking'],
			'referral_type'              => $payload['referral_type'] ?? $default['referral_type'],
			'referral_tracking_length'   => $payload['referral_tracking_length'] ?? $default['referral_tracking_length'],
			// Commission Settings
			'commission_type'            => $payload['commission_type'] ?? $default['commission_type'],
			'commission_rate'            => $payload['commission_rate'] ?? $default['commission_rate'],
			'allow_auto_commission'      => $payload['allow_auto_commission'] ?? $default['allow_auto_commission'],
			'allow_zero_commission'      => $payload['allow_zero_commission'] ?? $default['allow_zero_commission'],
			// Withdraw settings
			'minimum_withdraw_amount'    => $payload['minimum_withdraw_amount'] ?? $default['minimum_withdraw_amount'],
			'is_enabled_paypal_withdraw' => $payload['is_enabled_paypal_withdraw'] ?? $default['is_enabled_paypal_withdraw'],
			'is_enabled_echeck_withdraw' => $payload['is_enabled_echeck_withdraw'] ?? $default['is_enabled_echeck_withdraw'],
			'is_enabled_bank_withdraw'   => $payload['is_enabled_bank_withdraw'] ?? $default['is_enabled_bank_withdraw'],
		];

		// Save.
		$is_updated = AffiliateSettings::save_settings( $args );

		/**
		 * Fires after saving the affiliate settings.
		 *
		 * @param bool $is_updated is updated?
		 * @param array $args Arguments.
		 */
		do_action( 'storeengine/settings/affiliate/after_save_affiliate_settings', $is_updated, $args );

		wp_send_json_success( $is_updated );
	}
}
