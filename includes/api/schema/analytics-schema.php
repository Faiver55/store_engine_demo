<?php
namespace StoreEngine\API\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait AnalyticsSchema {
	public function get_collection_params() {
		return array(
			'page'       => array(
				'description'       => __( 'Current page of the collection.', 'storeengine' ),
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
				'minimum'           => 1,
			),
			'per_page'   => array(
				'description'       => __( 'Maximum number of items to be returned in result set.', 'storeengine' ),
				'type'              => 'integer',
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'end_date'   => array(
				'description'       => __( 'Analytics end_date query date', 'storeengine' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'start_date' => array(
				'description'       => __( 'Analytics start_date query date', 'storeengine' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'search'     => array(
				'description'       => __( 'Limit results to those matching a string.', 'storeengine' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}
}
