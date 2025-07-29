<?php

namespace StoreEngine\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxUtil {

	protected static ?bool $prices_include_tax = null;

	public static function is_tax_enabled(): bool {
		// @TODO apply filter storeengine/tax_enabled
		return Helper::get_settings( 'enable_product_tax' );
	}

	/**
	 * Get rounding mode for internal tax calculations.
	 *
	 * @return int
	 */
	public static function get_tax_rounding_mode(): int {
		$mode = self::prices_include_tax() ? PHP_ROUND_HALF_DOWN : PHP_ROUND_HALF_UP;

		return intval( apply_filters( 'storeengine/tax_rounding_mode', $mode ) );
	}

	public static function tax_based_on() {
		return Helper::get_settings( 'tax_based_on', 'shipping' );
	}

	public static function default_customer_address() {
		return Helper::get_settings( 'store_default_customer_address' );
	}

	public static function tax_round_at_subtotal() {
		return Helper::get_settings( 'tax_round_at_subtotal', false );
	}

	public static function prices_include_tax(): bool {
		if ( null === self::$prices_include_tax ) {
			self::$prices_include_tax = Helper::get_settings( 'prices_include_tax', false );
		}

		return self::is_tax_enabled() && apply_filters( 'storeengine/prices_include_tax', self::$prices_include_tax );
	}
}

// End of file tax-util.php.
