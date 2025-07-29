<?php

namespace StoreEngine\API;

use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Shipping\Methods\ShippingMethod;
use StoreEngine\Shipping\Shipping as ShippingObj;
use StoreEngine\Shipping\ShippingZone;
use StoreEngine\Shipping\ShippingZones;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\ShippingUtils;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

class Shipping extends AbstractRestApiController {

	public static function init() {
		$self            = new self();
		$self->namespace = STOREENGINE_PLUGIN_SLUG . '/v1';
		$self->rest_base = 'shipping';
		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/zones', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_shipping_zones' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_shipping_zone' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'zone_name'      => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'zone_locations' => [
						'required'          => false,
						'type'              => 'array',
						'items'             => [
							'type' => 'string',
						],
						'sanitize_callback' => [ $this, 'sanitize_zone_locations' ],
					],
					'zone_postcodes' => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					],
				],
			],
		] );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/zones/(?P<zone_id>[\d]+)', [
			'args' => [
				'zone_id' => [
					'description' => __( 'Unique identifier for the resource.', 'storeengine' ),
					'type'        => 'integer',
				],
			],
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_single_shipping_zone' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_shipping_zone' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'zone_name'      => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'zone_locations' => [
						'required'          => false,
						'type'              => 'array',
						'items'             => [
							'type' => 'string',
						],
						'sanitize_callback' => [ $this, 'sanitize_zone_locations' ],
					],
					'zone_postcodes' => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					],
				],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_shipping_zone' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/zones/(?P<zone_id>[\d]+)/methods', [
			'args'   => [
				'zone_id' => [
					'description' => __( 'Unique identifier for the resource.', 'storeengine' ),
					'type'        => 'integer',
				],
			],
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_shipping_methods_from_zone' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_shipping_method' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => $this->get_endpoint_args_for_item_schema(),
			],
			'schema' => [ $this, 'get_public_item_schema' ],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/methods', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_shipping_methods' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/methods/(?P<method_id>[a-zA-Z0-9_-]+)/(?P<instance_id>[\d]+)', [
			'args' => [
				'method_id'   => [
					'description' => __( 'Shipping method Id.', 'storeengine' ),
					'type'        => 'string',
				],
				'instance_id' => [
					'description' => __( 'Shipping method\'s instance id.', 'storeengine' ),
					'type'        => 'integer',
				],
			],
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_single_shipping_method' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_shipping_method' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'name'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'zone_id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_shipping_method' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/regions', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_regions' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );
	}

	public function get_shipping_zones( $request ) {
		$zones = ShippingZones::get_zones();

		$response = [];

		foreach ( $zones as $zone ) {
			$response[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $zone, $request ) );
		}

		return rest_ensure_response( $response );
	}

	public function create_shipping_zone( WP_REST_Request $request ) {
		$shipping_zone = new ShippingZone();
		$this->prepare_item_for_save( $shipping_zone, $request );
		$shipping_zone->save();

		return rest_ensure_response( $this->prepare_item_for_response( $shipping_zone, $request ) );
	}

	public function get_shipping_methods_from_zone( WP_REST_Request $request ) {
		$zone = $this->get_zone( $request );
		if ( is_wp_error( $zone ) ) {
			return $zone;
		}

		return rest_ensure_response( array_values( array_map( fn( $method ) => array_merge(
			$method->get_data(), [
				'instance_id'        => $method->get_instance_id(),
				'method_title'       => $method->get_method_title(),
				'method_description' => $method->get_method_description(),
				'fields'             => $method->get_admin_fields(),
			]
		), $zone->get_shipping_methods() ) ) );
	}

	/**
	 * @throws StoreEngineException
	 */
	public function create_shipping_method( WP_REST_Request $request ) {
		if ( empty( $request->get_param( 'method_id' ) ) ) {
			return new WP_Error( 'missing-required-params', __( 'Shipping method ID is required.', 'storeengine' ) );
		}

		if ( empty( $request->get_param( 'name' ) ) ) {
			return new WP_Error( 'missing-required-params', __( 'Shipping method name is required.', 'storeengine' ) );
		}

		$shipping_methods = ShippingObj::init()->get_shipping_method_class_names();
		$method_id        = sanitize_text_field( $request->get_param( 'method_id' ) );
		if ( ! array_key_exists( $method_id, $shipping_methods ) ) {
			return new WP_Error( 'invalid-value', __( 'Invalid shipping method Id provided.', 'storeengine' ) );
		}
		if ( ! class_exists( $shipping_methods[ $method_id ] ) ) {
			return new WP_Error( 'invalid-value', __( 'Class not found for shipping method Id!', 'storeengine' ) );
		}

		$zone_id = $request->get_param( 'zone_id' ) ? absint( $request->get_param( 'zone_id' ) ) : 0;
		try {
			$zone = new ShippingZone( $zone_id );
		} catch ( StoreEngineException $e ) {
			return new WP_Error( 'invalid-value', $e->getMessage() );
		}
		/** @var ShippingMethod $shipping_method */
		$shipping_method = new $shipping_methods[ $method_id ]();

		if ( 0 === $zone_id && Formatting::string_to_bool( $request->get_param( 'create_zone' ) ) ) {
			$zone_name = $request->get_param( 'zone_name' ) ? sanitize_text_field( $request->get_param( 'zone_name' ) ) : 'Everywhere';
			$zone->set_zone_name( $zone_name );
			$zone->save();
			$zone_id = $zone->get_id();
		}

		try {
			$shipping_method->handle_save_request( array_merge( $request->get_params(), [
				'zone_id' => $zone_id,
			] ) );
		} catch ( StoreEngineException $e ) {
			return new WP_Error( 'error-on-save', $e->getMessage(), [
				'zone_id' => $zone_id,
			] );
		}

		return rest_ensure_response( array_merge(
			$shipping_method->get_data(), [
				'instance_id'        => $shipping_method->get_instance_id(),
				'zone_id'            => $zone_id,
				'zone_name'          => $zone->get_zone_name(),
				'method_title'       => $shipping_method->get_method_title(),
				'method_description' => $shipping_method->get_method_description(),
				'fields'             => $shipping_method->get_admin_fields(),
			]
		) );
	}

	public function get_single_shipping_zone( WP_REST_Request $request ) {
		$shipping_zone = $this->get_zone( $request );

		if ( is_wp_error( $shipping_zone ) ) {
			return $shipping_zone;
		}

		return rest_ensure_response( $this->prepare_item_for_response( $shipping_zone, $request ) );
	}

	public function update_shipping_zone( WP_REST_Request $request ) {
		$shipping_zone = $this->get_zone( $request );
		if ( is_wp_error( $shipping_zone ) ) {
			return $shipping_zone;
		}

		$this->prepare_item_for_save( $shipping_zone, $request );
		$shipping_zone->save();

		return rest_ensure_response( $this->prepare_item_for_response( $shipping_zone, $request ) );
	}

	/**
	 * @param array|ShippingZone $item
	 * @param $request
	 *
	 * @return WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		if ( is_array( $item ) ) {
			$id   = $item['zone_id'];
			$data = array_merge( $item, [
				'shipping_methods' => array_values( array_map( fn( $method ) => array_merge(
					$method->get_data(),
					[
						'instance_id'        => $method->get_instance_id(),
						'method_title'       => $method->get_method_title(),
						'method_description' => $method->get_method_description(),
					]
				), $item['shipping_methods'] ) ),
				'zone_locations'   => array_values( array_map( [ $this, 'add_location_label' ], array_filter( $item['zone_locations'], fn( $item ) => 'postcode' !== $item->type ) ) ),
				'zone_postcode'    => implode( "\n", array_map( fn( $item ) => $item->code, array_filter( $item['zone_locations'], fn( $item ) => 'postcode' === $item->type ) ) ),
			] );
		} else {
			$id   = $item->get_id();
			$data = array_merge( $item->get_data(), [
				'zone_id'                 => $item->get_id(),
				'shipping_methods'        => array_values( array_map( fn( $method ) => array_merge(
					$method->get_data(),
					[
						'instance_id'        => $method->get_instance_id(),
						'method_title'       => $method->get_method_title(),
						'method_description' => $method->get_method_description(),
					]
				), $item->get_shipping_methods() ) ),
				'zone_locations'          => array_values( array_map( [ $this, 'add_location_label' ], array_filter( $item->get_zone_locations(), fn( $item ) => 'postcode' !== $item->type ) ) ),
				'zone_postcode'           => implode( "\n", array_map( fn( $item ) => $item->code, array_filter( $item->get_zone_locations(), fn( $item ) => 'postcode' === $item->type ) ) ),
				'formatted_zone_location' => $item->get_formatted_location( 6 ),
			] );
		}

		$response = rest_ensure_response( $data );
		$links    = [];

		if ( $id ) {
			$links['self'] = [
				'href' => rest_url( sprintf( '/%s/%s/zones/%d', $this->namespace, $this->rest_base, $id ) ),
			];
		}

		$links['collection'] = [
			'href' => rest_url( sprintf( '/%s/%s/zones', $this->namespace, $this->rest_base ) ),
		];

		$response->add_links( $links );

		return $response;
	}

	/**
	 * Prepares one item for create or update operation.
	 *
	 * @param ShippingZone $shipping_zone
	 * @param WP_REST_Request $request Request object.
	 *
	 * @see self::prepare_item_for_database
	 */
	protected function prepare_item_for_save( ShippingZone $shipping_zone, WP_REST_Request $request ) {
		$shipping_zone->set_zone_name( sanitize_text_field( $request->get_param( 'zone_name' ) ) );
		$shipping_zone->save();

		$zone_name = $request->get_param( 'zone_name' );
		if ( ! empty( $zone_name ) ) {
			$shipping_zone->set_zone_name( $zone_name );
		}

		$zone_locations = $request->get_param( 'zone_locations' );
		if ( is_array( $zone_locations ) && ! empty( $zone_locations ) ) {
			$shipping_zone->clear_locations( [ 'state', 'country', 'continent' ] );
			foreach ( $zone_locations as $zone_location ) {
				$location = explode( ':', $zone_location );
				if ( ! $location || ! is_array( $location ) || count( $location ) < 2 ) {
					continue;
				}
				$type = $location[0];
				array_shift( $location );
				$shipping_zone->add_location( implode( ':', $location ), $type );
			}
		}

		$zone_postcodes = $request->get_param( 'zone_postcodes' );

		if ( ! empty( $zone_postcodes ) ) {
			$shipping_zone->clear_locations( 'postcode' );

			// Allow user to input postcodes comma & semicolon as separator and normalize.
			// Don't normalize hyphen or other characters, as zip code can contain different characters too.
			$postcodes = str_replace( [ ';', ',' ], "\n", $zone_postcodes );
			$postcodes = array_filter( array_map( 'strtoupper', array_map( [
				Formatting::class,
				'clean',
			], explode( "\n", $postcodes ) ) ) );

			foreach ( $postcodes as $postcode ) {
				$shipping_zone->add_location( $postcode, 'postcode' );
			}
		}
	}

	public function delete_shipping_zone( WP_REST_Request $request ) {
		$zone = $this->get_zone( $request );
		if ( is_wp_error( $zone ) ) {
			return $zone;
		}

		try {
			$zone->clear_locations();

			global $wpdb;

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->delete( $wpdb->prefix . 'storeengine_shipping_zone_methods', [ 'zone_id' => $zone->get_id() ] );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( ! $result && ! empty( $wpdb->last_error ) ) {
				return new WP_Error( 'invalid-value', __( 'Error deleting shipping zone methods.', 'storeengine' ) );
			}

			$zone->save();
			$zone->delete();
		} catch ( StoreEngineException $e ) {
			return new WP_Error( 'invalid-value', $e->getMessage() );
		}

		return rest_ensure_response( [ 'success' => true ] );
	}

	public function get_shipping_methods() {
		$shipping_methods      = ShippingObj::init()->get_shipping_method_class_names();
		$shipping_methods_data = [];

		foreach ( $shipping_methods as $method_id => $class_name ) {
			if ( ! class_exists( $class_name ) ) {
				continue;
			}
			$shipping_method         = new $class_name();
			$shipping_methods_data[] = [
				'method'      => $method_id,
				'title'       => $shipping_method->get_method_title(),
				'description' => $shipping_method->get_method_description(),
				'fields'      => $shipping_method->get_admin_fields(),
			];
		}

		return rest_ensure_response( $shipping_methods_data );
	}

	public function get_single_shipping_method( WP_REST_Request $request ) {
		$method = $this->get_method( $request );
		if ( is_wp_error( $method ) ) {
			return $method;
		}

		return rest_ensure_response( array_merge(
			$method->get_data(), [
				'instance_id'        => $method->get_instance_id(),
				'method_title'       => $method->get_method_title(),
				'method_description' => $method->get_method_description(),
				'fields'             => $method->get_admin_fields(),
			]
		) );
	}

	public function update_shipping_method( WP_REST_Request $request ) {
		$method = $this->get_method( $request );
		if ( is_wp_error( $method ) ) {
			return $method;
		}

		try {
			$method->handle_save_request( $request->get_params() );
		} catch ( StoreEngineException $e ) {
			return new WP_Error( 'error-on-save', $e->getMessage() );
		}

		return rest_ensure_response( array_merge(
			$method->get_data(), [
				'instance_id'        => $method->get_instance_id(),
				'method_title'       => $method->get_method_title(),
				'method_description' => $method->get_method_description(),
				'fields'             => $method->get_admin_fields(),
			]
		) );
	}

	public function delete_shipping_method( WP_REST_Request $request ) {
		$method = $this->get_method( $request );
		if ( is_wp_error( $method ) ) {
			return $method;
		}

		try {
			$method->delete();
		} catch ( StoreEngineException $e ) {
			return new WP_Error( 'invalid-value', $e->getMessage() );
		}

		return rest_ensure_response( [
			'success' => true,
		] );
	}

	public function get_regions() {
		$allowed_countries   = Countries::init()->get_shipping_countries();
		$shipping_continents = Countries::init()->get_shipping_continents();

		return rest_ensure_response( $this->get_region_options( $allowed_countries, $shipping_continents ) );
	}

	public function sanitize_zone_locations( $value ): array {
		return array_map( 'sanitize_text_field', $value );
	}

	public function permissions_check() {
		return Helper::check_rest_user_cap( 'manage_options' );
	}

	public function add_location_label( $shipping_zone ): array {
		$codes   = explode( ':', $shipping_zone->code );
		$country = $codes[0];
		$state   = '';

		if ( count( $codes ) > 1 ) {
			$state = $codes[1];
		}

		$label = null;
		switch ( $shipping_zone->type ) {
			case 'country':
				$label = Countries::init()->get_countries()[ $country ];
				break;
			case 'state':
				$label = Countries::init()->get_states()[ $country ][ $state ];
				break;
			case 'continent':
				$label = Countries::init()->get_continents()[ $country ]['name'];
				break;
		}

		$shipping_zone = (array) $shipping_zone;

		return array_merge( $shipping_zone, [ 'label' => $label ] );
	}

	/**
	 * Get all available regions.
	 *
	 * @param array $allowed_countries Zone ID.
	 * @param array $shipping_continents Zone ID.
	 */
	private function get_region_options( array $allowed_countries, array $shipping_continents ): array {
		$options = [];
		foreach ( $shipping_continents as $continent_code => $continent ) {
			$continent_data = [
				'value'    => 'continent:' . esc_attr( $continent_code ),
				'label'    => esc_html( $continent['name'] ),
				'children' => [],
			];

			$countries = array_intersect( array_keys( $allowed_countries ), $continent['countries'] );

			foreach ( $countries as $country_code ) {
				$country_data = [
					'value'    => 'country:' . esc_attr( $country_code ),
					'label'    => esc_html( $allowed_countries[ $country_code ] ),
					'children' => [],
				];

				$states = Countries::init()->get_states( $country_code );

				if ( $states ) {
					foreach ( $states as $state_code => $state_name ) {
						$country_data['children'][] = [
							'value' => 'state:' . esc_attr( $country_code . ':' . $state_code ),
							'label' => esc_html( $state_name . ', ' . $allowed_countries[ $country_code ] ),
						];
					}
				}
				$continent_data['children'][] = $country_data;
			}
			$options[] = $continent_data;
		}

		return $options;
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return ShippingMethod|WP_Error
	 */
	private function get_method( WP_REST_Request $request ) {
		$method_id   = sanitize_text_field( $request->get_param( 'method_id' ) );
		$instance_id = absint( $request->get_param( 'instance_id' ) );
		if ( ! $method_id ) {
			return new WP_Error( 'missing-required-params', __( 'Shipping method Id is required.', 'storeengine' ) );
		}
		if ( ! $instance_id ) {
			return new WP_Error( 'missing-required-params', __( 'Shipping method instance id is required.', 'storeengine' ) );
		}

		return ShippingUtils::get_shipping_method( $method_id, $instance_id );
	}

	private function get_zone( WP_REST_Request $request ) {
		$zone_id = absint( $request->get_param( 'zone_id' ) );
		if ( ! $zone_id ) {
			return new WP_Error( 'missing-required-params', __( 'Zone Id is required.', 'storeengine' ) );
		}

		try {
			return new ShippingZone( $zone_id );
		} catch ( StoreEngineException $e ) {
			return new WP_Error( 'invalid-value', __( 'Invalid zone Id provided.', 'storeengine' ) );
		}
	}

}
