<?php

namespace StoreEngine\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Admin\Notices;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Payment_Gateways;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class Settings extends WP_REST_Controller {

	public static function init() {
		$self            = new self();
		$self->namespace = STOREENGINE_PLUGIN_SLUG . '/v1';
		$self->rest_base = 'settings';
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
			'schema' => [ $this, 'get_public_item_schema' ],
		] );

		$gateway_args = $this->get_gateway_args();

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/payment-gateways', [
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_payment_gateway_settings' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'context' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => 'rest_validate_request_arg',
						'default'           => 'edit',
					],
					'config'  => [
						'type'       => 'object',
						'required'   => true,
						'properties' => $gateway_args,
					],
				],
			],
			'schema' => [ $this, 'get_public_item_schema' ],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/verify-payment-gateways', [
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'verify_payment_gateway_settings' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'context' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => 'rest_validate_request_arg',
						'default'           => 'edit',
					],
					'method'  => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => 'rest_validate_request_arg',
						'required'          => true,
					],
					'config'  => [
						'type'       => 'object',
						'required'   => true,
						'properties' => array_merge( ...array_values( $gateway_args ) ),
					],
				],
			],
			'schema' => [ $this, 'get_public_item_schema' ],
		] );
	}

	protected function get_gateway_args(): array {
		$type_mapping = [
			'text'      => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'password'  => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'checkbox'  => [
				'type'              => 'boolean',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'safe_text' => [
				'type'              => 'string',
				'sanitize_callback' => [ Formatting::class, 'sanitize_safe_text_field' ],
				'validate_callback' => [ Formatting::class, 'sanitize_safe_text_field' ],
			],
			'textarea'  => [
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
				'validate_callback' => 'wp_kses_post',
			],
		];

		$fields = [];

		foreach ( Payment_Gateways::init()->get_gateways() as $gateway ) {
			$properties = [
				'is_enabled' => [
					'type'              => 'boolean',
					'validate_callback' => 'rest_validate_request_arg',
					'default'           => false,
				],
				'index'      => [
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
					'validate_callback' => 'rest_validate_request_arg',
					'default'           => 0,
				],
			];

			foreach ( $gateway->get_admin_fields_sorted() as $field => $cfg ) {
				$type = $cfg['type'] ?? 'text';
				if ( 'repeater' === $type ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
					$items = [];
					foreach ( $cfg['fields'] as $name => $repeated ) {
						$repeated_type = $repeated['type'] ?? 'text';
						$item          = [
							'type'              => $type_mapping[ $repeated_type ] ?? 'string',
							'description'       => $repeated['tooltip'] ?? '',
							'default'           => $repeated['default'] ?? '',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						];
						if ( ! empty( $type_mapping[ $type ] ) ) {
							$item = array_merge( $item, $type_mapping[ $type ] );
						}

						$items[ $name ] = $item;
					}
					$schema = [
						'type'        => 'array',
						'description' => $cfg['tooltip'] ?? '',
						'items'       => [
							'type'       => 'object',
							'properties' => $items,
						],
					];
				} else {
					$schema = [
						'type'              => $type_mapping[ $type ] ?? 'string',
						'description'       => $cfg['tooltip'] ?? '',
						'default'           => $cfg['default'] ?? '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					];
					if ( ! empty( $type_mapping[ $type ] ) ) {
						$schema = array_merge( $schema, $type_mapping[ $type ] );
					}
				}
				$properties[ $field ] = $schema;
			}

			$fields[ $gateway->id ] = [
				'type'       => 'object',
				'properties' => $properties,
			];
		}

		return $fields;
	}

	public function get_items( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		global $storeengine_settings;

		// Clone object or it mutates.
		$settings = clone $storeengine_settings;
		Notices::init()->dispatch_notices();
		$settings->admin_notices = array_values( Notices::get_notices() );

		// Return settings in response.
		return rest_ensure_response( apply_filters( 'storeengine/api/settings', $settings ) );
	}

	/**
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_payment_gateway_settings( WP_REST_Request $request ) {
		// @TODO make separate endpoints for each gateway to minimize processing time during saving.
		$error = new WP_Error();
		foreach ( $request->get_param( 'config' ) as $gateway => $payload ) {
			if ( ! Payment_Gateways::init()->get_gateway( $gateway ) ) {
				continue;
			}

			try {
				do_action( "storeengine/api/settings/payment-gateways/update/$gateway", $payload );
			} catch ( StoreEngineException $e ) {
				$error->add( $e->get_wp_error_code(), $e->getMessage(), $e->get_data() );
			}
		}

		return $error->has_errors() ? $error : rest_ensure_response( true );
	}

	public function verify_payment_gateway_settings( WP_REST_Request $request ) {
		if ( ! Payment_Gateways::init()->get_gateway( $request->get_param( 'method' ) ) ) {
			return new WP_Error(
				'invalid_gateway',
				__( 'Payment gateway doesnt exists.', 'storeengine' ),
				[
					'status'  => 404,
					'gateway' => $request->get_param( 'method' ),
				]
			);
		}


		try {
			$gateway = $request->get_param( 'method' );
			do_action( "storeengine/api/settings/payment-gateways/verify/$gateway", $request->get_param( 'config' ) );

			return rest_ensure_response( true );
		} catch ( StoreEngineException $e ) {
			return $e->toWpError();
		}
	}

	public function permissions_check() {
		return Helper::check_rest_user_cap( 'manage_options' );
	}
}
