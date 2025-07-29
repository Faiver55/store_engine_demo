<?php

namespace StoreEngine\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractModel;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use WP_Error;

/**
 * Use Price entity & PriceCollection class
 *
 * @see \StoreEngine\Classes\Price
 * @see \StoreEngine\Classes\PriceCollection
 * @deprecated
 */
class Price extends AbstractModel {
	protected string $table = 'storeengine_product_price';

	/**
	 * @param int[] $price_ids
	 *
	 * @return array
	 */
	public static function get_pricing_with_products( array $price_ids ): array {
		global $wpdb;

		$price_ids = array_unique( array_filter( array_map( 'absint', $price_ids ) ) );

		if ( empty( $price_ids ) ) {
			return [];
		}

		$id_placeholders = implode( ',', array_fill( 0, count( $price_ids ), '%d' ) );
		$where           = $wpdb->prepare( "AND price.id IN ({$id_placeholders})", $price_ids ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$orderby         = $wpdb->prepare( " FIELD(price.id, {$id_placeholders})", $price_ids ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Query prepared above.
		$results = $wpdb->get_results(
			"
				SELECT price.*, product.post_title
				FROM {$wpdb->prefix}storeengine_product_price price
				RIGHT JOIN {$wpdb->prefix}posts product ON price.product_id = product.ID
				WHERE
					product.post_type = 'storeengine_product' AND product.post_status = 'publish'
					{$where}
				ORDER BY {$orderby};
			"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $results ) {
			return [];
		}

		$data = [];
		foreach ( $results as $result ) {
			self::parsePrice( $result );
			$data[ $result->id ] = $result;
		}

		return $data;
	}

	public static function get_price_with_product( int $price_id ) {
		global $wpdb;

		if ( ! $price_id ) {
			return false;
		}

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "SELECT price.*, product.post_title
			FROM {$wpdb->prefix}storeengine_product_price price
			RIGHT JOIN {$wpdb->prefix}posts product ON price.product_id = product.ID
			WHERE price.id = %d
			AND product.post_type = 'storeengine_product'
			AND product.post_status = 'publish'",
				$price_id
			)
		);

		if ( $row ) {
			self::parsePrice( $row );
		}

		return $row ?: false;
	}

	public function save( array $args = [] ) {
		global $wpdb;

		$args = wp_parse_args( $args, [
			'price_name'    => '',
			'price_type'    => '',
			'price'         => '',
			'compare_price' => null,
			'product_id'    => 0,
			'settings'      => [],
			'order'         => 0,
		] );

		// Parse settings.
		$args['settings'] = self::prepare_settings( $args['settings'] );

		$price = new \StoreEngine\Classes\Price();
		$price->set_props( [
			'price_name'            => $args['price_name'],
			'price_type'            => $args['price_type'],
			'price'                 => (float) $args['price'],
			'compare_price'         => (float) $args['compare_price'] > 0 ? (float) $args['compare_price'] : null,
			'product_id'            => absint( $args['product_id'] ),
			'order'                 => absint( $args['order'] ),
			'setup_fee'             => (bool) ( $args['settings']['setup_fee'] ?: false ),
			'setup_fee_name'        => (string) ( $args['settings']['setup_fee_name'] ?: '' ),
			'setup_fee_price'       => (float) ( $args['settings']['setup_fee_price'] ?: 0 ),
			'setup_fee_type'        => (string) ( $args['settings']['setup_fee_type'] ?: 'fixed' ),
			'trial'                 => (bool) ( $args['settings']['trial'] ?: false ),
			'trial_days'            => absint( $args['settings']['trial_days'] ?: 0 ),
			'expire'                => (bool) ( $args['settings']['expire'] ?: false ),
			'expire_days'           => absint( $args['settings']['expire_days'] ?: 0 ),
			'payment_duration'      => absint( $args['settings']['payment_duration'] ?: 1 ),
			'payment_duration_type' => (string) ( $args['settings']['payment_duration_type'] ?: 'monthly' ),
			'upgradeable'           => (bool) ( $args['settings']['upgradeable'] ?: false ),
		] );

		if ( is_wp_error( $price->save() ) ) {
			return false;
		}

		return $price->get_id();
	}

