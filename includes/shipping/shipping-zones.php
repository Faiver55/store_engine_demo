<?php
/**
 * Handles storage and retrieval of shipping zones.
 *
 * @package StoreEngine
 */

namespace StoreEngine\Shipping;

use StoreEngine\Classes\AbstractCollection;
use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Shipping\Methods\ShippingMethod;
use StoreEngine\Utils\Caching;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * @see \WC_Shipping_Zones
 */
final class ShippingZones {

	/**
	 * Get shipping zones from the database.
	 *
	 * @param string $context Getting shipping methods for what context. Valid values, admin, json.
	 *
	 * @return array Array of arrays.
	 */
	public static function get_zones( string $context = 'admin' ): array {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$raw_zones = $wpdb->get_results( "SELECT id, zone_name, zone_order FROM {$wpdb->prefix}storeengine_shipping_zones order by zone_order ASC, id ASC;" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$zones = [];

		foreach ( $raw_zones as $raw_zone ) {
			try {
				$zone                                = new ShippingZone( $raw_zone );
				$zones[ $zone->get_id() ]            = $zone->get_data();
				$zones[ $zone->get_id() ]['zone_id'] = $zone->get_id();
				$zones[ $zone->get_id() ]['formatted_zone_location'] = $zone->get_formatted_location( 6 );
				$zones[ $zone->get_id() ]['shipping_methods']        = $zone->get_shipping_methods( false, $context );
			} catch ( StoreEngineException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// @TODO implement error logger.
				// no op
			}
		}

		return $zones;
	}

	/**
	 * Get shipping zone using it's ID
	 *
	 * @param int $zone_id Zone ID.
	 *
	 * @return ShippingZone|bool
	 */
	public static function get_zone( int $zone_id ) {
		return self::get_zone_by( 'zone_id', $zone_id );
	}

	/**
	 * Get shipping zone by an ID.
	 *
	 * @param string $by Get by 'zone_id' or 'instance_id'.
	 * @param int $id ID.
	 *
	 * @return ShippingZone|bool
	 */
	public static function get_zone_by( string $by = 'zone_id', int $id = 0 ) {
		$zone_id = false;

		switch ( $by ) {
			case 'zone_id':
				$zone_id = $id;
				break;
			case 'instance_id':
				global $wpdb;
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$zone_id = $wpdb->get_var( $wpdb->prepare( "SELECT zone_id FROM {$wpdb->prefix}wp_storeengine_shipping_zone_methods as methods WHERE methods.instance_id = %d LIMIT 1;", $id ) );
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				break;
		}

		if ( false !== $zone_id ) {
			try {
				return new ShippingZone( $zone_id );
			} catch ( StoreEngineException $e ) {
				return false;
			}
		}

		return false;
	}

	/**
	 * Get shipping zone using it's ID.
	 *
	 * @param int $instance_id Instance ID.
	 *
	 * @return bool|ShippingMethod
	 */
	public static function get_shipping_method( $instance_id ) {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$raw_shipping_method = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_shipping_zone_methods WHERE id = %d LIMIT 1;", $instance_id ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$shipping        = Shipping::get_instance();
		$allowed_classes = $shipping->get_shipping_method_class_names();

		if ( ! empty( $raw_shipping_method ) && in_array( $raw_shipping_method->method_id, array_keys( $allowed_classes ), true ) ) {
			$class_name = $allowed_classes[ $raw_shipping_method->method_id ];
			if ( is_object( $class_name ) ) {
				$class_name = get_class( $class_name );
			}

			return new $class_name( $raw_shipping_method->instance_id );
		}

		return false;
	}

	/**
	 * Delete a zone using it's ID
	 *
	 * @param int $zone_id Zone ID.
	 *
	 * @throws StoreEngineException
	 */
	public static function delete_zone( int $zone_id ) {
		$zone = new ShippingZone( $zone_id );
		$zone->delete();
	}

	/**
	 * Find a matching zone for a given package.
	 *
	 * @param array $package Shipping package.
	 *
	 * @return ShippingZone
	 * @throws StoreEngineException
	 * @uses   Formatting::make_numeric_postcode()
	 */
	public static function get_zone_matching_package( array $package ): ShippingZone {
		$country          = strtoupper( Formatting::clean( $package['destination']['country'] ) );
		$state            = strtoupper( Formatting::clean( $package['destination']['state'] ) );
		$postcode         = Formatting::normalize_postcode( Formatting::clean( $package['destination']['postcode'] ) );
		$cache_key        = Caching::get_cache_prefix( 'shipping_zones' ) . 'ShippingZone_' . md5( sprintf( '%s+%s+%s', $country, $state, $postcode ) );
		$matching_zone_id = wp_cache_get( $cache_key, 'shipping_zones' );

		if ( false === $matching_zone_id ) {
			$matching_zone_id = self::get_zone_id_from_package( $package );
			wp_cache_set( $cache_key, $matching_zone_id, 'shipping_zones' );
		}

		return new ShippingZone( $matching_zone_id ?: 0 );
	}

	private static function get_zone_id_from_package( $package ): ?string {
		global $wpdb;

		$country   = strtoupper( Formatting::clean( $package['destination']['country'] ) );
		$state     = strtoupper( Formatting::clean( $package['destination']['state'] ) );
		$continent = strtoupper( Formatting::clean( Countries::init()->get_continent_code_for_country( $country ) ) );
		$postcode  = Formatting::normalize_postcode( Formatting::clean( $package['destination']['postcode'] ) );

		// Work out criteria for our zone search.
		$criteria   = [];
		$criteria[] = $wpdb->prepare( "( ( location_type = 'country' AND location_code = %s )", $country );
		$criteria[] = $wpdb->prepare( "OR ( location_type = 'state' AND location_code = %s )", $country . ':' . $state );
		$criteria[] = $wpdb->prepare( "OR ( location_type = 'continent' AND location_code = %s )", $continent );
		$criteria[] = 'OR ( location_type IS NULL ) )';

		// Postcode range and wildcard matching.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$postcode_locations = $wpdb->get_results( "SELECT zone_id, location_code FROM {$wpdb->prefix}storeengine_shipping_zone_locations WHERE location_type = 'postcode';" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $postcode_locations ) {
			$zone_ids_with_postcode_rules = array_map( 'absint', wp_list_pluck( $postcode_locations, 'zone_id' ) );
			$matches                      = Helper::postcode_location_matcher( $postcode, $postcode_locations, 'zone_id', 'location_code', $country );
			$do_not_match                 = array_unique( array_diff( $zone_ids_with_postcode_rules, array_keys( $matches ) ) );

			if ( ! empty( $do_not_match ) ) {
				$criteria[] = 'AND zones.id NOT IN (' . implode( ',', $do_not_match ) . ')';
			}
		}

		/**
		 * Get shipping zone criteria
		 *
		 * @param array $criteria Get zone criteria.
		 * @param array $package Package information.
		 * @param array $postcode_locations Postcode range and wildcard matching.
		 */
		$criteria = apply_filters( 'storeengine/shipping/get_zone_criteria', $criteria, $package, $postcode_locations );

		// Get matching zones.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var(
			"SELECT zones.id FROM {$wpdb->prefix}storeengine_shipping_zones as zones
			LEFT OUTER JOIN {$wpdb->prefix}storeengine_shipping_zone_locations as locations ON zones.id = locations.zone_id AND location_type != 'postcode'
			WHERE " . implode( ' ', $criteria ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			. ' ORDER BY zone_order ASC, zones.id ASC LIMIT 1'
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}

// End of file shipping-zones.php.
