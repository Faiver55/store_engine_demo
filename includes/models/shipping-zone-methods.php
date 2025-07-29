<?php

namespace StoreEngine\models;

use StoreEngine\Classes\AbstractModel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ShippingZoneMethods extends AbstractModel {
	protected string $table = 'storeengine_shipping_zones';


	public function all() {
		global $wpdb;

		return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}storeengine_shipping_zones" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public function get( int $id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_shipping_zone_methods WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public function save( array $args = [] ) {
		$defaults = [
			'name'        => '',
			'zone_id'     => 0,
			'cost'        => 0,
			'is_enabled'  => 0,
			'type'        => '',
			'tax'         => 0,
			'description' => '',
		];

		$args = wp_parse_args( $args, $defaults );

		global $wpdb;

		$wpdb->insert( $this->prefix . 'storeengine_shipping_zone_methods', $args ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return $this->get( $wpdb->insert_id );
	}

	public function update( int $id, array $args ) {
		$defaults = [
			'name'        => '',
			'zone_id'     => 0,
			'cost'        => 0,
			'is_enabled'  => 0,
			'type'        => '',
			'tax'         => 0,
			'description' => '',
		];

		$args = wp_parse_args( $args, $defaults );

		global $wpdb;

		$wpdb->update( $this->prefix . 'storeengine_shipping_zone_methods', $args, [ 'id' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $this->get( $id );
	}

	public function delete( ?int $id = null ) {
		global $wpdb;

		$item_to_delete = $this->get( $id );

		$result = $wpdb->delete( $this->prefix . 'storeengine_shipping_zone_methods', [ 'id' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $result ) {
			return new \WP_Error( 'db-delete-failed', __( 'Unable to delete shipping zone method.', 'storeengine' ) );
		}

		return $item_to_delete;
	}

	public function get_by_zone_id( $zone_id ) {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}storeengine_shipping_zone_methods WHERE zone_id = %d", $zone_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
