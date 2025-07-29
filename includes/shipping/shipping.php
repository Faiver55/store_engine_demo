<?php
/**
 * StoreEngine Shipping
 *
 * Handles shipping and loads shipping methods via hooks.
 *
 * @package StoreEngine
 */

namespace StoreEngine\Shipping;

use StoreEngine\Classes\Cart;
use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Shipping\Methods\ShippingFlatRate;
use StoreEngine\Shipping\Methods\ShippingMethod;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Caching;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\ShippingUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * @see \WC_Shipping
 */
final class Shipping {
	use Singleton;

	/**
	 * True if shipping is enabled.
	 *
	 * @var bool
	 */
	public $enabled = false;

	/**
	 * Stores methods loaded into woocommerce.
	 *
	 * @var array|null
	 */
	public $shipping_methods = null;

	/**
	 * Stores the shipping classes.
	 *
	 * @var array
	 */
	public array $shipping_classes = [];

	/**
	 * Stores packages to ship and to get quotes for.
	 *
	 * @var array
	 */
	public array $packages = [];

	/**
	 * Magic getter.
	 *
	 * @param string $name Property name.
	 *
	 * @return mixed|void
	 */
	public function __get( string $name ) {
		// Grab from cart for backwards compatibility with versions prior to 3.2.
		if ( 'shipping_total' === $name ) {
			return Helper::cart()->get_shipping_total();
		}

		if ( 'shipping_taxes' === $name ) {
			return Helper::cart()->get_shipping_taxes();
		}
	}

	/**
	 * Initialize shipping.
	 */
	public function __construct() {
		$this->enabled = ShippingUtils::is_shipping_enabled();

		if ( $this->enabled ) {
			/**
			 * Initialize shipping.
			 */
			do_action( 'storeengine/shipping_init' );
		}
	}

	/**
	 * @return ShippingMethod[]
	 */
	public function get_all_shipping_methods(): array {
		static $shipping_methods = [];

		if ( empty( $shipping_methods ) ) {
			foreach ( $this->get_shipping_method_class_names() as $method_id => $method_class ) {
				if ( ! is_object( $method_class ) ) {
					if ( ! class_exists( $method_class ) ) {
						continue;
					}
					$method = new $method_class();
					if ( is_null( $shipping_methods ) ) {
						$shipping_methods = [];
					}
					$shipping_methods[ $method->get_id() ] = $method;
				}
			}
		}

		return $shipping_methods;
	}

	/**
	 * Shipping methods register themselves by returning their main class name through the woocommerce_shipping_methods filter.
	 *
	 * @return array
	 */
	public function get_shipping_method_class_names(): array {
		// Unique Method ID => Method Class name.
		$shipping_methods = [
			'flat_rate' => ShippingFlatRate::class,
		];

		return apply_filters( 'storeengine/shipping/methods', $shipping_methods );
	}

	/**
	 * Loads all shipping methods which are hooked in.
	 * If a $package is passed, some methods may add themselves conditionally and zones will be used.
	 *
	 * @param array $package Package information.
	 *
	 * @return ShippingMethod[]
	 */
	public function load_shipping_methods( $package = [] ) {
		if ( ! empty( $package ) ) {
			try {
				$shipping_zone          = ShippingZones::get_zone_matching_package( $package );
				$this->shipping_methods = $shipping_zone->get_shipping_methods( true );
			} catch ( StoreEngineException $e ) {
				// mostly if zone is not in db (maybe deleted).
				// @TODO add error logger.
				$this->shipping_methods = [];
			}
		} else {
			$this->shipping_methods = [];
		}

		// For the settings in the backend, and for non-shipping zone methods, we still need to load any registered classes here.
		foreach ( $this->get_shipping_method_class_names() as $method_id => $method_class ) {
			$this->register_shipping_method( $method_class );
		}

		// Methods can register themselves manually through this hook if necessary.
		do_action( 'storeengine/shipping/load_shipping_methods', $package );

		// Return loaded methods.
		return $this->get_shipping_methods();
	}

	/**
	 * Register a shipping method.
	 *
	 * @param object|string $method Either the name of the method's class, or an instance of the method's class.
	 *
	 * @return bool|void
	 */
	public function register_shipping_method( $method ) {
		if ( ! is_object( $method ) ) {
			if ( ! class_exists( $method ) ) {
				return false;
			}
			$method = new $method();
		}
		if ( is_null( $this->shipping_methods ) ) {
			$this->shipping_methods = [];
		}
		$this->shipping_methods[ $method->get_id() ] = $method;
	}

	/**
	 * Unregister shipping methods.
	 */
	public function unregister_shipping_methods() {
		$this->shipping_methods = null;
	}

	/**
	 * Returns all registered shipping methods for usage.
	 *
	 * @return ShippingMethod[]
	 */
	public function get_shipping_methods() {
		if ( is_null( $this->shipping_methods ) ) {
			$this->load_shipping_methods();
		}

		return $this->shipping_methods;
	}

	/**
	 * Get an array of shipping classes.
	 *
	 * @return array
	 */
	public function get_shipping_classes() {
		if ( empty( $this->shipping_classes ) ) {
			$classes                = get_terms(
				'product_shipping_class',
				array(
					'hide_empty' => '0',
					'orderby'    => 'name',
				)
			);
			$this->shipping_classes = ! is_wp_error( $classes ) ? $classes : [];
		}

		return apply_filters( 'storeengine/shipping/get_shipping_classes', $this->shipping_classes );
	}

