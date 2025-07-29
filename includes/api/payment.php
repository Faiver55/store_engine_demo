<?php

namespace StoreEngine\API;

use Exception;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\OrderCollection;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;

class Payment extends AbstractRestApiController {
	public static function init() {
		$self            = new self();
		$self->namespace = STOREENGINE_PLUGIN_SLUG . '/v1';
		$self->rest_base = 'payment';
		add_action( 'rest_api_init', array( $self, 'register_routes' ) );
	}


	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_payment' ],
				'permission_callback' => [ $this, 'check_payment_permissions' ],
				'args'                => $this->get_item_schema(),
			],
		] );
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_payment' ],
				'permission_callback' => [ $this, 'check_payment_permissions' ],
				'args'                => $this->get_item_schema(),
			],
		] );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/all', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_payments' ],
				'permission_callback' => [ $this, 'check_admin_permissions' ],
				'args'                => $this->get_collection_params(),
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
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
			'schema' => [ $this, 'get_item_schema' ],
		] );

		// @TODO implement update method.
		// @TODO implement delete method.
	}

	public function check_admin_permissions() {
		return Helper::check_rest_user_cap( 'manage_options' );
	}

	public function check_payment_permissions( WP_REST_Request $request ): bool {
		$order_id = $request->get_param( 'order_id' );
		if ( $order_id ) {
			$order = Helper::get_order( $order_id );

			return $order instanceof Order;
		}

		return false;
	}

	public function permissions_check() {
		return Helper::check_rest_user_cap( 'manage_options' );
	}

	public function create_payment() {
		return new WP_Error(
			'endpoint_is_deprecated',
			__( 'Please use checkout workflow. this endpoint is deprecated.', 'storeengine' ),
			[ 'status' => 400 ]
		);
	}

	public function get_payment( $request ) {
		$id = 0;
		foreach ( [ 'id', 'order', 'order_id', 'payment', 'payment_id' ] as $key ) {
			if ( $request->has_param( $key ) && $request->get_param( $key ) ) {
				$id = absint( $request->get_param( $key ) );
				break;
			}
		}

		if ( ! $id ) {
			return rest_ensure_response( new WP_Error( 'invalid-request', __( 'Invalid request', 'storeengine' ), [ 'status' => 400 ] ) );
		}

		try {
			return rest_ensure_response( $this->prepare_item_for_response( Helper::get_order( $id ), $request ) );
		} catch ( StoreEngineException $exception ) {
			return rest_ensure_response( $exception->toWpError() );
		} catch ( Exception $exception ) {
			return rest_ensure_response( new WP_Error( 'error', $exception->getMessage(), [ 'status' => 500 ] ) );
		}
	}

	public function collect_payment() {
		return rest_ensure_response( new WP_Error( 'not-implemented', __( 'Not implemented.', 'storeengine' ), [ 'status' => 501 ] ) );
	}

	public function get_payments( $request ) {
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$status   = $request->get_param( 'status' );
		$search   = $request->get_param( 'search' );
		$customer = $request->get_param( 'customer' );
		$where    = [
			[
				'key'     => 'total_amount',
				'value'   => 0,
				'compare' => '>',
				'type'    => 'NUMERIC',
			],
		];

		if ( $status && ! in_array( $status, [ 'all', 'any', 'draft', 'auto-draft' ], true ) ) {
			$where[] = [
				'key'   => 'status',
				'value' => $status,
			];
		} else {
			$where[] = [
				'key'     => 'status',
				'value'   => [ 'draft', 'auto-draft' ],
				'compare' => 'NOT IN',
			];
		}

		if ( $customer ) {
			$where[] = [
				'key'     => 'customer_id',
				'value'   => $customer,
				'compare' => '=',
				'type'    => 'NUMERIC',
			];
			if ( $search ) {
				$where[] = [
					'key'     => 'payment_method_title',
					'value'   => "%$search%",
					'compare' => 'LIKE',
				];
			}
		} else {
			if ( $search ) {
				$where[] = [
					'relation' => 'OR',
					[
						'key'     => 'billing_email',
						'value'   => "%$search%",
						'compare' => 'LIKE',
					],
					[
						'key'     => 'payment_method_title',
						'value'   => "%$search%",
						'compare' => 'LIKE',
					],
				];
			}
		}

		$query = new OrderCollection( [
			'per_page' => absint( $request->get_param( 'per_page' ) ),
			'page'     => $page,
			'where'    => $where,
		], 'order' );


		$payments = [];
		foreach ( $query->get_results() as $order ) {
			$response = rest_ensure_response( $this->prepare_item_for_response( $order, $request ) );
			$response->add_links( $this->prepare_links( $order, $request ) );
			$payments[] = $this->prepare_response_for_collection( $response );
		}

		return $this->prepare_query_response( $payments, $query, $request );
	}

	public function get_item( $request ) {
		try {
			$order    = Helper::get_order( $request['id'] );
			$response = $this->prepare_item_for_response( $order, $request );

			return rest_ensure_response( $response );
		} catch ( StoreEngineException $exception ) {
			return rest_ensure_response( $exception->toWpError() );
		} catch ( Exception $exception ) {
			return rest_ensure_response( new WP_Error( 'error', $exception->getMessage(), [ 'status' => 500 ] ) );
		}
	}

	/**
	 * @param $order Order object.
	 * @param WP_REST_Request $request Requests.
	 *
	 * @return array
	 */
	public function prepare_item_for_response( $order, $request ): array {
		return Helper::get_payment_data( $order );
	}
}
