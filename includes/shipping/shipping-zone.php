<?php
/**
 * Represents a single shipping zone.
 *
 * @package StoreEngine
 */

namespace StoreEngine\Shipping;

use StoreEngine\Classes\AbstractEntity;
use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Shipping\Methods\ShippingMethod;
use StoreEngine\Utils\Caching;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * @see \WC_Shipping_Zone
 */
final class ShippingZone extends AbstractEntity {

	protected string $table = 'storeengine_shipping_zones';

	/**F
	 * This is the name of this object type.
	 *
	 * @var string
	 */
	protected string $object_type = 'shipping_zone';

	/**
	 * Zone Data.
	 *
	 * @var array
	 */
	protected array $data = [
		'zone_name'      => '',
		'zone_order'     => 0,
		'zone_locations' => [],
	];

	public function create() {
		[ 'data' => $data, 'format' => $format ] = $this->prepare_for_db();

		if ( $this->wpdb->insert( $this->table, $data, $format ) ) {
			$this->set_id( $this->wpdb->insert_id );
			$this->save_meta_data();
			$this->save_item_data();
			$this->update_object_meta();
			$this->save_extra_data();
			$this->save_locations();
			$this->apply_changes();
			$this->clear_cache();
			Caching::invalidate_cache_group( 'shipping_zones' );
			Caching::get_transient_version( 'shipping', true );
		}

		if ( $this->wpdb->last_error ) {
			throw new StoreEngineException( $this->wpdb->last_error, 'db-error-insert-record' );
		}
	}

	public function update() {
		if ( ! $this->get_id() ) {
			return;
		}

		$this->save_meta_data();
		$this->save_item_data();
		$this->update_object_meta();
		$this->save_extra_data();

		[ 'data' => $data, 'format' => $format ] = $this->prepare_for_db( 'update' );

		if ( $this->wpdb->update( $this->table, $data, [ $this->primary_key => $this->get_id() ], $format, [ '%d' ] ) || count( $this->get_changes() ) > 0 ) {
			$this->save_locations();
			$this->apply_changes();
			$this->clear_cache();
			Caching::invalidate_cache_group( 'shipping_zones' );
			Caching::get_transient_version( 'shipping', true );
		}

		if ( $this->wpdb->last_error ) {
			throw new StoreEngineException( $this->wpdb->last_error, 'db-error-update-record' );
		}
	}

	public function read( bool $refresh = false ) {
		$this->set_defaults();

		// Zone 0 is used as a default if no other zones fit.
		if ( 0 === $this->get_id() ) {
			$this->read_zone_locations();
			$this->set_zone_name( __( 'Locations not covered by your other zones', 'storeengine' ) );
			$this->read_meta_data();
			$this->set_object_read( true );

			/**
			 * Indicate that the StoreEngine shipping zone has been loaded.
			 *
			 * @param ShippingZone $this The shipping zone that has been loaded.
			 */
			do_action( 'storeengine/shipping/zone_loaded', $this );

			return;
		}

		if ( ! $this->get_id() ) {
			throw new StoreEngineException(
				sprintf(
				/* translators: %s: Data object type. */
					__( 'ID is not set for %s.', 'storeengine' ),
					$this->object_type
				),
				'read-error-no-id',
				null,
				400
			);
		}

		// Get from cache if available.
		$data = wp_cache_get( $this->get_id(), $this->cache_group );

		if ( false === $data || true === $refresh ) {
			$this->clear_cache();
			$data = $this->read_data();
			wp_cache_set( $this->get_id(), $data, $this->cache_group );
		}

		$this->set_props( $data );
		$this->read_zone_locations();
		$this->maybe_read_meta_data();
		$this->maybe_read_extra_data();

		$this->set_object_read( true );

		/**
		 * Fires when a object is read into memory.
		 *
		 * @param int $id The product ID.
		 * @param self $this Product instance.
		 */
		do_action( 'storeengine/' . $this->object_type . '/read', $this->get_id(), $this );

		do_action( 'storeengine/shipping/zone_loaded', $this );
	}

