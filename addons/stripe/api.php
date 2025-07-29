<?php

namespace StoreEngine\Addons\Stripe;

use StoreEngine\API\AbstractRestApiController;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Payment\Gateways\PaymentGateway;
use StoreEngine\Stripe\Exception\ApiErrorException;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit(); // Exit if accessed directly.
}

class Api extends AbstractRestApiController {
	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'stripe';

	public static function init() {
		$self = new self();
		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/save-payment-method', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => $this->get_collection_params(),
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
			],
			'schema' => [ $this, 'get_public_item_schema' ],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
			'args'   => [
				'id' => [
					'description' => __( 'Unique identifier for the resource.', 'storeengine' ),
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
			'schema' => [ $this, 'get_public_item_schema' ],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/create-setup-intent', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'create_setup_intent' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [],
			],
			'schema' => [ $this, 'get_public_item_schema' ],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/create-payment-intent', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_payment_intent' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'order_id' => [
						'description'       => __( 'Unique identifier for the order.', 'storeengine' ),
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			],
			'schema' => [ $this, 'get_public_item_schema' ],
		] );
	}

	public function permissions_check(): bool {
		return is_user_logged_in();
	}

	/**
	 * Retrieves a collection of items.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
	}

	public function create_item( $request ) {
	}

	public function get_item( $request ) {
	}

	/**
	 * Retrieves a collection of items.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 * @throws StoreEngineException
	 */
	public function create_setup_intent() {
		try {
			$customer = Helper::get_customer( get_current_user_id() );

			if ( ! $customer || ! $customer->get_id() ) {
				return new WP_Error( 'customer_not_found', __( 'Customer not found.', 'storeengine' ), [ 'status' => 404 ] );
			}

			$intent = get_transient( 'stripe_setup_intent_' . $customer->get_id() );

			if ( ! $intent ) {
				$customer_id = StripeService::init()->get_customer( $customer );

				if ( is_wp_error( $customer_id ) ) {
					return $customer_id;
				}

				$intent = StripeService::init()->create_setup_intent( [
					'customer' => $customer_id,
					'usage'    => 'off_session',
				] );
			}

			return rest_ensure_response( [ 'client_secret' => $intent ] );
		} catch ( ApiErrorException $e ) {
			return new WP_Error( 'stripe_api_error', $e->getMessage() );
		}
	}

	/**
	 * Create & return payment intent for checkout.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_payment_intent( WP_REST_Request $request ) {
		try {
			$order = Helper::get_order( $request->get_param( 'order_id' ) );

			if ( is_wp_error( $order ) ) {
				return $order;
			}

			if ( ! $order->get_id() ) {
				return new WP_Error( 'order_not_found', __( 'Order not found', 'storeengine' ), [ 'status' => 404 ] );
			}

			$stripe_intent_id   = $order->get_meta( '_stripe_intent_id', true, 'edit' );
			$stripe_customer_id = $order->get_customer_id() ? get_user_option( '_stripe_customer_id', $order->get_customer_id() ) : null;

			if ( ! $stripe_intent_id ) {
				$intent = StripeService::init()->create_payment_intent( $order, $stripe_customer_id );

				$order->add_meta_data( '_stripe_intent_id', $intent->id, true );
				$order->add_meta_data( '_stripe_currency', $intent->currency, true );
				$order->save();
			} else {
				$intent = StripeService::init()->get_payment_intent( $stripe_intent_id );
			}

			return rest_ensure_response( [
				'intent_id'     => $intent->id,
				'client_secret' => $intent->client_secret,
				'customer_id'   => $stripe_customer_id,
			] );
		} catch ( StoreEngineException $e ) {
			return $e->toWpError();
		}
	}
}

// End of file api.php.
