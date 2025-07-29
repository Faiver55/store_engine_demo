<?php

namespace StoreEngine\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\models\ShippingZoneMethods;
use StoreEngine\models\ShippingZones;
use StoreEngine\Utils\Constants;

class Shipping extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [
			'create_shipping_zone'   => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'create_shipping_zone' ],
				'fields'     => [
					'zone_name' => 'string',
					'region'    => 'string',
				],
			],
			'get_shipping_zones'     => [
				'callback'   => [ $this, 'get_shipping_zones' ],
				'capability' => 'manage_options',
			],
			'update_shipping_zone'   => [
				'callback'   => [ $this, 'update_shipping_zone' ],
				'capability' => 'manage_options',
				'fields'     => [
					'id'        => 'absint',
					'zone_name' => 'string',
					'region'    => 'string',
				],
			],
			'delete_shipping_zone'   => [
				'callback'   => [ $this, 'delete_shipping_zone' ],
				'capability' => 'manage_options',
				'fields'     => [
					'id' => 'absint',
				],
			],

			'create_shipping_method' => [
				'callback'   => [ $this, 'create_shipping_method' ],
				'capability' => 'manage_options',
				'fields'     => [
					'name'        => 'string',
					'zone_id'     => 'absint',
					'cost'        => 'float',
					'is_enabled'  => 'boolean',
					'type'        => 'string',
					'tax'         => 'float',
					'description' => 'string',
				],
			],
			'get_shipping_methods'   => [
				'callback'   => [ $this, 'get_shipping_methods' ],
				'capability' => 'manage_options',
				'fields'     => [
					'zone_id' => 'absint',
				],
			],
			'update_shipping_method' => [
				'callback'   => [ $this, 'update_shipping_method' ],
				'capability' => 'manage_options',
				'fields'     => [
					'id'          => 'absint',
					'name'        => 'string',
					'zone_id'     => 'absint',
					'cost'        => 'float',
					'is_enabled'  => 'boolean',
					'type'        => 'string',
					'tax'         => 'float',
					'description' => 'string',
				],
			],
			'delete_shipping_method' => [
				'callback'   => [ $this, 'delete_shipping_method' ],
				'capability' => 'manage_options',
				'fields'     => [
					'id' => 'absint',
				],
			],
			'update_shipping_status' => [
				'callback'   => [ $this, 'update_shipping_status' ],
				'capability' => 'manage_options',
				'fields'     => [
					'order_id'        => 'absint',
					'product_id'      => 'absint',
					'shipping_status' => 'string',
				],
			],
		];
	}

	public function update_shipping_status( $payload ) {
		$shipping_statuses = [
			Constants::READY_FOR_SHIP,
			Constants::AWAITING_SHIPMENT,
			Constants::SHIPPED,
			Constants::ON_THE_WAY,
			Constants::OUT_FOR_DELIVERY,
			Constants::DELIVERED,
			Constants::RETURNED,
		];

		if ( ! in_array( $payload['shipping_status'], $shipping_statuses, true ) ) {
			wp_send_json_error( esc_html__( 'Incorrect shipping status.', 'storeengine' ) );
		}

		$product_type = get_post_meta( $payload['product_id'], '_storeengine_product_shipping_type', true );

		if ( 'digital' === $product_type ) {
			wp_send_json_error( esc_html__( 'Shipping is not applicable for digital products.', 'storeengine' ) );
		}

		$order          = new \StoreEngine\models\Order( $payload['order_id'] );
		$lookup_product = $order->get_order_meta_and_lookup_details( [
			'order_id'   => $payload['order_id'],
			'product_id' => $payload['product_id'],
		] );

		if ( ! $lookup_product ) {
			wp_send_json_error( esc_html__( 'No Product Is Found.', 'storeengine' ) );
		}

		$current_status = array_search( $lookup_product['shipping_status'], $shipping_statuses, true );
		$next_status    = array_search( $payload['shipping_status'], $shipping_statuses, true );

		if ( false !== $current_status && false !== $next_status && $next_status < $current_status ) {
			wp_send_json_error( esc_html__( 'You can not go the previous status', 'storeengine' ) );
		}

		$update = $order->update_shipping_status( $payload['order_id'], $payload['product_id'], $payload['shipping_status'] );

		do_action( 'storeengine/before_single_product_delivered', $payload['product_id'], $payload['order_id'], $shipping_statuses[ $current_status ], $payload['shipping_status'] );
		$updated_order = new \StoreEngine\models\Order( $payload['order_id'] );
		if ( $update ) {
			$is_all_delivered = true;
			foreach ( $updated_order->data['purchase_items'] as $item ) {
				if ( Constants::DELIVERED !== $item->shipping_status ) {
					$is_all_delivered = false;
					break;
				}
			}

			if ( $is_all_delivered ) {
				do_action( 'storeengine/all_product_delivered', $updated_order->data );
			}

			do_action( 'storeengine/after_single_product_delivered', $payload['product_id'], $payload['order_id'], $shipping_statuses[ $current_status ], $payload['shipping_status'] );
			wp_send_json_success( esc_html__( 'Shipping Status Updated Successfully.', 'storeengine' ) );
		}

		wp_send_json_error( esc_html__( 'There is an error while updating the order', 'storeengine' ) );
	}

	public function create_shipping_zone( $payload ) {
		if ( ! empty( $payload['zone_name'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone name is required.', 'storeengine' ) );
		}

		if ( ! empty( $payload['region'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone region is required.', 'storeengine' ) );
		}

		$result = ( new ShippingZones() )->save( [
			'zone_name' => $payload['zone_name'],
			'region'    => $payload['region'],
		] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	public function get_shipping_zones() {
		$zone = new ShippingZones();

		wp_send_json_success( $zone->all() );
	}


	public function update_shipping_zone( $payload ) {
		if ( ! empty( $payload['id'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone ID is required.', 'storeengine' ) );
		}

		if ( ! empty( $payload['zone_name'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone name is required.', 'storeengine' ) );
		}

		if ( ! empty( $payload['region'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone region is required.', 'storeengine' ) );
		}

		$result = ( new ShippingZones() )->update( absint( $payload['id'] ), [
			'zone_name' => $payload['zone_name'],
			'region'    => $payload['region'],
		] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	public function delete_shipping_zone( $payload ) {
		if ( empty( $payload['id'] ) ) {
			wp_send_json_error( esc_html__( 'Missing ID Parameter', 'storeengine' ) );
		}

		$result = ( new ShippingZones() )->delete( $payload['id'] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	public function create_shipping_method( $payload ) {
		if ( ! empty( $payload['name'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone name is required.', 'storeengine' ) );
		}

		if ( ! empty( $payload['zone_id'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone id required.', 'storeengine' ) );
		}

		if ( ! empty( $payload['type'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone type required.', 'storeengine' ) );
		}

		$result = ( new ShippingZoneMethods() )->save( [
			'name'        => $payload['name'],
			'zone_id'     => $payload['zone_id'],
			'cost'        => $payload['cost'] ?? 0,
			'is_enabled'  => $payload['is_enabled'] ?? false,
			'type'        => $payload['type'],
			'tax'         => $payload['tax'] ?? 0,
			'description' => $payload['description'] ?? '',
		] );

		if ( $result ) {
			wp_send_json_success( $result );
		}
	}

	public function get_shipping_methods( $payload ) {
		if ( empty( $payload['zone_id'] ) ) {
			wp_send_json_error( esc_html__( 'Missing zone ID Parameter', 'storeengine' ) );
		}

		wp_send_json_success( ( new ShippingZoneMethods() )->get_by_zone_id( $payload['zone_id'] ) );
	}

	public function update_shipping_method( $payload ) {
		if ( ! empty( $payload['name'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone name is required.', 'storeengine' ) );
		}

		if ( ! empty( $payload['zone_id'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone id required.', 'storeengine' ) );
		}

		if ( ! empty( $payload['type'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone type required.', 'storeengine' ) );
		}

		$result = ( new ShippingZoneMethods() )->update( $payload['id'], [
			'name'        => $payload['name'],
			'zone_id'     => $payload['zone_id'],
			'cost'        => $payload['cost'] ?? 0,
			'is_enabled'  => $payload['is_enabled'] ?? false,
			'type'        => $payload['type'],
			'tax'         => $payload['tax'] ?? 0,
			'description' => $payload['description'] ?? '',
		] );

		wp_send_json_success( $result );
	}

	public function delete_shipping_method( $payload ) {
		if ( empty( $payload['id'] ) ) {
			wp_send_json_error( esc_html__( 'Missing ID Parameter', 'storeengine' ) );
		}

		$result = ( new ShippingZoneMethods() )->delete( $payload['id'] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}
}