	/**
	 * Read location data from the database.
	 */
	private function read_zone_locations() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$locations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT location_code, location_type FROM {$wpdb->prefix}storeengine_shipping_zone_locations WHERE zone_id = %d",
				$this->get_id()
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $locations ) {
			foreach ( $locations as $location ) {
				$this->add_location( $location->location_code, $location->location_type );
			}
		}
	}

	/**
	 * Save locations to the DB.
	 * This function clears old locations, then re-inserts new if any changes are found.
	 *
	 * @return void
	 */
	private function save_locations(): void {
		$changed_props = array_keys( $this->get_changes() );
		if ( ! in_array( 'zone_locations', $changed_props, true ) ) {
			return;
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$wpdb->prefix . 'storeengine_shipping_zone_locations',
			[ 'zone_id' => $this->get_id() ],
			[ '%d' ]
		);
		$this->data['zone_locations'] = [];

		foreach ( $this->get_zone_locations( 'edit' ) as $location ) {
			$wpdb->insert(
				$wpdb->prefix . 'storeengine_shipping_zone_locations',
				[
					'zone_id'       => $this->get_id(),
					'location_code' => $location->code,
					'location_type' => $location->type,
				]
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * --------------------------------------------------------------------------
	 * Getters
	 * --------------------------------------------------------------------------
	 */

	/**
	 * Get zone name.
	 *
	 * @param string $context View or edit context.
	 *
	 * @return string
	 */
	public function get_zone_name( string $context = 'view' ) {
		return $this->get_prop( 'zone_name', $context );
	}

	/**
	 * Get zone order.
	 *
	 * @param string $context View or edit context.
	 *
	 * @return int
	 */
	public function get_zone_order( string $context = 'view' ) {
		return $this->get_prop( 'zone_order', $context );
	}

	/**
	 * Get zone locations.
	 *
	 * @param string $context View or edit context.
	 *
	 * @return array of zone objects
	 */
	public function get_zone_locations( string $context = 'view' ) {
		return $this->get_prop( 'zone_locations', $context );
	}

	/**
	 * Return a text string representing what this zone is for.
	 *
	 * @param int $max Max locations to return.
	 * @param string $context View or edit context.
	 *
	 * @return string
	 */
	public function get_formatted_location( $max = 10, string $context = 'view' ) {
		$location_parts = [];
		$all_continents = Countries::init()->get_continents();
		$all_countries  = Countries::init()->get_countries();
		$all_states     = Countries::init()->get_states();
		$locations      = $this->get_zone_locations( $context );
		$continents     = array_filter( $locations, array( $this, 'location_is_continent' ) );
		$countries      = array_filter( $locations, array( $this, 'location_is_country' ) );
		$states         = array_filter( $locations, array( $this, 'location_is_state' ) );
		$postcodes      = array_filter( $locations, array( $this, 'location_is_postcode' ) );

		foreach ( $continents as $location ) {
			$location_parts[] = $all_continents[ $location->code ]['name'];
		}

		foreach ( $countries as $location ) {
			$location_parts[] = $all_countries[ $location->code ];
		}

		foreach ( $states as $location ) {
			$location_codes   = explode( ':', $location->code );
			$location_parts[] = $all_states[ $location_codes[0] ][ $location_codes[1] ];
		}

		foreach ( $postcodes as $location ) {
			$location_parts[] = $location->code;
		}

		// Fix display of encoded characters.
		$location_parts = array_map( 'html_entity_decode', $location_parts );

		if ( count( $location_parts ) > $max ) {
			$remaining = count( $location_parts ) - $max;

			return sprintf(
				/* translators: %1$s: location parts, %2$d: Number of location parts remaining. */
				_n( '%1$s and %2$d other region', '%1$s and %2$d other regions', $remaining, 'storeengine' ),
				implode( ', ', array_splice( $location_parts, 0, $max ) ),
				$remaining
			);
		} elseif ( ! empty( $location_parts ) ) {
			return implode( ', ', $location_parts );
		} else {
			return __( 'Everywhere', 'storeengine' );
		}
	}

	/**
	 * Get shipping methods linked to this zone.
	 *
	 * @param bool $enabled_only Only return enabled methods.
	 * @param string $context Getting shipping methods for what context. Valid values, admin, json.
	 *
	 * @return ShippingMethod[]
	 */
	public function get_shipping_methods( bool $enabled_only = false, string $context = 'admin' ): array {
		if ( null === $this->get_id() ) {
			return array();
		}

		$raw_methods     = $this->get_methods( $enabled_only );
		$shipping        = Shipping::get_instance();
		$allowed_classes = $shipping->get_shipping_method_class_names();
		$methods         = [];

		foreach ( $raw_methods as $raw_method ) {
			if ( in_array( $raw_method->method_id, array_keys( $allowed_classes ), true ) ) {
				$class_name  = $allowed_classes[ $raw_method->method_id ];
				$instance_id = $raw_method->id;

				// The returned array may contain instances of shipping methods, as well
				// as classes. If the "class" is an instance, just use it. If not,
				// create an instance.
				if ( is_object( $class_name ) ) {
					$class_name_of_instance  = get_class( $class_name );
					$methods[ $instance_id ] = new $class_name_of_instance( $instance_id );
				} else {
					// If the class is not an object, it should be a string. It's better
					// to double-check, to be sure (a class must be a string, anything)
					// else would be useless.
					if ( is_string( $class_name ) && class_exists( $class_name ) ) {
						$methods[ $instance_id ] = new $class_name( $instance_id );
					}
				}

				// Let's make sure that we have an instance before setting its attributes.
				if ( is_object( $methods[ $instance_id ] ) ) {
					$methods[ $instance_id ]->method_order = absint( $raw_method->method_order );
					$methods[ $instance_id ]->enabled      = $raw_method->is_enabled ? 'yes' : 'no';
				}

				if ( 'json' === $context ) {
					// We don't want the entire object in this context, just the public props.
					$methods[ $instance_id ] = (object) get_object_vars( $methods[ $instance_id ] );
					unset( $methods[ $instance_id ]->instance_form_fields, $methods[ $instance_id ]->form_fields );
				}
			}
		}

		uasort( $methods, [ Helper::class, 'shipping_zone_method_order_uasort_comparison' ] );

		return apply_filters( 'storeengine/shipping/shipping_zone_shipping_methods', $methods, $raw_methods, $allowed_classes, $this );
	}

	/**
	 * Get a list of shipping methods for a specific zone.
	 *
	 * @param bool $enabled_only True to request enabled methods only.
	 *
	 * @return array               Array of objects containing method_id, method_order, instance_id, is_enabled
	 */
	public function get_methods( bool $enabled_only ) {
		global $wpdb;

		if ( $enabled_only ) {
			$raw_methods_sql = "SELECT method_id, method_order, id, is_enabled FROM {$wpdb->prefix}storeengine_shipping_zone_methods WHERE zone_id = %d AND is_enabled = 1";
		} else {
			$raw_methods_sql = "SELECT method_id, method_order, id, is_enabled FROM {$wpdb->prefix}storeengine_shipping_zone_methods WHERE zone_id = %d";
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results( $wpdb->prepare( $raw_methods_sql, $this->get_id() ) );
	}

	/**
	 * --------------------------------------------------------------------------
	 * Setters
	 * --------------------------------------------------------------------------
	 */

	/**
	 * Set zone name.
	 *
	 * @param string $set Value to set.
	 */
	public function set_zone_name( $set ) {
		$this->set_prop( 'zone_name', Formatting::clean( $set ) );
	}

	/**
	 * Set zone order. Value to set.
	 *
	 * @param int $set Value to set.
	 */
	public function set_zone_order( $set ) {
		$this->set_prop( 'zone_order', absint( $set ) );
	}

	/**
	 * Set zone locations.
	 *
	 * @param array $locations Value to set.
	 */
	public function set_zone_locations( $locations ) {
		if ( 0 !== $this->get_id() ) {
			$this->set_prop( 'zone_locations', $locations );
		}
	}

	/**
	 * --------------------------------------------------------------------------
	 * Other
	 * --------------------------------------------------------------------------
	 */

	protected function prepare_for_db( string $context = 'create' ): array {
		return [
			'data'   => [
				'zone_name'  => $this->get_zone_name( $context ),
				'zone_order' => $this->get_zone_order( $context ),
			],
			'format' => [ '%s', '%d' ],
		];
	}

	/**
	 * Save zone data to the database.
	 *
	 * @return int
	 */
	public function save() {
		if ( ! $this->get_zone_name() ) {
			$this->set_zone_name( $this->generate_zone_name() );
		}

		Caching::get_transient_version( 'shipping', true );

		return parent::save();
	}

	public function delete( bool $force_delete = true ): bool {
		Caching::get_transient_version( 'shipping', true );

		return parent::delete( $force_delete );
	}

	/**
	 * Generate a zone name based on location.
	 *
	 * @return string
	 */
	protected function generate_zone_name() {
		$zone_name = $this->get_formatted_location();

		if ( empty( $zone_name ) ) {
			$zone_name = __( 'Zone', 'storeengine' );
		}

		return $zone_name;
	}

	/**
	 * Location type detection.
	 *
	 * @param object $location Location to check.
	 *
	 * @return boolean
	 */
	private function location_is_continent( $location ) {
		return 'continent' === $location->type;
	}

	/**
	 * Location type detection.
	 *
	 * @param object $location Location to check.
	 *
	 * @return boolean
	 */
	private function location_is_country( $location ) {
		return 'country' === $location->type;
	}

	/**
	 * Location type detection.
	 *
	 * @param object $location Location to check.
	 *
	 * @return boolean
	 */
	private function location_is_state( $location ) {
		return 'state' === $location->type;
	}

	/**
	 * Location type detection.
	 *
	 * @param object $location Location to check.
	 *
	 * @return boolean
	 */
	private function location_is_postcode( $location ) {
		return 'postcode' === $location->type;
	}

	/**
	 * Is passed location type valid?
	 *
	 * @param string $type Type to check.
	 *
	 * @return boolean
	 */
	public function is_valid_location_type( string $type ): bool {
		return in_array(
			$type,
			apply_filters( 'storeengine/shipping/valid_location_types', [
				'postcode',
				'state',
				'country',
				'continent',
			] ), true );
	}

	/**
	 * Add location (state or postcode) to a zone.
	 *
	 * @param string $code Location code.
	 * @param string $type state or postcode.
	 */
	public function add_location( string $code, string $type ) {
		if ( 0 !== $this->get_id() && $this->is_valid_location_type( $type ) ) {
			if ( 'postcode' === $type ) {
				$code = trim( strtoupper( str_replace( chr( 226 ) . chr( 128 ) . chr( 166 ), '...', $code ) ) ); // No normalization - postcodes are matched against both normal and formatted versions to support wildcards.
			}
			$location         = array(
				'code' => Formatting::clean( $code ),
				'type' => Formatting::clean( $type ),
			);
			$zone_locations   = $this->get_prop( 'zone_locations', 'edit' );
			$zone_locations[] = (object) $location;
			$this->set_prop( 'zone_locations', $zone_locations );
		}
	}


	/**
	 * Clear all locations for this zone.
	 *
	 * @param array|string $types of location to clear.
	 */
	public function clear_locations( $types = [ 'postcode', 'state', 'country', 'continent' ] ) {
		if ( ! is_array( $types ) ) {
			$types = [ $types ];
		}
		$zone_locations = $this->get_prop( 'zone_locations', 'edit' );
		foreach ( $zone_locations as $key => $values ) {
			if ( in_array( $values->type, $types, true ) ) {
				unset( $zone_locations[ $key ] );
			}
		}
		$zone_locations = array_values( $zone_locations ); // reindex.
		$this->set_prop( 'zone_locations', $zone_locations );
	}

	/**
	 * Set locations.
	 *
	 * @param array $locations Array of locations.
	 */
	public function set_locations( $locations = array() ) {
		$this->clear_locations();
		foreach ( $locations as $location ) {
			$this->add_location( $location['code'], $location['type'] );
		}
	}

	/**
	 * Add a shipping method to this zone.
	 *
	 * @param string $type shipping method type.
	 *
	 * @return int new instance_id, 0 on failure
	 */
	public function add_shipping_method( string $type ): int {
		if ( null === $this->get_id() ) {
			$this->save();
		}

		$instance_id     = 0;
		$shipping        = Shipping::get_instance();
		$allowed_classes = $shipping->get_shipping_method_class_names();

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}storeengine_shipping_zone_methods WHERE zone_id = %d", $this->get_id() ) );


		if ( in_array( $type, array_keys( $allowed_classes ), true ) ) {
			$wpdb->insert(
				$wpdb->prefix . 'woocommerce_shipping_zone_methods',
				array(
					'method_id'    => $type,
					'zone_id'      => $this->get_id(),
					'method_order' => $count ++,
				),
				array(
					'%s',
					'%d',
					'%d',
				)
			);
			$instance_id = $wpdb->insert_id;
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $instance_id ) {
			do_action( 'storeengine/shipping/zone_method_added', $instance_id, $type, $this->get_id() );
		}

		Caching::get_transient_version( 'shipping', true );

		return $instance_id;
	}

	/**
	 * Delete a shipping method from a zone.
	 *
	 * @param int $instance_id Shipping method instance ID.
	 *
	 * @return bool True on success, false on failure
	 */
	public function delete_shipping_method( $instance_id ): bool {
		global $wpdb;
		if ( null === $this->get_id() ) {
			return false;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$method = $wpdb->get_row( $wpdb->prepare( "SELECT zone_id, method_id, instance_id, method_order, is_enabled FROM {$wpdb->prefix}storeengine_shipping_zone_methods WHERE id = %d LIMIT 1;", $instance_id ) );

		// Get method details.
		if ( $method ) {
			$wpdb->delete( $wpdb->prefix . 'storeengine_shipping_zone_methods', [ 'id' => $instance_id ] );
			do_action( 'storeengine/shipping/zone_method_deleted', $instance_id, $method->method_id, $this->get_id() );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		Caching::get_transient_version( 'shipping', true );

		return true;
	}
}

// End of file shipping-zone.php.
