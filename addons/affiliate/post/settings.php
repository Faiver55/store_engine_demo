<?php

namespace StoreEngine\Addons\Affiliate\Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;
use StoreEngine\Classes\AbstractPostHandler;
use StoreEngine\Addons\Affiliate\Settings\Affiliate as AffiliateSettings;
use StoreEngine\Addons\Affiliate\models\Affiliate;
use StoreEngine\Addons\Affiliate\models\Payout;

class Settings extends AbstractPostHandler {
	protected string $namespace = STOREENGINE_PLUGIN_SLUG . '_affiliate';

	public function __construct() {
		$this->actions = [
			'save_frontend_dashboard_withdraw_settings' => [
				'callback'   => [ $this, 'save_frontend_dashboard_withdraw_settings' ],
				'capability' => 'manage_storeengine_affiliate',
				'fields'     => [
					'withdrawMethodType' => 'string',
					'paypalEmailAddress' => 'string',
					'echeckAddress'      => 'string',
					'bankAccountName'    => 'string',
					'bankAccountNumber'  => 'string',
					'bankName'           => 'string',
					'bankIBAN'           => 'string',
					'bankSWIFTCode'      => 'string',
				],
			],
			'affiliate_earning_withdrawal'              => [
				'callback'   => [ $this, 'affiliate_earning_withdrawal' ],
				'capability' => 'manage_storeengine_affiliate',
				'fields'     => [
					'withdrawal_type'   => 'string',
					'withdrawal_amount' => 'integer',
				],
			],
		];
	}

	public function save_frontend_dashboard_withdraw_settings( $payload ) {
		$user_id              = get_current_user_id();
		$withdraw_method_type = ! empty( $payload['withdrawMethodType'] ) ? $payload['withdrawMethodType'] : get_user_meta( $user_id, 'storeengine_affiliate_withdraw_method_type', true );
		$paypal_email_address = ! empty( $payload['paypalEmailAddress'] ) ? $payload['paypalEmailAddress'] : get_user_meta( $user_id, 'storeengine_affiliate_withdraw_paypal_email', true );
		$check_address        = ! empty( $payload['echeckAddress'] ) ? $payload['echeckAddress'] : get_user_meta( $user_id, 'storeengine_affiliate_withdraw_echeck_address', true );
		$bank_account_name    = ! empty( $payload['bankAccountName'] ) ? $payload['bankAccountName'] : get_user_meta( $user_id, 'storeengine_affiliate_withdraw_bank_acocunt_name', true );
		$bank_account_number  = ! empty( $payload['bankAccountNumber'] ) ? $payload['bankAccountNumber'] : get_user_meta( $user_id, 'storeengine_affiliate_withdraw_bank_acocunt_number', true );
		$bank_name            = ! empty( $payload['bankName'] ) ? $payload['bankName'] : get_user_meta( $user_id, 'storeengine_affiliate_withdraw_bank_name', true );
		$bank_iban            = ! empty( $payload['bankIBAN'] ) ? $payload['bankIBAN'] : get_user_meta( $user_id, 'storeengine_affiliate_withdraw_bank_iban', true );
		$bank_SWIFT_code      = ! empty( $payload['bankSWIFTCode'] ) ? $payload['bankSWIFTCode'] : get_user_meta( $user_id, 'storeengine_affiliate_withdraw_bank_swiftcode', true );

		update_user_meta( $user_id, 'storeengine_affiliate_withdraw_method_type', $withdraw_method_type );
		update_user_meta( $user_id, 'storeengine_affiliate_withdraw_paypal_email', $paypal_email_address );
		update_user_meta( $user_id, 'storeengine_affiliate_withdraw_echeck_address', $check_address );
		update_user_meta( $user_id, 'storeengine_affiliate_withdraw_bank_acocunt_name', $bank_account_name );
		update_user_meta( $user_id, 'storeengine_affiliate_withdraw_bank_acocunt_number', $bank_account_number );
		update_user_meta( $user_id, 'storeengine_affiliate_withdraw_bank_name', $bank_name );
		update_user_meta( $user_id, 'storeengine_affiliate_withdraw_bank_iban', $bank_iban );
		update_user_meta( $user_id, 'storeengine_affiliate_withdraw_bank_swiftcode', $bank_SWIFT_code );

		$referer_url = Helper::sanitize_referer_url( wp_get_referer() );
		wp_safe_redirect( $referer_url );
	}

	public function affiliate_earning_withdrawal( $payload ) {
		$user_id                 = get_current_user_id();
		$withdraw_amount         = ! empty( $payload['withdrawal_amount'] ) ? $payload['withdrawal_amount'] : 0;
		$withdraw_method_type    = ! empty( $payload['withdrawal_type'] ) ? $payload['withdrawal_type'] : '';
		$affiliate_settings      = AffiliateSettings::get_settings_saved_data();
		$minimum_withdraw_amount = ! empty( $affiliate_settings['minimum_withdraw_amount'] ) ? $affiliate_settings['minimum_withdraw_amount'] : 0;
		$affiliate_data          = Affiliate::get_affiliates( [ 'user_id' => $user_id ] );
		$total_earning           = $affiliate_data ? $affiliate_data['total_commissions'] : 0;
		$available_balance       = $affiliate_data ? $affiliate_data['current_balance'] : 0;
		$affiliate_id            = $affiliate_data ? $affiliate_data['affiliate_id'] : 0;

		if ( $available_balance < $withdraw_amount ) {
			wp_die( esc_html__( "Your account doesn't have sufficient balance.", 'storeengine' ), esc_html__( 'Insufficient Balance!', 'storeengine' ), 406 );
		}

		if ( $withdraw_amount < $minimum_withdraw_amount ) {
			/* translators: %d) Minimum withdrawal amount. */
			wp_die( esc_html( sprintf( __( 'Minimum withdrawal amount is %d', 'storeengine' ), $minimum_withdraw_amount ) ), esc_html__( 'Minimum Withdrawal Amount!', 'storeengine' ), 406 );
		}

		$withdraw_args = apply_filters( 'storeengine/frontend/withdraw_data_insert_args', [
			'affiliate_id'   => $affiliate_id,
			'payout_amount'  => $withdraw_amount,
			'payment_method' => $withdraw_method_type,
		] );

		/**
		 * Fires before withdraw insert.
		 *
		 * @param array $withdraw_args Withdraw arguments.
		 */
		do_action( 'storeengine/frontend/affiliate/before_withdraw_data_insert', $withdraw_args );

		$payout   = new Payout();
		$withdraw = $payout->save( $withdraw_args );

		if ( is_wp_error( $withdraw ) ) {
			wp_die( esc_html__( 'Something Went Wrong! Your withdrawal request can not be processed at this moment, please try again.', 'storeengine' ), esc_html__( 'Something Went Wrong!', 'storeengine' ), 500 );
		}

		/**
		 * Fires after withdraw insert.
		 *
		 * @param mixed $withdraw Withdraw.
		 * @param array $withdraw_args Withdraw arguments.
		 */
		do_action( 'storeengine/affiliate/after_withdraw_data_insert', $withdraw, $withdraw_args );

		wp_safe_redirect( Helper::sanitize_referer_url( wp_get_referer() ) );
	}
}
