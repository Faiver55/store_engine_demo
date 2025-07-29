<?php

namespace StoreEngine\API;

use StoreEngine\API\Schema\CartSchema;
use StoreEngine\Models\Cart as CartModel;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Server;

// no aliasing required

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cart extends WP_REST_Controller {
	use CartSchema;

	public static function init() {
		$self            = new self();
		$self->namespace = STOREENGINE_PLUGIN_SLUG . '/v1';
		$self->rest_base = 'cart';

		add_action( 'rest_api_init', array( $self, 'register_routes' ) );
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'context' => $this->get_context_param( [ 'default' => 'view' ] ),
				],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => $this->get_item_schema(),
			],
			'schema' => [ $this, 'get_public_item_schema' ],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<hash>[\S]+)', [
			'args'   => [
				'id' => [
					'description' => __( 'Unique identifier for the object.', 'storeengine' ),
					'type'        => 'integer',
				],
			],
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'context' => $this->get_context_param( [ 'default' => 'view' ] ),
				],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => $this->get_item_schema(),
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'context' => $this->get_context_param( [ 'default' => 'view' ] ),
				],
			],
			'schema' => [ $this, 'get_cart_schema' ],
		] );
	}

	public function permissions_check(): bool {
		return Helper::check_rest_user_cap( 'manage_options' );
	}

	public function create_item( $request ) {
		$price_id = $request->get_param( 'price_id' );
		$quantity = $request->get_param( 'quantity' );

		if ( ! $price_id || ! $quantity ) {
			return rest_ensure_response( new WP_Error( 'missing-required-params', __( 'Missing required fields.', 'storeengine' ), [ 'status' => 400 ] ) );
		}

		return rest_ensure_response( Helper::cart()->add_product_to_cart( $price_id, $quantity ) );
	}

	public function get_items( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		return rest_ensure_response( Helper::cart()->get_cart_items() );
	}

	public function get_item( $request ) {
		$hash = $request->get_param( 'hash' );

		if ( ! $hash ) {
			return new WP_Error( 'invalid_cart_hash', __( 'Invalid Cart Hash.', 'storeengine' ), [ 'status' => 400 ] );
		}

		return rest_ensure_response( Helper::cart() );
	}

	public function update_item( $request ): WP_REST_Response {
		$hash       = $request->get_param( 'hash' );
		$product_id = $request->get_param( 'product_id' );
		$quantity   = $request->get_param( 'quantity' );

		if ( ! $hash || ! $product_id || ! $quantity ) {
			return new WP_REST_Response( [ 'error' => 'Invalid request' ], 400 );
		}

		$cart   = new CartModel();
		$result = $cart->update_product_in_cart( $product_id, $quantity )->save();
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 400 );
		}

		return new WP_REST_Response( $cart, 200 );
	}

	public function delete_item( $request ): WP_REST_Response {
		$hash       = $request->get_param( 'hash' );
		$product_id = $request->get_param( 'product_id' );

		if ( ! $hash || ! $product_id ) {
			return new WP_REST_Response( [ 'error' => 'Invalid request' ], 400 );
		}

		$cart = new CartModel( $hash );
		$cart = $cart->remove_product_from_cart( $product_id )->save();

		return new WP_REST_Response( $cart, 200 );
	}


}
