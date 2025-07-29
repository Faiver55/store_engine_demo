<?php

namespace StoreEngine\Addons\Webhooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Database {

	public static function init() {
		$self = new self();
		add_action( 'init', [ $self, 'create_storeengine_webhook_post_type' ] );
		add_action( 'rest_api_init', [ $self, 'register_storeengine_webhook_meta' ] );
	}

	public function create_storeengine_webhook_post_type() {
		register_post_type(
			'storeengine_webhook',
			[
				'labels'                => [
					'name'               => esc_html__( 'Webhooks', 'storeengine' ),
					'singular_name'      => esc_html__( 'Webhook', 'storeengine' ),
					'search_items'       => esc_html__( 'Search webhooks', 'storeengine' ),
					'parent_item_colon'  => esc_html__( 'Parent webhooks:', 'storeengine' ),
					'not_found'          => esc_html__( 'No webhooks found.', 'storeengine' ),
					'not_found_in_trash' => esc_html__( 'No webhooks found in Trash.', 'storeengine' ),
					'archives'           => esc_html__( 'webhook archives', 'storeengine' ),
				],
				'public'                => false,
				'publicly_queryable'    => false,
				'show_ui'               => false,
				'show_in_menu'          => false,
				'hierarchical'          => false,
				'rewrite'               => false,
				'query_var'             => false,
				'has_archive'           => false,
				'delete_with_user'      => false,
				'supports'              => [ 'title', 'author', 'custom-fields' ],
				'show_in_rest'          => true,
				'rest_base'             => 'webhook',
				'rest_namespace'        => STOREENGINE_PLUGIN_SLUG . '/v1',
				'rest_controller_class' => 'WP_REST_Posts_Controller',
				'capability_type'       => 'post',
				'capabilities'          => [
					'edit_post'          => 'manage_options',
					'read_post'          => 'manage_options',
					'delete_post'        => 'manage_options',
					'delete_posts'       => 'manage_options',
					'edit_posts'         => 'manage_options',
					'edit_others_posts'  => 'manage_options',
					'publish_posts'      => 'manage_options',
					'read_private_posts' => 'manage_options',
					'create_posts'       => 'manage_options',
				],
			]
		);
	}

	public function register_storeengine_webhook_meta() {
		register_meta(
			'post',
			'_storeengine_webhook_delivery_url',
			[
				'object_subtype'    => 'storeengine_webhook',
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_url',
			]
		);

		register_meta(
			'post',
			'_storeengine_webhook_secret',
			[
				'object_subtype'    => 'storeengine_webhook',
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			]
		);

		register_meta(
			'post',
			'_storeengine_webhook_events',
			[
				'object_subtype' => 'storeengine_webhook',
				'type'           => 'array',
				'single'         => true,
				'show_in_rest'   => [
					'schema' => [
						'items' => [
							'type' => 'string',
							'enum' => Webhooks::get_events(),
						],
					],
				],
			]
		);
	}
}
