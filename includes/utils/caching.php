<?php
/**
 * Caching utilities
 */

namespace StoreEngine\Utils;

use StoreEngine\Classes\Customer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Caching {
	/**
	 * Transients to delete on shutdown.
	 *
	 * @var array Array of transient keys.
	 */
	private static array $delete_transients = [];

	public static function init() {
		add_filter( 'nocache_headers', [ __CLASS__, 'additional_nocache_headers' ], 10 );
		add_action( 'shutdown', [ __CLASS__, 'delete_transients_on_shutdown' ], 10 );
		add_action( 'template_redirect', [ __CLASS__, 'geolocation_ajax_redirect' ] );
		add_action( 'storeengine/update_checkout', [ __CLASS__, 'update_geolocation_hash' ], 5 );
		add_action( 'admin_notices', [ __CLASS__, 'notices' ] );
		add_action( 'delete_version_transients', [ __CLASS__, 'delete_version_transients' ], 10 );
		add_action( 'wp', [ __CLASS__, 'prevent_caching' ] );
		add_action( 'clean_term_cache', [ __CLASS__, 'clean_term_cache' ], 10, 2 );
		add_action( 'edit_terms', [ __CLASS__, 'clean_term_cache' ], 10, 2 );
	}

	/**
	 * Set additional nocache headers.
	 *
	 * @param array $headers Header names and field values.
	 */
	public static function additional_nocache_headers( array $headers ): array {
		global $wp_query;

		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$set_cache = false;

		/**
		 * Allow plugins to enable nocache headers. Enabled for Google weblight.
		 *
		 * @param bool $enable_nocache_headers Flag indicating whether to add nocache headers. Default: false.
		 */
		if ( apply_filters( 'storeengine/enable_nocache_headers', false ) ) {
			$set_cache = true;
		}

		/**
		 * Enabled for Google weblight.
		 *
		 * @see https://support.google.com/webmasters/answer/1061943?hl=en
		 */
		if ( false !== strpos( $agent, 'googleweblight' ) ) {
			// no-transform: Opt-out of Google weblight. https://support.google.com/webmasters/answer/6211428?hl=en.
			$set_cache = true;
		}

		if ( false !== strpos( $agent, 'Chrome' ) && isset( $wp_query ) && Helper::is_cart() ) {
			$set_cache = true;
		}

		if ( $set_cache ) {
			$headers['Cache-Control'] = 'no-transform, no-cache, no-store, must-revalidate';
		}

		return $headers;
	}

	/**
	 * Add a transient to delete on shutdown.
	 *
	 * @param string|array $keys Transient key or keys.
	 */
	public static function queue_delete_transient( $keys ) {
		self::$delete_transients = array_unique( array_merge( is_array( $keys ) ? $keys : array( $keys ), self::$delete_transients ) );
	}

	/**
	 * Transients that don't need to be cleaned right away can be deleted on shutdown to avoid repetition.
	 */
	public static function delete_transients_on_shutdown() {
		if ( self::$delete_transients ) {
			foreach ( self::$delete_transients as $key ) {
				delete_transient( $key );
			}
			self::$delete_transients = array();
		}
	}

	/**
	 * Used to clear layered nav counts based on passed attribute names.
	 *
	 * @param array $attribute_keys Attribute keys.
	 */
	public static function invalidate_attribute_count( array $attribute_keys ) {
		if ( $attribute_keys ) {
			foreach ( $attribute_keys as $attribute_key ) {
				self::queue_delete_transient( 'storeengine_layered_nav_counts_' . $attribute_key );
			}
		}
	}

	/**
	 * Get a hash of the customer location.
	 *
	 * @return string
	 */
	public static function geolocation_ajax_get_location_hash(): string {
		$customer      = Helper::get_customer( null, true );
		$location      = [
			'country'  => $customer->get_billing_country(),
			'state'    => $customer->get_billing_state(),
			'postcode' => $customer->get_billing_postcode(),
			'city'     => $customer->get_billing_city(),
		];
		$location_hash = substr( md5( strtolower( implode( '', $location ) ) ), 0, 12 );

		/**
		 * Controls the location hash used in geolocation-based caching.
		 *
		 * @param string $location_hash The hash used for geolocation.
		 * @param array $location The location/address data.
		 * @param Customer $customer The current customer object.
		 */
		return apply_filters( 'storeengine/geolocation_ajax_get_location_hash', $location_hash, $location, $customer );
	}

	/**
	 * Prevent caching on certain pages
	 */
	public static function prevent_caching() {
		if ( ! is_blog_installed() ) {
			return;
		}
		$page_ids = array_filter( [
			(int) Helper::get_settings( 'cart_page' ),
			(int) Helper::get_settings( 'checkout_page' ),
			(int) Helper::get_settings( 'dashboard_page' ),
		] );

		if ( is_page( $page_ids ) ) {
			self::nocache_headers();
		}
	}

	/**
	 * Wrapper for nocache_headers which also disables page caching.
	 */
	public static function nocache_headers() {
		self::set_nocache_constants();
		nocache_headers();
	}

	/**
	 * When using geolocation via ajax, to bust cache, redirect if the location hash does not equal the querystring.
	 *
	 * This prevents caching of the wrong data for this request.
	 */
	public static function geolocation_ajax_redirect() {
		if ( 'geolocation_ajax' === Helper::get_settings( 'store_default_customer_address' ) && ! Helper::is_checkout() && ! Helper::is_cart() && ! Helper::is_account_page() && ! wp_doing_ajax() && empty( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$location_hash = self::geolocation_ajax_get_location_hash();
			$current_hash  = isset( $_GET['v'] ) ? sanitize_text_field( wp_unslash( $_GET['v'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( empty( $current_hash ) || $current_hash !== $location_hash ) {
				global $wp;

				$redirect_url = trailingslashit( home_url( $wp->request ) );

				if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
					$redirect_url = add_query_arg( wp_unslash( $_SERVER['QUERY_STRING'] ), '', $redirect_url ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				}

				if ( ! get_option( 'permalink_structure' ) ) {
					$redirect_url = add_query_arg( $wp->query_string, '', $redirect_url );
				}

				$redirect_url = add_query_arg( 'v', $location_hash, remove_query_arg( [ 'v', 'add-to-cart' ], $redirect_url ) );

				wp_safe_redirect( esc_url_raw( $redirect_url ), 307 );
				exit;
			}
		}
	}

	/**
	 * Updates the `storeengine_geo_hash` cookie, which is used to help ensure we display
	 * the correct pricing etc. to customers, according to their billing country.
	 *
	 * Note that:
	 *
	 * A) This only sets the cookie if the default customer address is set to "GeoLocate (with
	 *    Page Caching Support)".
	 *
	 * B) It is hooked into the `wc_ajax_update_order_review` action, which has the benefit of
	 *    ensuring we update the cookie any time the billing country is changed.
	 */
	public static function update_geolocation_hash() {
		if ( 'geolocation_ajax' === Helper::get_settings( 'store_default_customer_address' ) ) {
			Helper::setcookie( 'storeengine_geo_hash', static::geolocation_ajax_get_location_hash(), time() + HOUR_IN_SECONDS );
		}
	}

	/**
	 * Get transient version.
	 *
	 * When using transients with unpredictable names, e.g. those containing a md5
	 * hash in the name, we need a way to invalidate them all at once.
	 *
	 * When using default WP transients we're able to do this with a DB query to
	 * delete transients manually.
	 *
	 * With external cache however, this isn't possible. Instead, this function is used
	 * to append a unique string (based on time()) to each transient. When transients
	 * are invalidated, the transient version will increment and data will be regenerated.
	 *
	 * Raised in issue https://github.com/woocommerce/woocommerce/issues/5777.
	 * Adapted from ideas in http://tollmanz.com/invalidation-schemes/.
	 *
	 * @param string $group Name for the group of transients we need to invalidate.
	 * @param boolean $refresh true to force a new version.
	 *
	 * @return string transient version based on time(), 10 digits.
	 */
	public static function get_transient_version( string $group, bool $refresh = false ): string {
		$transient_name  = $group . '-transient-version';
		$transient_value = get_transient( $transient_name );

		if ( false === $transient_value || true === $refresh ) {
			$transient_value = (string) time();

			set_transient( $transient_name, $transient_value );
		}

		return $transient_value;
	}

	/**
	 * Set constants to prevent caching by some plugins.
	 *
	 * @param mixed $return Value to return. Previously hooked into a filter.
	 *
	 * @return mixed
	 */
	public static function set_nocache_constants( $return = true ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // Play nice with WP-Super-Cache.
		}
		if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
			define( 'DONOTCACHEOBJECT', true );
		}
		if ( ! defined( 'DONOTCACHEDB' ) ) {
			define( 'DONOTCACHEDB', true );
		}

		return $return;
	}

	/**
	 * Notices function.
	 */
	public static function notices() {
		if ( ! function_exists( 'w3tc_pgcache_flush' ) || ! function_exists( 'w3_instance' ) ) {
			return;
		}

		/** @noinspection PhpUndefinedFunctionInspection */
		$config   = w3_instance( 'W3_Config' );
		$enabled  = $config->get_integer( 'dbcache.enabled' );
		$settings = array_map( 'trim', $config->get_array( 'dbcache.reject.sql' ) );

		if ( $enabled && ! in_array( '_storeengine_session_', $settings, true ) ) {
			?>
			<div class="error">
				<p>
					<?php
					/** @noinspection HtmlUnknownTarget */
					/* translators: 1: key 2: URL */
					echo wp_kses_post( sprintf( __( 'In order for <strong>database caching</strong> to work with StoreEngine you must add %1$s to the "Ignored Query Strings" option in <a href="%2$s">W3 Total Cache settings</a>.', 'storeengine' ), '<code>_storeengine_session_</code>', esc_url( admin_url( 'admin.php?page=w3tc_dbcache' ) ) ) );
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Clean term caches.
	 *
	 * @param array|int $ids Array of ids or single ID to clear cache for.
	 * @param string $taxonomy Taxonomy name.
	 */
	public static function clean_term_cache( $ids, string $taxonomy ) {
		if ( Helper::PRODUCT_CATEGORY_TAXONOMY === $taxonomy ) {
			$ids = is_array( $ids ) ? $ids : array( $ids );

			$clear_ids = array( 0 );

			foreach ( $ids as $id ) {
				$clear_ids[] = $id;
				$clear_ids   = array_merge( $clear_ids, get_ancestors( $id, Helper::PRODUCT_CATEGORY_TAXONOMY, 'taxonomy' ) );
			}

			$clear_ids = array_unique( $clear_ids );

			foreach ( $clear_ids as $id ) {
				wp_cache_delete( 'product-category-hierarchy-' . $id, 'product_cat' );
			}
		}
	}

	/**
	 * When the transient version increases, this is used to remove all past transients to avoid filling the DB.
	 *
	 * Note; this only works on transients appended with the transient version, and when object caching is not being used.
	 *
	 * @param string $version Version of the transient to remove.
	 *
	 * @deprecated 3.6.0 Adjusted transient usage to include versions within the transient values, making this cleanup obsolete.
	 */
	public static function delete_version_transients( string $version = '' ) {
		if ( ! wp_using_ext_object_cache() && ! empty( $version ) ) {
			global $wpdb;

			$limit = apply_filters( 'storeengine/delete_version_transients_limit', 1000 );

			if ( ! $limit ) {
				return;
			}

			$affected = $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s LIMIT %d;", '\_transient\_%' . $version, $limit ) ); // : cache ok, db call ok.

			// If affected rows is equal to limit, there are more rows to delete. Delete in 30 secs.
			if ( $affected === $limit ) {
				wp_schedule_single_event( time() + 30, 'delete_version_transients', array( $version ) );
			}
		}
	}

	/**
	 * Get prefix for use with wp_cache_set. Allows all cache in a group to be invalidated at once.
	 *
	 * @param string $group Group of cache to get.
	 *
	 * @return string Prefix.
	 */
	public static function get_cache_prefix( string $group ): string {
		// Get cache key - uses cache key storeengine_orders_cache_prefix to invalidate when needed.
		$prefix = wp_cache_get( 'storeengine_' . $group . '_cache_prefix', $group );

		if ( false === $prefix ) {
			$prefix = microtime();
			wp_cache_set( 'storeengine_' . $group . '_cache_prefix', $prefix, $group );
		}

		return 'storeengine_cache_' . $prefix . '_';
	}

	/**
	 * Invalidate cache group.
	 *
	 * @param string $group Group of cache to clear.
	 */
	public static function invalidate_cache_group( string $group ): bool {
		return wp_cache_set( 'storeengine_' . $group . '_cache_prefix', microtime(), $group );
	}

	/**
	 * Helper method to get prefixed key.
	 *
	 * @param string|int $key Key to prefix.
	 * @param string $group Group of cache to get.
	 *
	 * @return string Prefixed key.
	 */
	public static function get_prefixed_key( $key, string $group ): string {
		return self::get_cache_prefix( $group ) . $key;
	}
}

// End of file caching.php.
