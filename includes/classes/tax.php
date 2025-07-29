<?php
/**
 * Tax calculation and rate finding class.
 */

namespace StoreEngine\Classes;

use StoreEngine\Utils\Caching;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\NumberUtil;
use StoreEngine\Utils\TaxUtil;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @see \WC_Tax::init()
 */
class Tax {

	/**
	 * Precision.
	 *
	 * @var int
	 */
	public static int $precision = 6;

	/**
	 * Round at subtotal.
	 *
	 * @var bool
	 */
	public static bool $round_at_subtotal = false;

	/**
	 * Load options.
	 */
	public static function init() {
		self::$precision         = Formatting::get_rounding_precision();
		self::$round_at_subtotal = TaxUtil::tax_round_at_subtotal();
	}

	/**
	 * Calculate tax for a line.
	 *
	 * @param float|string $price Price to calc tax on.
	 * @param array $rates Rates to apply.
	 * @param boolean $price_includes_tax Whether the passed price has taxes included.
	 *
	 * @return array                       Array of rates + prices after tax.
	 */
	public static function calc_tax( $price, array $rates, bool $price_includes_tax = false ): array {
		$price = (float) $price;
		if ( $price_includes_tax ) {
			$taxes = self::calc_inclusive_tax( $price, $rates );
		} else {
			$taxes = self::calc_exclusive_tax( $price, $rates );
		}

		return apply_filters( 'storeengine/calc_tax', $taxes, $price, $rates, $price_includes_tax );
	}

	/**
	 * Calculate the shipping tax using a passed array of rates.
	 *
	 * @param float|string $price Shipping cost.
	 * @param array $rates Taxation Rate.
	 *
	 * @return array
	 */
	public static function calc_shipping_tax( $price, array $rates ): array {
		$taxes = self::calc_exclusive_tax( $price, $rates );

		return apply_filters( 'storeengine/calc_shipping_tax', $taxes, $price, $rates );
	}

	/**
	 * Round to precision.
	 *
	 * Filter example: to return rounding to .5 cents you'd use:
	 *
	 * function euro_5cent_rounding( $in ) {
	 *      return round( $in / 5, 2 ) * 5;
	 * }
	 * add_filter( 'storeengine_tax_round', 'euro_5cent_rounding' );
	 *
	 * @param float|int|string $in Value to round.
	 *
	 * @return float
	 */
	public static function round( $in ): float {
		return apply_filters( 'storeengine/tax_round', NumberUtil::round( $in, self::$precision ), $in );
	}

	/**
	 * Calc tax from inclusive price.
	 *
	 * @param float|string $price Price to calculate tax for.
	 * @param array $rates Array of tax rates.
	 *
	 * @return array
	 */
	public static function calc_inclusive_tax( $price, array $rates ): array {
		$taxes          = [];
		$compound_rates = [];
		$regular_rates  = [];

		// Index array so taxes are output in correct order and see what compound/regular rates we have to calculate.
		foreach ( $rates as $key => $rate ) {
			$taxes[ $key ] = 0;

			if ( 'yes' === $rate['compound'] ) {
				$compound_rates[ $key ] = $rate['rate'];
			} else {
				$regular_rates[ $key ] = $rate['rate'];
			}
		}

		$compound_rates     = array_reverse( $compound_rates, true ); // Working backwards.
		$non_compound_price = $price;

		foreach ( $compound_rates as $key => $compound_rate ) {
			$tax_amount         = apply_filters( 'storeengine/price_inc_tax_amount', $non_compound_price - ( $non_compound_price / ( 1 + ( $compound_rate / 100 ) ) ), $key, $rates[ $key ], $price );
			$non_compound_price = $non_compound_price - $tax_amount;
			// Add to tax total data.
			$taxes[ $key ] += $tax_amount;
		}

		// Regular taxes.
		$regular_tax_rate = 1 + ( array_sum( $regular_rates ) / 100 );

		foreach ( $regular_rates as $key => $regular_rate ) {
			$the_rate   = ( $regular_rate / 100 ) / $regular_tax_rate;
			$net_price  = $price - ( $the_rate * $non_compound_price );
			$tax_amount = apply_filters( 'storeengine/price_inc_tax_amount', $price - $net_price, $key, $rates[ $key ], $price );
			// Add to tax total data.
			$taxes[ $key ] += $tax_amount;
		}

		/**
		 * Round all taxes to precision (4DP) before passing them back. Note, this is not the same rounding
		 * as in the cart calculation class which, depending on settings, will round to 2DP when calculating
		 * final totals. Also unlike that class, this rounds .5 up for all cases.
		 */
		return array_map( [ __CLASS__, 'round' ], $taxes );
	}

