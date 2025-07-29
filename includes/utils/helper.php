<?php

namespace StoreEngine\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine;
use StoreEngine\Classes\Countries;
use StoreEngine\Models\Cart;
use StoreEngine\Shipping\Methods\ShippingMethod;
use WP_Error;

use StoreEngine\Utils\traits\{
	Currency,
	Gateway,
	Integration,
	Order,
	Pages,
	Product,
	Attribute,
	Customer,
	DownloadPermission
};
use WP_Post;

class Helper extends Template {

	use Customer, Pages, Order, Currency, Gateway, Product, Integration, Attribute, DownloadPermission;

	const PRODUCT_POST_TYPE = STOREENGINE_PLUGIN_SLUG . '_product';

	const PRODUCT_CATEGORY_TAXONOMY = self::PRODUCT_POST_TYPE . '_category';

	const PRODUCT_ATTRIBUTE_TAXONOMY = self::PRODUCT_POST_TYPE . '_attribute';

	const PRODUCT_TAG_TAXONOMY = self::PRODUCT_POST_TYPE . '_tag';

	const COUPON_POST_TYPE = STOREENGINE_PLUGIN_SLUG . '_coupon';

	const DB_PREFIX = STOREENGINE_PLUGIN_SLUG . '_';

	/**
	 * Installed plugin list.
	 */
	protected static ?array $installed_plugins = null;

	public static function get_time() {
		return time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
	}

	public static function is_fse_theme() {
		if ( function_exists( 'wp_is_block_theme' ) ) {
			return wp_is_block_theme();
		}
		if ( function_exists( 'gutenberg_is_fse_theme' ) ) {
			return \gutenberg_is_fse_theme();
		}

		return false;
	}

	public static function remove_line_break( string $content ): string {
		$content = preg_replace( '/\s+/', ' ', $content );

		return trim( $content );
	}

	public static function remove_tag_space( string $content ): string {
		return preg_replace( '/>\s+</', '><', $content );
	}

	public static function add_string_quote( $value ) {
		if ( in_array( gettype( $value ), array( 'integer', 'double', 'float' ), true ) ) {
			return $value;
		} elseif ( 'boolean' === gettype( $value ) ) {
			return (int) $value;
		}

		return "'" . $value . "'";
	}

	public static function array_diff_recursive( $array1, $array2 ): array {
		$difference = [];

		foreach ( $array1 as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( ! isset( $array2[ $key ] ) || ! is_array( $array2[ $key ] ) ) {
					$difference[ $key ] = $value;
				} else {
					$newDiff = self::array_diff_recursive( $value, $array2[ $key ] );
					if ( ! empty( $newDiff ) ) {
						$difference[ $key ] = $newDiff;
					}
				}
			} elseif ( ! array_key_exists( $key, $array2 ) || ( $array2[ $key ] !== $value ) ) {
				$difference[ $key ] = $value;
			}
		}

