<?php
/**
 * This ongoing trait will have shared calculation logic between AbstractOrder and CartTotals classes.
 *
 * @package StoreEngine\Classes\Cart
 * @version 1.0.0
 */

namespace StoreEngine\Classes\Cart;

use StoreEngine\Classes\Tax;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\NumberUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @see \WC_Item_Totals
 */
trait ItemTotals {
	/**
	 * Line items to calculate. Define in child class.
	 *
	 * @param string $field Field name to calculate upon.
	 *
	 * @return array having `total`|`subtotal` property.
	 */
	abstract protected function get_values_for_total( string $field ): array;

	/**
	 * Return rounded total based on settings. Will be used by Cart and Orders.
	 *
	 * @param array $values Values to round. Should be with precision.
	 *
	 * @return float|int Appropriately rounded value.
	 */
	public static function get_rounded_items_total( array $values ) {
		return array_sum( array_map( [ self::class, 'round_item_subtotal' ], $values ) );
	}

	/**
	 * Apply rounding to item subtotal before summing.
	 *
	 * @param float|string $value Item subtotal value.
	 *
	 * @return float
	 */
	public static function round_item_subtotal( $value ) {
		if ( ! self::round_at_subtotal() ) {
			$value = NumberUtil::round( $value );
		}

		return $value;
	}

	/**
	 * Should always round at subtotal?
	 *
	 * @return bool
	 */
	protected static function round_at_subtotal(): bool {
		return Tax::$round_at_subtotal;
	}

	/**
	 * Apply rounding to an array of taxes before summing. Rounds to store DP setting, ignoring precision.
	 *
	 * @param float|string $value Tax value.
	 * @param bool $in_cents Whether precision of value is in cents.
	 *
	 * @return float
	 */
	protected static function round_line_tax( $value, bool $in_cents = true ) {
		if ( ! self::round_at_subtotal() ) {
			$value = Formatting::round_tax_total( $value, $in_cents ? 0 : null );
		}

		return $value;
	}
}
