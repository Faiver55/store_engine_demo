<?php

namespace StoreEngine\API;

use StoreEngine\Classes\Customer as CustomerEntity;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidArgumentException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusException;
use StoreEngine\Classes\OrderCollection;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_REST_Users_Controller;
use WP_User_Query;

/**
 * Customer API
 *
 * @see WP_REST_Users_Controller
 */
class Customer extends WP_REST_Users_Controller {
	public static function init() {
		$self            = new self();
		$self->namespace = STOREENGINE_PLUGIN_SLUG . '/v1';
		$self->rest_base = 'customer';

		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
	}

	public function __construct() {
		parent::__construct();
		$this->namespace = STOREENGINE_PLUGIN_SLUG . '/v1';
		$this->rest_base = 'customer';
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'get_permission_check' ],
				'args'                => $this->get_collection_params(),
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'get_permission_check' ],
				'args'                => array_merge( $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ), [
					'email'    => [
						'required'    => true,
						'type'        => 'string',
						'description' => __( 'New user email address.', 'storeengine' ),
					],
					'username' => [
						'required'    => true,
						'description' => __( 'New user username.', 'storeengine' ),
						'type'        => 'string',
					],
					'password' => [
						'required'    => false,
						'description' => __( 'New user password.', 'storeengine' ),
						'type'        => 'string',
					],
				] ),
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
				'permission_callback' => [ $this, 'get_permission_check' ],
				'args'                => [
					'context' => $this->get_context_param( [ 'default' => 'view' ] ),
				],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'get_permission_check' ],
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'get_permission_check' ],
				'args'                => [
					'force'    => [
						'default'     => false,
						'type'        => 'boolean',
						'description' => __( 'Required to be true, as resource does not support trashing.', 'storeengine' ),
					],
					'reassign' => [
						'default'     => 0,
						'type'        => 'integer',
						'description' => __( 'ID to reassign posts to.', 'storeengine' ),
					],
				],
			],
			'schema' => [ $this, 'get_public_item_schema' ],
		] );
	}

	public function get_permission_check() {
		return Helper::check_rest_user_cap( 'manage_options' );
	}

	/**
	 * Create a single customer.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'rest_customer_exists', __( 'Cannot create existing resource.', 'storeengine' ), [ 'status' => 400 ] );
		}

		// Sets the username.
		$request['username'] = ! empty( $request['username'] ) ? $request['username'] : '';

		// Sets the password.
		$request['password'] = ! empty( $request['password'] ) ? $request['password'] : '';

		$customer = new CustomerEntity();

		$customer->set_email( sanitize_email( $request['email'] ) );
		$customer->set_password( sanitize_text_field( $request['password'] ) );
		$customer->set_username( sanitize_text_field( $request['username'] ) );

		$saved = $customer->save();

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		if ( ! $customer->get_id() ) {
			return new WP_Error( 'rest_cannot_create', __( 'Cannot create the customer.', 'storeengine' ), [ 'status' => 400 ] );
		}

		// @FIXME send new user a welcome email.
		$this->update_customer_meta_fields( $customer, $request );

		$response = rest_ensure_response( $this->rest_prepare_item_for_response( $customer, $request ) );
		$response->add_links( $this->prepare_links( get_userdata( $customer->get_id() ) ) );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '/%s/%s/%d', $this->namespace, $this->rest_base, $customer->get_id() ) ) );

		return $response;
	}

	/**
	 * Update a single user.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_item( $request ) {
		$customer = Helper::get_customer( (int) $request['id'] );

		if ( ! $customer ) {
			return new WP_Error( 'rest_customer_invalid_id', __( 'Invalid customer ID.', 'storeengine' ), [ 'status' => 404 ] );
		}

		if ( ! empty( $request['email'] ) && email_exists( $request['email'] ) && $request['email'] !== $customer->get_email() ) {
			return new WP_Error( 'rest_customer_invalid_email', __( 'Email address is invalid.', 'storeengine' ), [ 'status' => 400 ] );
		}

		if ( ! empty( $request['username'] ) && $request['username'] !== $customer->get_username() ) {
			return new WP_Error( 'rest_customer_invalid_argument', __( "Username isn't editable.", 'storeengine' ), [ 'status' => 400 ] );
		}

		// Customer email.
		if ( isset( $request['email'] ) ) {
			$customer->set_email( sanitize_email( $request['email'] ) );
		}

		// Customer password.
		if ( isset( $request['password'] ) ) {
			$customer->set_password( $request['password'] );
		}

		$customer->save();

		$this->update_customer_meta_fields( $customer, $request );

		if ( ! is_user_member_of_blog( $customer->get_id() ) ) {
			$user_data = get_userdata( $customer->get_id() );
			$user_data->add_role( 'storeengine_customer' );
		}

		return rest_ensure_response( $this->rest_prepare_item_for_response( $customer, $request ) );
	}

	/**
	 * Delete a single customer.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete_item( $request ) {
		$id       = (int) $request['id'];
		$reassign = isset( $request['reassign'] ) ? absint( $request['reassign'] ) : null;
		$force    = isset( $request['force'] ) ? (bool) $request['force'] : false;
		// @TODO handle multisite.

		// We don't support trashing for this type, error out.
		if ( ! $force ) {
			return new WP_Error( 'rest_trash_not_supported', __( 'Customers do not support trashing.', 'storeengine' ), [ 'status' => 501 ] );
		}

		$customer = Helper::get_customer( (int) $request['id'] );

		if ( ! $customer ) {
			return new WP_Error( 'rest_customer_invalid_id', __( 'Invalid customer ID.', 'storeengine' ), [ 'status' => 404 ] );
		}

		if ( ! empty( $reassign ) ) {
			if ( $reassign === $id || ! get_userdata( $reassign ) ) {
				return new WP_Error( 'rest_customer_invalid_reassign', __( 'Invalid resource id for reassignment.', 'storeengine' ), [ 'status' => 400 ] );
			}
		}

		$request->set_param( 'context', 'edit' );
		$previous = $this->rest_prepare_item_for_response( $customer, $request );

		$result = parent::delete_item( $request );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'rest_cannot_delete', __( 'The customer cannot be deleted.', 'storeengine' ), [ 'status' => 500 ] );
		}

		return rest_ensure_response( [
			'deleted'  => true,
			'previous' => $previous,
		] );
	}

	protected function update_customer_meta_fields( CustomerEntity $customer, $request ) {
		if ( ! $customer->get_id() ) {
			return;
		}

		$schema = $this->get_item_schema();

		if ( ! empty( $request['first_name'] ) ) {
			$customer->set_first_name( sanitize_text_field( $request['first_name'] ) );
		}

		if ( ! empty( $request['last_name'] ) ) {
			$customer->set_last_name( sanitize_text_field( $request['last_name'] ) );
		}

		$subscribe_to_email = array_key_exists( 'subscribe_to_email', $request->get_params() ) ? $request['subscribe_to_email'] : false;
		$customer->set_subscribe_to_email( $subscribe_to_email );

		// Customer billing address.
		if ( isset( $request['billing_address'] ) ) {
			foreach ( array_keys( $schema['properties']['billing_address']['properties'] ) as $field ) {
				if ( isset( $request['billing_address'][ $field ] ) && is_callable( [
					$customer,
					"set_billing_{$field}",
				] ) ) {
					$customer->{"set_billing_{$field}"}( $request['billing_address'][ $field ] );
				}
			}
		}

		// Customer shipping address.
		if ( isset( $request['shipping_address'] ) ) {
			foreach ( array_keys( $schema['properties']['shipping_address']['properties'] ) as $field ) {
				if ( isset( $request['shipping_address'][ $field ] ) && is_callable( [
					$customer,
					"set_shipping_{$field}",
				] ) ) {
					$customer->{"set_shipping_{$field}"}( $request['shipping_address'][ $field ] );
				}
			}
		}

		$customer->save();

		// Meta data.
		if ( isset( $request['meta_data'] ) ) {
			if ( is_array( $request['meta_data'] ) ) {
				foreach ( $request['meta_data'] as $meta ) {
					// @TODO implement the use of meta id, it will enable safely deletion of the data.
					update_user_meta( $customer->get_id(), $meta['key'], $meta['value'] );
				}
			}
		}
	}

	public function get_item( $request ) {
		$customer_id = $request->get_param( 'id' );
		// get the customer by id
		$customer = Helper::get_customer( $customer_id );

		if ( ! $customer ) {
			return new WP_Error( 'rest_customer_invalid_id', __( 'Invalid customer ID.', 'storeengine' ), array( 'status' => 404 ) );
		}

		$response = rest_ensure_response( $this->rest_prepare_item_for_response( $customer, $request ) );
		$response->add_links( $this->prepare_links( get_userdata( $customer->get_id() ) ) );

		return $response;
	}

	/**
	 * Prepare customer data for response.
	 *
	 * @param CustomerEntity $customer
	 * @param WP_REST_Request $request
	 *
	 * @return array
	 * @throws StoreEngineInvalidArgumentException
	 * @throws StoreEngineInvalidOrderStatusException
	 */
	protected function rest_prepare_item_for_response( CustomerEntity $customer, WP_REST_Request $request ): array {
		// @FIXME missing limit, without limit system might crush;
		// @TODO Move order list, subscription list and purchase history to separate endpoints with proper pagination.
		//       Client should request for these data separately.
		$orders         = $this->get_formatted_orders( $customer->get_id() );
		$purchase_items = OrderCollection::get_purchase_history( $customer->get_id() );
		$total_spent    = OrderCollection::get_total_spent( $customer->get_id() );

		return apply_filters( 'storeengine/api/customer/customer_data',
			[
				'id'                 => $customer->get_id(),
				'user_registered'    => $customer->get_user_registered(),
				'name'               => $customer->get_name(),
				'first_name'         => $customer->get_first_name(),
				'last_name'          => $customer->get_last_name(),
				'email'              => $customer->get_email(),
				'total_orders'       => $customer->get_total_orders(),
				'subscribe_to_email' => $customer->has_subscribe_to_email(),
				'orders'             => $orders,
				'total_spent'        => $total_spent,
				'is_paying_customer' => $total_spent > 0,
				'billing_address'    => [
					'first_name' => $customer->get_billing_first_name(),
					'last_name'  => $customer->get_billing_last_name(),
					'email'      => $customer->get_billing_email(),
					'phone'      => $customer->get_billing_phone(),
					'company'    => $customer->get_billing_company(),
					'address_1'  => $customer->get_billing_address_1(),
					'address_2'  => $customer->get_billing_address_2(),
					'city'       => $customer->get_billing_city(),
					'state'      => $customer->get_billing_state(),
					'country'    => $customer->get_billing_country(),
					'postcode'   => $customer->get_billing_postcode(),
				],
				'shipping_address'   => [
					'first_name' => $customer->get_shipping_first_name(),
					'last_name'  => $customer->get_shipping_last_name(),
					'email'      => $customer->get_shipping_email(),
					'phone'      => $customer->get_shipping_phone(),
					'address_1'  => $customer->get_shipping_address_1(),
					'address_2'  => $customer->get_shipping_address_2(),
					'city'       => $customer->get_shipping_city(),
					'state'      => $customer->get_shipping_state(),
					'country'    => $customer->get_shipping_country(),
					'postcode'   => $customer->get_shipping_postcode(),
				],
				'purchase_items'     => $purchase_items,
				'payments'           => [],
			],
			$customer,
			$request
		);
	}

	public function get_items( $request ) {
		$per_page = $request->get_param( 'per_page' );
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$args     = [
			'number'  => $per_page,
			'offset'  => ! empty( $request['offset'] ) ? $request['offset'] : ( $page - 1 ) * $per_page,
			'order'   => $request->get_param( 'order' ),
			'include' => $request->get_param( 'include' ),
			'exclude' => $request->get_param( 'exclude' ),
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
		];

		$orderby_possibles = [
			'id'              => 'ID',
			'include'         => 'include',
			'name'            => 'display_name',
			'registered_date' => 'registered',
		];
		$args['orderby']   = $orderby_possibles[ $request['orderby'] ];

		$search = $request->get_param( 'search' );
		if ( ! empty( $search ) ) {
			$args['search'] = '*' . $search . '*';
		}

		// Filter by email.
		if ( ! empty( $request['email'] ) ) {
			$args['search']         = $request['email'];
			$args['search_columns'] = [ 'user_email' ];
		}

		$query     = new WP_User_Query( $args );
		$customers = [];

		foreach ( $query->get_results() as $user ) {
			$customer = new CustomerEntity();
			$customer->set_data( $user );
			$response = rest_ensure_response( $this->rest_prepare_item_for_response( $customer, $request ) );
			$response->add_links( $this->prepare_links( get_userdata( $customer->get_id() ) ) );
			$customers[] = $this->prepare_response_for_collection( $response );
		}

		$response = rest_ensure_response( $customers );

		// Store pagination values for headers then unset for count query.
		$per_page = (int) $args['number'];
		$page     = ceil( ( ( (int) $args['offset'] ) / $per_page ) + 1 );

		$args['fields'] = 'ID';

		$total_users = $query->get_total();
		if ( $total_users < 1 ) {
			// Out-of-bounds, run the query again without LIMIT for total count.
			unset( $args['number'] );
			unset( $args['offset'] );
			$count_query = new WP_User_Query( $args );
			$total_users = $count_query->get_total();
		}

		$max_pages = ceil( $total_users / $per_page );
		$response->header( 'X-WP-Total', (int) $total_users );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );

		$base = add_query_arg( $request->get_query_params(), rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ) );

		if ( $page > 1 ) {
			$prev_page = $page - 1;

			if ( $prev_page > $max_pages ) {
				$prev_page = $max_pages;
			}

			$prev_link = add_query_arg( 'page', $prev_page, $base );
			$response->link_header( 'prev', $prev_link );
		}

		if ( $max_pages > $page ) {
			$next_page = $page + 1;
			$next_link = add_query_arg( 'page', $next_page, $base );
			$response->link_header( 'next', $next_link );
		}

		return $response;
	}

	/**
	 * Get orders.
	 *
	 * @param int $customer_id
	 *
	 * @return array
	 * @throws StoreEngineInvalidArgumentException
	 * @throws StoreEngineInvalidOrderStatusException
	 */
	protected function get_formatted_orders( int $customer_id ): array {
		$orders = [];
		$query  = new OrderCollection( [
			'per_page' => - 1,
			'page'     => 1,
			'where'    => [
				[
					'key'   => 'customer_id',
					'value' => $customer_id,
				],
				[
					'key'     => 'status',
					'value'   => [ 'draft', 'auto-draft', 'trash' ],
					'compare' => 'NOT IN',
				],
			],
		], 'order' );

		foreach ( $query->get_results() as $order ) {
			$orders[] = [
				'order_id'       => $order->get_id(),
				'total_items'    => array_sum( array_map( fn( $order_item ) => $order_item->get_quantity(), $order->get_items() ) ),
				'refunds_total'  => $order->get_total_refunded(),
				'total'          => (float) $order->get_total(),
				'status'         => $order->get_status(),
				'status_title'   => $order->get_status_title(),
				'payment_method' => $order->get_payment_method_title(),
				'date'           => $order->get_date_created_gmt()->format( 'Y-m-d H:i:s' ),
			];
		}

		return $orders;
	}

	protected function prepare_links( $user ): array {
		return [
			'self'       => [
				'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $user->ID ) ),
			],
			'collection' => [
				'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ),
			],
		];
	}

	/**
	 * Get the Customer's schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema(): array {
		$schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'customer',
			'type'       => 'object',
			'properties' => [
				'id'                 => [
					'description' => __( 'Unique identifier for the resource.', 'storeengine' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'user_registered'    => [
					'description' => __( "The date the customer was registered, in the site's timezone.", 'storeengine' ),
					'type'        => 'date-time',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'email'              => [
					'description' => __( 'The email address for the customer.', 'storeengine' ),
					'type'        => 'string',
					'format'      => 'email',
					'context'     => [ 'view', 'edit' ],
				],
				'first_name'         => [
					'description' => __( 'Customer first name.', 'storeengine' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'arg_options' => [
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
				'last_name'          => [
					'description' => __( 'Customer last name.', 'storeengine' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'arg_options' => [
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
				'role'               => [
					'description' => __( 'Customer role.', 'storeengine' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'username'           => [
					'description' => __( 'Customer login name.', 'storeengine' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'arg_options' => [
						'sanitize_callback' => 'sanitize_user',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'default'     => '',
				],
				'password'           => [
					'description' => __( 'Customer password.', 'storeengine' ),
					'type'        => 'string',
					'context'     => [ 'edit' ],
					'default'     => '',
				],
				'billing_address'    => [
					'description' => __( 'List of billing address data.', 'storeengine' ),
					'type'        => 'object',
					'context'     => [ 'view', 'edit' ],
					'properties'  => [
						'first_name' => [
							'description' => __( 'First name.', 'storeengine' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'last_name'  => [
							'description' => __( 'Last name.', 'storeengine' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'company'    => [
							'description' => __( 'Company name.', 'storeengine' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'address_1'  => [
							'description' => __( 'Address line 1', 'storeengine' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'address_2'  => [
							'description' => __( 'Address line 2', 'storeengine' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'city'       => [
							'description' => __( 'City name.', 'storeengine' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'state'      => [
							'description' => __( 'ISO code or name of the state, province or district.', 'storeengine' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'postcode'   => [
							'description' => __( 'Postal code.', 'storeengine' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'country'    => [
							'description' => __( 'ISO code of the country.', 'storeengine' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'email'      => [
							'description' => __( 'Email address.', 'storeengine' ),
							'type'        => 'string',
							'format'      => 'email',
							'context'     => [ 'view', 'edit' ],
						],
						'phone'      => [
							'description' => __( 'Phone number.', 'storeengine' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
					],
				],
				'shipping_address'   => [
					'description' => __( 'List of shipping address data.', 'storeengine' ),
					'type'        => 'object',
					'context'     => [ 'view', 'edit' ],
					'properties'  => [
						'first_name' => [
							'description' => __( 'First name.', 'storeengine' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'last_name'  => [
							'description' => __( 'Last name.', 'storeengine' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'company'    => [
							'description' => __( 'Company name.', 'storeengine' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'address_1'  => [
							'description' => __( 'Address line 1', 'storeengine' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'address_2'  => [
							'description' => __( 'Address line 2', 'storeengine' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'city'       => [
							'description' => __( 'City name.', 'storeengine' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'state'      => [
							'description' => __( 'ISO code or name of the state, province or district.', 'storeengine' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'postcode'   => [
							'description' => __( 'Postal code.', 'storeengine' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
						'country'    => [
							'description' => __( 'ISO code of the country.', 'storeengine' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
						],
					],
				],
				'is_paying_customer' => [
					'description' => __( 'Is the customer a paying customer?', 'storeengine' ),
					'type'        => 'bool',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'total_orders'       => [
					'description' => __( 'Quantity of orders made by the customer.', 'storeengine' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'total_spent'        => [
					'description' => __( 'Total amount spent.', 'storeengine' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'subscribe_to_email' => [
					'description' => __( 'Is the customer subscribed newsletter.', 'storeengine' ),
					'type'        => 'boolean',
					'context'     => [ 'view', 'edit' ],
					'default'     => false,
				],
				'avatar_url'         => [
					'description' => __( 'Avatar URL.', 'storeengine' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'meta_data'          => [
					'description' => __( 'Meta data.', 'storeengine' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit' ],
					'items'       => [
						'type'       => 'object',
						'properties' => [
							// @TODO implement the use of meta id, it will enable safely deletion of the data.
							'key'   => [
								'description' => __( 'Meta key.', 'storeengine' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'value' => [
								'description' => __( 'Meta value.', 'storeengine' ),
								'type'        => 'mixed',
								'context'     => [ 'view', 'edit' ],
							],
						],
					],
				],
			],
		];

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Get the query params for collections.
	 *
	 * @return array
	 */
	public function get_collection_params(): array {
		$params = parent::get_collection_params();

		$params['context']['default'] = 'view';

		$params['exclude'] = [ // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
			'description'       => __( 'Ensure result set excludes specific IDs.', 'storeengine' ),
			'type'              => 'array',
			'items'             => [
				'type' => 'integer',
			],
			'default'           => [],
			'sanitize_callback' => 'wp_parse_id_list',
		];
		$params['include'] = [
			'description'       => __( 'Limit result set to specific IDs.', 'storeengine' ),
			'type'              => 'array',
			'items'             => [
				'type' => 'integer',
			],
			'default'           => [],
			'sanitize_callback' => 'wp_parse_id_list',
		];
		$params['offset']  = [
			'description'       => __( 'Offset the result set by a specific number of items.', 'storeengine' ),
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['order']   = [
			'default'           => 'desc',
			'description'       => __( 'Order sort attribute ascending or descending.', 'storeengine' ),
			'enum'              => [ 'asc', 'desc' ],
			'sanitize_callback' => 'sanitize_key',
			'type'              => 'string',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['orderby'] = [
			'default'           => 'id',
			'description'       => __( 'Sort collection by object attribute.', 'storeengine' ),
			'enum'              => [
				'id',
				'include',
				'name',
				'registered_date',
			],
			'sanitize_callback' => 'sanitize_key',
			'type'              => 'string',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['email']   = [
			'description'       => __( 'Limit result set to resources with a specific email.', 'storeengine' ),
			'type'              => 'string',
			'format'            => 'email',
			'validate_callback' => 'rest_validate_request_arg',
		];

		return $params;
	}
}
