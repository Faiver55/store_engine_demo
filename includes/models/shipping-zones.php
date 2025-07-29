<?php

namespace StoreEngine\models;

use StoreEngine\Classes\AbstractModel;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ShippingZones extends AbstractModel {
	protected string $table = 'storeengine_shipping_zones';

	public function all() {
		global $wpdb;

		return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}storeengine_shipping_zones" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}


	public function get( int $id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_shipping_zones WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public function save( array $args = [] ) {
		global $wpdb;

		$args = $this->validate_args( $args );

		if ( is_wp_error( $args ) ) {
			return $args;
		}

		$wpdb->insert( $this->prefix . 'storeengine_shipping_zones', $args ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return $this->get( $wpdb->insert_id );
	}

	protected function validate_args( array $args = [] ) {
		$args = wp_parse_args( $args, [
			'zone_name' => '',
			'region'    => '',
		] );

		if ( empty( $args['zone_name'] ) ) {
			return new WP_Error( 'missing-zone-name', __( 'Zone name is required.', 'storeengine' ) );
		}

		if ( empty( $args['region'] ) ) {
			return new WP_Error( 'missing-region', __( 'Region is required.', 'storeengine' ) );
		}

		return $args;
	}

	public function update( int $id, array $args ) {
		global $wpdb;

		$args = $this->validate_args( $args );

		if ( is_wp_error( $args ) ) {
			return $args;
		}

		if ( $wpdb->update( $this->prefix . 'storeengine_shipping_zones', $args, [ 'id' => $id ] ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $this->get( $id );
		}

		return new WP_Error( 'db-update-failed', __( 'Unable to update shipping zones.', 'storeengine' ) );
	}

	public function delete( ?int $id = null ) {
		global $wpdb;

		$item_to_delete = $this->get( $id );

		if ( ! $wpdb->delete( $this->prefix . 'storeengine_shipping_zones', [ 'id' => $id ] ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return new WP_Error( 'db-delete-failed', __( 'Unable to delete shipping zones.', 'storeengine' ) );
		}

		return $item_to_delete;
	}
}
