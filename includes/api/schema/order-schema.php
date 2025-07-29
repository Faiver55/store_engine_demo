<?php

namespace StoreEngine\API\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait OrderSchema {

	protected function address_schema(): array {
		return [
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
					'validate_callback' => 'rest_validate_request_arg',
				],
				'phone'      => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
			],
		];
	}

	public function get_public_item_schema() {
		$schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'order',
			'type'       => 'object',
			'properties' => [
				'id'                    => [
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'status'                => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'currency'              => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],

				'type'                  => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],

				'tax_amount'            => [
					'type'              => 'number',
					'validate_callback' => 'rest_validate_request_arg',
				],

				'coupons'               => [
					'type'       => 'object',
					'properties' => [
						'code'   => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'amount' => [
							'type'              => 'float',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],

				'refunds_total'         => [
					'type'              => 'number',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'total_amount'          => [
					'type'              => 'number',
					'validate_callback' => 'rest_validate_request_arg',
				],

				'customer_id'           => [
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'customer_email'        => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_email',
					'validate_callback' => 'is_email',
				],
				'customer_name'         => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'billing_email'         => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_email',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'date_created_gmt'      => [
					'type'              => 'string',
					'format'            => 'date-time',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'order_placed_date_gmt' => [
					'type'              => 'string',
					'format'            => 'date-time',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'order_placed_date'     => [
					'type'              => 'string',
					'format'            => 'date-time',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'payment_method'        => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],

				'payment_method_title'  => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'transaction_id'        => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'customer_note'         => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'rest_validate_request_arg',
				],
				'meta'                  => [
					'type'       => 'object',
					'properties' => [
						'url' => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
				'purchase_items'        => [
					'type'       => 'object',
					'properties' => [
						'product_id'          => [
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						],
						'variation_id'        => [
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						],
						'product_type'        => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						],
						'metadata'            => [
							'type'       => 'object',
							'properties' => [
								'key'           => [
									'type'              => 'string',
									'sanitize_callback' => 'sanitize_text_field',
									'validate_callback' => 'rest_validate_request_arg',
								],
								'value'         => [
									'type'              => 'string',
									'sanitize_callback' => 'sanitize_text_field',
									'validate_callback' => 'rest_validate_request_arg',
								],
								'display_key'   => [
									'type'              => 'string',
									'sanitize_callback' => 'sanitize_text_field',
									'validate_callback' => 'rest_validate_request_arg',
								],
								'display_value' => [
									'type'              => 'string',
									'sanitize_callback' => 'sanitize_text_field',
									'validate_callback' => 'rest_validate_request_arg',
								],
							],
						],
						'product_qty'         => [
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						],
						'coupon_amount'       => [
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						],
						'tax_amount'          => [
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						],
						'shipping_amount'     => [
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						],
						'shipping_tax_amount' => [
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						],
					],
				],
				'refunds'               => [
					'type'       => 'object',
					'properties' => [
						'id'         => [
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						],
						'amount'     => [
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
						'refund_by'  => [
							'type'       => 'object',
							'properties' => [
								'user_id'      => [
									'type'              => 'integer',
									'sanitize_callback' => 'absint',
								],
								'name'         => [
									'type'              => 'string',
									'sanitize_callback' => 'sanitize_text_field',
								],
								'display_name' => [
									'type'              => 'string',
									'sanitize_callback' => 'sanitize_text_field',
								],
							],
						],
						'created_at' => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
				'billing_address'       => $this->address_schema(),
				'shipping_address'      => $this->address_schema(),
			],
		];

		return apply_filters( 'storeengine/api/order/public_item_schema', $schema );
	}

	public function get_post_item_schema() {
		$schema = [
			'customer_id'      => [
				'type'              => 'integer',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'billing_address'  => $this->address_schema(),
			'shipping_address' => $this->address_schema(),
			'purchase_items'   => [
				'type'       => 'object',
				'properties' => [
					'product_id'   => [
						'type'              => 'integer',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'price_id'     => [
						'type'              => 'integer',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'variation_id' => [
						'type'              => 'integer',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'product_qty'  => [
						'type'              => 'integer',
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			],
			'payment_method'   => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'order_note'       => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'billing_email'    => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'status'           => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'coupon_code'      => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
		];

		return apply_filters( 'storeengine/api/order/item_schema', $schema );
	}

	public function get_item_schema(): array {
		$this->schema = [
			'status'               => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'currency'             => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'type'                 => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'tax_amount'           => [
				'type'              => 'number',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'total_amount'         => [
				'type'              => 'number',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'customer_id'          => [
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'billing_email'        => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'payment_method'       => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'payment_method_title' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'customer_note'        => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'transaction_id'       => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'meta'                 => [
				'type'       => 'object',
				'properties' => [
					'url' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			],
			'purchase_items'       => [
				'type'       => 'object',
				'properties' => [
					'product_id'          => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'variation_id'        => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'product_qty'         => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'coupon_amount'       => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'tax_amount'          => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'shipping_amount'     => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'shipping_tax_amount' => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			],
			'billing_address'      => $this->address_schema(),
			'shipping_address'     => $this->address_schema(),
		];

		return $this->add_additional_fields_schema( $this->schema );
	}
}
