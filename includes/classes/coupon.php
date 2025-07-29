<?php

namespace StoreEngine\Classes;

use Exception;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\Caching;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\NumberUtil;
use StoreEngine\Utils\StringUtil;
use StoreEngine\Utils\TaxUtil;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Coupon {

	public int $id             = 0;
	public string $code        = '';
	public string $name        = '';
	public string $status      = 'draft';
	public string $created     = '0000-00-00 00:00:00';
	public string $created_gmt = '0000-00-00 00:00:00';
	public ?array $settings    = null;

	protected array $internal_meta_keys = [
		'_storeengine_coupon_name',
		'_storeengine_coupon_type',
		'_storeengine_coupon_amount',
		'_storeengine_coupon_time_type',
		'_storeengine_coupon_is_one_usage_per_user',
		'_storeengine_coupon_is_total_usage_limit',
		'_storeengine_coupon_total_usage_limit',
		'_storeengine_coupon_type_of_min_requirement',
		'_storeengine_coupon_min_purchase_quantity',
		'_storeengine_coupon_min_purchase_amount',
		'_storeengine_coupon_who_can_use',
		'_storeengine_coupon_start_date_time',
		'_storeengine_coupon_end_date_time',
		'_storeengine_per_user_coupon_usage_limit',
		'_storeengine_coupon_usage_count',
	];

	/**
	 * Sorting.
	 *
	 * Used by `CartTotals::get_coupons_from_cart` to sort coupons.
	 *
	 * @see CartTotals::get_coupons_from_cart()
	 *
	 * @var int
	 */
	public int $sort = 0;

	public function __construct( ?string $code = null ) {
		if ( ! StringUtil::is_null_or_whitespace( $code ) && ! is_numeric( $code ) ) {
			$this->code = $code;
		} elseif ( is_numeric( $code ) && absint( $code ) > 0 ) {
			$this->code = get_post_meta( absint( $code ), '_storeengine_coupon_name', true );
		}

		if ( $this->code ) {
			$this->get();
		}
	}

	public function get_id(): int {
		return $this->id;
	}

	public function get_code(): string {
		return $this->code;
	}

	public function get_name(): string {
		return $this->name;
	}

	public function get_status(): string {
		return $this->status;
	}

	public function get_created(): string {
		return $this->created;
	}

	public function get_created_gmt(): string {
		return $this->created_gmt;
	}

	public function get_settings(): ?array {
		return $this->settings;
	}

	/**
	 * Get coupon amount.
	 *
	 * @return string
	 */
	public function get_amount(): string {
		return Formatting::format_decimal(  $this->settings['coupon_amount'] ?? 0 );
	}

	public function get_discount_type(): string {
		return $this->settings['coupon_type'] ?? '';
	}

	public function get_maximum_amount(): float {
		return abs( floatval( $this->settings['maximum_amount'] ?? 0 ) );
	}

	public function get_minimum_amount(): float {
		return abs( floatval( $this->settings['minimum_amount'] ?? 0 ) );
	}

	public function get_product_ids(): array {
		return $this->settings['product_ids'] ?? [];
	}

	public function get_excluded_product_ids(): array {
		return $this->settings['excluded_product_ids'] ?? [];
	}

	public function get_product_categories(): array {
		return $this->settings['product_categories'] ?? [];
	}

	public function get_excluded_product_categories(): array {
		return $this->settings['excluded_product_categories'] ?? [];
	}

	public function get_exclude_sale_items(): bool {
		return $this->settings['exclude_sale_items'] ?? false;
	}

	public function get_email_restrictions(): bool {
		return $this->settings['email_restrictions'] ?? false;
	}

	/**
	 * @param string|array $type
	 *
	 * @return bool
	 */
	public function is_type( $type ): bool {
		return ( $this->get_discount_type() === $type || ( is_array( $type ) && in_array( $this->get_discount_type(), $type, true ) ) );
	}

	/**
	 * @throws Exception
	 * @throws StoreEngineException
	 */
	public function get_date_expires() {
		if ( ! $this->settings ) {
			return false;
		}
		if ( 'forever_time' === $this->settings['coupon_time_type'] ) {
			// Early bail, no time limit on this coupon.
			return false;
		}

		if ( empty( $this->settings['coupon_end_date_time'] ) || empty( $this->settings['coupon_end_date_time']['date'] ) ) {
			// End datetime not saved.
			return false;
		}

		[
			'date' => $end_date,
			'time' => $end_time,
		] = $this->settings['coupon_end_date_time'];

		if ( ! $end_date ) {
			// End datetime not saved.
			return false;
		}

		$end_datetime = trim( $end_date . ' ' . $end_time );

		if ( ! $end_datetime ) {
			throw new StoreEngineException( esc_html__( 'We’re sorry, but the coupon code you entered isn’t valid or already expired.', 'storeengine' ), 'invalid-end-datetime', null, 400 );
		}

		return new StoreengineDatetime( $end_datetime );
	}

	public function validate_coupon( $check_total_usage = true ) {
		try {
			$this->is_valid();

			if ( $check_total_usage ) {
				$this->check_total_usage_limit();
			}

			$this->check_coupon_minimum_requirement();
			$this->check_coupon_between_start_and_end_date();
			$this->check_user_can_usage();
			do_action( 'storeengine/validate_coupon', $this );
		} catch ( StoreEngineException $e ) {
			return $e->toWpError();
		} catch ( Exception $e ) {
			return new WP_Error( 'coupon_validation_error', $e->getMessage() );
		}

		return true;
	}

	public function calculate( $amount ) {
		if ( ! empty( $this->settings['coupon_type'] ) ) {
			if ( 'percentage' === $this->settings['coupon_type'] ) {
				return $amount * ( $this->settings['coupon_amount'] / 100 );
			}

			if ( 'fixedAmount' === $this->settings['coupon_type'] ) {
				return $this->settings['coupon_amount'];
			}
		}

		return 0;
	}

	private function get() {
		if ( ! $this->code ) {
			return;
		}
		$coupons_query = get_posts( [
			'posts_per_page' => 1,
			'post_type'      => Helper::COUPON_POST_TYPE,
			'post_status'    => 'publish',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => '_storeengine_coupon_name',
					'value'   => $this->code,
					'compare' => '=',
				],
			],
		] );

		// Check if the coupon is active
		if ( empty( $coupons_query ) ) {
			return;
		}

		$this->id          = $coupons_query[0]->ID;
		$this->name        = $coupons_query[0]->post_title;
		$this->status      = $coupons_query[0]->post_status;
		$this->created     = $coupons_query[0]->post_date;
		$this->created_gmt = $coupons_query[0]->post_date_gmt;
		$this->settings    = [];

		foreach ( get_post_meta( $coupons_query[0]->ID ) as $key => $value ) {
			if ( ! in_array( $key, $this->internal_meta_keys, true ) ) {
				continue;
			}
			if ( 1 === count( $value ) ) {
				$value = maybe_unserialize( $value[0] );
			}

			$this->settings[ str_replace( '_' . Helper::DB_PREFIX, '', $key ) ] = $value;
		}
	}

	public static function get_by_code( string $code, $exclude = 0 ) {
		if ( StringUtil::is_null_or_whitespace( $code ) ) {
			return null;
		}

		// @TODO purge cache on post update.
		$ids = wp_cache_get( Caching::get_cache_prefix( 'coupons' ) . 'coupon_id_from_code_' . $code, 'coupons' );

		if ( false === $ids ) {
			global $wpdb;

			// Sort by date for latest coupon if there is more than one with same code.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"
					SELECT p.ID
					FROM wp_posts p
					RIGHT JOIN wp_postmeta m ON m.post_id = p.ID
					WHERE
						post_type = %s AND
						m.meta_key = '_storeengine_coupon_name' AND
						m.meta_value = %s
					ORDER BY post_date DESC;
					",
					Helper::COUPON_POST_TYPE,
					Formatting::sanitize_coupon_code( $code )
				)
			);

			if ( $ids ) {
				wp_cache_set( Caching::get_cache_prefix( 'coupons' ) . 'coupon_id_from_code_' . $code, $ids, 'coupons' );
			}
		}

		$exclude = absint( $exclude );
		$ids     = array_diff( array_filter( array_map( 'absint', (array) $ids ) ), [ $exclude ] );

		return apply_filters( 'storeengine/get_coupon_id_from_code', absint( current( $ids ) ), $code, $exclude );
	}

	public function get_used_by(): array {
		return $this->settings['coupon_used_by'] ?? [];
	}

	private function is_valid() {
		if ( ! $this->id ) {
			throw new StoreEngineException( __( 'Coupon does not exist!', 'storeengine' ), 'coupon-not-found', null, 404 );
		}

		if ( 'publish' !== $this->status ) {
			throw new StoreEngineException( __( 'Coupon does not exist!', 'storeengine' ), 'coupon-is-not-active' );
		}

		if ( ! $this->settings ) {
			throw new StoreEngineException( esc_html__( 'Invalid Coupon!', 'storeengine' ), 'invalid-coupon', null, 400 );
		}
	}

	/**
	 * @return int|null
	 */
	public function get_limit_usage_to_x_items(): ?int {
		// @TODO implement limit_usage_to_x_items
		return null;
	}

	/**
	 * @param $product_id
	 * @param CartItem|Object $cart_item
	 *
	 * @return bool
	 */
	public function is_valid_for_product( $product_id, $cart_item ): bool {
		return apply_filters( 'storeengine/coupon/is_valid_for_product', true );
	}

	public function is_valid_for_cart(): bool {
		return apply_filters( 'storeengine/coupon/is_valid_for_cart', true );
	}

	/**
	 * @throws StoreEngineException
	 */
	private function check_total_usage_limit() {
		if ( 'unlimitedPerCustomer' !== $this->settings['coupon_is_total_usage_limit'] && is_user_logged_in() ) {
			$current_user_id     = get_current_user_id();
			$current_users_usage = array_filter( $this->get_used_by(), fn( $user_id ) => (int) $user_id === $current_user_id );

			if ( count( $current_users_usage ) >= (int) $this->settings['coupon_total_usage_limit'] ) {
				throw new StoreEngineException( esc_html__( 'Sorry, Coupon has reached its limit', 'storeengine' ), 'coupon-limit-reached', null, 400 );
			}
		}
	}

	private function check_coupon_minimum_requirement() {
		if ( 'none' !== $this->settings['coupon_type_of_min_requirement'] ) {
			$cart                      = Helper::cart();
			$cart_items                = $cart->get_cart_items();
			$cart_items_count          = count( $cart_items );
			$minimum_purchase_quantity = $this->settings['coupon_min_purchase_quantity'];
			if ( $cart_items_count < $minimum_purchase_quantity ) {
				throw new StoreEngineException( esc_html__( 'Sorry, Coupon has minimum purchase quantity', 'storeengine' ), 'min-purchase-qty', null, 400 );
			}

			if ( 'amount' === $this->settings['coupon_type_of_min_requirement'] ) {
				$cart_total              = $cart->get_total( 'coupon' );
				$minimum_purchase_amount = isset( $this->settings['coupon_min_requirement_amount'] ) ? $this->settings['coupon_min_requirement_amount'] : 0;
				if ( $cart_total < $minimum_purchase_amount ) {
					throw new StoreEngineException( esc_html__( 'Sorry, Coupon has minimum purchase amount', 'storeengine' ), 'min-purchase-amount', null, 400 );
				}
			}
		}
	}

	private function check_coupon_between_start_and_end_date() {
		if ( 'forever_time' === $this->settings['coupon_time_type'] ) {
			// Early bail, no time limit on this coupon.
			return;
		}

		[
			'date' => $start_date,
			'time' => $start_time,
		] = $this->settings['coupon_start_date_time'];

		if ( ! $start_date || ! $start_time ) {
			throw new StoreEngineException( esc_html__( 'We’re sorry, but the coupon code you entered isn’t valid.', 'storeengine' ), 'empty-start-datetime', null, 500 );
		}

		$start_datetime = strtotime( trim( $start_date . ' ' . $start_time ) );

		if ( ! $start_datetime ) {
			throw new StoreEngineException( esc_html__( 'We’re sorry, but the coupon code you entered isn’t valid.', 'storeengine' ), 'invalid-start-datetime', null, 500 );
		}

		if ( $start_datetime > time() ) {
			// @TODO update the error message and datetime format.
			throw new StoreEngineException(
				sprintf(
				/* translators: %s. Coupon start date. */
					esc_html__( 'We’re sorry, but the coupon code you entered will be valid starting %s. Please try again later.', 'storeengine' ),
					wp_date( 'Y-m-d H:i:s', $start_datetime )
				),
				'coupon-unavailable',
				null,
				400
			);
		}

		if ( empty( $this->settings['coupon_end_date_time'] ) ) {
			// End datetime not saved.
			return;
		}

		[
			'date' => $end_date,
			'time' => $end_time,
		] = $this->settings['coupon_end_date_time'];

		if ( ! $end_date ) {
			// End datetime not saved.
			return;
		}

		$end_datetime = strtotime( trim( $end_date . ' ' . $end_time ) );

		if ( ! $end_datetime ) {
			throw new StoreEngineException( esc_html__( 'We’re sorry, but the coupon code you entered isn’t valid or already expired.', 'storeengine' ), 'invalid-end-datetime', null, 400 );
		}

		if ( $end_datetime < time() ) {
			throw new StoreEngineException( esc_html__( 'We’re sorry, but the coupon code you entered is expired.', 'storeengine' ), 'coupon-expired', null, 400 );
		}
	}

	private function check_user_can_usage() {
		$coupon_who_can_use = $this->settings['coupon_who_can_use'];
		if ( ( 'new_customers' === $coupon_who_can_use && get_current_user_id() ) || ( 'current_customers' === $coupon_who_can_use && ! get_current_user_id() ) ) {
			throw new StoreEngineException( esc_html__( 'Sorry, You can not use this coupon.', 'storeengine' ), 'coupon-user-cannot-use', null, 400 );
		}
	}

	public function get_usage_limit() {
		return 0;
	}

	public function get_usage_limit_per_user() {
		return 0;
	}

	/**
	 * Get discount amount for a cart item.
	 *
	 * @param  float|int     $discounting_amount Amount the coupon is being applied to.
	 * @param  array|null $cart_item          Cart item being discounted if applicable.
	 * @param boolean $single             True if discounting a single qty item, false if its the line.
	 *
	 * @return float Amount this coupon has discounted.
	 */
	public function get_discount_amount( $discounting_amount, $cart_item = null, bool $single = false ): float {
		$discount      = 0;
		$cart_item_qty = is_null( $cart_item ) ? 1 : $cart_item['quantity'];

		if ( $this->is_type( [ 'percent', 'percentage' ] ) ) {
			$discount = (float) $this->get_amount() * ( $discounting_amount / 100 );
		} elseif ( $this->is_type( [ 'fixed_cart', 'fixedAmount' ] ) && ! is_null( $cart_item ) && Helper::cart()->get_subtotal() ) {
			/**
			 * This is the most complex discount - we need to divide the discount between rows based on their price in.
			 * proportion to the subtotal. This is so rows with different tax rates get a fair discount, and so rows.
			 * with no price (free) don't get discounted.
			 *
			 * Get item discount by dividing item cost by subtotal to get a %.
			 *
			 * Uses price inc tax if prices include tax to work around https://github.com/woocommerce/woocommerce/issues/7669 and https://github.com/woocommerce/woocommerce/issues/8074.
			 */
			if ( TaxUtil::prices_include_tax() ) {
				$discount_percent = ( Formatting::get_price_including_tax( $cart_item['data'] ) * $cart_item_qty ) / WC()->cart->subtotal;
			} else {
				$discount_percent = ( Formatting::get_price_excluding_tax( $cart_item['data'] ) * $cart_item_qty ) / WC()->cart->subtotal_ex_tax;
			}
			$discount = ( (float) $this->get_amount() * $discount_percent ) / $cart_item_qty;
		} elseif ( $this->is_type( 'fixed_product' ) ) {
			$discount = min( $this->get_amount(), $discounting_amount );
			$discount = $single ? $discount : $discount * $cart_item_qty;
		}

		return (float) apply_filters(
			'storeengine/coupon_get_discount_amount',
			NumberUtil::round( min( $discount, $discounting_amount ), Formatting::get_rounding_precision() ),
			$discounting_amount,
			$cart_item,
			$single,
			$this
		);
	}

	public function get_free_shipping(): bool {
		return false;
	}
}