	/**
	 * Calc tax from exclusive price.
	 *
	 * @param float|string $price Price to calculate tax for.
	 * @param array $rates Array of tax rates.
	 *
	 * @return array
	 */
	public static function calc_exclusive_tax( $price, array $rates ): array {
		$taxes = [];
		$price = (float) $price;

		if ( ! empty( $rates ) ) {
			foreach ( $rates as $key => $rate ) {
				if ( 'yes' === $rate['compound'] ) {
					continue;
				}

				$tax_amount = $price * ( floatval( $rate['rate'] ) / 100 );
				$tax_amount = apply_filters( 'storeengine/price_ex_tax_amount', $tax_amount, $key, $rate, $price ); // ADVANCED: Allow third parties to modify this rate.

				if ( ! isset( $taxes[ $key ] ) ) {
					$taxes[ $key ] = (float) $tax_amount;
				} else {
					$taxes[ $key ] += (float) $tax_amount;
				}
			}

			$pre_compound_total = array_sum( $taxes );

			// Compound taxes.
			foreach ( $rates as $key => $rate ) {
				if ( 'no' === $rate['compound'] ) {
					continue;
				}
				$the_price_inc_tax = $price + $pre_compound_total;
				$tax_amount        = $the_price_inc_tax * ( floatval( $rate['rate'] ) / 100 );
				$tax_amount        = apply_filters( 'storeengine/price_ex_tax_amount', $tax_amount, $key, $rate, $price, $the_price_inc_tax, $pre_compound_total ); // ADVANCED: Allow third parties to modify this rate.

				if ( ! isset( $taxes[ $key ] ) ) {
					$taxes[ $key ] = (float) $tax_amount;
				} else {
					$taxes[ $key ] += (float) $tax_amount;
				}

				$pre_compound_total = array_sum( $taxes );
			}
		}

		/**
		 * Round all taxes to precision (4DP) before passing them back. Note, this is not the same rounding
		 * as in the cart calculation class which, depending on settings, will round to 2DP when calculating
		 * final totals. Also unlike that class, this rounds .5 up for all cases.
		 */
		return array_map( [ __CLASS__, 'round' ], $taxes );
	}

	/**
	 * Searches for all matching country/state/postcode tax rates.
	 *
	 * @param array|string $args Args that determine the rate to find.
	 *
	 * @return array
	 */
	public static function find_rates( $args = [] ): array {
		$args = wp_parse_args( $args, [
			'country'   => '',
			'state'     => '',
			'city'      => '',
			'postcode'  => '',
			'tax_class' => '',
		] );

		$country   = $args['country'];
		$state     = $args['state'];
		$city      = $args['city'];
		$postcode  = Formatting::normalize_postcode( sanitize_text_field( $args['postcode'] ) );
		$tax_class = $args['tax_class'];

		if ( ! $country ) {
			return [];
		}

		$cache_key         = Caching::get_cache_prefix( 'taxes' ) . 'storeengine_tax_rates_' . md5( sprintf( '%s+%s+%s+%s+%s', $country, $state, $city, $postcode, $tax_class ) );
		$matched_tax_rates = wp_cache_get( $cache_key, 'taxes' );

		if ( false === $matched_tax_rates ) {
			$matched_tax_rates = self::get_matched_tax_rates( $country, $state, $postcode, $city, $tax_class );
			wp_cache_set( $cache_key, $matched_tax_rates, 'taxes' );
		}

		return apply_filters( 'storeengine/find_rates', $matched_tax_rates, $args );
	}

	/**
	 * Searches for all matching country/state/postcode tax rates.
	 *
	 * @param array|string $args Args that determine the rate to find.
	 *
	 * @return array
	 */
	public static function find_shipping_rates( $args = [] ): array {
		$rates          = self::find_rates( $args );
		$shipping_rates = [];

		if ( $rates ) {
			foreach ( $rates as $key => $rate ) {
				if ( 'yes' === $rate['shipping'] ) {
					$shipping_rates[ $key ] = $rate;
				}
			}
		}

		return $shipping_rates;
	}

	/**
	 * Does the sort comparison. Compares (in this order):
	 * - Priority
	 * - Country
	 * - State
	 * - Number of postcodes
	 * - Number of cities
	 * - ID
	 *
	 * @param object $rate1 First rate to compare.
	 * @param object $rate2 Second rate to compare.
	 *
	 * @return int
	 */
	private static function sort_rates_callback( object $rate1, object $rate2 ): int {
		if ( $rate1->tax_rate_priority !== $rate2->tax_rate_priority ) {
			return $rate1->tax_rate_priority < $rate2->tax_rate_priority ? - 1 : 1; // ASC.
		}

		if ( $rate1->tax_rate_country !== $rate2->tax_rate_country ) {
			if ( '' === $rate1->tax_rate_country ) {
				return 1;
			}
			if ( '' === $rate2->tax_rate_country ) {
				return - 1;
			}

			return strcmp( $rate1->tax_rate_country, $rate2->tax_rate_country ) > 0 ? 1 : - 1;
		}

		if ( $rate1->tax_rate_state !== $rate2->tax_rate_state ) {
			if ( '' === $rate1->tax_rate_state ) {
				return 1;
			}
			if ( '' === $rate2->tax_rate_state ) {
				return - 1;
			}

			return strcmp( $rate1->tax_rate_state, $rate2->tax_rate_state ) > 0 ? 1 : - 1;
		}

		if ( isset( $rate1->postcode_count, $rate2->postcode_count ) && $rate1->postcode_count !== $rate2->postcode_count ) {
			return $rate1->postcode_count < $rate2->postcode_count ? 1 : - 1;
		}

		if ( isset( $rate1->city_count, $rate2->city_count ) && $rate1->city_count !== $rate2->city_count ) {
			return $rate1->city_count < $rate2->city_count ? 1 : - 1;
		}

		return $rate1->tax_rate_id < $rate2->tax_rate_id ? - 1 : 1;
	}

