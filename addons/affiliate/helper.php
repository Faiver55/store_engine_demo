<?php

namespace StoreEngine\Addons\Affiliate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Addons\Affiliate\Settings\Affiliate;
use StoreEngine\Addons\Affiliate\models\AffiliateReport;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper as UtilsHelper;

class Helper {
	/**
	 * @throws StoreEngineException
	 */
	public static function generate_random_code( string $code_type, int $code_length = 8 ) {
		global $wpdb;
		if ( 'payouts' === $code_type ) {
			$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}storeengine_affiliate_payouts WHERE transaction_id = %s;";
		} elseif ( 'referrals' === $code_type ) {
			$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}storeengine_affiliate_referrals WHERE referral_code = %s;";
		} else {
			throw new StoreEngineException( __( 'Invalid code type', 'storeengine' ), 'invalid_code_type' );
		}

		do {
			$random_code   = wp_generate_password( $code_length, false, false );
			$existing_code = $wpdb->get_var( $wpdb->prepare( $sql, $random_code ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- query prepared above.
		} while ( $existing_code > 0 );

		return $random_code;
	}

	public static function get_affiliate_setting( $setting_name ) {
		$affiliate_settings = Affiliate::get_settings_saved_data();

		return $affiliate_settings[ $setting_name ] ?? null;
	}

	public static function get_commission_amount( $total_amount = 0 ) {
		$commission_rate = (float) self::get_affiliate_setting( 'commission_rate' );
		if ( 'percentage' === self::get_affiliate_setting('commission_type') ) {
			return Formatting::format_decimal( $total_amount * ( $commission_rate / 100 ), 2 );
		} else {
			return Formatting::format_decimal( $commission_rate, 2 );
		}
	}

	public static function format_payment_method( $payment_method = null ): string {
		switch ( $payment_method ) {
			case 'bank_transfer':
				return __( 'Bank Transfer', 'storeengine' );
			case 'check_payment':
				return __( 'Check Payment', 'storeengine' );
			case 'cash_on_delivery':
				return __( 'Cash on Delivery', 'storeengine' );
			case 'paypal':
				return __( 'PayPal', 'storeengine' );
			case 'stripe':
				return __( 'Stripe', 'storeengine' );
			case 'echeck':
				return __( 'E-Check', 'storeengine' );
			default:
				return '';
		}
	}

	public static function is_valid_referrer( $referral_code = null ) {
		if ( ! $referral_code ) {
			return false;
		}

		$request_url = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$post_id     = url_to_postid( $request_url );

		if ( ! $post_id ) {
			$post_id = UtilsHelper::get_settings('shop_page');
		}

		if ( $post_id ) {
			global $wpdb;
			$result = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				"SELECT
				r.referral_id,
				r.affiliate_id,
				r.referral_post_id,
				r.click_counts,
				a.status
				FROM
					{$wpdb->prefix}storeengine_affiliate_referrals r
				LEFT JOIN
					{$wpdb->prefix}storeengine_affiliates a ON r.affiliate_id = a.affiliate_id
				WHERE
					referral_code = %s;",
				$referral_code
			), ARRAY_A );

			if ( $result && $post_id === (int) $result['referral_post_id'] ) {
				return $result;
			}
		}

		return false;
	}

	public static function update_affiliate_commission( $affiliate_id, $commission_amount ) {
		$report_row = AffiliateReport::get_affiliate_reports( null, $affiliate_id );
		if ( $report_row ) {
			return AffiliateReport::update(
				$affiliate_id,
				[
					'total_commissions' => $report_row['total_commissions'] + $commission_amount,
					'current_balance'   => $report_row['current_balance'] + $commission_amount,
				],
				'affiliate_id'
			);
		}

		return false;
	}

	public static function get_payment_card( $payment_method = '', $minimum_withdraw = 0 ) {
		if ( ! $payment_method ) {
			return;
		}

		$payment_method_classes = sprintf( 'storeengine-withdraw-method%s storeengine-withdraw-method--selected', $payment_method );
		?>
		<label class="<?php echo esc_attr( $payment_method_classes ); ?>" id="<?php echo esc_attr( $payment_method ); ?>-label">
			<h3 class="storeengine-withdraw-method__heading"><?php echo esc_html( self::format_payment_method( $payment_method ) ); ?></h3>
			<p class="storeengine-withdraw-method__subheading">
				<?php
				echo sprintf(
				/* translators: %s) Minimum withdrawal amount. */
					esc_html__( 'Min withdraw %s', 'storeengine' ),
					wp_kses_post( Formatting::price( $minimum_withdraw ) )
				);
				?>
			</p>
			<input name="withdrawMethodType" type="radio" value="<?php echo esc_attr( $payment_method ); ?>" <?php checked( $payment_method, 'paypal' ); ?>>
		</label>
		<?php
	}
}
