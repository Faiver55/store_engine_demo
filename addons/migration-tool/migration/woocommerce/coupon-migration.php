<?php

namespace StoreEngine\Addons\MigrationTool\Migration\Woocommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Database;
use WC_Coupon;

class CouponMigration {
	protected ?int $wc_coupon_id = null;
	protected WC_Coupon $wc_coupon;
	protected ?string $coupon_code;

	public static function get_by_wc_code( string $code ): string {
		global $wpdb;

		return (string) $wpdb->get_var( $wpdb->prepare(
			"
			SELECT m.meta_value FROM {$wpdb->postmeta} m
			INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
			WHERE p.post_type = 'storeengine_coupon' AND m.meta_key = %s AND m.meta_value = %s",
			'_storeengine_coupon_name',
			$code
		) );
	}

	public function __construct( int $wc_coupon_id ) {
		// Restore WC Table info in wpdb.
		WC()->wpdb_table_fix();

		$this->wc_coupon_id = $wc_coupon_id;
		$this->wc_coupon    = new WC_Coupon( $this->wc_coupon_id );
		$this->coupon_code  = self::get_by_wc_code( $this->wc_coupon->get_code() );
	}

	public function is_exists(): bool {
		return ! empty( $this->coupon_code );
	}

	public function migrate(): ?string {
		if ( $this->is_exists() ) {
			return $this->coupon_code;
		} else {
			return $this->create_coupon();
		}
	}

	public function create_coupon(): ?string {
		$coupon_code = $this->wc_coupon->get_code();
		$meta_data   = self::prepare_meta_data( $this->wc_coupon );

		// Restore SE Table info in wpdb.
		Database::init()->register_database_table_name();

		// Coupon data.
		$coupon_data = [
			'post_title'  => $coupon_code,
			'post_type'   => 'storeengine_coupon',
			'post_status' => 'publish',
		];

		// Create Coupon Post.
		$coupon_id = wp_insert_post( $coupon_data, true );

		if ( is_wp_error( $coupon_id ) ) {
			return null;
		}

		foreach ( $meta_data as $key => $value ) {
			update_post_meta( $coupon_id, $key, $value );
		}

		return $coupon_code;
	}

	public static function prepare_meta_data( WC_Coupon $wc_coupon ): array {
		$meta    = get_post_meta( $wc_coupon->get_id() );
		$used_by = $meta['_used_by'][0] ?? '';
		$data    = [
			'_storeengine_coupon_name'                    => $wc_coupon->get_code(),
			'_storeengine_coupon_type'                    => 'percent' === $wc_coupon->get_discount_type() ? 'percentage' : 'fixedAmount',
			'_storeengine_coupon_amount'                  => $wc_coupon->get_amount(),
			'_storeengine_per_user_coupon_usage_limit'    => $wc_coupon->get_usage_limit_per_user(),
			'_storeengine_coupon_time_type'               => $wc_coupon->get_date_expires() ? 'set_time_limit' : 'forever_time',
			'_storeengine_coupon_is_one_usage_per_user'   => true,
			'_storeengine_coupon_is_total_usage_limit'    => 'fixedLimit',
			'_storeengine_coupon_total_usage_limit'       => $wc_coupon->get_usage_limit_per_user(),
			'_storeengine_coupon_min_purchase_quantity'   => 0,
			'_storeengine_coupon_min_purchase_amount'     => $wc_coupon->get_minimum_amount(),
			'_storeengine_coupon_who_can_use'             => 'allCustomer',
			'_storeengine_coupon_usage_count'             => $wc_coupon->get_usage_count(),
			'_storeengine_coupon_used_by'                 => $used_by,
			'_storeengine_coupon_start_date_time'         => [
				'date'     => date( 'Y-m-d', time() ),
				'time'     => date( 'H:i', time() ),
				'timezone' => '',
			],
			'_storeengine_coupon_end_date_time'           => [
				'date'     => '',
				'time'     => '',
				'timezone' => '',
			],
			'_storeengine_coupon_type_of_min_requirement' => $wc_coupon->get_minimum_amount() > 0 ? 'amount' : 'none',
		];
		$expire  = strtotime( $wc_coupon->get_date_expires() );

		if ( $expire ) {
			$data['_storeengine_coupon_end_date_time'] = [
				'date' => date( 'Y-m-d', strtotime( $wc_coupon->get_date_expires() ) ),
				'time' => date( 'H:i', strtotime( $wc_coupon->get_date_expires() ) ),
			];
		}

		return $data;
	}
}
