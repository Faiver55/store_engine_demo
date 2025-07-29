<?php

namespace StoreEngine\Addons\Affiliate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Order;
use StoreEngine\Utils\Helper;
use StoreEngine\Addons\Affiliate\Helper as HelperAddon;
use StoreEngine\Addons\Affiliate\models\Commission;
use StoreEngine\Addons\Affiliate\models\Referral;
use StoreEngine\Addons\Affiliate\models\ReferralTrack;
use StoreEngine\Addons\Affiliate\models\AffiliateReport;
use StoreEngine\Addons\Affiliate\Settings\Affiliate;

class CookieHandler {

	public static function init() {
		$self = new self();
		add_action( 'init', [ $self, 'set_affiliate_code_cookie' ] );
		add_action( 'storeengine/checkout/order_processed', [ $self, 'handle_order' ] );
	}

	public function set_affiliate_code_cookie() {
		if ( current_user_can( 'manage_storeengine_affiliate' ) ) {
			return;
		}

		if ( isset( $_GET[ STOREENGINE_AFFILIATE_COOKIE_KEY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$referral_code = sanitize_text_field( wp_unslash( $_GET[ STOREENGINE_AFFILIATE_COOKIE_KEY ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$referral_row  = HelperAddon::is_valid_referrer( $referral_code );

			if ( ! $referral_row ) {
				return;
			}

			if ( 'active' !== $referral_row['status'] ) {
				return;
			}

			$is_exists = [];

			if ( isset( $_COOKIE[ STOREENGINE_AFFILIATE_COOKIE_KEY ] ) ) {
				$is_exists = sanitize_text_field( wp_unslash( $_COOKIE[ STOREENGINE_AFFILIATE_COOKIE_KEY ] ), true );
			}

			if ( ! empty( $is_exists ) ) {
				if ( 'first' === HelperAddon::get_affiliate_setting('referral_type') ) {
					return;
				}

				$cookie_data = json_decode( $is_exists );

				if ( $cookie_data->referral_id === $referral_row['referral_id'] ) {
					return;
				}
			}

			$track_id = 0;

			if ( HelperAddon::get_affiliate_setting('allow_referral_tracking') ) {
				$track_row = ReferralTrack::save( [
					'referral_id' => $referral_row['referral_id'],
					'referral_ip' => Helper::get_user_ip(),
					'status'      => 'pending',
				] );

				$track_id = $track_row['track_id'];

				Referral::update( $referral_row['referral_id'], [ 'click_counts' => $referral_row['click_counts'] + 1 ] );

				$report_row = AffiliateReport::get_affiliate_reports( null, $referral_row['affiliate_id'] );

				if ( $report_row ) {
					AffiliateReport::update($referral_row['affiliate_id'], [ 'total_clicks' => $report_row['total_clicks'] + 1 ], 'affiliate_id');
				} else {
					AffiliateReport::save([
						'affiliate_id' => $referral_row['affiliate_id'],
						'referral_id'  => $referral_row['referral_id'],
						'total_clicks' => 1,
					]);
				}
			}

			$this->set_affiliate_cookie( wp_json_encode( [
				'referral_id'  => $referral_row['referral_id'],
				'affiliate_id' => $referral_row['affiliate_id'],
				'track_id'     => $track_id,
			] ) );
		}
	}

	public function set_affiliate_cookie( $cookie_data ) {
		$cookie_time = time() + ( 60 * 60 * 24 * (int) HelperAddon::get_affiliate_setting('referral_tracking_length') );
		$cookie_path = '/';

		setcookie( STOREENGINE_AFFILIATE_COOKIE_KEY, $cookie_data, $cookie_time, $cookie_path );
	}

	public function handle_order( Order $order ) {
		if ( ! isset( $_COOKIE[ STOREENGINE_AFFILIATE_COOKIE_KEY ] ) ) {
			return;
		}

		$cookie_data    = sanitize_text_field( wp_unslash( $_COOKIE[ STOREENGINE_AFFILIATE_COOKIE_KEY ] ), true );
		$affiliate_data = json_decode( $cookie_data );

		$commission_data = [
			'affiliate_id'      => $affiliate_data->affiliate_id,
			'order_id'          => $order->get_id(),
			'commission_amount' => HelperAddon::get_commission_amount( $order->get_total() ),
			'status'            => 'pending',
		];

		Commission::save( $commission_data );

		if ( HelperAddon::get_affiliate_setting('allow_referral_tracking') ) {
			$referral_data = [
				'status' => 'converted',
			];
			ReferralTrack::update( $affiliate_data->track_id, $referral_data );
		}
	}

}
