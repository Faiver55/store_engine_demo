<?php
/**
 * Formatting utilities
 */

namespace StoreEngine\Utils;

use DateTime;
use DateTimeZone;
use Exception;
use StoreEngine;
use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Coupon;
use StoreEngine\Classes\Customer;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\StoreengineDatetime;
use StoreEngine\Classes\Tax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Formatting {

	public static function init_hooks() {
		add_filter( 'storeengine/coupon_code', [ self::class, 'entity_decode_utf8' ] );
		add_filter( 'storeengine/coupon_code', [ self::class, 'sanitize_coupon_code' ] );
		add_filter( 'storeengine/coupon_code', [ self::class, 'strtolower' ] );
	}

	/**
	 * Wrapper for mb_strtoupper which see's if supported first.
	 *
	 * @param  ?string $string String to format.
	 *
	 * @return string
	 */
	public static function strtoupper( ?string $string ): string {
		$string ??= '';

		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $string ) : strtoupper( $string );
	}

	/**
	 * Make a string lowercase.
	 * Try to use mb_strtolower() when available.
	 *
	 * @param  ?string $string String to format.
	 *
	 * @return string
	 */
	public static function strtolower( ?string $string ): string {
		$string ??= '';

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $string ) : strtolower( $string );
	}

	public static function entity_decode_utf8( string $string ): string {
		return html_entity_decode( $string, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	public static function entity_encode_utf8( string $string ): string {
		return htmlentities( $string, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Make a slug (string joined with dash/underscore) into words and returned with applied callback.
	 *
	 * @param string $slug Slug/string to split into multiple word.
	 * @param callable $pretty_cb Prettify callback. Default is `ucwords`
	 *
	 * @return string
	 */
	public static function slug_to_words( string $slug, $pretty_cb = 'ucwords' ): string {
		$slug = str_replace( [ '_', '-' ], ' ', $slug );
		$slug = preg_replace( '/\s+/', ' ', $slug );

		return call_user_func( $pretty_cb, trim( $slug ) );
	}

	/**
	 * Sanitize a coupon code.
	 *
	 * Uses sanitize_post_field since coupon codes are stored as post_titles - the sanitization and escaping must match.
	 *
	 * Due to the unfiltered_html capability that some (admin) users have, we need to account for slashes.
	 *
	 * @param string|int|float $value Coupon code to format.
	 *
	 * @return string
	 */
	public static function sanitize_coupon_code( $value ): string {
		$value = wp_kses( sanitize_post_field( 'post_title', $value ?? '', 0, 'db' ), 'entities' );

		return current_user_can( 'unfiltered_html' ) ? $value : stripslashes( $value );
	}

	public static function sanitize_permalink( $value ): string {
		global $wpdb;
		$value = $wpdb->strip_invalid_text_for_column( $wpdb->options, 'option_value', $value );

		if ( is_wp_error( $value ) ) {
			$value = '';
		}

		$value = esc_url_raw( trim( $value ) );
		/** @noinspection HttpUrlsUsage */
		$value = str_replace( 'http://', '', $value );

		return untrailingslashit( $value );
	}

	/**
	 * Clean variables using sanitize_text_field. Arrays are cleaned recursively.
	 * Non-scalar values are ignored.
	 *
	 * @param string|array $var Data to sanitize.
	 *
	 * @return string|array
	 */
	public static function clean( $var ) {
		if ( is_array( $var ) ) {
			return array_map( [ self::class, 'clean' ], $var );
		} else {
			return is_scalar( $var ) ? sanitize_text_field( $var ) : $var;
		}
	}

	/**
	 * Cleanup extra whitespaces (tab, newline) from html/text content.
	 *
	 * @param string $html
	 *
	 * @return string
	 */
	public static function clean_html_whitespaces( string $html ): string {
		// Remove tabs
		$html = str_replace( "\t", '', $html );

		// Remove newlines
		$html = str_replace( [ "\r", "\n", "\r\n" ], ' ', $html );

		// Replace multiple spaces with one
		$html = preg_replace( '/ {2,}/', ' ', $html );

		// WP generating empty p tag for comment blocks too...
		// Remove HTML comments (but keep IE conditionals and WP block comments)
		$html = preg_replace_callback(
			'/<!--(.*?)-->/s',
			function ( $matches ) {
				$comment = trim( $matches[1] );

				// Keep if it's a conditional comment or WP block comment
				if (
					str_starts_with( $comment, '[' ) || // IE conditional
					str_starts_with( $comment, 'wp:' ) || // WP block start
					str_starts_with( $comment, '/wp:' )     // WP block end
				) {
					return $matches[0]; // return as-is
				}

				return ''; // remove standard comment
			},
			$html
		);

		// Handle empty space between tags (excluding span)
		// This also generates extra/empty p tags specially before ending of div.
		// Caching should be done by the server or third-party content caching plugin.
		$html = str_replace( '/span> <span', '/span>|<span', $html );
		$html = str_replace( '> <', '><', $html );
		$html = str_replace( '>|<', '> <', $html );

		// Trim any leading/trailing space
		return trim( $html );
	}

	protected static array $safe_text_kses_rules = [
		'br'   => true,
		'img'  => [
			'alt'   => true,
			'class' => true,
			'src'   => true,
			'title' => true,
		],
		'p'    => [
			'class' => true,
		],
		'span' => [
			'class' => true,
			'title' => true,
		],
	];

	public static function sanitize_safe_text_field( ?string $value ): string {
		return wp_kses( force_balance_tags( stripslashes( wp_unslash( $value ?? '' ) ) ), self::$safe_text_kses_rules );
	}

	/**
	 * Format the postcode according to the country and length of the postcode.
	 *
	 * @param ?string $postcode Unformatted postcode.
	 * @param string $country Base country.
	 *
	 * @return string
	 */
	public static function format_postcode( ?string $postcode, string $country ): string {
		$postcode = self::normalize_postcode( $postcode ?? '' );

		switch ( $country ) {
			case 'SE':
				$postcode = substr_replace( $postcode, ' ', - 2, 0 );
				break;
			case 'CA':
			case 'GB':
				$postcode = substr_replace( $postcode, ' ', - 3, 0 );
				break;
			case 'IE':
				$postcode = substr_replace( $postcode, ' ', 3, 0 );
				break;
			case 'BR':
			case 'PL':
				$postcode = substr_replace( $postcode, '-', - 3, 0 );
				break;
			case 'JP':
				$postcode = substr_replace( $postcode, '-', 3, 0 );
				break;
			case 'PT':
				$postcode = substr_replace( $postcode, '-', 4, 0 );
				break;
			case 'PR':
			case 'US':
			case 'MN':
				$postcode = rtrim( substr_replace( $postcode, '-', 5, 0 ), '-' );
				break;
			case 'NL':
				$postcode = substr_replace( $postcode, ' ', 4, 0 );
				break;
			case 'LV':
				$postcode = preg_replace( '/^(LV)?-?(\d+)$/', 'LV-${2}', $postcode );
				break;
			case 'CZ':
			case 'SK':
				$postcode = preg_replace( "/^({$country})-?(\d+)$/", '${1}-${2}', $postcode );
				$postcode = substr_replace( $postcode, ' ', - 2, 0 );
				break;
			case 'DK':
				$postcode = preg_replace( '/^(DK)(.+)$/', '${1}-${2}', $postcode );
				break;
		}

		return apply_filters( 'storeengine/format_postcode', trim( $postcode ), $country );
	}

	/**
	 * Normalize postcodes.
	 *
	 * Remove spaces and convert characters to uppercase.
	 *
	 * @param ?string $postcode Postcode.
	 *
	 * @return string
	 */
	public static function normalize_postcode( ?string $postcode ): string {
		return preg_replace( '/[\s\-]/', '', trim( self::strtoupper( $postcode ?? '' ) ) );
	}

	/**
	 * Make numeric postcode.
	 *
	 * Converts letters to numbers so we can do a simple range check on postcodes.
	 * E.g. PE30 becomes 16050300 (P = 16, E = 05, 3 = 03, 0 = 00)
	 *
	 * @param string|int $postcode Regular postcode.
	 *
	 * @return string
	 */
	public static function make_numeric_postcode( $postcode ): string {
		$postcode           = str_replace( [ ' ', '-' ], '', $postcode ?? '' );
		$postcode_length    = strlen( $postcode );
		$letters_to_numbers = array_merge( [ 0 ], range( 'A', 'Z' ) );
		$letters_to_numbers = array_flip( $letters_to_numbers );
		$numeric_postcode   = '';

		for ( $i = 0; $i < $postcode_length; $i ++ ) {
			if ( is_numeric( $postcode[ $i ] ) ) {
				$numeric_postcode .= str_pad( $postcode[ $i ], 2, '0', STR_PAD_LEFT );
			} elseif ( isset( $letters_to_numbers[ $postcode[ $i ] ] ) ) {
				$numeric_postcode .= str_pad( $letters_to_numbers[ $postcode[ $i ] ], 2, '0', STR_PAD_LEFT );
			} else {
				$numeric_postcode .= '00';
			}
		}

		return $numeric_postcode;
	}

	/**
	 * Format phone numbers.
	 *
	 * @param string|null $phone Phone number.
	 *
	 * @return string
	 */
	public static function format_phone_number( ?string $phone ): string {
		if ( ! Validation::is_phone( $phone ) ) {
			return '';
		}

		/** @noinspection RegExpRedundantEscape */
		return preg_replace( '/[^0-9\+\-\(\)\s]/', '-', preg_replace( '/[\x00-\x1F\x7F-\xFF]/', '', $phone ?? '' ) );
	}


	/**
	 * Get the price format depending on the currency position.
	 *
	 * @return string
	 */
	public static function get_price_format(): string {
		$currency_pos = self::get_currency_position();
		$format       = '%1$s%2$s';

		switch ( $currency_pos ) {
			case 'left':
				$format = '%1$s%2$s';
				break;
			case 'right':
				$format = '%2$s%1$s';
				break;
			case 'left_space':
				$format = '%1$s&nbsp;%2$s';
				break;
			case 'right_space':
				$format = '%2$s&nbsp;%1$s';
				break;
		}

		return apply_filters( 'storeengine/price_format', $format, $currency_pos );
	}

	public static function get_currency(): string {
		return apply_filters( 'storeengine/currency', Helper::get_settings( 'store_currency', 'USD' ) );
	}

	public static function get_currency_position(): string {
		return apply_filters( 'storeengine/currency_position', Helper::get_settings( 'store_currency_position' ) );
	}

	/**
	 * Return the thousand separator for prices.
	 *
	 * @return string
	 */
	public static function get_price_thousand_separator(): string {
		return stripslashes( apply_filters( 'storeengine/price_thousand_separator', Helper::get_settings( 'store_currency_thousand_separator' ) ) );
	}

	/**
	 * Return the decimal separator for prices.
	 *
	 * @return string
	 */
	public static function get_price_decimal_separator(): string {
		$separator = apply_filters( 'storeengine/price_decimal_separator', Helper::get_settings( 'store_currency_decimal_separator' ) );

		return $separator ? stripslashes( $separator ) : '.';
	}

	/**
	 * Return the number of decimals after the decimal point.
	 *
	 * @return int
	 */
	public static function get_price_decimals(): int {
		return absint( apply_filters( 'storeengine/price_decimals', Helper::get_settings( 'store_currency_decimal_limit', 2 ) ) );
	}

	/**
	 * Format the price with a currency symbol.
	 *
	 * @param float|string|int $price Raw price.
	 * @param array{
	 *     ex_tax_label:bool,
	 *     currency:string,
	 *     decimal_separator:string,
	 *     thousand_separator:string,
	 *     decimals:string,
	 *     price_format:string
	 * }|string $args Arguments to format a price {
	 *     Array of arguments.
	 *     Defaults to empty array.
	 *
	 * @type bool $ex_tax_label Adds exclude tax label. Defaults to false.
	 * @type string $currency Currency code. Defaults to empty string (Use the result from get_storeengine/currency()).
	 * @type string $decimal_separator A Decimal separator. Defaults the result of self::get_price_decimal_separator().
	 * @type string $thousand_separator A Thousand separator. Defaults the result of self::get_price_thousand_separator().
	 * @type string $decimals Number of decimals. Defaults the result of self::get_price_decimals().
	 * @type string $price_format Price format depending on the currency position. Defaults the result of self::get_price_format().
	 * }
	 * @return string
	 */
	public static function price( $price, $args = [] ): string {
		$args = apply_filters( 'storeengine/price_args', wp_parse_args( $args, [
			'ex_tax_label'       => false,
			'currency'           => '',
			'decimal_separator'  => self::get_price_decimal_separator(),
			'thousand_separator' => self::get_price_thousand_separator(),
			'decimals'           => self::get_price_decimals(),
			'price_format'       => self::get_price_format(),
		] ) );

		$original_price = $price;

		// Convert to float to avoid issues on PHP 8.
		$price = (float) $price;

		$unformatted_price = $price;
		$negative          = $price < 0;

		/**
		 * Filter raw price.
		 *
		 * @param float $raw_price Raw price.
		 * @param float|string $original_price Original price as float, or empty string. Since 5.0.0.
		 */
		$price = apply_filters( 'storeengine/raw/price', $negative ? $price * - 1 : $price, $original_price );

		/**
		 * Filter formatted price.
		 *
		 * @param float $formatted_price Formatted price.
		 * @param float $price Unformatted price.
		 * @param int $decimals Number of decimals.
		 * @param string $decimal_separator A Decimal separator.
		 * @param string $thousand_separator A Thousand separator.
		 * @param float|string $original_price Original price as float, or empty string. Since 5.0.0.
		 */
		$price = apply_filters( 'storeengine/formatted/price', number_format( $price, $args['decimals'], $args['decimal_separator'], $args['thousand_separator'] ), $price, $args['decimals'], $args['decimal_separator'], $args['thousand_separator'], $original_price );

		if ( apply_filters( 'storeengine/price_trim_zeros', false ) && $args['decimals'] > 0 ) {
			$price = self::trim_zeros( $price );
		}

		$formatted_price = ( $negative ? '-' : '' ) . sprintf( $args['price_format'], '<span class="storeengine-price__currency-symbol">' . Helper::get_currency_symbol( $args['currency'] ) . '</span>', $price );
		$return          = '<span class="storeengine-price"><bdi>' . $formatted_price . '</bdi></span>';

		if ( $args['ex_tax_label'] && TaxUtil::is_tax_enabled() ) {
			$return .= ' <small class="storeengine-price-tax">' . Countries::init()->ex_tax_or_vat() . '</small>';
		}

		/**
		 * Filters the string of price markup.
		 *
		 * @param string $return Price HTML markup.
		 * @param string $price Formatted price.
		 * @param array $args Pass on the args.
		 * @param float $unformatted_price Price as float to allow plugins custom formatting. Since 3.2.0.
		 * @param float|string $original_price Original price as float, or empty string. Since 5.0.0.
		 */
		return apply_filters( 'storeengine/price', $return, $price, $args, $unformatted_price, $original_price );
	}

	/**
	 * Normalise dimensions, unify to cm then convert to wanted unit value.
	 *
	 * Usage:
	 * Formatting::get_dimension( 55, 'in' );
	 * Formatting::get_dimension( 55, 'in', 'm' );
	 *
	 * @param int|float $dimension Dimension.
	 * @param string $to_unit Unit to convert to.
	 *                                Options: 'in', 'mm', 'cm', 'm'.
	 * @param string $from_unit Unit to convert from.
	 *                                Defaults to ''.
	 *                                Options: 'in', 'mm', 'cm', 'm'.
	 *
	 * @return float
	 */
	public static function get_dimension( $dimension, string $to_unit, string $from_unit = '' ): float {
		$to_unit = strtolower( $to_unit );

		if ( empty( $from_unit ) ) {
			$from_unit = strtolower( Helper::get_settings( 'store_dimension_unit' ) );
		}

		// Unify all units to cm first.
		if ( $from_unit !== $to_unit ) {
			switch ( $from_unit ) {
				case 'in':
					$dimension *= 2.54;
					break;
				case 'm':
					$dimension *= 100;
					break;
				case 'mm':
					$dimension *= 0.1;
					break;
				case 'yd':
					$dimension *= 91.44;
					break;
			}

			// Output desired unit.
			switch ( $to_unit ) {
				case 'in':
					$dimension *= 0.3937;
					break;
				case 'm':
					$dimension *= 0.01;
					break;
				case 'mm':
					$dimension *= 10;
					break;
				case 'yd':
					$dimension *= 0.010936133;
					break;
			}
		}

		return (float) ( ( $dimension < 0 ) ? 0 : $dimension );
	}

	/**
	 * Format dimensions for display.
	 *
	 * @param int[]|float[]|string[] $dimensions Array of dimensions.
	 *
	 * @return string
	 */
	public static function format_dimensions( array $dimensions ): string {
		$dimension_string = implode( ' &times; ', array_filter( array_map( [ self::class, 'format_localized_decimal' ], $dimensions ) ) );

		if ( ! empty( $dimension_string ) ) {
			$dimension_label = I18n::get_dimensions_unit_label( Helper::get_settings( 'store_dimension_unit' ) );

			$dimension_string = sprintf(
			// translators: 1. A formatted number; 2. A label for a dimensions unit of measure. E.g. 3.14 cm.
				_x( '%1$s %2$s', 'formatted dimensions', 'storeengine' ),
				$dimension_string,
				$dimension_label
			);
		} else {
			$dimension_string = __( 'N/A', 'storeengine' );
		}

		return apply_filters( 'storeengine/format_dimensions', $dimension_string, $dimensions );
	}

	/**
	 * Normalise weights, unify to kg then convert to wanted unit value.
	 *
	 * Usage:
	 * Formatting::get_weight(55, 'kg');
	 * Formatting::get_weight(55, 'kg', 'lbs');
	 *
	 * @param int|float $weight Weight.
	 * @param string $to_unit Unit to convert to.
	 *                             Options: 'g', 'kg', 'lbs', 'oz'.
	 * @param string $from_unit Unit to convert from.
	 *                             Defaults to ''.
	 *                             Options: 'g', 'kg', 'lbs', 'oz'.
	 *
	 * @return float
	 */
	public static function get_weight( $weight, string $to_unit, string $from_unit = '' ): float {
		$weight  = (float) $weight;
		$to_unit = strtolower( $to_unit );

		if ( empty( $from_unit ) ) {
			$from_unit = strtolower( Helper::get_settings( 'store_weight_unit' ) );
		}

		// Unify all units to kg first.
		if ( $from_unit !== $to_unit ) {
			switch ( $from_unit ) {
				case 'g':
					$weight *= 0.001;
					break;
				case 'lbs':
					$weight *= 0.453592;
					break;
				case 'oz':
					$weight *= 0.0283495;
					break;
			}

			// Output desired unit.
			switch ( $to_unit ) {
				case 'g':
					$weight *= 1000;
					break;
				case 'lbs':
					$weight *= 2.20462;
					break;
				case 'oz':
					$weight *= 35.274;
					break;
			}
		}

		return (float) ( $weight < 0 ) ? 0 : $weight;
	}

	/**
	 * Format a weight for display.
	 *
	 * @param float|int|string $weight Weight.
	 *
	 * @return string
	 */
	public static function format_weight( $weight ): string {
		$weight_string = self::format_localized_decimal( $weight );

		if ( ! empty( $weight_string ) ) {
			$weight_label = I18n::get_weight_unit_label( Helper::get_settings( 'store_weight_unit' ) );

			$weight_string = sprintf(
			// translators: 1. A formatted number; 2. A label for a weight unit of measure. E.g. 2.72 kg.
				_x( '%1$s %2$s', 'formatted weight', 'storeengine' ),
				$weight_string,
				$weight_label
			);
		} else {
			$weight_string = __( 'N/A', 'storeengine' );
		}

		return apply_filters( 'storeengine/format_weight', $weight_string, $weight );
	}

	/**
	 * Trim trailing zeros off prices.
	 *
	 * @param string|float|int $price Price.
	 *
	 * @return string
	 */
	public static function trim_zeros( $price ): string {
		return preg_replace( '/' . preg_quote( self::get_price_decimal_separator(), '/' ) . '0++$/', '', $price ?? '' );
	}

	/**
	 * Round a tax amount.
	 *
	 * @param float|string $value Amount to round.
	 * @param int|null $precision DP to round. Defaults to self::get_price_decimals.
	 *
	 * @return float
	 */
	public static function round_tax_total( $value, ?int $precision = null ): float {
		$precision   = is_null( $precision ) ? self::get_price_decimals() : intval( $precision );
		$rounded_tax = NumberUtil::round( $value, $precision, TaxUtil::get_tax_rounding_mode() ); // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctionParameters.round_modeFound

		return apply_filters( 'storeengine/round_tax_total', $rounded_tax, $value, $precision, TaxUtil::get_tax_rounding_mode() );
	}


	/**
	 * Round discount.
	 *
	 * @param float $value Amount to round.
	 * @param int $precision DP to round.
	 *
	 * @return float
	 */
	public static function round_discount( float $value, int $precision ): float {
		$mode = apply_filters( 'storeengine/discount_coupon_rounding_mode', PHP_ROUND_HALF_DOWN );

		return NumberUtil::round( $value, $precision, $mode ); // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctionParameters.round_modeFound
	}

	/**
	 * Format decimal numbers ready for DB storage.
	 *
	 * Sanitize, optionally remove decimals, and optionally round + trim off zeros.
	 *
	 * This function does not remove thousands - this should be done before passing a value to the function.
	 *
	 * @param float|string $number Expects either a float or a string with a decimal separator only (no thousands).
	 * @param mixed $dp number.  Number of decimal points to use, blank to use storeengine/price_num_decimals, or false to avoid all rounding.
	 * @param bool $trim_zeros From end of string.
	 *
	 * @return string|float
	 */
	public static function format_decimal( $number, $dp = false, bool $trim_zeros = false ) {
		$number ??= '';

		$locale   = localeconv();
		$decimals = array(
			self::get_price_decimal_separator(),
			$locale['decimal_point'],
			$locale['mon_decimal_point'],
		);

		// Remove locale from string.
		if ( ! is_float( $number ) ) {
			$number = str_replace( $decimals, '.', $number );

			// Convert multiple dots to just one.
			$number = preg_replace( '/\.(?![^.]+$)|[^0-9.-]/', '', sanitize_text_field( $number ) );
		}

		if ( false !== $dp ) {
			$dp     = intval( '' === $dp ? self::get_price_decimals() : $dp );
			$number = number_format( floatval( $number ), $dp, '.', '' );
		} elseif ( is_float( $number ) ) {
			// DP is false - don't use number format, just return a string using whatever is given. Remove scientific notation using sprintf.
			$number = str_replace( $decimals, '.', sprintf( '%.' . self::get_rounding_precision() . 'f', $number ) );
			// We already had a float, so trailing zeros are not needed.
			$trim_zeros = true;
		}

		if ( $trim_zeros && strstr( $number, '.' ) ) {
			$number = rtrim( rtrim( $number, '0' ), '.' );
		}

		return $number;
	}

	/**
	 * Convert a float to a string without locale formatting which PHP adds when changing floats to strings.
	 *
	 * @param float|string $float Float value to format.
	 *
	 * @return string
	 */
	public static function float_to_string( $float ): string {
		if ( ! is_float( $float ) ) {
			return $float;
		}

		$locale = localeconv();
		$string = strval( $float );

		return str_replace( $locale['decimal_point'], '.', $string );
	}

	/**
	 * Format a price with Currency Locale settings.
	 *
	 * @param string|float $value Price to localize.
	 *
	 * @return string
	 */
	public static function format_localized_price( $value ): string {
		return apply_filters( 'storeengine/format_localized_price', str_replace( '.', self::get_price_decimal_separator(), strval( $value ) ), $value );
	}

	/**
	 * Format a decimal with the decimal separator for prices or PHP Locale settings.
	 *
	 * @param string|float $value Decimal to localize.
	 *
	 * @return string
	 */
	public static function format_localized_decimal( $value ): string {
		$locale        = localeconv();
		$decimal_point = $locale['decimal_point'] ?? '.';
		$decimal       = ( ! empty( self::get_price_decimal_separator() ) ) ? self::get_price_decimal_separator() : $decimal_point;

		return apply_filters( 'storeengine/format_localized_decimal', str_replace( '.', $decimal, strval( $value ) ), $value );
	}

	/**
	 * Format a coupon code.
	 *
	 * @param string|int|float $value Coupon code to format.
	 *
	 * @return string
	 */
	public static function format_coupon_code( $value ): string {
		return apply_filters( 'storeengine/coupon_code', $value );
	}

	public static function get_base_rounding_precision(): int {
		return absint( apply_filters( 'storeengine/base_rounding_precision', 6 ) );
	}

	/**
	 * Get rounding precision for internal calculations.
	 * Will return the value of self::get_price_decimals increased by 2 decimals, with Formatting::ROUNDING_PRECISION being the minimum.
	 *
	 * @return int
	 */
	public static function get_rounding_precision(): int {
		$precision      = self::get_price_decimals() + 2;
		$base_precision = self::get_base_rounding_precision();

		if ( $precision < $base_precision ) {
			$precision = $base_precision;
		}

		/**
		 * Filter the rounding precision for internal calculations. This is different from the number of decimals used for display.
		 * Generally, this filter can be used to decrease the precision, but if you choose to decrease, there maybe side effects such as off by one rounding errors for certain tax rate combinations.
		 *
		 * @param int $precision The number of decimals to round to.
		 */
		return apply_filters( 'storeengine/internal_rounding_precision', $precision );
	}

	/**
	 * Add precision to a number by moving the decimal point to the right as many places as indicated by self::get_price_decimals().
	 * Optionally the result is rounded so that the total number of digits equals self::get_rounding_precision() plus one.
	 *
	 * @param float|int|null $value Number to add precision to.
	 * @param bool $round If the result should be rounded.
	 *
	 * @return int|float
	 */
	public static function add_number_precision( $value, bool $round = true ) {
		if ( ! $value ) {
			return 0.0;
		}

		$cent_precision = pow( 10, self::get_price_decimals() );
		$value          = $value * $cent_precision;

		return $round ? NumberUtil::round( $value, self::get_rounding_precision() - self::get_price_decimals() ) : $value;
	}

	/**
	 * Remove precision from a number and return a float.
	 *
	 * @param float|int|null $value Number to add precision to.
	 *
	 * @return float
	 */
	public static function remove_number_precision( float $value ): float {
		if ( ! $value ) {
			return 0.0;
		}

		$cent_precision = pow( 10, self::get_price_decimals() );

		return $value / $cent_precision;
	}

	/**
	 * Add precision to an array of number and return an array of int.
	 *
	 * @param int|int[]|float|float[]|array $value Number to add precision to.
	 * @param bool $round Should we round after adding precision?.
	 *
	 * @return int|array
	 */
	public static function add_number_precision_deep( $value, bool $round = true ) {
		if ( ! is_array( $value ) ) {
			return self::add_number_precision( $value, $round );
		}

		foreach ( $value as $key => $sub_value ) {
			$value[ $key ] = self::add_number_precision_deep( $sub_value, $round );
		}

		return $value;
	}

	/**
	 * Remove precision from an array of number and return an array of int.
	 *
	 * @param array|int|int[]|float|float[] $value Number to add precision to.
	 *
	 * @return float[]|float
	 */
	public static function remove_number_precision_deep( $value ) {
		if ( ! is_array( $value ) ) {
			return self::remove_number_precision( $value );
		}

		foreach ( $value as $key => $sub_value ) {
			$value[ $key ] = self::remove_number_precision_deep( $sub_value );
		}

		return $value;
	}

	/**
	 * Converts a string (e.g. 'yes' or 'no') to a bool.
	 *
	 * @param string|bool $string String to convert. If a bool is passed it will be returned as-is.
	 *
	 * @return bool
	 */
	public static function string_to_bool( $string ): bool {
		$string = $string ?? '';

		return is_bool( $string ) ? $string : ( 'yes' === strtolower( $string ) || 1 === $string || 'true' === strtolower( $string ) || '1' === $string );
	}

	/**
	 * Converts a bool to a 'yes' or 'no'.
	 *
	 * @param bool|string $bool Bool to convert. If a string is passed it will first be converted to a bool.
	 *
	 * @return string
	 */
	public static function bool_to_string( $bool ): string {
		if ( ! is_bool( $bool ) ) {
			$bool = self::string_to_bool( $bool );
		}

		return true === $bool ? 'yes' : 'no';
	}

	/**
	 * WooCommerce Date Format - Allows to change date format for everything WooCommerce.
	 *
	 * @return string
	 */
	public static function date_format(): string {
		$date_format = get_option( 'date_format' );
		if ( empty( $date_format ) ) {
			// Return default date format if the option is empty.
			$date_format = 'F j, Y';
		}

		return apply_filters( 'storeengine/date_format', $date_format );
	}

	/**
	 * WooCommerce Time Format - Allows to change time format for everything WooCommerce.
	 *
	 * @return string
	 */
	public static function time_format(): string {
		$time_format = get_option( 'time_format' );
		if ( empty( $time_format ) ) {
			// Return default time format if the option is empty.
			$time_format = 'g:i a';
		}

		return apply_filters( 'storeengine/time_format', $time_format );
	}

	/**
	 * Convert mysql datetime to PHP timestamp, forcing UTC. Wrapper for strtotime.
	 * Based on wcs_strtotime_dark_knight() from WC Subscriptions by Prospress.
	 *
	 * @param string|null $time_string Time string.
	 * @param int|null $from_timestamp Timestamp to convert from.
	 *
	 * @return int
	 * @noinspection SpellCheckingInspection
	 */
	public static function string_to_timestamp( ?string $time_string = null, ?int $from_timestamp = null ): int {
		$time_string = $time_string ?? '';

		$original_timezone = date_default_timezone_get();

		// @codingStandardsIgnoreStart
		date_default_timezone_set( 'UTC' );

		if ( null === $from_timestamp ) {
			$next_timestamp = strtotime( $time_string );
		} else {
			$next_timestamp = strtotime( $time_string, $from_timestamp );
		}

		date_default_timezone_set( $original_timezone );

		// @codingStandardsIgnoreEnd

		return $next_timestamp;
	}

	/**
	 * Convert a date string to a StoreengineDatetime.
	 *
	 * @param string|int|null $time_string Time string.
	 *
	 * @return StoreengineDatetime
	 * @throws StoreEngineException
	 */
	public static function string_to_datetime( $time_string = null ): StoreengineDatetime {
		try {
			$time_string = $time_string ?? '';

			if ( is_a( $time_string, StoreengineDatetime::class ) ) {
				$datetime = $time_string;
			} elseif ( is_numeric( $time_string ) ) {
				// Timestamps are handled as UTC timestamps in all cases.
				$datetime = new StoreengineDatetime( "@{$time_string}", new DateTimeZone( 'UTC' ) );
			} else {
				// Strings are defined in local WP timezone. Convert to UTC.
				/** @noinspection RegExpSingleCharAlternation */
				if ( 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(Z|((-|\+)\d{2}:\d{2}))$/', $time_string, $date_bits ) ) {
					$offset    = ! empty( $date_bits[7] ) ? iso8601_timezone_to_offset( $date_bits[7] ) : self::timezone_offset();
					$timestamp = gmmktime( $date_bits[4], $date_bits[5], $date_bits[6], $date_bits[2], $date_bits[3], $date_bits[1] ) - $offset;
				} else {
					$timestamp = self::string_to_timestamp( get_gmt_from_date( gmdate( 'Y-m-d H:i:s', self::string_to_timestamp( $time_string ) ) ) );
				}

				$datetime = new StoreengineDatetime( "@{$timestamp}", new DateTimeZone( 'UTC' ) );
			}

			// Set local timezone or offset.
			if ( get_option( 'timezone_string' ) ) {
				$datetime->setTimezone( new DateTimeZone( self::timezone_string() ) );
			} else {
				$datetime->set_utc_offset( self::timezone_offset() );
			}

			return $datetime;
		} catch ( Exception $e ) {
			throw new StoreEngineException( $e->getMessage(), 'failed-to-convert-string-into-datetime', [ 'datetime_string' => $time_string ], $e->getCode(), $e );
		}
	}

	/**
	 * Convert a date string into a timestamp without ever adding or deducting time.
	 *
	 * The strtotime() would be handy for this purpose, but alas, if other code running on the server
	 * is calling date_default_timezone_set() to change the timezone, strtotime() will assume the
	 * date is in that timezone unless the timezone is specific on the string (which it isn't for
	 * any MySQL formatted date) and attempt to convert it to UTC time by adding or deducting the
	 * GMT/UTC offset for that timezone, so for example, when 3rd party code has set the servers
	 * timezone using date_default_timezone_set( 'America/Los_Angeles' ) doing something like
	 * gmdate( "Y-m-d H:i:s", strtotime( gmdate( "Y-m-d H:i:s" ) ) ) will actually add 7 hours to
	 * the date even though it is a date in UTC timezone because the timezone wasn't specificed.
	 *
	 * This makes sure the date is never converted.
	 *
	 * @param string $date_string A date string formatted in MySQl or similar format that will map correctly when instantiating an instance of DateTime()
	 *
	 * @return int Unix timestamp representation of the timestamp passed in without any changes for timezones
	 */
	public static function string_to_datetime_utc( $date_string ): int {
		if ( ! $date_string ) {
			return 0;
		}

		$date_time = new StoreengineDatetime( $date_string, new DateTimeZone( 'UTC' ) );

		return intval( $date_time->getTimestamp() );
	}

	/**
	 * Take a date in the form of a timestamp, MySQL date/time string or DateTime object (or perhaps
	 * a WC_Datetime object when WC > 3.0 is active) and create a WC_DateTime object.
	 *
	 * @param string|integer|null $variable_date_type UTC timestamp, or ISO 8601 DateTime. If the DateTime string has no timezone or offset, WordPress site timezone will be assumed. Null if their is no date.
	 *
	 * @return null|StoreengineDatetime in site's timezone
	 */
	public static function get_datetime_from( $variable_date_type ): ?StoreengineDatetime {
		try {
			if ( empty( $variable_date_type ) ) {
				$datetime = null;
			} elseif ( is_a( $variable_date_type, StoreengineDatetime::class ) ) {
				$datetime = $variable_date_type;
			} elseif ( is_numeric( $variable_date_type ) ) {
				$datetime = new StoreengineDatetime( "@{$variable_date_type}", new DateTimeZone( 'UTC' ) );
				$datetime->setTimezone( new DateTimeZone( self::timezone_string() ) );
			} else {
				$datetime = new StoreengineDatetime( $variable_date_type, new DateTimeZone( self::timezone_string() ) );
			}
		} catch ( Exception $e ) {
			$datetime = null;
		}

		return $datetime;
	}

	public static function is_datetime( ?string $maybe_datetime ): bool {
		/** @noinspection RegExpSingleCharAlternation */
		return $maybe_datetime && preg_match( '/^(\d{4})-(\d{2})-(\d{2}).*(\d{2}):(\d{2}):(\d{2})(Z|((-|\+)\d{2}:\d{2}))?$/', $maybe_datetime );
	}

	/**
	 * WooCommerce Timezone - helper to retrieve the timezone string for a site until.
	 * a WP core method exists (see https://core.trac.wordpress.org/ticket/24730).
	 *
	 * Adapted from https://secure.php.net/manual/en/function.timezone-name-from-abbr.php#89155.
	 *
	 * @return string PHP timezone string for the site
	 */
	public static function timezone_string(): string {
		// Added in WordPress 5.3 Ref https://developer.wordpress.org/reference/functions/wp_timezone_string/.
		if ( function_exists( 'wp_timezone_string' ) ) {
			return wp_timezone_string();
		}

		// If site timezone string exists, return it.
		$timezone = get_option( 'timezone_string' );
		if ( $timezone ) {
			return $timezone;
		}

		// Get UTC offset, if it isn't set then return UTC.
		$utc_offset = floatval( get_option( 'gmt_offset', 0 ) );
		if ( ! is_numeric( $utc_offset ) || 0.0 === $utc_offset ) {
			return 'UTC';
		}

		// Adjust UTC offset from hours to seconds.
		$utc_offset = (int) ( $utc_offset * 3600 );

		// Attempt to guess the timezone string from the UTC offset.
		$timezone = timezone_name_from_abbr( '', $utc_offset );
		if ( $timezone ) {
			return $timezone;
		}

		// Last try, guess timezone string manually.
		foreach ( timezone_abbreviations_list() as $abbr ) {
			foreach ( $abbr as $city ) {
				// WordPress restrict the use of date(), since it's affected by timezone settings, but in this case is just what we need to guess the correct timezone.
				if ( (bool) date( 'I' ) === (bool) $city['dst'] && $city['timezone_id'] && intval( $city['offset'] ) === $utc_offset ) { // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
					return $city['timezone_id'];
				}
			}
		}

		// Fallback to UTC.
		return 'UTC';
	}

	/**
	 * Get timezone offset in seconds.
	 *
	 * @return float
	 * @throws Exception
	 */
	public static function timezone_offset() {
		$timezone = get_option( 'timezone_string' );

		if ( $timezone ) {
			$timezone_object = new DateTimeZone( $timezone );

			return $timezone_object->getOffset( new DateTime( 'now' ) );
		} else {
			return floatval( get_option( 'gmt_offset', 0 ) ) * HOUR_IN_SECONDS;
		}
	}

	/**
	 * Format a date for output.
	 *
	 * @param StoreengineDatetime|DateTime $date Instance of StoreengineDatetime.
	 * @param string $format Data format. Defaults to the Formatting::date_format function if not set.
	 *
	 * @return string
	 */
	public static function format_datetime( $date, string $format = '' ): string {
		if ( ! $format ) {
			$format = self::date_format();
		}

		if ( is_a( $date, StoreengineDatetime::class ) ) {
			return $date->date_i18n( $format );
		}

		if ( is_a( $date, DateTime::class ) ) {
			return date_i18n( $format, $date->getTimestamp() + $date->getOffset() );
		}

		return '';
	}

	/**
	 * Get a coupon label.
	 *
	 * @param string|Coupon $coupon Coupon data or code.
	 * @param bool $echo Echo or return.
	 *
	 * @return string|void
	 */
	public static function cart_totals_coupon_label( $coupon, bool $echo = true ) {
		if ( is_string( $coupon ) ) {
			$coupon = new Coupon( $coupon );
		}

		/* translators: %s: coupon code */
		$label = apply_filters( 'storeengine/cart/totals_coupon_label', sprintf( esc_html__( 'Coupon: %s', 'storeengine' ), $coupon->get_code() ), $coupon );

		if ( $echo ) {
			echo $label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			return $label;
		}
	}

	/**
	 * Get coupon display HTML.
	 *
	 * @param string|Coupon $coupon Coupon data or code.
	 */
	public static function cart_totals_coupon_html( $coupon ) {
		if ( is_string( $coupon ) ) {
			$coupon = new Coupon( $coupon );
		}

		$amount               = storeengine_cart()->get_coupon_discount_amount( $coupon->get_code(), storeengine_cart()->display_prices_including_tax() );
		$discount_amount_html = '-' . self::price( $amount );

		if ( $coupon->get_free_shipping() && empty( $amount ) ) {
			$discount_amount_html = __( 'Free shipping coupon', 'storeengine' );
		}

		$discount_amount_html = apply_filters( 'storeengine/coupon_discount_amount_html', $discount_amount_html, $coupon );
		$remove_url           = add_query_arg( 'remove_coupon', rawurlencode( $coupon->get_code() ), Helper::is_checkout() ? Helper::get_checkout_url() : Helper::get_cart_url() );
		$remove_url           = wp_nonce_url( $remove_url, 'storeengine/cart/remove_coupon' );
		$coupon_html          = $discount_amount_html . ' <a href="' . esc_url( $remove_url ) . '" class="storeengine-remove-coupon" data-coupon="' . esc_attr( $coupon->get_code() ) . '" aria-label="' . esc_html__( 'Remove coupon', 'storeengine' ) . '"><i class="storeengine-icon storeengine-icon--trash" aria-hidden="true"></i></a>';

		echo wp_kses( apply_filters( 'storeengine/cart/totals_coupon_html', $coupon_html, $coupon, $discount_amount_html ), array_replace_recursive( wp_kses_allowed_html( 'post' ), [ 'a' => [ 'data-coupon' => true ] ] ) ); // phpcs:ignore PHPCompatibility.PHP.NewFunctions.array_replace_recursiveFound
	}

	/**
	 * Get a coupon label.
	 *
	 * @param string|Coupon $coupon Coupon data or code.
	 * @param bool $echo Echo or return.
	 *
	 * @return string|void
	 */
	public static function cart_totals_fee_label( $fee, bool $echo = true ) {
		if ( is_string( $fee ) ) {
			$fee = (object) [ 'name' => $fee ];
		}

		/* translators: %s: Fee name */
		$label = apply_filters( 'storeengine/cart/totals_fee_label', sprintf( esc_html__( '%s Fee', 'storeengine' ), $fee->name ), $fee );

		if ( $echo ) {
			echo $label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			return $label;
		}
	}

	/**
	 * Get order total html including inc tax if needed.
	 */
	public static function cart_totals_order_total_html() {
		$value = '<strong>' . storeengine_cart()->get_total() . '</strong> ';

		// If prices are tax inclusive, show taxes here.
		if ( TaxUtil::is_tax_enabled() && storeengine_cart()->display_prices_including_tax() ) {
			$tax_string_array = array();
			$cart_tax_totals  = storeengine_cart()->get_tax_totals();

			if ( 'itemized' === Helper::get_settings( 'tax_total_display' ) ) {
				foreach ( $cart_tax_totals as $code => $tax ) {
					$tax_string_array[] = sprintf( '%s %s', $tax->formatted_amount, $tax->label );
				}
			} elseif ( ! empty( $cart_tax_totals ) ) {
				$tax_string_array[] = sprintf( '%s %s', self::price( storeengine_cart()->get_taxes_total() ), Countries::init()->tax_or_vat() );
			}

			if ( ! empty( $tax_string_array ) ) {
				$taxable_address = StoreEngine::init()->customer->get_taxable_address();
				if ( StoreEngine::init()->customer->is_customer_outside_base() && ! StoreEngine::init()->customer->has_calculated_shipping() ) {
					$country = Countries::init()->estimated_for_prefix( $taxable_address[0] ) . Countries::init()->get_countries()[ $taxable_address[0] ];
					/* translators: 1: tax amount 2: country name */
					$tax_text = wp_kses_post( sprintf( __( '(includes %1$s estimated for %2$s)', 'storeengine' ), implode( ', ', $tax_string_array ), $country ) );
				} else {
					/* translators: %s: tax amounts */
					$tax_text = wp_kses_post( sprintf( __( '(includes %s)', 'storeengine' ), implode( ', ', $tax_string_array ) ) );
				}

				$value .= '<small class="includes_tax">' . $tax_text . '</small>';
			}
		}

		echo apply_filters( 'storeengine/cart/totals_order_total_html', $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function cart_totals_shipping_html() {
		$cart           = Helper::cart();
		$chosen_methods = $cart->get_meta( 'chosen_shipping_methods' );
		$packages       = StoreEngine\Shipping\Shipping::init()->get_packages();
		$first          = true;

		foreach ( $packages as $i => $package ) {
			$chosen_method = $chosen_methods[ $i ] ?? '';
			$product_names = [];

			if ( count( $packages ) > 1 ) {
				foreach ( $package['contents'] as $item_id => $values ) {
					$product_names[ $item_id ] = $values['data']->get_name() . ' &times;' . $values['quantity'];
				}
				$product_names = apply_filters( 'storeengine/shipping/package_details_array', $product_names, $package );
			}

			Template::get_template(
				'cart/cart-shipping.php',
				[
					'package'                  => $package,
					'available_methods'        => $package['rates'],
					'show_package_details'     => count( $packages ) > 1,
					'show_shipping_calculator' => Helper::is_cart() && apply_filters( 'storeengine/shipping/show_shipping_calculator', $first, $i, $package ),
					'package_details'          => implode( ', ', $product_names ),
					/* translators: %d: shipping package number */
					'package_name'             => apply_filters( 'storeengine/shipping/package_name', ( ( $i + 1 ) > 1 ) ? sprintf( _x( 'Shipping %d', 'shipping packages', 'storeengine' ), ( $i + 1 ) ) : _x( 'Shipping', 'shipping packages', 'storeengine' ), $i, $package ),
					'index'                    => $i,
					'chosen_method'            => $chosen_method,
					'formatted_destination'    => Countries::init()->get_formatted_address( $package['destination'], ', ' ),
					'has_calculated_shipping'  => $cart->has_calculated_shipping(),
				]
			);

			$first = false;
		}
	}

	/**
	 * Array merge and sum function.
	 *
	 * Source:  https://gist.github.com/Nickology/f700e319cbafab5eaedc
	 *
	 * @return array
	 */
	public static function array_merge_recursive_numeric(): array {
		$arrays = func_get_args();

		// If there's only one array, it's already merged.
		if ( 1 === count( $arrays ) ) {
			return $arrays[0];
		}

		// Remove any items in $arrays that are NOT arrays.
		foreach ( $arrays as $key => $array ) {
			if ( ! is_array( $array ) ) {
				unset( $arrays[ $key ] );
			}
		}

		// We start by setting the first array as our final array.
		// We will merge all other arrays with this one.
		$final = array_shift( $arrays );

		foreach ( $arrays as $b ) {
			foreach ( $final as $key => $value ) {
				// If $key does not exist in $b, then it is unique and can be safely merged.
				if ( ! isset( $b[ $key ] ) ) {
					$final[ $key ] = $value;
				} else {
					// If $key is present in $b, then we need to merge and sum numeric values in both.
					if ( is_numeric( $value ) && is_numeric( $b[ $key ] ) ) {
						// If both values for these keys are numeric, we sum them.
						$final[ $key ] = $value + $b[ $key ];
					} elseif ( is_array( $value ) && is_array( $b[ $key ] ) ) {
						// If both values are arrays, we recursively call ourselves.
						$final[ $key ] = self::array_merge_recursive_numeric( $value, $b[ $key ] );
					} else {
						// If both keys exist but differ in type, then we cannot merge them.
						// In this scenario, we will $b's value for $key is used.
						$final[ $key ] = $b[ $key ];
					}
				}
			}

			// Finally, we need to merge any keys that exist only in $b.
			foreach ( $b as $key => $value ) {
				if ( ! isset( $final[ $key ] ) ) {
					$final[ $key ] = $value;
				}
			}
		}

		return $final;
	}

	/**
	 * For a given product, and optionally price/qty, work out the price with tax included, based on store settings.
	 *
	 * @param float|int|string $price Product object.
	 * @param ?int $priceId Product id.
	 * @param ?int $product Product object.
	 * @param array $args Optional arguments to pass product quantity and price.
	 *
	 * @return float|string Price with tax included, or an empty string if price calculation failed.
	 */
	public static function get_price_including_tax( $price, ?int $priceId = null, ?int $product = null, array $args = [] ) {
		$product = $product ? Helper::get_product( $product ) : null;
		$args    = wp_parse_args( $args, [
			'qty'     => '',
			'price'   => '',
			'taxable' => ! $product || $product->is_taxable(),
		] );

		$price = '' !== $args['price'] ? max( 0.0, (float) $args['price'] ) : (float) $price;
		$qty   = '' !== $args['qty'] ? max( 0, absint( $args['qty'] ) ) : 1;

		if ( empty( $qty ) ) {
			return 0.0;
		}

		$line_price   = $price * $qty;
		$return_price = $line_price;

		if ( $args['taxable'] ) {
			if ( ! TaxUtil::prices_include_tax() ) {
				// If the customer is exempt from VAT, set tax total to 0.
				if ( ! empty( StoreEngine::init()->customer ) && StoreEngine::init()->customer->get_is_vat_exempt() ) {
					$taxes_total = 0.00;
				} else {
					$tax_rates = Tax::get_rates( '' );
					$taxes     = Tax::calc_tax( $line_price, $tax_rates, false );

					if ( Tax::$round_at_subtotal ) {
						$taxes_total = array_sum( $taxes );
					} else {
						$taxes_total = array_sum( array_map( [ self::class, 'round_tax_total' ], $taxes ) );
					}
				}

				$return_price = NumberUtil::round( $line_price + $taxes_total, self::get_price_decimals() );
			} else {
				$tax_rates      = Tax::get_rates( $product ? $product->get_tax_class() : '' );
				$base_tax_rates = Tax::get_base_tax_rates( $product ? $product->get_tax_class( 'unfiltered' ) : '' );

				/**
				 * If the customer is exempt from VAT, remove the taxes here.
				 * Either remove the base or the user taxes depending on storeengine/adjust_non_base_location_prices setting.
				 */
				if ( ! empty( StoreEngine::init()->customer ) && StoreEngine::init()->customer->get_is_vat_exempt() ) { // @codingStandardsIgnoreLine.
					if ( apply_filters( 'storeengine/adjust_non_base_location_prices', true ) ) {
						$remove_taxes = Tax::calc_tax( $line_price, $base_tax_rates, true );
					} else {
						$remove_taxes = Tax::calc_tax( $line_price, $tax_rates, true );
					}

					if ( Tax::$round_at_subtotal ) {
						$remove_taxes_total = array_sum( $remove_taxes );
					} else {
						$remove_taxes_total = array_sum( array_map( [ self::class, 'round_tax_total' ], $remove_taxes ) );
					}

					$return_price = NumberUtil::round( $line_price - $remove_taxes_total, self::get_price_decimals() );

					/**
					 * The storeengine/adjust_non_base_location_prices filter can stop base taxes being taken off when
					 * dealing without of base locations. e.g. If a product costs 10 including tax, all users will pay
					 * 10 regardless of location and taxes.
					 *
					 * This feature is experimental and may change in the future. Use at your risk.
					 */
				} elseif ( $tax_rates !== $base_tax_rates && apply_filters( 'storeengine/adjust_non_base_location_prices', true ) ) {
					$base_taxes   = Tax::calc_tax( $line_price, $base_tax_rates, true );
					$modded_taxes = Tax::calc_tax( $line_price - array_sum( $base_taxes ), $tax_rates, false );

					if ( Tax::$round_at_subtotal ) {
						$base_taxes_total   = array_sum( $base_taxes );
						$modded_taxes_total = array_sum( $modded_taxes );
					} else {
						$base_taxes_total   = array_sum( array_map( [ self::class, 'round_tax_total' ], $base_taxes ) );
						$modded_taxes_total = array_sum( array_map( [ self::class, 'round_tax_total' ], $modded_taxes ) );
					}

					$return_price = NumberUtil::round( $line_price - $base_taxes_total + $modded_taxes_total, self::get_price_decimals() );
				}
			}
		}

		return apply_filters( 'storeengine/get_price_including_tax', $return_price, $qty, $priceId, $product );
	}

	/**
	 * For a given product, and optionally price/qty, work out the price with tax excluded, based on store settings.
	 *
	 * @param float|int|string $price Product object.
	 * @param ?int $priceId Product id.
	 * @param ?int $product Product id.
	 * @param array $args Optional arguments to pass product quantity and price.
	 *
	 * @return float|string Price with tax excluded, or an empty string if price calculation failed.
	 */
	public static function get_price_excluding_tax( $price, ?int $priceId = null, ?int $product = null, array $args = [] ) {
		$product = $product ? Helper::get_product( $product ) : null;
		$args    = wp_parse_args( $args, [
			'qty'     => '',
			'price'   => '',
			'taxable' => ! $product || $product->is_taxable(),
		] );

		$price = '' !== $args['price'] ? max( 0.0, (float) $args['price'] ) : (float) $price;
		$qty   = '' !== $args['qty'] ? max( 0, absint( $args['qty'] ) ) : 1;

		if ( empty( $qty ) ) {
			return 0.0;
		}

		$line_price = $price * $qty;

		if ( $args['taxable'] && TaxUtil::prices_include_tax() ) {
			$order       = $args['order'] ?? null;
			$customer_id = $order ? $order->get_customer_id() : 0;
			if ( apply_filters( 'storeengine/adjust_non_base_location_prices', true ) ) {
				$tax_rates = Tax::get_base_tax_rates( $product ? $product->get_tax_class( 'unfiltered' ) : '' );
			} else {
				$customer  = $customer_id ? new Customer( $customer_id ) : null;
				$tax_rates = Tax::get_rates( '', $customer );
			}

			$remove_taxes = Tax::calc_tax( $line_price, $tax_rates, true );
			$return_price = $line_price - array_sum( $remove_taxes ); // Un-rounded since we're dealing with tax inclusive prices. Matches logic in cart-totals class. @see adjust_non_base_location_price.
		} else {
			$return_price = $line_price;
		}

		return apply_filters( 'storeengine/get_price_excluding_tax', $return_price, $qty, $priceId, $product );
	}

	/**
	 * Returns the price including or excluding tax.
	 *
	 * By default, it's based on the 'tax_display_shop' setting.
	 * Set `$arg['display_context']` to 'cart' to base on the 'tax_display_cart' setting instead.
	 *
	 * @param float|int|string $price Product object.
	 * @param ?int $priceId Product id.
	 * @param ?int $product Product id.
	 * @param array $args Optional arguments to pass product quantity and price.
	 *
	 * @return float|string Price with tax excluded, or an empty string if price calculation failed.
	 */
	public static function get_price_to_display( $price, ?int $priceId = null, ?int $product = null, array $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'qty'             => 1,
				'price'           => '',
				'display_context' => 'shop',
			]
		);

		$price       = '' !== $args['price'] ? max( 0.0, (float) $args['price'] ) : (float) $price;
		$qty         = '' !== $args['qty'] ? max( 0, absint( $args['qty'] ) ) : 1;
		$tax_display = Helper::get_settings( 'cart' === $args['display_context'] ? 'tax_display_cart' : 'tax_display_shop' );

		if ( 'incl' === $tax_display ) {
			return self::get_price_including_tax( $price, $priceId, $product, [
				'qty'   => $qty,
				'price' => $price,
			] );
		} else {
			return self::get_price_excluding_tax( $price, $priceId, $product, [
				'qty'   => $qty,
				'price' => $price,
			] );
		}
	}

	/**
	 * Format a sale price for display.
	 *
	 * @param string|int|float $regular_price Regular price.
	 * @param string|int|float $sale_price Sale price.
	 *
	 * @return string
	 */
	public static function format_sale_price( $regular_price, $sale_price ): string {
		// Format the prices.
		$formatted_regular_price = is_numeric( $regular_price ) ? self::price( $regular_price ) : $regular_price;
		$formatted_sale_price    = is_numeric( $sale_price ) ? self::price( $sale_price ) : $sale_price;

		// Strikethrough pricing.
		$price = '<del aria-hidden="true">' . $formatted_regular_price . '</del> ';

		// For accessibility (a11y) we'll also display that information to screen readers.
		$price .= '<span class="screen-reader-text"> ';
		// translators: %s is a product's regular price.
		$price .= esc_html( sprintf( __( 'Original price was: %s.', 'storeengine' ), wp_strip_all_tags( $formatted_regular_price ) ) );
		$price .= '</span>';

		// Add the sale price.
		$price .= ' <ins aria-hidden="true">' . $formatted_sale_price . '</ins> ';

		// For accessibility (a11y) we'll also display that information to screen readers.
		$price .= '<span class="screen-reader-text"> ';
		// translators: %s is a product's current (sale) price.
		$price .= esc_html( sprintf( __( 'Current price is: %s.', 'storeengine' ), wp_strip_all_tags( $formatted_sale_price ) ) );
		$price .= '</span>';

		return apply_filters( 'storeengine/format_sale_price', trim( $price ), $regular_price, $sale_price );
	}

	/**
	 * Format a price range for display.
	 *
	 * @param string|int|float $from Price from.
	 * @param string|int|float $to Price to.
	 *
	 * @return string
	 */
	public static function format_price_range( $from, $to ): string {
		/* translators: 1: price from 2: price to */
		$price = sprintf( _x( '%1$s &ndash; %2$s', 'Price range: from-to', 'storeengine' ), is_numeric( $from ) ? self::price( $from ) : $from, is_numeric( $to ) ? self::price( $to ) : $to );

		return apply_filters( 'storeengine/format_price_range', $price, $from, $to );
	}

	/**
	 * Make a refund total negative.
	 *
	 * @param float $amount Refunded amount.
	 *
	 * @return float
	 */
	public static function format_refund_total( $amount ): float {
		return $amount * - 1;
	}
}

// End of file formatting.php
