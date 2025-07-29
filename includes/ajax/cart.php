<?php

namespace StoreEngine\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\Countries;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\TaxUtil;

class Cart extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [
			'add_to_cart'               => [
				'callback'             => [ $this, 'add_to_cart' ],
				'allow_visitor_action' => true,
				'fields'               => [
					'product_id'   => 'integer',
					'quantity'     => 'integer',
					'price_id'     => 'integer',
					'variation_id' => 'integer',
				],
			],
			'remove_cart_item'          => [
				'callback'             => [ $this, 'remove_cart_item' ],
				'allow_visitor_action' => true,
				'fields'               => [ 'item_key' => 'string' ],
			],
			'update_cart_item_quantity' => [
				'callback'             => [ $this, 'update_cart_item_quantity' ],
				'allow_visitor_action' => true,
				'fields'               => [
					'item_key' => 'string',
					'quantity' => 'integer',
				],
			],
			'clear_cart'                => [
				'callback'             => [ $this, 'clear_cart' ],
				'allow_visitor_action' => true,
			],
		];
	}

	protected function add_to_cart( $payload ) {
		if ( empty( $payload['product_id'] ) ) {
			wp_send_json_error( esc_html__( 'Sorry, Product ID is missing', 'storeengine' ) );
		}

		if ( empty( $payload['price_id'] ) ) {
			wp_send_json_error( esc_html__( 'Sorry, Price ID is missing', 'storeengine' ) );
		}

		$quantity = $payload['quantity'] ?? 1;

		if ( (int) $quantity < 1 ) {
			wp_send_json_error( esc_html__( 'Sorry, Quantity must be greater than 0', 'storeengine' ) );
		}

		$product_type = get_post_meta( $payload['product_id'], '_storeengine_product_type', true );
		$variation_id = $payload['variation_id'] ?? 0;
		if ( 'variable' === $product_type && $variation_id <= 0 ) {
			wp_send_json_error( esc_html__( 'Sorry, Variation ID is missing', 'storeengine' ) );
		}

		$variation_data = [];
		if ( $variation_id > 0 ) {
			$variation = Helper::get_product_variation( $variation_id );
			if ( ! $variation ) {
				wp_send_json_error( esc_html__( 'Sorry, Variation not found', 'storeengine' ) );
			}
			foreach ( $variation->get_attributes() as $attributeData ) {
				$variation_data[ $attributeData->taxonomy ] = $attributeData->slug;
			}
		}

		$result = Helper::cart()->add_product_to_cart( $payload['price_id'], $quantity, $variation_id, $variation_data );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		if ( Helper::cart()->has_subscription_product() ) {
			wp_send_json_success( [ 'redirect' => esc_url( apply_filters( 'storeengine/checkout/get_checkout_url', Helper::get_page_permalink( 'checkout_page' ) ) ) ] );
		}

		wp_send_json_success( Helper::cart()->get_cart_items() );
	}


	protected function remove_cart_item( $payload ) {
		if ( empty( $payload['item_key'] ) ) {
			wp_send_json_error( esc_html__( 'Invalid request.', 'storeengine' ) );
		}

		$item = Helper::cart()->get_cart_item( $payload['item_key'] );
		if ( ! $item ) {
			wp_send_json_error( esc_html__( 'Car item not found.', 'storeengine' ), 404 );
		}

		$product = Helper::get_product( $item->product_id );

		/* translators: %s: Item name. */
		$item_removed_title = apply_filters( 'storeengine/cart/item_removed_title', $product ? sprintf( _x( '&ldquo;%s&rdquo;', 'Item name in quotes', 'storeengine' ), $product->get_name() ) : __( 'Item', 'storeengine' ), $item );
		/* Translators: %s Product title. */
		$removed_notice = sprintf( __( '%s removed from your cart.', 'storeengine' ), $item_removed_title );

		if ( Helper::cart()->remove_cart_item( $payload['item_key'] ) ) {
			wp_send_json_success( $removed_notice );
		}
		wp_send_json_error( esc_html__( 'Sorry, failed to remove cart', 'storeengine' ) );
	}

	protected function update_cart_item_quantity( $payload ) {
		if ( empty( $payload['item_key'] ) ) {
			wp_send_json_error( esc_html__( 'Invalid request.', 'storeengine' ) );
		}

		if ( ! array_key_exists( 'quantity', $payload ) ) {
			wp_send_json_error( esc_html__( 'Quantity missing.', 'storeengine' ) );
		}

		$result = Helper::cart()->update_quantity( $payload['item_key'], $payload['quantity'] ?? 1 );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'Cart updated.', 'storeengine' ) );
	}

	public function clear_cart() {
		$cart = new CartModel();
		$cart->clean_cart()->save();
		wp_send_json_success( $cart->cart_data );
	}

}
