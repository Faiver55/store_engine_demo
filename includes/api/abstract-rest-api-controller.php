<?php
/**
 * REST Controller
 *
 * This class extend `WP_REST_Controller` in order to include /batch endpoint
 * for almost all endpoints in WooCommerce REST API.
 *
 * It's required to follow "Controller Classes" guide before extending this class:
 * <https://developer.wordpress.org/rest-api/extending-the-rest-api/controller-classes/>
 *
 * NOTE THAT ONLY CODE RELEVANT FOR MOST ENDPOINTS SHOULD BE INCLUDED INTO THIS CLASS.
 * If necessary extend this class and create new abstract classes like `WC_REST_CRUD_Controller` or `WC_REST_Terms_Controller`.
 *
 * @class   WC_REST_Controller
 * @package WooCommerce\RestApi
 * @see     https://developer.wordpress.org/rest-api/extending-the-rest-api/controller-classes/
 */

namespace StoreEngine\API;

use Closure;
use Exception;
use stdClass;
use StoreEngine\Classes\AbstractCollection;
use StoreEngine\Classes\AbstractEntity;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Rest Controller Class
 *
 * @package StoreEngine\Api
 * @extends  WP_REST_Controller
 */
abstract class AbstractRestApiController extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = STOREENGINE_PLUGIN_SLUG . '/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = '';

	/**
	 * Used to cache computed return fields.
	 *
	 * @var null|array
	 */
	private ?array $_fields = null; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Used to verify if cached fields are for correct request object.
	 *
	 * @var null|WP_REST_Request
	 */
	private ?WP_REST_Request $_request = null; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	public function __construct() {
		if ( ! $this->rest_base ) {
			$base = explode( '\\', get_class($this) );
			$base = end( $base );
			$base = array_filter( preg_split('/(?=[A-Z])/', $base ) );

			// Set rest base.
			$this->rest_base = strtolower( implode( '-', $base ) );
		}
	}

	/**
	 * Add the schema from additional fields to an schema array.
	 *
	 * The type of object is inferred from the passed schema.
	 *
	 * @param array $schema Schema array.
	 *
	 * @return array
	 */
	protected function add_additional_fields_schema( $schema ): array {
		if ( empty( $schema['title'] ) ) {
			return $schema;
		}

		/**
		 * Can't use $this->get_object_type otherwise we cause an inf loop.
		 */
		$object_type = $schema['title'];

		$additional_fields = $this->get_additional_fields( $object_type );

		foreach ( $additional_fields as $field_name => $field_options ) {
			if ( ! $field_options['schema'] ) {
				continue;
			}

			$schema['properties'][ $field_name ] = $field_options['schema'];
		}

		$schema['properties'] = apply_filters( 'storeengine/rest_' . $object_type . '_schema', $schema['properties'] );

		return $schema;
	}

	/**
	 * Get normalized rest base.
	 *
	 * @return string
	 */
	protected function get_normalized_rest_base(): string {
		return preg_replace( '/\(.*\)\//i', '', $this->rest_base );
	}

	/**
	 * Check batch limit.
	 *
	 * @param array $items Request items.
	 *
	 * @return bool|WP_Error
	 */
	protected function check_batch_limit( array $items ) {
		$limit = apply_filters( 'storeengine/rest_batch_items_limit', 100, $this->get_normalized_rest_base() );
		$total = 0;

		if ( ! empty( $items['create'] ) && is_countable( $items['create'] ) ) {
			$total += count( $items['create'] );
		}

		if ( ! empty( $items['update'] ) && is_countable( $items['update'] ) ) {
			$total += count( $items['update'] );
		}

		if ( ! empty( $items['delete'] ) && is_countable( $items['delete'] ) ) {
			$total += count( $items['delete'] );
		}

		if ( $total > $limit ) {
			/* translators: %s: items limit */
			return new WP_Error( 'rest_request_entity_too_large', sprintf( __( 'Unable to accept more than %s items for this request.', 'storeengine' ), $limit ), array( 'status' => 413 ) );
		}

		return true;
	}

	/**
	 * Bulk create, update and delete items.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_Error[]|array Of WP_Error or WP_REST_Response.
	 */
	public function batch_items( WP_REST_Request $request ) {
		/**
		 * REST Server
		 *
		 * @var WP_REST_Server $wp_rest_server
		 */
		global $wp_rest_server;

		// Get the request params.
		$items    = array_filter( $request->get_params() );
		$query    = $request->get_query_params();
		$response = [];

		// Check batch limit.
		$limit = $this->check_batch_limit( $items );
		if ( is_wp_error( $limit ) ) {
			return $limit;
		}

		if ( ! empty( $items['create'] ) ) {
			foreach ( $items['create'] as $item ) {
				$_item = new WP_REST_Request( 'POST', $request->get_route() );

				// Default parameters.
				$defaults = [];
				$schema   = $this->get_public_item_schema();
				foreach ( $schema['properties'] as $arg => $options ) {
					if ( isset( $options['default'] ) ) {
						$defaults[ $arg ] = $options['default'];
					}
				}
				$_item->set_default_params( $defaults );

				// Set request parameters.
				$_item->set_body_params( $item );

				// Set query (GET) parameters.
				$_item->set_query_params( $query );

				$allowed = $this->create_item_permissions_check( $_item );
				if ( is_wp_error( $allowed ) ) {
					$response['create'][] = [
						'id'    => 0,
						'error' => [
							'code'    => $allowed->get_error_code(),
							'message' => $allowed->get_error_message(),
							'data'    => $allowed->get_error_data(),
						],
					];
					continue;
				}

				$_response = $this->create_item( $_item );

				if ( is_wp_error( $_response ) ) {
					$response['create'][] = [
						'id'    => 0,
						'error' => [
							'code'    => $_response->get_error_code(),
							'message' => $_response->get_error_message(),
							'data'    => $_response->get_error_data(),
						],
					];
				} else {
					$response['create'][] = $wp_rest_server->response_to_data( $_response, '' );
				}
			}
		}

		if ( ! empty( $items['update'] ) ) {
			foreach ( $items['update'] as $item ) {
				$_item = new WP_REST_Request( 'PUT', $request->get_route() );
				$_item->set_body_params( $item );

				$allowed = $this->update_item_permissions_check( $_item );
				if ( is_wp_error( $allowed ) ) {
					$response['update'][] = [
						'id'    => $_item['id'],
						'error' => [
							'code'    => $allowed->get_error_code(),
							'message' => $allowed->get_error_message(),
							'data'    => $allowed->get_error_data(),
						],
					];
					continue;
				}

				$_response = $this->update_item( $_item );

				if ( is_wp_error( $_response ) ) {
					$response['update'][] = [
						'id'    => $item['id'],
						'error' => [
							'code'    => $_response->get_error_code(),
							'message' => $_response->get_error_message(),
							'data'    => $_response->get_error_data(),
						],
					];
				} else {
					$response['update'][] = $wp_rest_server->response_to_data( $_response, '' );
				}
			}
		}

		if ( ! empty( $items['delete'] ) ) {
			foreach ( $items['delete'] as $id ) {
				$id = is_array( $id ) ? $id : (int) $id;

				if ( 0 === $id ) {
					continue;
				}

				$_item = new WP_REST_Request( 'DELETE', $request->get_route() );
				if ( is_array( $id ) ) {
					$id['force'] = true;
					$_item->set_query_params( $id );
				} else {
					$_item->set_query_params( [
						'id'    => $id,
						'force' => true,
					] );
				}

				$allowed = $this->delete_item_permissions_check( $_item );
				if ( is_wp_error( $allowed ) ) {
					$response['delete'][] = [
						'id'    => $id,
						'error' => [
							'code'    => $allowed->get_error_code(),
							'message' => $allowed->get_error_message(),
							'data'    => $allowed->get_error_data(),
						],
					];
					continue;
				}

				$_response = $this->delete_item( $_item );

				if ( is_wp_error( $_response ) ) {
					$response['delete'][] = [
						'id'    => $id,
						'error' => [
							'code'    => $_response->get_error_code(),
							'message' => $_response->get_error_message(),
							'data'    => $_response->get_error_data(),
						],
					];
				} else {
					$response['delete'][] = $wp_rest_server->response_to_data( $_response, '' );
				}
			}
		}

		return $response;
	}

	/**
	 * Validate a text value for a text based setting.
	 *
	 * @param ?string $value Value.
	 * @param array $setting Setting.
	 *
	 * @return string
	 */
	public function validate_setting_text_field( ?string $value, array $setting ): string {
		$value = is_null( $value ) ? '' : $value;

		return wp_kses_post( trim( stripslashes( $value ) ) );
	}

	/**
	 * Validate select based settings.
	 *
	 * @param string $value Value.
	 * @param array $setting Setting.
	 *
	 * @return string|WP_Error
	 */
	public function validate_setting_select_field( string $value, array $setting ) {
		if ( array_key_exists( $value, $setting['options'] ) ) {
			return $value;
		} else {
			return new WP_Error( 'rest_setting_value_invalid', __( 'An invalid setting value was passed.', 'storeengine' ), [ 'status' => 400 ] );
		}
	}

	/**
	 * Validate multiselect based settings.
	 *
	 * @param array $values Values.
	 * @param array $setting Setting.
	 *
	 * @return array|WP_Error
	 */
	public function validate_setting_multiselect_field( array $values, array $setting ) {
		if ( empty( $values ) ) {
			return [];
		}

		if ( ! is_array( $values ) ) {
			return new WP_Error( 'rest_setting_value_invalid', __( 'An invalid setting value was passed.', 'storeengine' ), array( 'status' => 400 ) );
		}

		$final_values = [];
		foreach ( $values as $value ) {
			if ( array_key_exists( $value, $setting['options'] ) ) {
				$final_values[] = $value;
			}
		}

		return $final_values;
	}

	/**
	 * Validate image_width based settings.
	 *
	 * @param array $values Values.
	 * @param array $setting Setting.
	 *
	 * @return string|WP_Error
	 */
	public function validate_setting_image_width_field( array $values, array $setting ) {
		if ( ! is_array( $values ) ) {
			return new WP_Error( 'rest_setting_value_invalid', __( 'An invalid setting value was passed.', 'storeengine' ), array( 'status' => 400 ) );
		}

		$current = $setting['value'];

		if ( isset( $values['width'] ) ) {
			$current['width'] = intval( $values['width'] );
		}

		if ( isset( $values['height'] ) ) {
			$current['height'] = intval( $values['height'] );
		}

		if ( isset( $values['crop'] ) ) {
			$current['crop'] = (bool) $values['crop'];
		}

		return $current;
	}

	/**
	 * Validate radio based settings.
	 *
	 * @param string $value Value.
	 * @param array $setting Setting.
	 *
	 * @return string|WP_Error
	 */
	public function validate_setting_radio_field( string $value, array $setting ) {
		return $this->validate_setting_select_field( $value, $setting );
	}

	/**
	 * Validate checkbox based settings.
	 *
	 * @param string $value Value.
	 * @param array $setting Setting.
	 *
	 * @return string|WP_Error
	 */
	public function validate_setting_checkbox_field( string $value, array $setting ) {
		if ( in_array( $value, [ 'yes', 'no' ], true ) ) {
			return $value;
		} elseif ( empty( $value ) ) {
			return $setting['default'] ?? 'no';
		} else {
			return new WP_Error( 'rest_setting_value_invalid', __( 'An invalid setting value was passed.', 'storeengine' ), array( 'status' => 400 ) );
		}
	}

	/**
	 * Validate textarea based settings.
	 *
	 * @param string $value Value.
	 * @param array $setting Setting.
	 *
	 * @return string
	 */
	public function validate_setting_textarea_field( string $value, array $setting ) {
		$value = is_null( $value ) ? '' : $value;

		return wp_kses_post( trim( stripslashes( $value ) ) );
	}

	/**
	 * Add meta query.
	 *
	 * @param array $args Query args.
	 * @param array $meta_query Meta query.
	 *
	 * @return array
	 */
	protected function add_meta_query( array $args, array $meta_query ): array {
		if ( empty( $args['meta_query'] ) ) {
			$args['meta_query'] = []; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$args['meta_query'][] = $meta_query;

		return $args['meta_query'];
	}

	/**
	 * Get the batch schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_public_batch_schema(): array {
		return apply_filters( 'storeengine/get_public_batch_schema', [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'batch',
			'type'       => 'object',
			'properties' => [
				'create' => [
					'description' => __( 'List of created resources.', 'storeengine' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit' ],
					'items'       => [
						'type' => 'object',
					],
				],
				'update' => [
					'description' => __( 'List of updated resources.', 'storeengine' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit' ],
					'items'       => [
						'type' => 'object',
					],
				],
				'delete' => [
					'description' => __( 'List of delete resources.', 'storeengine' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit' ],
					'items'       => [
						'type' => 'integer',
					],
				],
			],
		] );
	}

	/**
	 * Limit the contents of the meta_data property based on certain request parameters.
	 *
	 * Note that if both `include_meta` and `exclude_meta` are present in the request,
	 * `include_meta` will take precedence.
	 *
	 * @param WP_REST_Request $request The request.
	 * @param array $meta_data All the meta data for an object.
	 *
	 * @return array
	 */
	protected function get_meta_data_for_response( WP_REST_Request $request, array $meta_data ): array {
		$fields = $this->get_fields_for_response( $request );
		if ( ! in_array( 'meta_data', $fields, true ) ) {
			return array();
		}

		$include = (array) $request['include_meta'];
		$exclude = (array) $request['exclude_meta'];

		if ( ! empty( $include ) ) {
			$meta_data = array_filter( $meta_data, fn( $item ) => in_array( $item['key'], $include, true ) );
		} elseif ( ! empty( $exclude ) ) {
			$meta_data = array_filter( $meta_data, fn( $item ) => ! in_array( $item['key'], $exclude, true ) );
		}

		// Ensure the array indexes are reset so it doesn't get converted to an object in JSON.
		return array_values( $meta_data );
	}

	/**
	 * @param AbstractEntity|array|stdClass $item
	 * @param WP_REST_Request $request
	 *
	 * @return array
	 */
	protected function prepare_links( $item, WP_REST_Request $request ): array {
		$id    = null;
		$links = [];

		if ( $item instanceof AbstractEntity || is_callable( [ $item, 'get_id' ] ) ) {
			$id = $item->get_id();
		} elseif ( is_array( $item ) && ! empty( $item['id'] ) ) {
			$id = $item['id'];
		} elseif ( is_array( $item ) && ! empty( $item['ID'] ) ) {
			$id = $item['ID'];
		} elseif ( is_object( $item ) && isset( $item->id ) ) {
			$id = $item->id;
		} elseif ( is_object( $item ) && isset( $item->ID ) ) {
			$id = $item->ID;
		}

		if ( $id ) {
			$links['self'] = [
				'href' => rest_url( sprintf( '/%s/%s/%d', $this->namespace, $this->rest_base, $id ) ),
			];
		}

		$links['collection'] = [
			'href' => rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ),
		];

		return $links;
	}

	protected function prepare_pagination_headers( WP_REST_Response $response, WP_REST_Request $request, int $current_page = 1, int $total = 0, int $total_pages = 1 ) {
		$base = add_query_arg( $request->get_query_params(), rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ) );

		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', $total_pages );

		if ( $current_page > 1 ) {
			$prev_page = $current_page - 1;
			if ( $prev_page > $total_pages ) {
				$prev_page = $total_pages;
			}
			$prev_link = add_query_arg( 'page', $prev_page, $base );
			$response->link_header( 'prev', $prev_link );
		}

		if ( $total_pages > $current_page ) {
			$next_page = $current_page + 1;
			$next_link = add_query_arg( 'page', $next_page, $base );
			$response->link_header( 'next', $next_link );
		}
	}

	protected function prepare_query_response( array $data, AbstractCollection $query, WP_REST_Request $request ) {
		$response = rest_ensure_response( $data );

		$this->prepare_pagination_headers( $response, $request, $query->query['page'], $query->get_found_results(), $query->get_max_num_pages() );

		return $response;
	}
}

// End of file abstract-rest-api-controller.php.