	public function update( int $id, array $args ) {
		$args = wp_parse_args( $args, [
			'price_name'    => '',
			'price_type'    => '',
			'price'         => '',
			'compare_price' => null,
			'settings'      => [],
			'order'         => 0,
		] );

		// Parse settings.
		$args['settings'] = self::prepare_settings( $args['settings'] );

		try {
			$price = new \StoreEngine\Classes\Price( $id );
			$price->set_props( [
				'price_name'            => $args['price_name'],
				'price_type'            => $args['price_type'],
				'price'                 => (float) $args['price'],
				'compare_price'         => (float) $args['compare_price'] > 0 ? (float) $args['compare_price'] : null,
				'product_id'            => absint( $args['product_id'] ),
				'order'                 => absint( $args['order'] ),
				'setup_fee'             => (bool) ( $args['settings']['setup_fee'] ?: false ),
				'setup_fee_name'        => (string) ( $args['settings']['setup_fee_name'] ?: '' ),
				'setup_fee_price'       => (float) ( $args['settings']['setup_fee_price'] ?: 0 ),
				'setup_fee_type'        => (string) ( $args['settings']['setup_fee_type'] ?: 'fixed' ),
				'trial'                 => (bool) ( $args['settings']['trial'] ?: false ),
				'trial_days'            => absint( $args['settings']['trial_days'] ?: 0 ),
				'expire'                => (bool) ( $args['settings']['expire'] ?: false ),
				'expire_days'           => absint( $args['settings']['expire_days'] ?: 0 ),
				'payment_duration'      => absint( $args['settings']['payment_duration'] ?: 1 ),
				'payment_duration_type' => (string) ( $args['settings']['payment_duration_type'] ?: 'monthly' ),
				'upgradeable'           => (bool) ( $args['settings']['upgradeable'] ?: false ),
			] );

			$save = $price->save();
			if ( is_wp_error( $save ) ) {
				return $save;
			}

			return $price->get_id();
		} catch ( StoreEngineException $e ) {
			return $e->get_wp_error();
		}
	}

	public function delete( ?int $id = null ): bool {
		if ( null === $id ) {
			return false;
		}

		try {
			$price = new \StoreEngine\Classes\Price( $id );

			return $price->delete();
		} catch ( StoreEngineException $e ) {
			return false;
		}
	}

	/**
	 * @param int $id
	 *
	 * @return ?object
	 * @deprecated
	 */
	public function get( int $id ): ?object {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_product_price WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $row ) {
			self::parsePrice( $row );
		}

		return $row;
	}

	public function get_prices() {
		global $wpdb;

		$results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}storeengine_product_price" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( $results as &$result ) {
			self::parsePrice( $result );
		}

		return $results;
	}

	public function get_prices_by_product_id( int $product_id ) {
		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_product_price WHERE product_id = %d", $product_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $results ) {
			return [];
		}

		foreach ( $results as &$result ) {
			self::parsePrice( $result );
		}

		return $results;
	}

	protected static function parsePrice( object &$price ) {
		$price->id            = (int) $price->id;
		$price->price         = (float) $price->price;
		$price->compare_price = $price->compare_price ? (float) $price->compare_price : null;
		$price->product_id    = (int) $price->product_id;
		$price->order         = (int) $price->order;

		if ( $price->settings ) {
			$price->settings = maybe_unserialize( $price->settings );
			if ( ! is_array( $price->settings ) ) {
				$price->settings = json_decode( $price->settings, true );
			}

			if ( ! is_array( $price->settings ) ) {
				$price->settings = [];
			}
		}

		$price->settings = self::prepare_settings( $price->settings );
	}

	protected static function get_default_settings(): array {
		return [
			'setup_fee'             => false,
			'setup_fee_name'        => '',
			'setup_fee_price'       => 0.0,
			'setup_fee_type'        => 'fixed',
			'trial'                 => false,
			'trial_days'            => 0,
			'expire'                => false,
			'expire_days'           => 0,
			'payment_duration'      => 1,
			'payment_duration_type' => 'monthly',
			'upgradeable'           => false,
		];
	}

	protected static function prepare_settings( array $settings ): array {
		$settings = wp_parse_args( $settings, self::get_default_settings() );

		return [
			'setup_fee'             => (bool) ( $settings['setup_fee'] ?: false ),
			'setup_fee_name'        => (string) ( $settings['setup_fee_name'] ?: '' ),
			'setup_fee_price'       => (float) ( $settings['setup_fee_price'] ?: 0 ),
			'setup_fee_type'        => (string) ( $settings['setup_fee_type'] ?: 'fixed' ),
			'trial'                 => (bool) ( $settings['trial'] ?: false ),
			'trial_days'            => absint( $settings['trial_days'] ?: 0 ),
			'expire'                => (bool) ( $settings['expire'] ?: false ),
			'expire_days'           => absint( $settings['expire_days'] ?: 0 ),
			'payment_duration'      => absint( $settings['payment_duration'] ?: 1 ),
			'payment_duration_type' => (string) ( $settings['payment_duration_type'] ?: 'monthly' ),
			'upgradeable'           => (bool) ( $settings['upgradeable'] ?: false ),
		];
	}
}
