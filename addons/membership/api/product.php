<?php

namespace StoreEngine\Addons\Membership\Api;

use StoreEngine\Integrations\IntegrationTrait;
use StoreEngine\Utils\Helper;
use WP_Post;

class Product {

	use IntegrationTrait;

	public static function init() {
		$self = new self();
		$self->init_integration();

		add_action( 'rest_api_init', [ $self, 'register_rest_fields' ] );
	}

	public function register_rest_fields() {
		register_rest_field( 'storeengine_groups', 'prices', [
			'schema'          => [
				'description' => __( 'Membership prices', 'storeengine' ),
				'type'        => 'array',
				'items'       => [
					'type'       => 'object',
					'required'   => [ 'price' ],
					'properties' => [
						'id'                    => [
							'type' => 'integer',
						],
						'product_id'            => [
							'type' => 'integer',
						],
						'type'                  => [
							'type' => 'string',
						],
						'price_name'            => [
							'type' => 'string',
						],
						'price_type'            => [
							'type' => 'string',
						],
						'price'                 => [
							'type' => 'number',
						],
						'compare_price'         => [
							'type' => [ 'number', 'null' ],
						],
						'payment_duration'      => [
							'type' => 'number',
						],
						'payment_duration_type' => [
							'type' => 'string',
						],
						'order'                 => [
							'type' => 'integer',
						],
					],
				],
				'context'     => [ 'view', 'edit' ],
			],
			'get_callback'    => [ $this, 'get_product' ],
			'update_callback' => [ $this, 'save_product' ],
		] );
	}

	public function get_product( array $data ): array {
		$integrations = Helper::get_integration_repository_by_id( $this->integration_name, $data['id'] );
		if ( empty( $integrations ) ) {
			return [];
		}

		$prices = [];
		foreach ( $integrations as $integration ) {
			$prices[] = $this->format_price( $integration->integration->get_id(), $integration->price );
		}

		return $prices;
	}

	public function save_product( array $value, WP_Post $post ) {
		$this->item_id    = $post->ID;
		$this->item_title = $post->post_title;
		$this->prices     = $value;

		$this->handle_integrations();
	}

	protected function set_integration_config(): void {
		$this->integration_name = 'storeengine/membership-addon';
	}
}
