<?php

namespace StoreEngine\Utils;

/** @define "STOREENGINE_ROOT_DIR_PATH" "./../../" */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A class of utilities for dealing with internationalization.
 */
final class I18n {
	/**
	 * A cache for the i18n units data.
	 *
	 * @var ?array $units
	 */
	private static ?array $units = null;

	public static function get_units() {
		if ( null === self::$units ) {
			self::$units = apply_filters( 'storeengine/units', include STOREENGINE_ROOT_DIR_PATH . 'i18n/units.php' );
		}

		return self::$units;
	}

	/**
	 * Get the translated label for a weight unit of measure.
	 *
	 * This will return the original input string if it isn't found in the units array. This way a custom unit of
	 * measure can be used even if it's not getting translated.
	 *
	 * @param string $weight_unit The abbreviated weight unit in English, e.g. kg.
	 *
	 * @return string
	 */
	public static function get_weight_unit_label( string $weight_unit ): string {
		return self::get_units()['weight'][ $weight_unit ] ?? $weight_unit;
	}

	/**
	 * Get the translated label for a dimensions unit of measure.
	 *
	 * This will return the original input string if it isn't found in the units array. This way a custom unit of
	 * measure can be used even if it's not getting translated.
	 *
	 * @param string $dimensions_unit The abbreviated dimension unit in English, e.g. cm.
	 *
	 * @return string
	 */
	public static function get_dimensions_unit_label( string $dimensions_unit ): string {
		return self::get_units()['dimensions'][ $dimensions_unit ] ?? $dimensions_unit;
	}
}
