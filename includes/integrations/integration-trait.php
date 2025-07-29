<?php

namespace StoreEngine\Integrations;

use StoreEngine\Classes\Integration;
use StoreEngine\Classes\Price;
use StoreEngine\Classes\Product\SimpleProduct;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if access directly
}

trait IntegrationTrait {
	private string $integration_name;
	private int $item_id;
	private string $item_title;
	private array $prices;

	abstract protected function set_integration_config(): void;

	public function init_integration() {
		$this->set_integration_config();
	}

	public function handle_price_validation() {
		$prices = [];
		foreach ( $this->prices as $price_arr ) {
			$type       = isset( $price_arr['type'] ) ? sanitize_text_field( $price_arr['type'] ) : 'custom';
			$price_id   = isset( $price_arr['price_id'] ) ? (int) sanitize_text_field( $price_arr['price_id'] ) : 0;
			$order      = isset( $price_arr['order'] ) ? (int) sanitize_text_field( $price_arr['order'] ) : 0;
			$product_id = isset( $price_arr['product_id'] ) ? absint( $price_arr['product_id'] ) : 0;

			if ( 'custom' === $type ) {
				if ( ! isset( $price_arr['price_type'] ) ) {
					wp_send_json_error( [ 'message' => __( 'price_type is required', 'storeengine' ) ] );
				}

				if ( ! isset( $price_arr['price'] ) ) {
					wp_send_json_error( [ 'message' => __( 'price is required', 'storeengine' ) ] );
				}

				if ( ! isset( $price_arr['price_name'] ) ) {
					$price_arr['price_name'] = sanitize_text_field( $price_arr['price_type'] );
				}
			} else {
				if ( 0 === $price_id ) {
					wp_send_json_error( [ 'message' => __( 'price_id is required', 'storeengine' ) ] );
				}
				if ( 0 === $product_id ) {
					wp_send_json_error( [ 'message' => __( 'product_id is required', 'storeengine' ) ] );
				}

				$prices[] = [
					'product_id'    => $product_id,
					'type'          => $type,
					'price_id'      => $price_id,
					'order'         => $order,
					'price_name'    => null,
					'price_type'    => null,
					'price'         => 0,
					'compare_price' => null,
				];
				continue;
			}

			$price_name    = sanitize_text_field( $price_arr['price_name'] );
			$price_type    = sanitize_text_field( $price_arr['price_type'] );
			$price         = (float) sanitize_text_field( $price_arr['price'] );
			$compare_price = isset( $price_arr['compare_price'] ) ? (float) sanitize_text_field( $price_arr['compare_price'] ) : null;
			if ( $compare_price && $compare_price <= $price ) {
				wp_send_json_error( [ 'message' => __( 'Sale price cannot be greater than or equals to compare price', 'storeengine' ) ] );
			}

			if ( 'subscription' === $price_type ) {
				if ( ! isset( $price_arr['payment_duration'] ) ) {
					wp_send_json_error( [
						'message' => __( 'Duration is required', 'storeengine' ),
					] );
				}

				if ( ! isset( $price_arr['payment_duration_type'] ) ) {
					wp_send_json_error( [
						'message' => __( 'Duration type is required', 'storeengine' ),
					] );
				}
			}

			$price_data = [
				'product_id'    => $product_id,
				'type'          => $type,
				'price_id'      => $price_id,
				'price_name'    => $price_name,
				'price_type'    => $price_type,
				'price'         => $price,
				'compare_price' => $compare_price,
				'order'         => $order,
			];

			if ( 'subscription' === $price_type ) {
				$price_data['payment_duration']      = absint( $price_arr['payment_duration'] );
				$price_data['payment_duration_type'] = sanitize_text_field( $price_arr['payment_duration_type'] );
			}

			$prices[] = $price_data;
		}//end foreach

		$this->prices = $prices;
	}

	public function create_product(): SimpleProduct {
		$product = new SimpleProduct();
		$product->set_name( $this->item_title );
		$product->set_author_id( get_current_user_id() );
		$product->set_shipping_type( 'digital' );
		$product->set_digital_auto_complete( true );
		$product->save();

		if ( ! $product->get_id() ) {
			wp_send_json_error( [ 'message' => __( 'Could not create product!', 'storeengine' ) ] );
		}

		return $product;
	}

	public function create_prices_and_integration() {
		foreach ( $this->prices as &$price_data ) {
			$product_id = $price_data['product_id'];
			if ( ! $product_id ) {
				$product_id = $this->create_product()->get_id();
			}

			$price = new Price( $price_data['price_id'] );
			if ( 'predefined' === $price_data['type'] ) {
				$price->add_integration( $this->integration_name, $this->item_id );
				continue;
			}

			$price->set_name( $price_data['price_name'] );
			$price->set_product_id( $product_id );
			$price->set_type( $price_data['price_type'] );
			if ( $price_data['compare_price'] ) {
				$price->set_price( $price_data['price'] );
				$price->set_compare_price( $price_data['compare_price'] );
			} else {
				$price->set_price( $price_data['price'] );
				$price->set_compare_price( null );
			}
			$price->set_menu_order( $price_data['order'] );
			if ( 'subscription' === $price_data['price_type'] ) {
				$price->set_payment_duration( $price_data['payment_duration'] );
				$price->set_payment_duration_type( $price_data['payment_duration_type'] );
			}
			$price->save();
			if ( 0 === $price_data['price_id'] ) {
				$price_data['price_id'] = $price->get_id();
				$price->add_integration( $this->integration_name, $this->item_id );
			}
		}
	}

	public function update_missing_pricing_ids() {
		$price_ids = array_map( function ( $price ) {
			return $price['price_id'];
		}, $this->prices );

		global $wpdb;
		$integrations = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}storeengine_integrations
					WHERE provider = %s AND integration_id = %d", $this->integration_name, $this->item_id ) );

		$product_price_ids = array_map( fn( $integration ) => (int) $integration->price_id, $integrations );

		$missing_price_ids = array_diff( $product_price_ids, $price_ids );
		foreach ( $missing_price_ids as $price_id ) {
			$integration = array_find( $integrations, function ( $integration ) use ( $price_id ) {
				return (int) $integration->price_id === $price_id;
			} );
			( new Integration( (int) $integration->id ) )->delete();
		}
	}

	public function get_integrations() {
		$integrations = Helper::get_integration_repository_by_id( $this->integration_name, $this->item_id );
		if ( empty( $integrations ) ) {
			wp_send_json_success( [
				'prices' => [],
			] );
		}

		$prices = [];
		foreach ( $integrations as $integration ) {
			$price    = $this->format_price( $integration->integration->get_id(), $integration->price );
			$prices[] = $price;
		}

		wp_send_json_success( [
			'prices' => $prices,
		] );
	}

	private function format_price( int $id, Price $price ): array {
		$data = [
			'id'            => $id,
			'product_id'    => $price->get_product_id(),
			'product_name'  => get_the_title( $price->get_product_id() ),
			'price_id'      => $price->get_id(),
			'price_name'    => $price->get_name(),
			'price_type'    => $price->get_type(),
			'price'         => $price->get_price(),
			'compare_price' => $price->get_compare_price(),
			'order'         => $price->get_menu_order(),
		];

		if ( 'subscription' === $price->get_type() ) {
			$data['payment_duration']      = $price->get_payment_duration();
			$data['payment_duration_type'] = $price->get_payment_duration_type();
		}

		return $data;
	}

	public function handle_integrations() {
		$this->handle_price_validation();
		$this->create_prices_and_integration();
		$this->update_missing_pricing_ids();
	}
}
