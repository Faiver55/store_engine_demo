<?php
namespace StoreEngine\API\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CartSchema {

	public function get_public_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'order',
			'type'       => 'object',
			'properties' => array(
				'cart_id'       => [
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
					'validate_callback' => 'rest_validate_request_arg',
				],

				'user_id'       => [
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'cart_data'     => [
					'type'              => 'stringstring',
					'sanitize_callback' => 'absint',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'cart_hash'     => [
					'type'              => 'string',
					'sanitize_callback' => 'absint',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'date_created'  => [
					'type'              => 'string',
					'format'            => 'date-time',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'date_modified' => [
					'type'              => 'string',
					'format'            => 'date-time',
					'sanitize_callback' => 'sanitize_text_field',
				],
			),
		);

		return apply_filters( 'storeengine/api/cart/get_public_cart_schema', $schema );
	}

	public function get_post_cart_schema() {
		$schema = [
			'cart_id'       => [
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],

			'user_id'       => [
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'cart_data'     => [
				'type'              => 'string',
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'cart_hash'     => [
				'type'              => 'string',
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'date_created'  => [
				'type'              => 'string',
				'format'            => 'date-time',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'date_modified' => [
				'type'              => 'string',
				'format'            => 'date-time',
				'sanitize_callback' => 'sanitize_text_field',
			],

		];
		return apply_filters( 'storeengine/api/cart/cart_schema', $schema );
	}

	public function get_cart_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'order',
			'type'       => 'object',
			'properties' => [
				'cart_id'   => [
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
					'validate_callback' => 'rest_validate_request_arg',
				],

				'user_id'   => [
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'cart_data' => [
					'type'              => 'stringstring',
					'sanitize_callback' => 'absint',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'cart_hash' => [
					'type'              => 'string',
					'sanitize_callback' => 'absint',
					'validate_callback' => 'rest_validate_request_arg',
				],
			],
		];

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