	/**
	 * Calculate shipping for (multiple) packages of cart items.
	 *
	 * @param array $packages multidimensional array of cart items to calc shipping for.
	 * @param ?Cart $cart Cart instance to avoid infinite looping.
	 *
	 * @return array Array of calculated packages.
	 */
	public function calculate_shipping( array $packages = [], ?Cart $cart = null ): array {
		$this->packages = [];

		if ( ! $this->enabled || empty( $packages ) ) {
			return [];
		}

		// Calculate costs for passed packages.
		foreach ( $packages as $package_key => $package ) {
			$this->packages[ $package_key ] = $this->calculate_shipping_for_package( $cart, $package, $package_key );
		}

		/**
		 * Allow packages to be reorganized after calculating the shipping.
		 *
		 * This filter can be used to apply some extra manipulation after the shipping costs are calculated for the packages
		 * but before WooCommerce does anything with them. A good example of usage is to merge the shipping methods for multiple
		 * packages for marketplaces.
		 *
		 * @param array $packages The array of packages after shipping costs are calculated.
		 */
		$this->packages = array_filter( (array) apply_filters( 'storeengine/shipping/packages', $this->packages ) );

		return $this->packages;
	}

	/**
	 * See if package is shippable.
	 *
	 * Packages are shippable until proven otherwise e.g. after getting a shipping country.
	 *
	 * @param array $package Package of cart items.
	 *
	 * @return bool
	 */
	public function is_package_shippable( array $package ): bool {
		// Packages are shippable until proven otherwise.
		if ( empty( $package['destination']['country'] ) ) {
			return true;
		}

		$allowed = array_keys( Countries::init()->get_shipping_countries() );

		return in_array( $package['destination']['country'], $allowed, true );
	}

	/**
	 * Calculate shipping rates for a package,
	 *
	 * Calculates each shipping methods cost. Rates are stored in the session based on the package hash to avoid re-calculation every page load.
	 *
	 * @param Cart $cart Cart instance.
	 * @param array $package Package of cart items.
	 * @param int|string $package_key Index of the package being calculated. Used to cache multiple package rates.
	 *
	 * @return array|bool
	 */
	public function calculate_shipping_for_package( Cart $cart, array $package = [], $package_key = 0 ) {
		// If shipping is disabled or the package is invalid, return false.
		if ( ! $this->enabled || empty( $package ) ) {
			return false;
		}

		$package['rates'] = [];

		// If the package is not shippable, e.g. trying to ship to an invalid country, do not calculate rates.
		if ( ! $this->is_package_shippable( $package ) ) {
			return $package;
		}

		// Check if we need to recalculate shipping for this package.
		$package_to_hash = $package;

		// Remove data objects so hashes are consistent.
		foreach ( $package_to_hash['contents'] as $item_id => $item ) {
			if ( isset( $item->data ) ) {
				unset( $package_to_hash['contents'][ $item_id ]->data );
			}
		}

		// Get rates stored in the session data for this package.
		$session_key  = 'shipping_for_package_' . $package_key;
		$stored_rates = $cart->get_meta( $session_key );

		// Calculate the hash for this package so we can tell if it's changed since last calculation.
		$package_hash = 'se_ship_' . md5( wp_json_encode( $package_to_hash ) . Caching::get_transient_version( 'shipping' ) );

		if ( ! is_array( $stored_rates ) || $package_hash !== $stored_rates['package_hash'] ) {
			foreach ( $this->load_shipping_methods( $package ) as $shipping_method ) {
				if ( ! $shipping_method->supports( 'shipping-zones' ) || $shipping_method->get_instance_id() ) {
					/**
					 * Fires before getting shipping rates for a package.
					 *
					 * @param array $package Package of cart items.
					 * @param ShippingMethod $shipping_method Shipping method instance.
					 */
					do_action( 'storeengine/shipping/before_get_rates_for_package', $package, $shipping_method );

					// Use + instead of array_merge to maintain numeric keys.
					$package['rates'] = $package['rates'] + $shipping_method->get_rates_for_package( $package );

					/**
					 * Fires after getting shipping rates for a package.
					 *
					 * @param array $package Package of cart items.
					 * @param ShippingMethod $shipping_method Shipping method instance.
					 */
					do_action( 'storeengine/shipping/after_get_rates_for_package', $package, $shipping_method );
				}
			}

			/**
			 * Filter the calculated shipping rates.
			 *
			 * @see https://gist.github.com/woogists/271654709e1d27648546e83253c1a813 for cache invalidation methods.
			 *
			 * @param array $package ['rates'] Package rates.
			 * @param array $package Package of cart items.
			 */
			$package['rates'] = apply_filters( 'storeengine/shipping/package_rates', $package['rates'], $package );

			// Package rates should be an array, if it was filtered into a non-array, reset it. Don't reset to the
			// unfiltered value, as e.g. a 3pd could have set it to "false" to remove rates.
			if ( ! is_array( $package['rates'] ) ) {
				$package['rates'] = [];
			}

			// Store in session to avoid recalculation.
			$cart->set_meta(
				$session_key,
				array(
					'package_hash' => $package_hash,
					'rates'        => $package['rates'],
				)
			);
		} else {
			$package['rates'] = $stored_rates['rates'];
		}

		return $package;
	}

	/**
	 * Get packages.
	 *
	 * @return array
	 */
	public function get_packages(): array {
		return $this->packages;
	}

	/**
	 * Reset shipping.
	 *
	 * Reset the totals for shipping as a whole.
	 */
	public function reset_shipping() {
		$this->packages = [];
	}
}

// End of file shipping.php.