		return $difference;
	}

	public static function get_country_name( string $key ) {
		$countries = Countries::init()->get_countries();

		return $countries[ $key ] ?? '';
	}

	public static function cart(): ?\StoreEngine\Classes\Cart {
		return StoreEngine::init()->get_cart();
	}

	public static function get_price_duration( $price, $duration, $duration_type ): string {
		if ( 1 === $duration ) {
			/* translators: 1. Price 2, duration */
			return sprintf( __( '%1$s Every %2$s', 'storeengine' ), Formatting::price( $price ), ucfirst( $duration_type ) );
		} else {
			return ( Formatting::price( $price ) . ' / ' . $duration . '-' . $duration_type . 's' );
		}
	}

	/**
	 * @deprecated
	 */
	public static function get_enabled_payment_methods(): array {
		$payments_settings       = self::get_payments_settings();
		$enabled_payment_methods = [];
		if ( is_array( $payments_settings ) ) {
			foreach ( $payments_settings as $payment_settings ) {
				if ( $payment_settings['is_enabled'] ) {
					$enabled_payment_methods[] = array(
						'type'         => $payment_settings['type'],
						'title'        => $payment_settings['title'] ?? $payment_settings['type'],
						'instructions' => $payment_settings['instructions'] ?? null,
					);
				}
			}
		}

		return $enabled_payment_methods;
	}

	/**
	 * @deprecated
	 */
	public static function get_payments_settings( $payment_method = '', $default = null ) {
		$payments_settings = \StoreEngine\Admin\Settings\Payments::get_settings_saved_data();

		if ( is_array( $payments_settings ) ) {
			if ( ! $payment_method ) {
				return $payments_settings;
			}

			foreach ( $payments_settings as $payment_settings ) {
				if ( $payment_settings['type'] === $payment_method ) {
					return $payment_settings;
				}
			}

			return [];
		}

		return $default;
	}

	/**
	 * @param string $page
	 * @param ?string $fallback
	 *
	 * @return string
	 */
	public static function get_page_permalink( string $page, string $fallback = null ): string {
		$page_id   = self::get_settings( $page );
		$permalink = 0 < $page_id ? get_permalink( $page_id ) : '';

		if ( ! $permalink ) {
			$permalink = is_null( $fallback ) ? get_home_url() : $fallback;
		}

		$permalink = apply_filters( "storeengine/get_{$page}_permalink", $permalink, $page_id, $fallback );

		return apply_filters( 'storeengine/get_page_permalink', $permalink, $page, $page_id, $fallback );
	}

	public static function get_dashboard_url(): string {
		return self::get_page_permalink( 'dashboard_page' );
	}

	/**
	 * Get endpoint URL.
	 *
	 * Gets the URL for an endpoint, which varies depending on permalink settings.
	 *
	 * @param string $endpoint
	 * @param string|int|float $value
	 * @param string|false $permalink
	 *
	 * @return string
	 */
	public static function get_endpoint_url( string $endpoint, $value = '', $permalink = '' ): string {
		global $wp_query;

		if ( ! $permalink ) {
			$permalink = get_permalink();
		}

		// Map endpoint to options.
		$query_vars = $wp_query->query_vars;
		$endpoint   = ! empty( $query_vars[ $endpoint ] ) ? $query_vars[ $endpoint ] : $endpoint;

		if ( get_option( 'permalink_structure' ) ) {
			if ( strstr( $permalink, '?' ) ) {
				$query_string = '?' . wp_parse_url( $permalink, PHP_URL_QUERY );
				$permalink    = current( explode( '?', $permalink ) );
			} else {
				$query_string = '';
			}

			// Cleanup trailing slash.
			$url = trailingslashit( untrailingslashit( $permalink ) );

			if ( $value ) {
				$url .= trailingslashit( untrailingslashit( $endpoint ) ) . user_trailingslashit( $value );
			} else {
				$url .= user_trailingslashit( $endpoint );
			}

			$url .= $query_string;
		} else {
			$url = add_query_arg( $endpoint, $value, $permalink );
		}

		$url = apply_filters( "storeengine/get_{$endpoint}_endpoint_url", $url, $value, $permalink );

		return apply_filters( 'storeengine/get_endpoint_url', $url, $endpoint, $value, $permalink );
	}

	/**
	 * Get account endpoint URL.
	 *
	 * @param string $endpoint Endpoint.
	 *
	 * @return string
	 */
	public static function get_account_endpoint_url( string $endpoint, $value = '' ): ?string {
		if ( 'dashboard' === $endpoint || 'myaccount' === $endpoint || 'index' === $endpoint ) {
			return self::get_dashboard_url();
		}

		$url = self::get_endpoint_url( $endpoint, $value, self::get_dashboard_url() );

		if ( 'customer-logout' === $endpoint ) {
			return wp_nonce_url( $url, 'customer-logout' );
		}

		$url = apply_filters( "storeengine/dashboard/get_{$endpoint}_endpoint_url", $url, $value );

		return apply_filters( 'storeengine/dashboard/get_endpoint_url', $url, $endpoint, $value );
	}

	/**
	 * Get the link to the edit account details page.
	 *
	 * @return string
	 */
	public static function customer_edit_account_url(): string {
		$edit_account_url = self::get_endpoint_url( 'edit-account', '', self::get_dashboard_url() );

		return apply_filters( 'storeengine/customer/edit_account_url', $edit_account_url );
	}

	/**
	 * add-filter to lostpassword_url
	 *
	 * @param $default_url
	 * @param $redirect
	 *
	 * @return mixed|string
	 */
	public static function get_lost_password_url( $default_url = '', $redirect = '' ) {
		// Avoid loading too early.
		if ( ! did_action( 'init' ) ) {
			return $default_url;
		}

		// Don't change the admin form.
		if ( did_action( 'login_form_login' ) ) {
			return $default_url;
		}

		// Don't redirect to the woocommerce endpoint on global network admin lost passwords.
		if ( is_multisite() && isset( $_GET['redirect_to'] ) && false !== strpos( wp_unslash( $_GET['redirect_to'] ), network_admin_url() ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return $default_url;
		}

		$permalink = self::get_page_permalink( 'password_reset_page' );

		if ( ! $permalink ) {
			return $default_url;
		}

		if ( ! empty( $redirect ) ) {
			return add_query_arg( [ 'redirect_to' => rawurlencode( $redirect ) ], $permalink );
		}

		return $permalink;
	}

	public static function get_cart_url() {
		return apply_filters( 'storeengine/get_cart_url', self::get_page_permalink( 'cart_page' ) );
	}

	public static function get_checkout_url() {
		$checkout_url = self::get_page_permalink( 'checkout_page' );
		if ( $checkout_url ) {
			// Force SSL if needed.
			if ( is_ssl() || self::get_settings( 'force_ssl_checkout' ) ) {
				$checkout_url = str_replace( 'http:', 'https:', $checkout_url );
			}
		}

		return apply_filters( 'storeengine/get_checkout_url', $checkout_url );
	}

	public static function get_settings( $key, $default = null ) {
		global $storeengine_settings;

		$value = $storeengine_settings->{$key} ?? $default;
		$value = apply_filters( "storeengine/get_{$key}_settings", $value, $key );

		return apply_filters( 'storeengine/get_settings', $value, $key );
	}

	public static function get_shop_address(): string {
		global $storeengine_settings;

		return Countries::init()->get_formatted_address( [
			'address_1' => $storeengine_settings->store_address_1,
			'address_2' => $storeengine_settings->store_address_2,
			'city'      => $storeengine_settings->store_city,
			'state'     => $storeengine_settings->store_state,
			'postcode'  => $storeengine_settings->store_postcode,
			'country'   => $storeengine_settings->store_country,
		] );
	}

	public static function get_addon_active_status( $addon_name, $is_pro = false ) {
		global $storeengine_addons;
		if ( $is_pro && ! self::is_active_storeengine_pro() ) {
			return false;
		}
		if ( isset( $storeengine_addons->{$addon_name} ) ) {
			return (bool) $storeengine_addons->{$addon_name};
		}

		return false;
	}

	public static function is_active_storeengine_pro() {
		$storeengine_pro = 'storeengine-pro/storeengine-pro.php';

		return self::is_plugin_active( $storeengine_pro );
	}

	public static function is_plugin_active( $basename ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			include_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $basename );
	}

	public static function dif_from_human( $date ) {
		$now = time();
		if ( ! is_numeric( $date ) ) {
			$date = strtotime( $date );
		}
		$diff = $now - $date;
		if ( $diff < 60 ) {
			/* translators: %s is the number of seconds */
			return sprintf( __( '%s seconds ago', 'storeengine' ), $diff );
		}
		if ( $diff < 3600 ) {
			/* translators: %s is the number of minutes */
			return sprintf( __( '%s minutes ago', 'storeengine' ), round( $diff / 60 ) );
		}
		if ( $diff < 86400 ) {
			/* translators: %s is the number of hours */
			return sprintf( __( '%s hours ago', 'storeengine' ), round( $diff / 3600 ) );
		}
		if ( $diff < 604800 ) {
			/* translators: %s is the number of days */
			return sprintf( __( '%s days ago', 'storeengine' ), round( $diff / 86400 ) );
		}
		if ( $diff < 2419200 ) {
			/* translators: %s is the number of weeks */
			return sprintf( __( '%s weeks ago', 'storeengine' ), round( $diff / 604800 ) );
		}
		if ( $diff < 29030400 ) {
			/* translators: %s is the number of months */
			return sprintf( __( '%s months ago', 'storeengine' ), round( $diff / 2419200 ) );
		}

		/* translators: %s is the number of years */

		return sprintf( __( '%s years ago', 'storeengine' ), round( $diff / 29030400 ) );
	}

	public static function get_tax_rate( string $postcode ): ?float {
		$tax_rates = [
			'1000' => 5.5,
			'2000' => 6.3,
			'3000' => 7.1,
			'1206' => 7,
			'1207' => 7,
			'7300' => 7,
		];

		return $tax_rates[ $postcode ] ?? null;
	}

	/**
	 * @return array<array{
	 *     label: string,
	 *    icon: string,
	 *    public: bool,
	 *    priority: int|float,
	 *    children:<array{
	 *        label: string,
	 *        icon: string,
	 *        public: bool,
	 *        priority: int|float,
	 *    }>,
	 *  }>
	 */
	public static function get_frontend_dashboard_menu_items(): array {
		$items = [
			'index'                      => [
				'label'    => __( 'Dashboard', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--layout',
				'public'   => true,
				'priority' => - 1,
			],
			'orders'                     => [
				'label'    => __( 'Orders', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--box',
				'public'   => true,
				'priority' => 30,
			],
			'plans'                      => [
				'label'    => __( 'Plans', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--invoice',
				'public'   => true,
				'priority' => 50,
			],
			'downloads'                  => [
				'label'    => __( 'Downloads', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--brand-style',
				'public'   => true,
				'priority' => 70,
			],
			'payment-methods'            => [
				'label'    => __( 'Payment methods', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--payment',
				'public'   => true,
				'priority' => 90,
			],
			'add-payment-method'         => [
				'label'    => __( 'Add Payment method', 'storeengine' ),
				'public'   => false,
				'priority' => 91,
			],
			'delete-payment-method'      => [
				'label'    => __( 'Delete Payment method', 'storeengine' ),
				'public'   => false,
				'priority' => 92,
			],
			'set-default-payment-method' => [
				'label'    => __( 'Set Default Payment method', 'storeengine' ),
				'public'   => false,
				'priority' => 93,
			],
			'edit-address'               => [
				'label'    => __( 'Addresses', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--edit',
				'public'   => true,
				'priority' => 110,
				'children' => [
					'billing'  => [
						'label'    => __( 'Edit Billing Address', 'storeengine' ),
						'public'   => false,
						'priority' => 10,
					],
					'shipping' => [
						'label'    => __( 'Edit Shipping Address', 'storeengine' ),
						'public'   => false,
						'priority' => 20,
					],
				],
			],
			'edit-account'               => [
				'label'    => __( 'Account', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--build',
				'public'   => true,
				'priority' => 130,
			],
			'customer-logout'            => [
				'label'    => __( 'Log out', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--logout',
				'public'   => true,
				'priority' => 999,
			],
		];

		$support_payment_methods = false;
		foreach ( self::get_payment_gateways()->get_available_payment_gateways() as $gateway ) {
			if ( $gateway->supports( 'add_payment_method' ) || $gateway->supports( 'tokenization' ) ) {
				$support_payment_methods = true;
				break;
			}
		}

		if ( ! $support_payment_methods ) {
			unset( $items['payment-methods'] );
		}

		return apply_filters( 'storeengine/frontend_dashboard_menu_items', $items );
	}

	public static function get_frontend_dashboard_page_title( $path, $sub_path = '' ) {
		$menu = self::get_frontend_dashboard_menu_items();

		if ( empty( $menu[ $path ] ) ) {
			return '';
		}

		if ( $sub_path ) {
			if ( ! empty( $menu[ $path ]['children'][ $sub_path ] ) ) {
				return $menu[ $path ]['children'][ $sub_path ]['label'];
			}
		}

		return $menu[ $path ]['label'];
	}

	public static function round( $val, int $precision = 0, int $mode = PHP_ROUND_HALF_UP ): float {
		if ( ! is_numeric( $val ) ) {
			$val = floatval( $val );
		}

		return round( $val, $precision, $mode );
	}

	public static function meta_parser( $meta ) {
		return array_map( function ( $i ) {
			return $i[0];
		}, $meta );
	}

	/**
	 * @param $basename
	 *
	 * @return false|array{Name:string,PluginURI:string,Version:string,Description:string,Author:string,AuthorURI:string,TextDomain:string,DomainPath:string,Network:string,RequiresWP:string,RequiresPHP:string,UpdateURI:string,RequiresPlugins:string}
	 */
	public static function is_plugin_installed( $basename ) {
		if ( null === static::$installed_plugins ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				include_once ABSPATH . '/wp-admin/includes/plugin.php';
			}

			static::$installed_plugins = get_plugins();
		}

		return static::$installed_plugins[ $basename ] ?? false;
	}

	public static function get_cart_hash(): ?string {
		$cart_hash = self::get_cart_hash_from_cookie();
		if ( $cart_hash ) {
			return $cart_hash;
		}

		return Cart::get_cart_hash_by_user_id( get_current_user_id() );
	}

	public static function get_cart_hash_from_cookie(): string {
		return isset( $_COOKIE['storeengine_cart_hash'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['storeengine_cart_hash'] ) ) : '';
	}

	/**
	 * Set cart has in cookie.
	 *
	 * @param string $cart_hash
	 *
	 * @return void
	 * @deprecated
	 */
	public static function set_cart_hash_in_cookie( string $cart_hash ): void {
		setcookie( 'storeengine_cart_hash', $cart_hash, [
			'expires'  => time() + YEAR_IN_SECONDS,
			'path'     => defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
			'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Strict',
		] );
	}

	/**
	 * Unset Cart hash cookie.
	 *
	 * @return void
	 * @deprecated
	 */
	public static function unset_cart_hash_in_cookie(): void {
		if ( headers_sent() ) {
			return;
		}

		// @TODO delete cache on hash changes.
		// wp_cache_delete 'order:draft:' . Helper::get_cart_hash_from_cookie(), 'storeengine_orders' ;
		header( 'Set-Cookie: storeengine_cart_hash=; Path=/; HttpOnly; Max-Age=-1', false );
		setcookie( 'storeengine_cart_hash', '', [
			'expires'  => - 1,
			'path'     => defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
			'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Strict',
		] );
	}

	public static function sanitize_referer_url( string $referer_url ): string {
		$parse_url = wp_parse_url( $referer_url );

		if ( isset( $parse_url['query'] ) ) {
			// Parse query parameters
			parse_str( $parse_url['query'], $query_params );
			if ( ! empty( $query_params['redirect_to'] ) ) {
				$referer_url = $query_params['redirect_to'];
			}
			if ( ! empty( $query_params['redirect_url'] ) ) {
				$referer_url = $query_params['redirect_url'];
			}
		}

		// Sanitize the input URL
		$referer_url = esc_url_raw( $referer_url );
		if ( filter_var( $referer_url, FILTER_VALIDATE_URL ) !== false && wp_http_validate_url( $referer_url ) && strpos( $referer_url, home_url() ) === 0 ) {
			return esc_url( $referer_url );
		} elseif ( ! empty( $parse_url['path'] ) ) {
			return esc_url( home_url( sanitize_text_field( $parse_url['path'] ) ) );
		}

		return esc_url( home_url( '/' ) );
	}

	public static function asort_by_locale( &$data, $locale = '' ) {
		// Use Collator if PHP Internationalization Functions (php-intl) is available.
		if ( class_exists( 'Collator' ) ) {
			try {
				$locale   = $locale ? $locale : get_locale();
				$collator = new \Collator( $locale );
				$collator->asort( $data, \Collator::SORT_STRING );

				return $data;
			} catch ( \IntlException $e ) {
				/*
				 * Just skip if some error got caused.
				 * It may be caused in installations that doesn't include ICU TZData.
				 */
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// @TODO implement error logger.
					// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging.
					error_log(
						sprintf(
							'An unexpected error occurred while trying to use PHP Intl Collator class, it may be caused by an incorrect installation of PHP Intl and ICU, and could be fixed by reinstalling PHP Intl, see more details about PHP Intl installation: %1$s. Error message: %2$s',
							'https://www.php.net/manual/en/intl.installation.php',
							$e->getMessage()
						)
					);
					// phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
		}

		// Keep a reference to original data before removing accent marks
		// as strcmp works better without accent marks and add the value back
		// to the sorted array from this reference.
		$raw_data = $data;

		array_walk( $data, function ( &$value ) {
			$value = remove_accents( html_entity_decode( $value ) );
		} );

		uasort( $data, 'strcmp' );

		foreach ( $data as $key => $val ) {
			$data[ $key ] = $raw_data[ $key ];
		}

		return $data;
	}

	public static function get_all_roles(): array {
		global $wp_roles;

		if ( ! class_exists( '\WP_Roles' ) ) {
			return [];
		}

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new \WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$roles_array = [];

		foreach ( $wp_roles->roles as $role_id => $role ) {
			$roles_array[] = [
				'role_id'   => $role_id,
				'role_name' => $role['name'],
			];
		}

		return $roles_array;
	}

	public static function get_sample_permalink_args( $id, $new_title = null, $new_slug = null ) {
		$post = get_post( $id );
		if ( ! $post ) {
			return '';
		}

		list( $permalink, $post_name ) = get_sample_permalink( $post->ID, $new_title, $new_slug );
		$view_link                     = false;

		if ( current_user_can( 'read_post', $post->ID ) ) {
			if ( 'draft' === $post->post_status || empty( $post->post_name ) ) {
				$view_link = get_preview_post_link( $post );
			} elseif ( 'publish' === $post->post_status || 'storeengine_product' === $post->post_type ) {
				$view_link = get_permalink( $post );
			} else {
				$view_link = str_replace( [ '%pagename%', '%postname%' ], $post->post_name, $permalink );
			}
		}

		return [
			'view_link'         => $view_link ? esc_url( $view_link ) : null,
			'editable_postname' => $post_name,
			'display_link'      => rtrim( str_replace( '%pagename%', $post_name, $permalink ) ),
			'post_name'         => $post_name,
		];
	}

	/**
	 * Get Page by title.
	 *
	 * @param string $page_title
	 * @param string $post_type
	 *
	 * @return WP_Post|null
	 */
	public static function get_page_by_title( string $page_title, string $post_type = 'page' ): ?WP_Post {
		global $wpdb;

		$page = wp_cache_get( 'storeengine:get_page_by_title:' . sanitize_title( $page_title ), $post_type );

		if ( false === $page ) {
			$page = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = %s;",
					$page_title,
					$post_type
				)
			);

			wp_cache_set( 'storeengine:get_page_by_title:' . sanitize_title( $page_title ), $page, $post_type );
		}

		if ( $page ) {
			$page = get_post( $page, OBJECT );

			if ( ! $page ) {
				wp_cache_delete( 'storeengine:get_page_by_title:' . sanitize_title( $page_title ), $post_type );
			}

			return $page;
		}

		return null;
	}

	public static function get_user_ip(): string {
		if ( isset( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// Proxy servers can send through this header like this: X-Forwarded-For: client1, proxy1, proxy2
			// Make sure we always only send through the first IP in the list which should always be the client IP.
			$value = trim( current( preg_split( '/,/', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) ) );
			// Account for the '<IPv4 address>:<port>', '[<IPv6>]' and '[<IPv6>]:<port>' cases, removing the port.
			// The regular expression is oversimplified on purpose, later 'rest_is_ip_address' will do the actual IP address validation.
			$value = preg_replace( '/([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)\:.*|\[([^]]+)\].*/', '$1$2', $value );

			return (string) rest_is_ip_address( $value );
		} elseif ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) && filter_var( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ), FILTER_VALIDATE_IP ) ) {
			// Check if HTTP_CLIENT_IP is set
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) && filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP ) ) {
			// Fallback to REMOTE_ADDR
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		// Return empty string if no valid IP is found
		return '';
	}

	/**
	 * Get user agent string.
	 *
	 * @return string
	 */
	public static function get_user_agent(): string {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? Formatting::clean( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : ''; // @codingStandardsIgnoreLine
	}

	public static function get_email_template_name( string $template_name, ?string $template_sub_name = null ): string {
		if ( $template_sub_name ) {
			return str_replace( '_', '-', $template_name ) . '-' . $template_sub_name . '.php';
		}

		return str_replace( '_', '-', $template_name ) . '.php';
	}

	/**
	 * Schedule rewrite rule flushing on next reload.
	 *
	 * @return void
	 * @since 0.0.4
	 */
	public static function flush_rewire_rules() {
		update_option( 'storeengine_required_rewrite_flush', 'yes' );
	}

	/**
	 * Checks whether the content passed contains a specific short code.
	 *
	 * @param string $tag Shortcode tag to check.
	 *
	 * @return bool
	 */
	public static function post_content_has_shortcode( string $tag = '' ): bool {
		global $post;

		return is_singular() && is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, $tag );
	}

	public static function is_storeengine(): bool {
		return apply_filters( 'storeengine/is_storeengine', self::is_shop() || self::is_product_taxonomy() || self::is_product() );
	}

	public static function is_shop(): bool {
		return ( is_post_type_archive( self::PRODUCT_POST_TYPE ) || is_page( self::get_settings( 'shop_page' ) ) );
	}

	public static function is_product(): bool {
		return is_singular( [ self::PRODUCT_POST_TYPE ] );
	}

	public static function is_product_taxonomy(): bool {
		return is_tax( get_object_taxonomies( self::PRODUCT_POST_TYPE ) );
	}

	public static function is_product_category( $term = '' ): bool {
		return is_tax( self::PRODUCT_CATEGORY_TAXONOMY, $term );
	}

	public static function is_product_tag( $term = '' ): bool {
		return is_tax( self::PRODUCT_TAG_TAXONOMY, $term );
	}

	public static function is_cart(): bool {
		$page_id = self::get_settings( 'cart_page' );

		return ( $page_id && is_page( $page_id ) ) || defined( 'STOREENGINE_CART' ) || self::post_content_has_shortcode( 'storeengine_cart' );
	}

	public static function is_checkout(): bool {
		$page_id = self::get_settings( 'checkout_page' );

		return ( $page_id && is_page( $page_id ) ) || self::post_content_has_shortcode( 'storeengine_checkout' ) || apply_filters( 'storeengine_is_checkout', false ) || defined( 'STOREENGINE_CART' );
	}

	public static function is_dashboard(): bool {
		$page_id = self::get_settings( 'dashboard_page' );

		return ( $page_id && is_page( $page_id ) ) || self::post_content_has_shortcode( 'storeengine_dashboard' ) || apply_filters( 'storeengine_is_dashboard_page', false );
	}

	public static function is_account_page(): bool {
		return self::is_dashboard();
	}

	public static function get_all_product_category_lists() {
		$categories = get_terms(
			array(
				'taxonomy'   => 'storeengine_product_category',
				'hide_empty' => true,
			)
		);

		return self::prepare_category_results( $categories );
	}

	public static function prepare_category_results( $terms, $parent_id = 0 ) {
		$category = array();
		foreach ( $terms as $term ) {
			if ( $term->parent === $parent_id ) {
				$term->children = self::prepare_category_results( $terms, $term->term_id );
				$category[]     = $term;
			}
		}

		return $category;
	}

	public static function is_endpoint( $endpoint = null ): bool {
		global $wp_query;

		if ( empty( $wp_query->query['storeengine_dashboard_page'] ) ) {
			return false;
		}

		if ( $endpoint ) {
			return $wp_query->query['storeengine_dashboard_page'] === $endpoint;
		}

		return true;
	}

	public static function is_add_payment_method_page(): bool {
		return self::is_dashboard() && ( self::is_endpoint( 'payment-methods' ) || self::is_endpoint( 'add-payment-method' ) );
	}

	public static function is_edit_address_page(): bool {
		return self::is_dashboard() && ( self::is_endpoint( 'edit-address' ) && ! empty( get_query_var( 'storeengine_dashboard_sub_page' ) ) );
	}

	public static function check_rest_user_cap( string $capability, ?string $response = null ) {
		$permission = true;
		if ( ! is_user_logged_in() || ! current_user_can( $capability ) ) {
			$permission = new WP_Error(
				'storeengine_rest_forbidden_context',
				$response ? esc_html( $response ) : esc_html__( 'Sorry, insufficient permission.', 'storeengine' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return apply_filters( 'storeengine/rest_user_capability', $permission, $capability );
	}

	public static function prepare_product_search_query_args( $data ) {
		$defaults = array(
			'search'         => '',
			'category'       => [],
			'tags'           => [],
			'paged'          => 1,
			'posts_per_page' => 12,
		);
		$data     = wp_parse_args( $data, $defaults );

		// base
		$args = array(
			'post_type'      => apply_filters( 'storeengine/get_product_archive_post_types', array( 'storeengine_product' ) ),
			'post_status'    => 'publish',
			'posts_per_page' => $data['posts_per_page'],
			'paged'          => $data['paged'],
		);

		// taxonomy
		$tax_query = array();
		if ( count( $data['category'] ) > 0 ) {
			$tax_query[] = array(
				'taxonomy' => 'storeengine_product_category',
				'field'    => 'slug',
				'terms'    => $data['category'],
			);
		}
		if ( count( $data['tags'] ) > 0 ) {
			$tax_query[] = array(
				'taxonomy' => 'storeengine_product_tag',
				'field'    => 'slug',
				'terms'    => $data['tags'],
			);
		}
		if ( count( $tax_query ) > 0 ) {
			$tax_query['relation'] = 'AND';
			$args['tax_query']     = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		// search
		if ( ! empty( $data['search'] ) ) {
			$args['s'] = $data['search'];
		}

		// order by
		if ( isset( $data['orderby'] ) ) {
			switch ( $data['orderby'] ) {
				case 'name':
				case 'title':
					$args['orderby'] = 'post_title';
					$args['order']   = 'asc';
					break;
				case 'date':
					$args['orderby'] = 'publish_date';
					$args['order']   = 'desc';
					break;
				case 'modified':
					$args['orderby'] = 'modified';
					$args['order']   = 'desc';
					break;
				case 'menu_order':
					$args['orderby'] = 'menu_order';
					$args['order']   = 'desc';
					break;
				default:
					$args['orderby'] = 'ID';
					$args['order']   = 'desc';
			}//end switch
		}//end if
		return apply_filters( 'storeengine/get_product_archive_search_query_args', $args, $data );
	}

	public static function get_responsive_column( $columns ): string {
		if ( is_array( $columns ) ) {
			$device  = [
				'desktop' => 'lg',
				'tablet'  => 'md',
				'mobile'  => 'sm',
			];
			$classes = '';
			foreach ( $columns as $mode => $column ) {
				if ( $column ) {
					$classes .= ' storeengine-col-' . $device[ $mode ] . '-' . ceil( 12 / $column );
				}
			}

			return ltrim( $classes );
		}

		return '';
	}

	public static function get_permalink_structure() {
		$saved_permalinks = (array) get_option( 'storeengine_permalinks', array() );
		$permalinks       = wp_parse_args(
			array_filter( $saved_permalinks ),
			array(
				'product_base'           => _x( 'product', 'slug', 'storeengine' ),
				'category_base'          => _x( 'product-category', 'slug', 'storeengine' ),
				'tag_base'               => _x( 'product-tag', 'slug', 'storeengine' ),
				'use_verbose_page_rules' => false,
			)
		);

		if ( $saved_permalinks !== $permalinks ) {
			update_option( 'storeengine_permalinks', $permalinks );
		}

		$permalinks['product_rewrite_slug']  = untrailingslashit( $permalinks['product_base'] );
		$permalinks['category_rewrite_slug'] = untrailingslashit( $permalinks['category_base'] );
		$permalinks['tag_rewrite_slug']      = untrailingslashit( $permalinks['tag_base'] );

		return $permalinks;
	}

	/**
	 * Switch plugin to site language.
	 *
	 * @return void
	 */
	public static function switch_to_site_locale() {
		global $wp_locale_switcher;

		if ( function_exists( 'switch_to_locale' ) && isset( $wp_locale_switcher ) ) {
			switch_to_locale( get_locale() );

			// Filter on plugin_locale so load_plugin_textdomain loads the correct locale.
			add_filter( 'plugin_locale', 'get_locale' );

			// Init locale.
			storeengine_start()->load_textdomain();
		}
	}

	/**
	 * Switch plugin language to original.
	 *
	 * @return void
	 */
	public static function restore_locale() {
		global $wp_locale_switcher;

		if ( function_exists( 'restore_previous_locale' ) && isset( $wp_locale_switcher ) ) {
			restore_previous_locale();

			// Remove filter.
			remove_filter( 'plugin_locale', 'get_locale' );

			// Init locale.
			storeengine_start()->load_textdomain();
		}
	}

	/**
	 * Simple check for validating a URL, it must start with http:// or https://.
	 * and pass FILTER_VALIDATE_URL validation.
	 *
	 * @param string $url to check.
	 *
	 * @return bool
	 */
	public static function is_valid_url( string $url ): bool {

		// Must start with http:// or https://.
		/** @noinspection HttpUrlsUsage */
		if ( 0 !== strpos( $url, 'http://' ) && 0 !== strpos( $url, 'https://' ) ) {
			return false;
		}

		// Must pass validation.
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Alias for is_valid_url()
	 *
	 * @param string $url
	 *
	 * @return bool
	 * @see is_valid_url()
	 */
	public static function is_url( string $url ): bool {
		return self::is_valid_url( $url );
	}

	public static function is_valid_site_url( string $url ): bool {
		return str_starts_with( $url, get_option( 'siteurl' ) );
	}

	public static function get_reveiw_survey_form_radios( $slug ) {
		$html = '';
		for ( $counter = 1; $counter <= 5; $counter ++ ) {
			$html .= sprintf(
				"<td><input type='radio' name='%s-rating' value='%s' /></td>",
				$slug,
				$counter
			);
		}

		return $html;
	}

	public static function get_date_format() {
		$date_format = get_option( 'date_format' );
		if ( empty( $date_format ) ) {
			// Return default date format if the option is empty.
			$date_format = 'F j, Y';
		}

		return apply_filters( 'storeengine/date_format', $date_format );
	}

	public static function single_star_rating_generator( $current_rating = 0.00 ) {
		$output = '<span class="storeengine-group-star">';
		if ( 5 < $current_rating && 0 > $current_rating ) {
			$output .= '<i class="storeengine-icon storeengine-icon--star-fill"></i>';
		} elseif ( 0 === $current_rating ) {
			$output .= '<i class="storeengine-icon storeengine-icon--star-fill"></i>';
		} else {
			$output .= '<i class="storeengine-icon storeengine-icon--star-fill"></i>';
		}
		$output .= '</span>';

		return $output;
	}

	public static function star_rating_generator( $current_rating = 0.00 ) {
		$output = '<span class="storeengine-group-star">';

		for ( $i = 1; $i <= 5; $i ++ ) {
			$intRating = (int) $current_rating;

			if ( $intRating >= $i ) {
				$output .= '<i class="storeengine-icon storeengine-icon--star-fill" data-rating-value="' . $i . '"></i>';
			} else {
				if ( ( $current_rating - $i ) === - 0.5 ) {
					$output .= '<i class="storeengine-icon storeengine-icon--star-half" data-rating-value="' . $i . '"></i>';
				} else {
					$output .= '<i class="storeengine-icon storeengine-icon--star-line" data-rating-value="' . $i . '"></i>';
				}
			}
		}

		$output .= '</span>';

		return $output;
	}

	/**
	 * @param $product_id
	 * @param $user_id
	 *
	 * @return string|null
	 *
	 * @see wc_customer_bought_product()
	 */
	public static function is_purchase_the_product( $product_id, $user_id = 0 ): ?string {
		global $wpdb;

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// @TODO cache user's purchase story for 30 days.
		// @see wc_customer_bought_product fn for details.

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT o.id
            FROM {$wpdb->prefix}storeengine_orders o
            JOIN {$wpdb->prefix}storeengine_order_product_lookup op ON o.id = op.order_id
            WHERE o.customer_id = %d
            AND op.product_id = %d
            AND o.status = 'completed'
            LIMIT 1;",
				$user_id,
				$product_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public static function is_purchase_the_membership( $product_id, $price_id, $customer_id = 0, $order_status = Constants::ORDER_STATUS_COMPLETED ) {
		global $wpdb;

		if ( ! $customer_id ) {
			$customer_id = get_current_user_id();
		}

		$user_meta = get_user_meta( $customer_id, '_storeengine_memberships', true );

		if ( ! is_array( $user_meta ) ) {
			$user_meta = [];
		}

		if ( ! empty( $user_meta ) ) {
			$result = false;
			foreach ( $user_meta as $u_meta ) {
				if ( $price_id === $u_meta['price_id'] && Constants::ORDER_STATUS_COMPLETED === $u_meta['order_status'] ) {
					$result = true;
					break;
				}
			}

			return $result;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- query result cached in user meta
		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}storeengine_order_product_lookup op
			    JOIN {$wpdb->prefix}storeengine_orders o ON op.order_id = o.id
			    WHERE op.product_id = %d
			    AND op.price_id = %d
			    AND o.customer_id = %d
			    AND o.status = %s",
			$product_id,
			$price_id,
			$customer_id,
			$order_status
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- query result cached in user meta

		if ( $count ) {
			$user_meta[] = compact( 'customer_id', 'price_id', 'order_status' );
			update_user_meta( $customer_id, '_storeengine_memberships', $user_meta );
		}

		return $count;
	}

	/**
	 * Used to sort shipping zone methods with uasort.
	 *
	 * @param ShippingMethod|\stdClass $a First shipping zone method to compare.
	 * @param ShippingMethod|\stdClass $b Second shipping zone method to compare.
	 *
	 * @return int
	 */
	public static function shipping_zone_method_order_uasort_comparison( $a, $b ): int {
		return self::uasort_comparison( $a->method_order, $b->method_order );
	}

	/**
	 * User to sort checkout fields based on priority with uasort.
	 *
	 * @param array $a First field to compare.
	 * @param array $b Second field to compare.
	 *
	 * @return int
	 */
	public static function checkout_fields_uasort_comparison( array $a, array $b ): int {
		/*
		 * We are not guaranteed to get a priority
		 * setting. So don't compare if they don't
		 * exist.
		 */
		if ( ! isset( $a['priority'], $b['priority'] ) ) {
			return 0;
		}

		return self::uasort_comparison( $a['priority'], $b['priority'] );
	}

	/**
	 * User to sort two values with uasort.
	 *
	 * @param int $a First value to compare.
	 * @param int $b Second value to compare.
	 *
	 * @return int
	 */
	public static function uasort_comparison( int $a, int $b ): int {
		if ( $a === $b ) {
			return 0;
		}

		return ( $a < $b ) ? - 1 : 1;
	}

	/**
	 * Merge two arrays.
	 *
	 * @param array $a1 First array to merge.
	 * @param array $a2 Second array to merge.
	 *
	 * @return array
	 */
	public static function array_overlay( array $a1, array $a2 ): array {
		foreach ( $a1 as $k => $v ) {
			if ( ! array_key_exists( $k, $a2 ) ) {
				continue;
			}
			if ( is_array( $v ) && is_array( $a2[ $k ] ) ) {
				$a1[ $k ] = self::array_overlay( $v, $a2[ $k ] );
			} else {
				$a1[ $k ] = $a2[ $k ];
			}
		}

		return $a1;
	}

	/**
	 * Set a cookie - wrapper for setcookie using WP constants.
	 *
	 * @param string $name Name of the cookie being set.
	 * @param string|int|float $value Value of the cookie.
	 * @param integer $expire Expiry of the cookie.
	 * @param bool $secure Whether the cookie should be served only over https.
	 * @param bool $httponly Whether the cookie is only accessible over HTTP, not scripting languages like JavaScript.
	 */
	public static function setcookie( string $name, $value, int $expire = 0, bool $secure = false, bool $httponly = false ): void {
		/**
		 * Controls whether the cookie should be set via wc_setcookie().
		 *
		 * @param bool $set_cookie_enabled If wc_setcookie() should set the cookie.
		 * @param string $name Cookie name.
		 * @param string $value Cookie value.
		 * @param integer $expire When the cookie should expire.
		 * @param bool $secure If the cookie should only be served over HTTPS.
		 */
		if ( ! apply_filters( 'storeengine/set_cookie_enabled', true, $name, $value, $expire, $secure ) ) {
			return;
		}

		if ( ! headers_sent() ) {
			/**
			 * Controls the options to be specified when setting the cookie.
			 *
			 * @see   https://www.php.net/manual/en/function.setcookie.php
			 *
			 * @param array $cookie_options Cookie options.
			 * @param string $name Cookie name.
			 * @param string $value Cookie value.
			 */
			$options = apply_filters(
				'storeengine/set_cookie_options',
				[
					'expires'  => $expire,
					'secure'   => $secure,
					'path'     => COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => COOKIE_DOMAIN,
					/**
					 * Controls whether the cookie should only be accessible via the HTTP protocol, or if it should also be
					 * accessible to Javascript.
					 *
					 * @see   https://www.php.net/manual/en/function.setcookie.php
					 *
					 * @param bool $httponly If the cookie should only be accessible via the HTTP protocol.
					 * @param string $name Cookie name.
					 * @param string $value Cookie value.
					 * @param int $expire When the cookie should expire.
					 * @param bool $secure If the cookie should only be served over HTTPS.
					 */
					'httponly' => apply_filters( 'storeengine/cookie_httponly', $httponly, $name, $value, $expire, $secure ),
				],
				$name,
				$value
			);

			setcookie( $name, $value, $options );
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			headers_sent( $file, $line );
			trigger_error( "{$name} cookie cannot be set - headers already sent by {$file} on line {$line}", E_USER_NOTICE ); // @codingStandardsIgnoreLine
		}
	}

	/**
	 * What type of request is this?
	 *
	 * @param string $type admin, ajax, cron or frontend.
	 *
	 * @return bool
	 */
	public static function is_request( string $type ): bool {
		switch ( $type ) {
			case 'admin':
				return is_admin();
			case 'ajax':
				return defined( 'DOING_AJAX' );
			case 'cron':
				return defined( 'DOING_CRON' );
			case 'frontend':
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' ) && ! self::is_rest_api_request();
			case 'rest':
			case 'restapi':
				return self::is_rest_api_request();
			default:
				return false;
		}
	}

	/**
	 * Returns true if the request is a non-legacy REST API request.
	 *
	 * Legacy REST requests should still run some extra code for backwards compatibility.
	 *
	 * @todo: replace this function once core WP function is available: https://core.trac.wordpress.org/ticket/42061.
	 *
	 * @return bool
	 */
	public static function is_rest_api_request(): bool {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$rest_prefix         = trailingslashit( rest_get_url_prefix() );
		$is_rest_api_request = ( false !== strpos( $_SERVER['REQUEST_URI'], $rest_prefix ) ); // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		/**
		 * Whether this is a REST API request.
		 */
		return apply_filters( 'storeengine/is_rest_api_request', $is_rest_api_request );
	}

	/**
	 * Wrapper for set_time_limit to see if it is enabled.
	 *
	 * @param int $limit Time limit.
	 */
	public static function set_time_limit( int $limit = 0 ) {
		if ( function_exists( 'set_time_limit' ) && false === strpos( ini_get( 'disable_functions' ), 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) { // phpcs:ignore PHPCompatibility.IniDirectives.RemovedIniDirectives.safe_modeDeprecatedRemoved
			@set_time_limit( $limit ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- server may choose to disable this function.
		}
	}

	public static function get_product_term_ids( $product_id, $taxonomy ) {
		$terms = get_the_terms( $product_id, $taxonomy );

		return ( empty( $terms ) || is_wp_error( $terms ) ) ? array() : wp_list_pluck( $terms, 'term_id' );
	}

	/**
	 * Get all product cats for a product by ID, including hierarchy
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return array
	 */
	public static function get_product_cat_ids( int $product_id ): array {
		$product_cats = self::get_product_term_ids( $product_id, self::PRODUCT_CATEGORY_TAXONOMY );

		foreach ( $product_cats as $product_cat ) {
			$product_cats = array_merge( $product_cats, get_ancestors( $product_cat, self::PRODUCT_CATEGORY_TAXONOMY, 'taxonomy' ) );
		}

		return $product_cats;
	}

	public static function get_coupon_types(): array {
		return (array) apply_filters( 'storeengine/product_coupon_types', [ 'percentage', 'fixedAmount' ] );
	}

	/**
	 * Return a list of potential postcodes for wildcard searching.
	 *
	 * @param string $postcode Postcode.
	 * @param string $country Country to format postcode for matching.
	 *
	 * @return string[]
	 */
	public static function get_wildcard_postcodes( $postcode, $country = '' ) {
		$formatted_postcode = Formatting::format_postcode( $postcode, $country );
		$length             = function_exists( 'mb_strlen' ) ? mb_strlen( $formatted_postcode ) : strlen( $formatted_postcode );
		$postcodes          = [
			$postcode,
			$formatted_postcode,
			$formatted_postcode . '*',
		];

		for ( $i = 0; $i < $length; $i ++ ) {
			$postcodes[] = ( function_exists( 'mb_substr' ) ? mb_substr( $formatted_postcode, 0, ( $i + 1 ) * - 1 ) : substr( $formatted_postcode, 0, ( $i + 1 ) * - 1 ) ) . '*';
		}

		return $postcodes;
	}

	/**
	 * Used by shipping zones and taxes to compare a given $postcode to stored
	 * postcodes to find matches for numerical ranges, and wildcards.
	 *
	 * @param string $postcode Postcode you want to match against stored postcodes.
	 * @param array $objects Array of postcode objects from Database.
	 * @param string $object_id_key DB column name for the ID.
	 * @param string $object_compare_key DB column name for the value.
	 * @param string $country Country from which this postcode belongs. Allows for formatting.
	 *
	 * @return array Array of matching object ID and matching values.
	 */
	public static function postcode_location_matcher( $postcode, $objects, $object_id_key, $object_compare_key, $country = '' ) {
		$postcode           = Formatting::normalize_postcode( $postcode );
		$wildcard_postcodes = array_map( [
			Formatting::class,
			'clean',
		], self::get_wildcard_postcodes( $postcode, $country ) );
		$matches            = [];

		foreach ( $objects as $object ) {
			$object_id       = $object->$object_id_key;
			$compare_against = $object->$object_compare_key;

			// Handle postcodes containing ranges.
			if ( strstr( $compare_against, '...' ) ) {
				$range = array_map( 'trim', explode( '...', $compare_against ) );

				if ( 2 !== count( $range ) ) {
					continue;
				}

				list( $min, $max ) = $range;

				// If the postcode is non-numeric, make it numeric.
				if ( ! is_numeric( $min ) || ! is_numeric( $max ) ) {
					$compare = Formatting::make_numeric_postcode( $postcode );
					$min     = str_pad( Formatting::make_numeric_postcode( $min ), strlen( $compare ), '0' );
					$max     = str_pad( Formatting::make_numeric_postcode( $max ), strlen( $compare ), '0' );
				} else {
					$compare = $postcode;
				}

				if ( $compare >= $min && $compare <= $max ) {
					$matches[ $object_id ]   = $matches[ $object_id ] ?? [];
					$matches[ $object_id ][] = $compare_against;
				}
			} elseif ( in_array( $compare_against, $wildcard_postcodes, true ) ) {
				// Wildcard and standard comparison.
				$matches[ $object_id ]   = $matches[ $object_id ] ?? [];
				$matches[ $object_id ][] = $compare_against;
			}
		}

		return $matches;
	}

	/**
	 * Based on wp_list_pluck, this calls a method instead of returning a property.
	 *
	 * @param array $list List of objects or arrays.
	 * @param int|string $callback_or_field Callback method from the object to place instead of the entire object.
	 * @param int|string $index_key Optional. Field from the object to use as keys for the new array.
	 *                                      Default null.
	 *
	 * @return array Array of values.
	 */
	public static function list_pluck( array $list, $callback_or_field, $index_key = null ): array {
		// Use wp_list_pluck if this isn't a callback.
		$first_el = current( $list );
		if ( ! is_object( $first_el ) || ! is_callable( [ $first_el, $callback_or_field ] ) ) {
			return wp_list_pluck( $list, $callback_or_field, $index_key );
		}
		if ( ! $index_key ) {
			/*
			 * This is simple. Could at some point wrap array_column()
			 * if we knew we had an array of arrays.
			 */
			foreach ( $list as $key => $value ) {
				$list[ $key ] = $value->{$callback_or_field}();
			}

			return $list;
		}

		/*
		 * When index_key is not set for a particular item, push the value
		 * to the end of the stack. This is how array_column() behaves.
		 */
		$newlist = [];
		foreach ( $list as $value ) {
			// Get index. @since 3.2.0 this supports a callback.
			if ( is_callable( array( $value, $index_key ) ) ) {
				$newlist[ $value->{$index_key}() ] = $value->{$callback_or_field}();
			} elseif ( isset( $value->$index_key ) ) {
				$newlist[ $value->$index_key ] = $value->{$callback_or_field}();
			} else {
				$newlist[] = $value->{$callback_or_field}();
			}
		}

		return $newlist;
	}

	/**
	 * Get an item of post data if set, otherwise return a default value.
	 *
	 * @param string $key Meta key.
	 * @param mixed $default Default value.
	 *
	 * @return mixed Value sanitized by Formatting::clean.
	 */
	public static function get_post_data_by_key( string $key, $default = '' ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing
		return Formatting::clean( wp_unslash( self::get_var( $_POST[ $key ], $default ) ) );
	}

	/**
	 * Get data if set, otherwise return a default value or null. Prevents notices when data is not set.
	 *
	 * @param mixed $var Variable.
	 * @param mixed $default Default value.
	 *
	 * @return mixed
	 */
	public static function get_var( &$var, $default = null ) {
		return isset( $var ) ? $var : $default;
	}

	public static function is_storeengine_page( int $post_id = 0 ): bool {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		return in_array( (int) $post_id, self::get_storeengine_page_ids(), true );
	}

	public static function get_storeengine_page_ids(): array {
		$settings_keys = [
			'checkout_page',
			'shop_page',
			'store_shop',
			'cart_page',
			'thankyou_page',
			'dashboard_page',
			'membership_pricing_page',
		];

		$page_ids = array_map( fn( $key ) => (int) self::get_settings( $key ), $settings_keys );

		return array_filter( $page_ids );
	}

	/**
	 * Is registration required to checkout?
	 *
	 * @return boolean
	 */
	public static function is_registration_required(): bool {
		/**
		 * Controls if registration is required in order for checkout to be completed.
		 *
		 * @param bool $checkout_registration_required If customers must be registered to checkout.
		 */
		return apply_filters( 'storeengine/checkout/registration_required', ! self::get_settings( 'enable_guest_checkout', true ) );
	}

	/**
	 * Define a constant if it is not already defined.
	 *
	 * @param string $name Constant name.
	 * @param mixed $value Value.
	 */
	public static function maybe_define_constant( string $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	public static function get_assets_url( string $path = '' ): string {
		return STOREENGINE_ASSETS_URI . ltrim( $path, '/\\' );
	}

	public static function get_plugin_url( string $path = '' ): string {
		return STOREENGINE_PLUGIN_ROOT_URI . ltrim( $path, '/\\' );
	}

	public static function get_addons_url( string $addon, string $path = '' ): string {
		return self::get_plugin_url( 'addons/' . ltrim( rtrim( $addon, '/\\' ), '/\\' ) . '/' . ltrim( $path, '/\\' ) );
	}

	public static function get_upload_dir(): string {
		$upload = wp_upload_dir();

		return $upload['basedir'] . '/storeengine_uploads';
	}

	public static function array_every( array $arr, callable $predicate ): bool {
		foreach ( $arr as $e ) {
			if ( ! $predicate( $e ) ) {
				return false;
			}
		}

		return true;
	}

	public static function array_any( array $arr, callable $predicate ): bool {
		return ! self::array_every( $arr, fn( $e ) => ! $predicate( $e ) );
	}

	/***
	 * @param string|StoreEngine\Classes\Exceptions\StoreEngineException|\Exception $exception
	 *
	 * @return void
	 */
	public static function log_error( $exception ) {
		// @TODO implement error logger.
		// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r, WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( is_string( $exception ) ) {
				error_log( $exception );
			}

			if ( $exception instanceof \Exception ) {
				error_log( $exception->getMessage() );
				error_log( 'Error Code: ' . $exception->getCode() . ' File: ' . $exception->getFile() . '[Line:' . $exception->getLine() . ']' );
				error_log( print_r( wp_debug_backtrace_summary( self::class, 0, false ), true ) );
			}
		}
		// phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r, WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary
	}
}