	/**
	 * Logical sort order for tax rates based on the following in order of priority.
	 *
	 * @param array $rates Rates to be sorted.
	 *
	 * @return array
	 */
	private static function sort_rates( array $rates ): array {
		uasort( $rates, __CLASS__ . '::sort_rates_callback' );

		$i = 0;
		foreach ( $rates as $rate ) {
			$rate->tax_rate_order = $i ++;
		}

		return $rates;
	}

	/**
	 * Loop through a set of tax rates and get the matching rates (1 per priority).
	 *
	 * @param string $country Country code to match against.
	 * @param string $state State code to match against.
	 * @param string $postcode Postcode to match against.
	 * @param string $city City to match against.
	 * @param string $tax_class Tax class to match against.
	 *
	 * @return array
	 */
	private static function get_matched_tax_rates( string $country, string $state, string $postcode, string $city, string $tax_class ): array {
		global $wpdb;

		// Query criteria - these will be ANDed.
		$criteria   = [];
		$criteria[] = $wpdb->prepare( "tax_rate_country IN ( %s, '' )", strtoupper( $country ) );
		$criteria[] = $wpdb->prepare( "tax_rate_state IN ( %s, '' )", strtoupper( $state ) );
		$criteria[] = $wpdb->prepare( 'tax_rate_class = %s', sanitize_title( $tax_class ) );

		// Pre-query postcode ranges for PHP based matching.
		$postcode_search = Helper::get_wildcard_postcodes( $postcode, $country );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$postcode_ranges = $wpdb->get_results( "SELECT tax_rate_id, location_code FROM {$wpdb->prefix}storeengine_tax_rate_locations WHERE location_type = 'postcode' AND location_code LIKE '%...%';" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $postcode_ranges ) {
			$matches = Helper::postcode_location_matcher( $postcode, $postcode_ranges, 'tax_rate_id', 'location_code', $country );
			if ( ! empty( $matches ) ) {
				foreach ( $matches as $matched_postcodes ) {
					$postcode_search = array_merge( $postcode_search, $matched_postcodes );
				}
			}
		}

		$postcode_search = array_unique( $postcode_search );

		/**
		 * Location matching criteria - ORed
		 * Needs to match:
		 * - rates with no postcodes and cities
		 * - rates with a matching postcode and city
		 * - rates with matching postcode, no city
		 * - rates with matching city, no postcode
		 */
		$locations_criteria   = [];
		$locations_criteria[] = 'locations.location_type IS NULL';
		$locations_criteria[] = "
			locations.location_type = 'postcode' AND locations.location_code IN ('" . implode( "','", array_map( 'esc_sql', $postcode_search ) ) . "')
			AND (
				( locations2.location_type = 'city' AND locations2.location_code = '" . esc_sql( strtoupper( $city ) ) . "' )
				OR NOT EXISTS (
					SELECT sub.tax_rate_id FROM {$wpdb->prefix}storeengine_tax_rate_locations as sub
					WHERE sub.location_type = 'city'
					AND sub.tax_rate_id = tax_rates.tax_rate_id
				)
			)
		";
		$locations_criteria[] = "
			locations.location_type = 'city' AND locations.location_code = '" . esc_sql( strtoupper( $city ) ) . "'
			AND NOT EXISTS (
				SELECT sub.tax_rate_id FROM {$wpdb->prefix}storeengine_tax_rate_locations as sub
				WHERE sub.location_type = 'postcode'
				AND sub.tax_rate_id = tax_rates.tax_rate_id
			)
		";

		$criteria[] = '( ( ' . implode( ' ) OR ( ', $locations_criteria ) . ' ) )';

		$criteria_string = implode( ' AND ', $criteria );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		/** @noinspection SqlAggregates */
		/** @noinspection SqlConstantExpression */
		$found_rates = $wpdb->get_results(
			"
			SELECT tax_rates.*, COUNT( locations.location_id ) as postcode_count, COUNT( locations2.location_id ) as city_count
			FROM {$wpdb->prefix}storeengine_tax_rates as tax_rates
			LEFT OUTER JOIN {$wpdb->prefix}storeengine_tax_rate_locations as locations ON tax_rates.tax_rate_id = locations.tax_rate_id
			LEFT OUTER JOIN {$wpdb->prefix}storeengine_tax_rate_locations as locations2 ON tax_rates.tax_rate_id = locations2.tax_rate_id
			WHERE 1=1 AND {$criteria_string}
			GROUP BY tax_rates.tax_rate_id
			ORDER BY tax_rates.tax_rate_priority
			"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$found_rates       = self::sort_rates( $found_rates );
		$matched_tax_rates = [];
		$found_priority    = [];

		foreach ( $found_rates as $found_rate ) {
			if ( in_array( $found_rate->tax_rate_priority, $found_priority, true ) ) {
				continue;
			}

			$matched_tax_rates[ $found_rate->tax_rate_id ] = [
				'rate'     => (float) $found_rate->tax_rate,
				'label'    => $found_rate->tax_rate_name,
				'shipping' => $found_rate->tax_rate_shipping ? 'yes' : 'no',
				'compound' => $found_rate->tax_rate_compound ? 'yes' : 'no',
			];

			$found_priority[] = $found_rate->tax_rate_priority;
		}

		return apply_filters( 'storeengine/matched_tax_rates', $matched_tax_rates, $country, $state, $postcode, $city, $tax_class );
	}

	/**
	 * Get the customer tax location based on their status and the current page.
	 *
	 * Used by get_rates(), get_shipping_rates().
	 *
	 * @param string $tax_class string Optional, passed to the filter for advanced tax setups.
	 * @param ?Customer $customer Override the customer object to get their location.
	 *
	 * @return array
	 */
	public static function get_tax_location( string $tax_class = '', ?Customer $customer = null ): array {
		$location = [];

		if ( is_null( $customer ) && storeengine_start()->customer ) {
			$customer = storeengine_start()->customer;
		}

		if ( ! empty( $customer ) ) {
			$location = $customer->get_taxable_address();
		} elseif ( TaxUtil::prices_include_tax() || 'base' === TaxUtil::default_customer_address() || 'base' === TaxUtil::tax_based_on() ) {
			$location = [
				Countries::init()->get_base_country(),
				Countries::init()->get_base_state(),
				Countries::init()->get_base_postcode(),
				Countries::init()->get_base_city(),
			];
		}

		return apply_filters( 'storeengine/get_tax_location', $location, $tax_class, $customer );
	}

	/**
	 * Get's an array of matching rates for a tax class.
	 *
	 * @param string $tax_class Tax class to get rates for.
	 * @param ?Customer $customer Override the customer object to get their location.
	 *
	 * @return  array
	 */
	public static function get_rates( string $tax_class = '', ?Customer $customer = null ): ?array {
		$tax_class = sanitize_title( $tax_class );
		$location  = self::get_tax_location( $tax_class, $customer );

		return self::get_rates_from_location( $tax_class, $location, $customer );
	}

	/**
	 * Get's an array of matching rates from location and tax class. $customer parameter is used to preserve backward compatibility for filter.
	 *
	 * @param string $tax_class Tax class to get rates for.
	 * @param array $location Location to compute rates for. Should be in form: array( country, state, postcode, city).
	 * @param ?Customer $customer Only used to maintain backward compatibility for filter `woocommerce-matched_rates`.
	 *
	 * @return mixed|void Tax rates.
	 */
	public static function get_rates_from_location( string $tax_class, array $location, ?Customer $customer = null ) {
		$tax_class         = sanitize_title( $tax_class );
		$matched_tax_rates = [];

		if ( count( $location ) === 4 ) {
			list( $country, $state, $postcode, $city ) = $location;

			$matched_tax_rates = self::find_rates( [
				'country'   => $country,
				'state'     => $state,
				'postcode'  => $postcode,
				'city'      => $city,
				'tax_class' => $tax_class,
			] );
		}

		return apply_filters( 'storeengine/matched_rates', $matched_tax_rates, $tax_class, $customer );
	}

	/**
	 * Get's an array of matching rates for the shop's base country.
	 *
	 * @param string $tax_class Tax Class.
	 *
	 * @return array
	 */
	public static function get_base_tax_rates( string $tax_class = '' ): array {
		return apply_filters(
			'storeengine/base_tax_rates',
			self::find_rates(
				[
					'country'   => Countries::init()->get_base_country(),
					'state'     => Countries::init()->get_base_state(),
					'postcode'  => Countries::init()->get_base_postcode(),
					'city'      => Countries::init()->get_base_city(),
					'tax_class' => $tax_class,
				]
			),
			$tax_class
		);
	}

	/**
	 * Gets an array of matching shipping tax rates for a given class.
	 *
	 * @param ?string $tax_class Tax class to get rates for.
	 * @param ?Customer $customer Override the customer object to get their location.
	 *
	 * @return array
	 */
	public static function get_shipping_tax_rates( ?string $tax_class = null, ?Customer $customer = null ): array {
		// See if we have an explicitly set shipping tax class.
		$shipping_tax_class = Helper::get_settings( 'shipping_tax_class' );

		if ( 'inherit' !== $shipping_tax_class ) {
			$tax_class = $shipping_tax_class;
		}

		$location          = self::get_tax_location( $tax_class, $customer );
		$matched_tax_rates = [];

		if ( 4 === count( $location ) ) {
			list( $country, $state, $postcode, $city ) = $location;

			if ( ! is_null( $tax_class ) ) {
				// This will be per item shipping.
				$matched_tax_rates = self::find_shipping_rates(
					[
						'country'   => $country,
						'state'     => $state,
						'postcode'  => $postcode,
						'city'      => $city,
						'tax_class' => $tax_class,
					]
				);
			} elseif ( Helper::cart()->has_items() ) {

				// This will be per order shipping - loop through the order and find the highest tax class rate.

				$cart_tax_classes = Helper::cart()->get_cart_item_tax_classes_for_shipping();

				// No tax classes = no taxable items.
				if ( empty( $cart_tax_classes ) ) {
					return [];
				}

				// If multiple classes are found, use the first one found unless a standard rate item is found. This will be the first listed in the 'additional tax class' section.
				if ( count( $cart_tax_classes ) > 1 && ! in_array( '', $cart_tax_classes, true ) ) {
					$tax_classes = self::get_tax_class_slugs();

					foreach ( $tax_classes as $tax_class ) {
						if ( in_array( $tax_class, $cart_tax_classes, true ) ) {
							$matched_tax_rates = self::find_shipping_rates( [
								'country'   => $country,
								'state'     => $state,
								'postcode'  => $postcode,
								'city'      => $city,
								'tax_class' => $tax_class,
							] );
							break;
						}
					}
				} elseif ( 1 === count( $cart_tax_classes ) ) {
					// If a single tax class is found, use it.
					$matched_tax_rates = self::find_shipping_rates( [
						'country'   => $country,
						'state'     => $state,
						'postcode'  => $postcode,
						'city'      => $city,
						'tax_class' => $cart_tax_classes[0],
					] );
				}
			}

			// Get standard rate if no taxes were found.
			if ( ! count( $matched_tax_rates ) ) {
				$matched_tax_rates = self::find_shipping_rates( [
					'country'  => $country,
					'state'    => $state,
					'postcode' => $postcode,
					'city'     => $city,
				] );
			}
		}

		return $matched_tax_rates;
	}

	/**
	 * Return true/false depending on if a rate is a compound rate.
	 *
	 * @param mixed $key_or_rate Tax rate ID, or the db row itself in object format.
	 *
	 * @return  bool
	 */
	public static function is_compound( $key_or_rate ): bool {
		global $wpdb;

		if ( is_object( $key_or_rate ) ) {
			$key      = $key_or_rate->tax_rate_id;
			$compound = $key_or_rate->tax_rate_compound;
		} else {
			$key = $key_or_rate;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$compound = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT tax_rate_compound FROM {$wpdb->prefix}storeengine_tax_rates WHERE tax_rate_id = %s", $key ) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		return (bool) apply_filters( 'storeengine/rate_compound', $compound, $key );
	}

	/**
	 * Return a given rates label.
	 *
	 * @param mixed $key_or_rate Tax rate ID, or the db row itself in object format.
	 *
	 * @return  string
	 */
	public static function get_rate_label( $key_or_rate ): string {
		global $wpdb;

		if ( is_object( $key_or_rate ) ) {
			$key       = $key_or_rate->tax_rate_id;
			$rate_name = $key_or_rate->tax_rate_name;
		} else {
			$key = $key_or_rate;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$rate_name = $wpdb->get_var( $wpdb->prepare( "SELECT tax_rate_name FROM {$wpdb->prefix}storeengine_tax_rates WHERE tax_rate_id = %s", $key ) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		if ( ! $rate_name ) {
			$rate_name = Countries::init()->tax_or_vat();
		}

		return apply_filters( 'storeengine/rate_label', $rate_name, $key );
	}

	/**
	 * Return a given rates percent.
	 *
	 * @param mixed $key_or_rate Tax rate ID, or the db row itself in object format.
	 *
	 * @return  string
	 */
	public static function get_rate_percent( $key_or_rate ): string {
		$rate_percent_value = self::get_rate_percent_value( $key_or_rate );
		$tax_rate_id        = is_object( $key_or_rate ) ? $key_or_rate->tax_rate_id : $key_or_rate;

		return apply_filters( 'storeengine/rate_percent', $rate_percent_value . '%', $tax_rate_id );
	}

	/**
	 * Return a given rates percent.
	 *
	 * @param mixed $key_or_rate Tax rate ID, or the db row itself in object format.
	 *
	 * @return  float
	 */
	public static function get_rate_percent_value( $key_or_rate ): float {
		global $wpdb;

		if ( is_object( $key_or_rate ) ) {
			$tax_rate = $key_or_rate->tax_rate;
		} else {
			$key = $key_or_rate;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$tax_rate = $wpdb->get_var( $wpdb->prepare( "SELECT tax_rate FROM {$wpdb->prefix}storeengine_tax_rates WHERE tax_rate_id = %s", $key ) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		return floatval( $tax_rate );
	}


	/**
	 * Get a rates code. Code is made up of COUNTRY-STATE-NAME-Priority. E.g GB-VAT-1, US-AL-TAX-1.
	 *
	 * @param mixed $key_or_rate Tax rate ID, or the db row itself in object format.
	 *
	 * @return string
	 */
	public static function get_rate_code( $key_or_rate ): string {
		global $wpdb;

		if ( is_object( $key_or_rate ) ) {
			$key  = $key_or_rate->tax_rate_id;
			$rate = $key_or_rate;
		} else {
			$key = $key_or_rate;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$rate = $wpdb->get_row( $wpdb->prepare( "SELECT tax_rate_country, tax_rate_state, tax_rate_name, tax_rate_priority FROM {$wpdb->prefix}storeengine_tax_rates WHERE tax_rate_id = %s", $key ) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		$code_string = '';

		if ( null !== $rate ) {
			$code        = [];
			$code[]      = $rate->tax_rate_country;
			$code[]      = $rate->tax_rate_state;
			$code[]      = $rate->tax_rate_name ? $rate->tax_rate_name : 'TAX';
			$code[]      = absint( $rate->tax_rate_priority );
			$code_string = strtoupper( implode( '-', array_filter( $code ) ) );
		}

		return apply_filters( 'storeengine/rate_code', $code_string, $key );
	}

	/**
	 * Sums a set of taxes to form a single total. Values are pre-rounded to precision from 3.6.0.
	 *
	 * @param float[]|int[] $taxes Array of taxes.
	 *
	 * @return float
	 */
	public static function get_tax_total( array $taxes ): float {
		return array_sum( $taxes );
	}

	/**
	 * Gets all tax rate classes from the database.
	 *
	 * @return array Array of tax class objects consisting of tax_rate_class_id, name, and slug.
	 */
	public static function get_tax_rate_classes(): array {
		return [];

		// @TODO implement tax rate class table.
		// phpcs:disable Squiz.PHP.CommentedOutCode.Found
		// Not implemented.
		/*global $wpdb;

		$cache_key        = 'tax-rate-classes';
		$tax_rate_classes = wp_cache_get( $cache_key, 'taxes' );

		if ( ! is_array( $tax_rate_classes ) ) {
			$tax_rate_classes = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}storeengine_tax_rate_classes ORDER BY name;" );
			wp_cache_set( $cache_key, $tax_rate_classes, 'taxes' );
		}

		return $tax_rate_classes;*/
		// phpcs:enable Squiz.PHP.CommentedOutCode.Found
	}

	/**
	 * Get store tax class names.
	 *
	 * @return array Array of class names ("Reduced rate", "Zero rate", etc).
	 */
	public static function get_tax_classes(): array {
		return wp_list_pluck( self::get_tax_rate_classes(), 'name' );
	}

	/**
	 * Get store tax classes as slugs.
	 *
	 * @return array Array of class slugs ("reduced-rate", "zero-rate", etc).
	 */
	public static function get_tax_class_slugs(): array {
		return wp_list_pluck( self::get_tax_rate_classes(), 'slug' );
	}

	/**
	 * Create a new tax class.
	 *
	 * @param string $name Name of the tax class to add.
	 * @param string $slug (optional) Slug of the tax class to add. Defaults to sanitized name.
	 *
	 * @return WP_Error|array Returns name and slug (array) if the tax class is created, or WP_Error if something went wrong.
	 */
	public static function create_tax_class( string $name, string $slug = '' ) {
		global $wpdb;

		if ( empty( $name ) ) {
			return new WP_Error( 'tax_class_invalid_name', __( 'Tax class requires a valid name', 'storeengine' ) );
		}

		$existing       = self::get_tax_classes();
		$existing_slugs = self::get_tax_class_slugs();
		$name           = sanitize_text_field( $name );

		if ( in_array( $name, $existing, true ) ) {
			return new WP_Error( 'tax_class_exists', __( 'Tax class already exists', 'storeengine' ) );
		}

		if ( ! $slug ) {
			$slug = sanitize_title( $name );
		}

		// Stop if there's no slug.
		if ( ! $slug ) {
			return new WP_Error( 'tax_class_slug_invalid', __( 'Tax class slug is invalid', 'storeengine' ) );
		}

		if ( in_array( $slug, $existing_slugs, true ) ) {
			return new WP_Error( 'tax_class_slug_exists', __( 'Tax class slug already exists', 'storeengine' ) );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$insert = $wpdb->insert(
			$wpdb->prefix . 'storeengine_tax_rate_classes',
			[
				'name' => $name,
				'slug' => $slug,
			],
			[ '%s', '%s' ]
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( ! $insert && $wpdb->last_error ) {
			return new WP_Error( 'tax_class_insert_error', $wpdb->last_error );
		}

		wp_cache_delete( 'tax-rate-classes', 'taxes' );

		return [
			'name' => $name,
			'slug' => $slug,
		];
	}

	/**
	 * Get an existing tax class.
	 *
	 * @param string $field Field to get by. Valid values are id, name, or slug.
	 * @param string|int $item Item to get.
	 *
	 * @return array|bool|WP_Error Returns the tax class as an array. False if not found.
	 */
	public static function get_tax_class_by( string $field, $item ) {
		if ( ! in_array( $field, [ 'id', 'name', 'slug' ], true ) ) {
			return new WP_Error( 'invalid_field', __( 'Invalid field', 'storeengine' ) );
		}

		if ( 'id' === $field ) {
			$field = 'tax_rate_class_id';
		}

		$matches = wp_list_filter( self::get_tax_rate_classes(), [ $field => $item ] );

		if ( ! $matches ) {
			return false;
		}

		$tax_class = current( $matches );

		return [
			'name' => $tax_class->name,
			'slug' => $tax_class->slug,
		];
	}

	/**
	 * Delete an existing tax class.
	 *
	 * @param string $field Field to delete by. Valid values are id, name, or slug.
	 * @param string|int $item Item to delete.
	 *
	 * @return WP_Error|bool Returns true if deleted successfully, false if nothing was deleted, or WP_Error if there is an invalid request.
	 */
	public static function delete_tax_class_by( string $field, $item ) {
		global $wpdb;

		if ( ! in_array( $field, [ 'id', 'name', 'slug' ], true ) ) {
			return new WP_Error( 'invalid_field', __( 'Invalid field', 'storeengine' ) );
		}

		$tax_class = self::get_tax_class_by( $field, $item );

		if ( ! $tax_class ) {
			return new WP_Error( 'invalid_tax_class', __( 'Invalid tax class', 'storeengine' ) );
		}

		$format = '%s';
		if ( 'id' === $field ) {
			$field  = 'tax_rate_class_id';
			$format = '%d';
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$delete = $wpdb->delete( $wpdb->prefix . 'storeengine_tax_rate_classes', [ $field => $item ], [ $format ] );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( $delete ) {
			// Delete associated tax rates.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}storeengine_tax_rates WHERE tax_rate_class = %s;", $tax_class['slug'] ) );
			$wpdb->query( "DELETE locations FROM {$wpdb->prefix}storeengine_tax_rate_locations locations LEFT JOIN {$wpdb->prefix}storeengine_tax_rates rates ON rates.tax_rate_id = locations.tax_rate_id WHERE rates.tax_rate_id IS NULL;" );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		wp_cache_delete( 'tax-rate-classes', 'taxes' );
		Caching::invalidate_cache_group( 'taxes' );

		return (bool) $delete;
	}

	/**
	 * Format the city.
	 *
	 * @param string $city Value to format.
	 *
	 * @return string
	 */
	private static function format_tax_rate_city( string $city ): string {
		return strtoupper( trim( $city ) );
	}

	/**
	 * Format the state.
	 *
	 * @param string $state Value to format.
	 *
	 * @return string
	 */
	private static function format_tax_rate_state( string $state ): string {
		$state = strtoupper( $state );

		return ( '*' === $state ) ? '' : $state;
	}

	/**
	 * Format the country.
	 *
	 * @param string $country Value to format.
	 *
	 * @return string
	 */
	private static function format_tax_rate_country( string $country ): string {
		$country = strtoupper( $country );

		return ( '*' === $country ) ? '' : $country;
	}

	/**
	 * Format the tax rate name.
	 *
	 * @param string $name Value to format.
	 *
	 * @return string
	 */
	private static function format_tax_rate_name( string $name ): string {
		return $name ? $name : __( 'Tax', 'storeengine' );
	}

	/**
	 * Format the rate.
	 *
	 * @param float|string|int $rate Value to format.
	 *
	 * @return string
	 */
	private static function format_tax_rate( $rate ): string {
		return number_format( (float) $rate, 4, '.', '' );
	}

	/**
	 * Format the priority.
	 *
	 * @param string|int $priority Value to format.
	 *
	 * @return int
	 */
	private static function format_tax_rate_priority( string $priority ): int {
		return absint( $priority );
	}

	/**
	 * Format the class.
	 *
	 * @param string $class Value to format.
	 *
	 * @return string
	 */
	public static function format_tax_rate_class( string $class ): string {
		$class   = sanitize_title( $class );
		$classes = self::get_tax_class_slugs();
		if ( ! in_array( $class, $classes, true ) ) {
			$class = '';
		}

		return ( 'standard' === $class ) ? '' : $class;
	}

	/**
	 * Prepare and format tax rate for DB insertion.
	 *
	 * @param array $tax_rate Tax rate to format.
	 *
	 * @return array
	 */
	private static function prepare_tax_rate( array $tax_rate ): array {
		foreach ( $tax_rate as $key => $value ) {
			if ( method_exists( __CLASS__, 'format_' . $key ) ) {
				if ( 'tax_rate_state' === $key ) {
					$tax_rate[ $key ] = call_user_func( [ __CLASS__, 'format_' . $key ], sanitize_key( $value ) );
				} else {
					$tax_rate[ $key ] = call_user_func( [ __CLASS__, 'format_' . $key ], $value );
				}
			}
		}

		return $tax_rate;
	}

	/**
	 * Insert a new tax rate.
	 *
	 * Internal use only.
	 *
	 * @param array $tax_rate Tax rate to insert.
	 *
	 * @return int tax rate id
	 */
	public static function _insert_tax_rate( array $tax_rate ): int {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $wpdb->prefix . 'storeengine_tax_rates', self::prepare_tax_rate( $tax_rate ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery

		$tax_rate_id = (int) $wpdb->insert_id;

		Caching::invalidate_cache_group( 'taxes' );

		do_action( 'storeengine/tax_rate_added', $tax_rate_id, $tax_rate );

		return $tax_rate_id;
	}

	/**
	 * Get tax rate.
	 *
	 * Internal use only.
	 *
	 * @param int|string $tax_rate_id Tax rate ID.
	 * @param string $output_type Type of output.
	 *
	 * @return array|object
	 */
	public static function _get_tax_rate( $tax_rate_id, string $output_type = ARRAY_A ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->get_row(
			$wpdb->prepare(
				"
					SELECT *
					FROM {$wpdb->prefix}storeengine_tax_rates
					WHERE tax_rate_id = %d
				",
				absint( $tax_rate_id )
			),
			$output_type
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * Update a tax rate.
	 *
	 * Internal use only.
	 *
	 * @param int|string $tax_rate_id Tax rate to update.
	 * @param array $tax_rate Tax rate values.
	 */
	public static function _update_tax_rate( $tax_rate_id, array $tax_rate ) {
		global $wpdb;

		$tax_rate_id = absint( $tax_rate_id );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $wpdb->prefix . 'storeengine_tax_rates', self::prepare_tax_rate( $tax_rate ), [ 'tax_rate_id' => $tax_rate_id ] );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		Caching::invalidate_cache_group( 'taxes' );

		do_action( 'storeengine/tax_rate_updated', $tax_rate_id, $tax_rate );
	}

	/**
	 * Delete a tax rate from the database.
	 *
	 * Internal use only.
	 *
	 * @param int|string $tax_rate_id Tax rate to delete.
	 */
	public static function _delete_tax_rate( $tax_rate_id ) {
		global $wpdb;

		$tax_rate_id = absint( $tax_rate_id );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}storeengine_tax_rate_locations WHERE tax_rate_id = %d;", $tax_rate_id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}storeengine_tax_rates WHERE tax_rate_id = %d;", $tax_rate_id ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		Caching::invalidate_cache_group( 'taxes' );

		do_action( 'storeengine/tax_rate_deleted', $tax_rate_id );
	}

	/**
	 * Update postcodes for a tax rate in the DB.
	 *
	 * Internal use only.
	 *
	 * @param int|string $tax_rate_id Tax rate to update.
	 * @param string|string[] $postcodes String of postcodes separated by ; characters.
	 */
	public static function _update_tax_rate_postcodes( $tax_rate_id, $postcodes ) {
		if ( ! is_array( $postcodes ) ) {
			$postcodes = explode( ';', $postcodes );
		}
		// No normalization - postcodes are matched against both normal and formatted versions to support wildcards.
		foreach ( $postcodes as $key => $postcode ) {
			$postcodes[ $key ] = strtoupper( trim( str_replace( chr( 226 ) . chr( 128 ) . chr( 166 ), '...', $postcode ) ) );
		}
		self::update_tax_rate_locations( $tax_rate_id, array_diff( array_filter( $postcodes ), [ '*' ] ), 'postcode' );
	}

	/**
	 * Update cities for a tax rate in the DB.
	 *
	 * Internal use only.
	 *
	 * @param int|string $tax_rate_id Tax rate to update.
	 * @param string|string[] $cities Cities to set.
	 */
	public static function _update_tax_rate_cities( $tax_rate_id, $cities ) {
		if ( ! is_array( $cities ) ) {
			$cities = explode( ';', $cities );
		}
		$cities = array_filter( array_diff( array_map( [ __CLASS__, 'format_tax_rate_city' ], $cities ), [ '*' ] ) );

		self::update_tax_rate_locations( $tax_rate_id, $cities, 'city' );
	}

	/**
	 * Updates locations (postcode and city).
	 *
	 * Internal use only.
	 *
	 * @param int|string $tax_rate_id Tax rate ID to update.
	 * @param array $values Values to set.
	 * @param string $type Location type.
	 */
	private static function update_tax_rate_locations( $tax_rate_id, array $values, string $type ) {
		global $wpdb;

		$tax_rate_id = absint( $tax_rate_id );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- query prepared.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}storeengine_tax_rate_locations WHERE tax_rate_id = %d AND location_type = %s;",
				$tax_rate_id,
				$type
			)
		);

		if ( count( $values ) > 0 ) {
			$sql = "( '" . implode( "', $tax_rate_id, '" . esc_sql( $type ) . "' ),( '", array_map( 'esc_sql', $values ) ) . "', $tax_rate_id, '" . esc_sql( $type ) . "' )";

			$wpdb->query( "INSERT INTO {$wpdb->prefix}storeengine_tax_rate_locations ( location_code, tax_rate_id, location_type ) VALUES $sql;" );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- query prepared.

		Caching::invalidate_cache_group( 'taxes' );
	}

	/**
	 * Used by admin settings page.
	 *
	 * @param string $tax_class Tax class slug.
	 *
	 * @return array|null|object
	 */
	public static function get_rates_for_tax_class( $tax_class ) {
		global $wpdb;

		$tax_class = self::format_tax_rate_class( $tax_class );

		// Get all the rates and locations. Snagging all at once should significantly cut down on the number of queries.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rates     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}storeengine_tax_rates` WHERE `tax_rate_class` = %s;", $tax_class ) );
		$locations = $wpdb->get_results( "SELECT * FROM `{$wpdb->prefix}storeengine_tax_rate_locations`" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! empty( $rates ) ) {
			// Set the rates keys equal to their ids.
			$rates = array_combine( wp_list_pluck( $rates, 'tax_rate_id' ), $rates );
		}

		// Drop the locations into the rates array.
		foreach ( $locations as $location ) {
			// Don't set them for nonexistent rates.
			if ( ! isset( $rates[ $location->tax_rate_id ] ) ) {
				continue;
			}
			// If the rate exists, initialize the array before appending to it.
			if ( ! isset( $rates[ $location->tax_rate_id ]->{$location->location_type} ) ) {
				$rates[ $location->tax_rate_id ]->{$location->location_type} = [];
			}
			$rates[ $location->tax_rate_id ]->{$location->location_type}[] = $location->location_code;
		}

		foreach ( $rates as $rate_id => $rate ) {
			$rates[ $rate_id ]->postcode_count = isset( $rates[ $rate_id ]->postcode ) ? count( $rates[ $rate_id ]->postcode ) : 0;
			$rates[ $rate_id ]->city_count     = isset( $rates[ $rate_id ]->city ) ? count( $rates[ $rate_id ]->city ) : 0;
		}

		return self::sort_rates( $rates );
	}
}

// End of file tax.php
