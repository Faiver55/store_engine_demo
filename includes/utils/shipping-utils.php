<?php

namespace StoreEngine\Utils;

use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Shipping\Methods\ShippingMethod;
use StoreEngine\Shipping\Shipping;
use StoreEngine\Shipping\Shipping as ShippingObj;
use StoreEngine\Shipping\ShippingRate;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ShippingUtils {
	public static function is_shipping_enabled(): bool {
		return apply_filters( 'storeengine/shipping_enabled', 'disabled' !== Helper::get_settings( 'ship_to_countries' ) );
	}

	public static function get_shipping_methods_count( $enabled_only = false ): int {
		global $wpdb;

		$transient_name    = 'storeengine_shipping_method_count';
		$transient_version = Caching::get_transient_version( 'shipping' );
		$transient_value   = get_transient( $transient_name );
		$counts            = array(
			'enabled'  => 0,
			'disabled' => 0,
		);

		if ( ! isset( $transient_value['enabled'], $transient_value['disabled'], $transient_value['version'] ) || $transient_value['version'] !== $transient_version ) {
			$methods    = Shipping::init()->get_shipping_methods();
			$method_ids = array_map( fn( $method ) => $method->get_method_id(), $methods );

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$counts['enabled']  = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}storeengine_shipping_zone_methods WHERE is_enabled=1 AND method_id IN ('" . implode( "','", array_map( 'esc_sql', $method_ids ) ) . "')" ) );
			$counts['disabled'] = absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}storeengine_shipping_zone_methods WHERE is_enabled=0 AND method_id IN ('" . implode( "','", array_map( 'esc_sql', $method_ids ) ) . "')" ) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			$transient_value = array(
				'version'  => $transient_version,
				'enabled'  => $counts['enabled'],
				'disabled' => $counts['disabled'],
			);

			set_transient( $transient_name, $transient_value, DAY_IN_SECONDS * 30 );
		} else {
			$counts = $transient_value;
		}

		if ( $enabled_only ) {
			$return = $counts['enabled'];
		} else {
			$return = $counts['enabled'] + $counts['disabled'];
		}

		return $return;
	}

	public static function cart_totals_shipping_method_label( ShippingRate $method ) {
		$label     = $method->get_label();
		$has_cost  = 0 < $method->get_cost();
		$hide_cost = ! $has_cost && in_array( $method->get_method_id(), [ 'free_shipping', 'local_pickup' ], true );

		if ( $has_cost && ! $hide_cost ) {
			if ( Helper::cart()->display_prices_including_tax() ) {
				$label .= ': ' . Formatting::price( (float) $method->get_cost() + $method->get_shipping_tax() );
				if ( $method->get_shipping_tax() > 0 && ! Helper::get_settings( 'prices_include_tax' ) ) {
					$label .= ' <small class="tax_label">' . Countries::init()->inc_tax_or_vat() . '</small>';
				}
			} else {
				$label .= ': ' . Formatting::price( $method->get_cost() );
				if ( $method->get_shipping_tax() > 0 && Helper::get_settings( 'prices_include_tax' ) ) {
					$label .= ' <small class="tax_label">' . Countries::init()->ex_tax_or_vat() . '</small>';
				}
			}
		}

		return apply_filters( 'storeengine/shipping/cart_shipping_method_full_label', $label, $method );
	}

	/**
	 * @param string $method_id
	 * @param int $instance_id
	 *
	 * @return ShippingMethod|WP_Error
	 */
	public static function get_shipping_method( string $method_id, int $instance_id = 0 ) {
		$shipping_methods = ShippingObj::init()->get_shipping_method_class_names();
		if ( ! array_key_exists( $method_id, $shipping_methods ) ) {
			return new WP_Error( 'invalid-value', __( 'Invalid shipping method Id provided.', 'storeengine' ) );
		}
		if ( ! class_exists( $shipping_methods[ $method_id ] ) ) {
			return new WP_Error( 'invalid-value', __( 'Class not found for shipping method Id!', 'storeengine' ) );
		}

		try {
			return new $shipping_methods[ $method_id ]( $instance_id );
		} catch ( StoreEngineException $e ) {
			return $e->get_wp_error();
		}
	}

	public static function get_chosen_shipping_method_ids(): array {
		return [];
		// @TODO implement separate session data manager and then use this,
		//       as this gets called in user object before cart gets initialized.
		/*$cart = Helper::cart();
		if ( ! $cart ) {
			return [];
		}
		$chosen_methods = $cart->get_meta( 'chosen_shipping_methods' ) ?? [];
		$method_ids     = [];

		foreach ( $chosen_methods as $chosen_method ) {
			if ( ! is_string( $chosen_method ) ) {
				continue;
			}
			$chosen_method = explode( ':', $chosen_method );
			$method_ids[]  = current( $chosen_method );
		}

		return $method_ids;*/
	}
}

// End of file shipping-utils.php.
