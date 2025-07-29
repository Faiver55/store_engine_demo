<?php

namespace StoreEngine\Addons\Subscription\API\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SubscriptionSchema {
	protected array $available_fields = [
		'order_id'              => [
			'type'              => 'integer',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'customer_id'           => [
			'type'              => 'integer',
			'required'          => true,
			'validate_callback' => 'rest_validate_request_arg',
		],
		'currency'              => [
			'type'              => 'string',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'start_date'            => [
			'type'              => 'string',
			'required'          => true,
			'validate_callback' => 'rest_validate_request_arg',
		],
		'trial_end_date'        => [
			'type'              => 'string',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'next_payment_date'     => [
			'type'              => 'string',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'end_date'              => [
			'type'              => 'string',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'trial'                 => [
			'type'              => 'integer',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'trial_days'            => [
			'type'              => 'integer',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'payment_duration'      => [
			'type'              => 'integer',
			'required'          => true,
			'minimum'           => 1,
			'validate_callback' => 'rest_validate_request_arg',
		],
		'payment_duration_type' => [
			'type'              => 'string',
			'required'          => true,
			'enum'              => [ 'day', 'week', 'month', 'year' ],
			'validate_callback' => 'rest_validate_request_arg',
		],
		'customer_email'        => [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_email',
			'validate_callback' => 'is_email',
		],
		'billing_address'       => [
			'type'       => 'object',
			'properties' => [
				'first_name' => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'last_name'  => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'company'    => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'address_1'  => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'address_2'  => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'city'       => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'state'      => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'postcode'   => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'country'    => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'email'      => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_email',
					'validate_callback' => 'is_email',
				],
				'phone'      => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
			],
		],
		'shipping_address'      => [
			'type'       => 'object',
			'properties' => [
				'first_name' => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'last_name'  => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'company'    => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'address_1'  => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'address_2'  => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'city'       => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'state'      => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'postcode'   => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'country'    => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'email'      => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_email',
					'validate_callback' => 'is_email',
				],
				'phone'      => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
			],
		],
		'purchase_items'        => [
			'type'  => 'array',
			'items' => [
				'type'       => 'object',
				'properties' => [
					'product_id'  => [
						'type'              => 'integer',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'price_id'    => [
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => 'rest_validate_request_arg',
					],
					'product_qty' => [
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			],
		],
		'order_note'            => [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'payment_method'        => [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'status'                => [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'payment_method_title'  => [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'coupons'               => [
			'type'  => 'array',
			'items' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
		],
		'page'                  => [
			'type'              => 'integer',
			'default'           => 1,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
			'minimum'           => 1,
		],
		'per_page'              => [
			'type'              => 'integer',
			'default'           => 10,
			'minimum'           => 1,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		],
		'search'                => [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		],
	];

	public function get_fields( array $includes ): array {
		$fields = [];
		foreach ( $includes as $field ) {
			if ( array_key_exists( $field, $this->available_fields ) ) {
				$fields[ $field ] = $this->available_fields[ $field ];
			}
		}

		return $fields;
	}

	public function create_args(): array {
		return $this->get_fields( [
			'status',
			'order_id',
			'customer_id',
			'currency',
			'customer_email',
			'start_date',
			'trial_end_date',
			'end_date',
			'next_payment_date',
			'trial',
			'trial_days',
			'payment_duration',
			'payment_duration_type',
			'billing_address',
			'shipping_address',
			'purchase_items',
			'order_note',
			'coupons',
			'payment_method',
			'payment_method_title',
		] );
	}

	public function edit_args(): array {
		return $this->get_fields( [
			'status',
			'customer_id',
			'currency',
			'customer_email',
			'start_date',
			'trial_end_date',
			'end_date',
			'next_payment_date',
			'trial',
			'trial_days',
			'payment_duration',
			'payment_duration_type',
			'billing_address',
			'shipping_address',
			'order_note',
			'payment_method',
			'payment_method_title',
		] );
	}

	public function read_args(): array {
		return $this->get_fields( [
			'page',
			'per_page',
			'search',
		] );
	}

	public function delete_args(): array {
		return $this->get_fields( [] );
	}
}
