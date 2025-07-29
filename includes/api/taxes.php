<?php /** @noinspection PhpMissingReturnTypeInspection */

/**
 * Tax api endpoints.
 */

namespace StoreEngine\API;

use stdClass;
use StoreEngine\Classes\Tax;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Taxes extends AbstractRestApiController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'taxes';

	public static function init() {
		$self = new self();
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
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'force' => [
						'default'     => false,
						'type'        => 'boolean',
						'description' => __( 'Required to be true, as resource does not support trashing.', 'storeengine' ),
					],
				],
			],
			'schema' => [ $this, 'get_public_item_schema' ],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/batch', [
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'batch_items' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			],
			'schema' => [ $this, 'get_public_batch_schema' ],
		] );
	}

	public function permissions_check() {
		return Helper::check_rest_user_cap( 'manage_options' );
	}

	/**
	 * Get all taxes.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		global $wpdb;

		$prepared_args           = [];
		$prepared_args['order']  = $request['order'];
		$prepared_args['number'] = $request['per_page'];
		if ( ! empty( $request['offset'] ) ) {
			$prepared_args['offset'] = $request['offset'];
		} else {
			$prepared_args['offset'] = ( $request['page'] - 1 ) * $prepared_args['number'];
		}
		$orderby_possibles = [
			'id'       => 'tax_rate_id',
			'order'    => 'tax_rate_order',
			'priority' => 'tax_rate_priority',
		];

		$prepared_args['orderby'] = $orderby_possibles[ $request['orderby'] ];
		$prepared_args['class']   = $request['class'] ?? '';

		/**
		 * Filter arguments, before passing to $wpdb->get_results(), when querying taxes via the REST API.
		 *
		 * @param array $prepared_args Array of arguments for $wpdb->get_results().
		 * @param WP_REST_Request $request The current request.
		 */
		$prepared_args = apply_filters( 'storeengine/rest_tax_query', $prepared_args, $request );

		$orderby = sanitize_key( $prepared_args['orderby'] ) . ' ' . sanitize_key( $prepared_args['order'] );
		$query   = "
			SELECT *
			FROM {$wpdb->prefix}storeengine_tax_rates
			%s
			ORDER BY {$orderby}
			LIMIT %%d, %%d
		";

		$wpdb_prepare_args = [ $prepared_args['offset'], $prepared_args['number'] ];

		// Filter by tax class.
		if ( empty( $prepared_args['class'] ) ) {
			$query = sprintf( $query, '' );
		} else {
			$class = 'standard' !== $prepared_args['class'] ? sanitize_title( $prepared_args['class'] ) : '';
			array_unshift( $wpdb_prepare_args, $class );
			$query = sprintf( $query, 'WHERE tax_rate_class = %s' );
		}

		// Query taxes.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- query prepared.
		$results = $wpdb->get_results( $wpdb->prepare( $query, $wpdb_prepare_args ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- query prepared.

		$taxes = [];
		foreach ( $results as $tax ) {
			$data    = $this->prepare_item_for_response( $tax, $request );
			$taxes[] = $this->prepare_response_for_collection( $data );
		}

		$response = rest_ensure_response( $taxes );

		$per_page = (int) $prepared_args['number'];
		$page     = ceil( ( ( (int) $prepared_args['offset'] ) / $per_page ) + 1 );

		// Unset LIMIT args.
		array_splice( $wpdb_prepare_args, - 2 );

		// Count query.
		$query = str_replace( [ 'SELECT *', 'LIMIT %d, %d' ], [ 'SELECT COUNT(*)', '' ], $query );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- query prepared.
		$total_taxes = (int) $wpdb->get_var( empty( $wpdb_prepare_args ) ? $query : $wpdb->prepare( $query, $wpdb_prepare_args ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- query prepared.

		// Calculate totals.
		$response->header( 'X-WP-Total', $total_taxes );
		$max_pages = ceil( $total_taxes / $per_page );
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
	 * Take tax data from the request and return the updated or newly created rate.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @param stdClass|null $current Existing tax object.
	 *
	 * @return object
	 */
	protected function create_or_update_tax( $request, $current = null ) {
		$id     = absint( isset( $request['id'] ) ? $request['id'] : 0 );
		$data   = array();
		$fields = array(
			'tax_rate_country',
			'tax_rate_state',
			'tax_rate',
			'tax_rate_name',
			'tax_rate_priority',
			'tax_rate_compound',
			'tax_rate_shipping',
			'tax_rate_order',
			'tax_rate_class',
		);

		foreach ( $fields as $field ) {
			// Keys via API differ from the stored names returned by _get_tax_rate.
			$key = 'tax_rate' === $field ? 'rate' : str_replace( 'tax_rate_', '', $field );

			// Remove data that was not posted.
			if ( ! isset( $request[ $key ] ) ) {
				continue;
			}

			// Test new data against current data.
			if ( $current && $current->$field === $request[ $key ] ) {
				continue;
			}

			// Add to data array.
			switch ( $key ) {
				case 'tax_rate_priority':
				case 'tax_rate_compound':
				case 'tax_rate_shipping':
				case 'tax_rate_order':
					$data[ $field ] = absint( $request[ $key ] );
					break;
				case 'tax_rate_class':
					$data[ $field ] = 'standard' !== $request['tax_rate_class'] ? $request['tax_rate_class'] : '';
					break;
				default:
					$data[ $field ] = sanitize_text_field( $request[ $key ] );
					break;
			}
		}

		if ( ! $id ) {
			$id = Tax::_insert_tax_rate( $data );
		} elseif ( $data ) {
			Tax::_update_tax_rate( $id, $data );
		}

		// Add locales.
		if ( ! empty( $request['postcode'] ) ) {
			Tax::_update_tax_rate_postcodes( $id, sanitize_text_field( $request['postcode'] ) );
		}
		if ( ! empty( $request['city'] ) ) {
			Tax::_update_tax_rate_cities( $id, sanitize_text_field( $request['city'] ) );
		}

		return Tax::_get_tax_rate( $id, OBJECT );
	}

	/**
	 * Create a single tax.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'rest_tax_exists', __( 'Cannot create existing resource.', 'storeengine' ), array( 'status' => 400 ) );
		}

		$tax = $this->create_or_update_tax( $request );

		$this->update_additional_fields_for_object( $tax, $request );

		/**
		 * Fires after a tax is created or updated via the REST API.
		 *
		 * @param stdClass $tax Data used to create the tax.
		 * @param WP_REST_Request $request Request object.
		 * @param boolean $creating True when creating tax, false when updating tax.
		 */
		do_action( 'storeengine/rest_insert_tax', $tax, $request, true );

		$request->set_param( 'context', 'edit' );
		$response = $this->prepare_item_for_response( $tax, $request );
		$response = rest_ensure_response( $response );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '/%s/%s/%d', $this->namespace, $this->rest_base, $tax->tax_rate_id ) ) );

		return $response;
	}

	/**
	 * Get a single tax.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_item( $request ) {
		$id      = (int) $request['id'];
		$tax_obj = Tax::_get_tax_rate( $id, OBJECT );

		if ( empty( $id ) || empty( $tax_obj ) ) {
			return new WP_Error( 'rest_invalid_id', __( 'Invalid resource ID.', 'storeengine' ), array( 'status' => 404 ) );
		}

		$tax = $this->prepare_item_for_response( $tax_obj, $request );

		return rest_ensure_response( $tax );
	}

	/**
	 * Update a single tax.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_item( $request ) {
		$id      = (int) $request['id'];
		$tax_obj = Tax::_get_tax_rate( $id, OBJECT );

		if ( empty( $id ) || empty( $tax_obj ) ) {
			return new WP_Error( 'rest_invalid_id', __( 'Invalid resource ID.', 'storeengine' ), array( 'status' => 404 ) );
		}

		$tax = $this->create_or_update_tax( $request, $tax_obj );

		$this->update_additional_fields_for_object( $tax, $request );

		/**
		 * Fires after a tax is created or updated via the REST API.
		 *
		 * @param stdClass $tax Data used to create the tax.
		 * @param WP_REST_Request $request Request object.
		 * @param boolean $creating True when creating tax, false when updating tax.
		 */
		do_action( 'storeengine/rest_insert_tax', $tax, $request, false );

		$request->set_param( 'context', 'edit' );
		$response = $this->prepare_item_for_response( $tax, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Delete a single tax.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete_item( $request ) {
		global $wpdb;

		$id    = (int) $request['id'];
		$force = isset( $request['force'] ) && (bool) $request['force'];

		// We don't support trashing for this type, error out.
		if ( ! $force ) {
			return new WP_Error( 'rest_trash_not_supported', __( 'Taxes do not support trashing.', 'storeengine' ), array( 'status' => 501 ) );
		}

		$tax = Tax::_get_tax_rate( $id, OBJECT );

		if ( empty( $id ) || empty( $tax ) ) {
			return new WP_Error( 'rest_invalid_id', __( 'Invalid resource ID.', 'storeengine' ), array( 'status' => 400 ) );
		}

		$request->set_param( 'context', 'edit' );
		$response = $this->prepare_item_for_response( $tax, $request );

		Tax::_delete_tax_rate( $id );

		if ( 0 === $wpdb->rows_affected ) {
			return new WP_Error( 'rest_cannot_delete', __( 'The resource cannot be deleted.', 'storeengine' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires after a tax is deleted via the REST API.
		 *
		 * @param stdClass $tax The tax data.
		 * @param WP_REST_Response $response The response returned from the API.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		do_action( 'storeengine/rest_delete_tax', $tax, $response, $request );

		return $response;
	}

	/**
	 * Prepare a single tax output for response.
	 *
	 * @param stdClass $item Tax object.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response $response Response data.
	 */
	public function prepare_item_for_response( $item, $request ) {
		$data = [
			'id'       => (int) $item->tax_rate_id,
			'country'  => $item->tax_rate_country,
			'state'    => $item->tax_rate_state,
			'postcode' => '',
			'city'     => '',
			'rate'     => (float) $item->tax_rate,
			'name'     => $item->tax_rate_name,
			'priority' => (int) $item->tax_rate_priority,
			'compound' => (bool) $item->tax_rate_compound,
			'shipping' => (bool) $item->tax_rate_shipping,
			'order'    => (int) $item->tax_rate_order,
			'class'    => $item->tax_rate_class ?: 'standard',
		];

		$data = $this->add_tax_rate_locales( $data, $item );

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		$response->add_links( $this->prepare_links( $item, $request ) );

		/**
		 * Filter tax object returned from the REST API.
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param stdClass $item Tax object used to create response.
		 * @param WP_REST_Request $request Request object.
		 */
		return apply_filters( 'storeengine/rest_prepare_tax', $response, $item, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param $item
	 * @param WP_REST_Request $request *
	 *
	 * @return array Links for the given tax.
	 */
	protected function prepare_links( $item, WP_REST_Request $request ): array {
		return [
			'self'       => [
				'href' => rest_url( sprintf( '/%s/%s/%d', $this->namespace, $this->rest_base, $item->tax_rate_id ) ),
			],
			'collection' => [
				'href' => rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ),
			],
		];
	}

	/**
	 * Add tax rate locales to the response array.
	 *
	 * @param array $data Response data.
	 * @param ?stdClass $tax Tax object.
	 *
	 * @return array
	 */
	protected function add_tax_rate_locales( array $data, ?stdClass $tax ): array {
		global $wpdb;

		if ( ! is_wp_error( $tax ) && ! is_null( $tax ) ) {
			// Get locales from a tax rate.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$locales = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT location_code, location_type
					FROM {$wpdb->prefix}storeengine_tax_rate_locations
					WHERE tax_rate_id = %d
					",
					$tax->tax_rate_id
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			foreach ( $locales as $locale ) {
				$data[ $locale->location_type ] = $locale->location_code;
			}
		}

		return $data;
	}

	/**
	 * Get the Taxes schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'tax',
			'type'       => 'object',
			'properties' => [
				'id'       => [
					'description' => __( 'Unique identifier for the resource.', 'storeengine' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'country'  => [
					'description' => __( 'Country ISO 3166 code.', 'storeengine' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'state'    => [
					'description' => __( 'State code.', 'storeengine' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'postcode' => [
					'description' => __( 'Postcode / ZIP.', 'storeengine' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'city'     => [
					'description' => __( 'City name.', 'storeengine' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'rate'     => [
					'description' => __( 'Tax rate.', 'storeengine' ),
					'type'        => 'float',
					'context'     => [ 'view', 'edit' ],
				],
				'name'     => [
					'description' => __( 'Tax rate name.', 'storeengine' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
				],
				'priority' => [
					'description' => __( 'Tax priority.', 'storeengine' ),
					'type'        => 'integer',
					'default'     => 1,
					'context'     => [ 'view', 'edit' ],
				],
				'compound' => [
					'description' => __( 'Whether or not this is a compound rate.', 'storeengine' ),
					'type'        => 'boolean',
					'default'     => false,
					'context'     => [ 'view', 'edit' ],
				],
				'shipping' => [
					'description' => __( 'Whether or not this tax rate also gets applied to shipping.', 'storeengine' ),
					'type'        => 'boolean',
					'default'     => true,
					'context'     => [ 'view', 'edit' ],
				],
				'order'    => [
					'description' => __( 'Indicates the order that will appear in queries.', 'storeengine' ),
					'type'        => 'integer',
					'context'     => [ 'view', 'edit' ],
				],
				'class'    => [ // @TODO remove experimental after implementation is completed.
					'description' => __( 'Tax class (experimental).', 'storeengine' ),
					'type'        => 'string',
					'default'     => 'standard',
					'enum'        => array_merge( [ 'standard' ], \StoreEngine\Classes\Tax::get_tax_class_slugs() ),
					'context'     => [ 'view', 'edit' ],
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
	public function get_collection_params() {
		$params                       = [];
		$params['context']            = $this->get_context_param();
		$params['context']['default'] = 'view';

		$params['page']     = [
			'description'       => __( 'Current page of the collection.', 'storeengine' ),
			'type'              => 'integer',
			'default'           => 1,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
			'minimum'           => 1,
		];
		$params['per_page'] = [
			'description'       => __( 'Maximum number of items to be returned in result set.', 'storeengine' ),
			'type'              => 'integer',
			'default'           => 10,
			'minimum'           => 1,
			'maximum'           => 100,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['offset']   = [
			'description'       => __( 'Offset the result set by a specific number of items.', 'storeengine' ),
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['order']    = [
			'default'           => 'asc',
			'description'       => __( 'Order sort attribute ascending or descending.', 'storeengine' ),
			'enum'              => [ 'asc', 'desc' ],
			'sanitize_callback' => 'sanitize_key',
			'type'              => 'string',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['orderby']  = [
			'default'           => 'order',
			'description'       => __( 'Sort collection by object attribute.', 'storeengine' ),
			'enum'              => [
				'id',
				'order',
				'priority',
			],
			'sanitize_callback' => 'sanitize_key',
			'type'              => 'string',
			'validate_callback' => 'rest_validate_request_arg',
		];
		$params['class']    = [ // @TODO remove experimental after implementation is completed.
			'description'       => __( 'Sort by tax class (experimental).', 'storeengine' ),
			'enum'              => array_merge( [ 'standard' ], \StoreEngine\Classes\Tax::get_tax_class_slugs() ),
			'sanitize_callback' => 'sanitize_title',
			'type'              => 'string',
			'validate_callback' => 'rest_validate_request_arg',
		];

		return $params;
	}
}

// End of file tax.php.
