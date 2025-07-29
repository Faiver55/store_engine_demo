<?php

namespace StoreEngine\Addons\Membership;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Database {

	public static function init() {
		$self = new self();
		add_action( 'init', [ $self, 'create_membership_post_type' ] );
		add_action( 'rest_api_init', [ $self, 'register_membership_meta' ] );
	}

	public function create_membership_post_type() {
		$post_type = STOREENGINE_MEMBERSHIP_POST_TYPE;
		register_post_type(
			$post_type,
			array(
				'labels'                => array(
					'name'               => esc_html__( 'Access Groups', 'storeengine' ),
					'singular_name'      => esc_html__( 'Access Group', 'storeengine' ),
					'search_items'       => esc_html__( 'Search Access Group', 'storeengine' ),
					'parent_item_colon'  => esc_html__( 'Parent Access Group:', 'storeengine' ),
					'not_found'          => esc_html__( 'No Access Group found.', 'storeengine' ),
					'not_found_in_trash' => esc_html__( 'No Access Group found in Trash.', 'storeengine' ),
					'archives'           => esc_html__( 'Access Group archives', 'storeengine' ),
				),
				'public'                => true,
				'publicly_queryable'    => true,
				'show_ui'               => true,
				'show_in_menu'          => false,
				'show_in_admin_bar'     => false,
				'show_in_nav_menus'     => false,
				'hierarchical'          => false,
				'has_archive'           => true,
				'rewrite'               => array( 'slug' => 'access_groups' ),
				'query_var'             => true,
				'delete_with_user'      => false,
				'supports'              => array(
					'title',
					'editor',
					'author',
					'thumbnail',
					'excerpt',
					'trackbacks',
					'custom-fields',
					'comments',
					'post-formats',
				),
				'show_in_rest'          => true,
				'rest_base'             => $post_type,
				'rest_namespace'        => STOREENGINE_PLUGIN_SLUG . '/v1',
				'rest_controller_class' => 'WP_REST_Posts_Controller',
				'capability_type'       => 'post',
				'capabilities'          => array(
					'edit_post'          => 'edit_storeengine_group',
					'read_post'          => 'read_storeengine_group',
					'delete_post'        => 'delete_storeengine_group',
					'delete_posts'       => 'delete_storeengine_groups',
					'edit_posts'         => 'edit_storeengine_groups',
					'edit_others_posts'  => 'edit_others_storeengine_groups',
					'publish_posts'      => 'publish_storeengine_groups',
					'read_private_posts' => 'read_private_storeengine_groups',
					'create_posts'       => 'edit_storeengine_groups',
				),
			)
		);
	}

	public function register_membership_meta() {
		register_meta(
			'post',
			'_storeengine_membership_content_protect_types',
			array(
				'object_subtype' => STOREENGINE_MEMBERSHIP_POST_TYPE,
				'type'           => 'object',
				'single'         => true,
				'show_in_rest'   => [
					'schema' => array(
						'additionalProperties' => true,
						'items'                => array(
							'type'       => 'object',
							'properties' => [
								'label' => array(
									'type' => 'string',
								),
								'value' => array(
									'type' => 'string',
								),
							],
						),
					),
				],
			)
		);

		// Membership Content Protect Excluded Items
		register_meta(
			'post',
			'_storeengine_membership_content_protect_excluded_items',
			array(
				'object_subtype' => STOREENGINE_MEMBERSHIP_POST_TYPE,
				'type'           => 'array',
				'single'         => true,
				'show_in_rest'   => [
					'schema' => array(
						'additionalProperties' => true,
						'items'                => array(
							'type'       => 'object',
							'properties' => [
								'label' => array(
									'type' => 'string',
								),
								'value' => array(
									'type' => 'string',
								),
							],
						),
					),
				],
			)
		);

		// Membership Attachments
		register_meta(
			'post',
			'_storeengine_membership_attachments',
			array(
				'object_subtype' => STOREENGINE_MEMBERSHIP_POST_TYPE,
				'type'           => 'array',
				'single'         => true,
				'show_in_rest'   => [
					'schema' => array(
						'type'                 => 'array',
						'additionalProperties' => true,
						'items'                => array(
							'type'  => array(
								'type' => 'integer',
							),
							'value' => array(
								'type' => 'integer',
							),
						),
					),
				],
			)
		);

		// Membership Authorization
		register_meta(
			'post',
			'_storeengine_membership_authorization',
			array(
				'object_subtype' => STOREENGINE_MEMBERSHIP_POST_TYPE,
				'type'           => 'object',
				'single'         => true,
				'show_in_rest'   => [
					'schema' => array(
						'additionalProperties' => true,
						'items'                => array(
							'type'       => 'object',
							'properties' => [
								'type'  => array(
									'type' => 'string',
								),
								'value' => array(
									'type' => 'string',
								),
							],
						),
					),
				],
			)
		);

		// Membership Expiration
		register_meta(
			'post',
			'_storeengine_membership_expiration',
			array(
				'object_subtype' => STOREENGINE_MEMBERSHIP_POST_TYPE,
				'type'           => 'object',
				'single'         => true,
				'show_in_rest'   => [
					'schema' => array(
						'additionalProperties' => true,
						'items'                => array(
							'type'       => 'object',
							'properties' => [
								'is_enable_expiration' => array(
									'type' => 'boolean',
								),
								'date_type'            => array(
									'type' => 'string',
								),
								'fixed_date_duration'  => array(
									'type' => 'integer',
								),
								'specific_date'        => array(
									'type' => 'string',
								),
							],
						),
					),
				],
			)
		);

		// Membership User Roles
		register_meta(
			'post',
			'_storeengine_membership_user_roles',
			array(
				'object_subtype' => STOREENGINE_MEMBERSHIP_POST_TYPE,
				'type'           => 'array',
				'single'         => true,
				'show_in_rest'   => [
					'schema' => array(
						'additionalProperties' => true,
						'items'                => array(
							'type'       => 'object',
							'properties' => [
								'value' => array(
									'type' => 'string',
								),
								'label' => array(
									'type' => 'string',
								),
							],
						),
					),
				],
			)
		);

		// User meta
		register_meta(
			'user',
			'_storeengine_membership_user_meta',
			array(
				'object_subtype' => STOREENGINE_MEMBERSHIP_POST_TYPE,
				'type'           => 'array',
				'single'         => true,
				'show_in_rest'   => [
					'schema' => array(
						'additionalProperties' => true,
						'items'                => array(
							'type'       => 'object',
							'properties' => [
								'plans' => array(
									'type'  => 'array',
									'items' => array(
										'content_protect_types' => array(
											'type'  => 'array',
											'items' => array(
												'type' => 'string',
											),
										),
										'excluded_ids'    => array(
											'type'  => 'array',
											'items' => array(
												'type' => 'integer',
											),
										),
										'expiration_date' => array(
											'type' => 'string',
										),
									),
								),
							],
						),
					),
				],
			)
		);

		// Features
		register_meta(
			'post',
			'_storeengine_membership_features',
			array(
				'object_subtype' => STOREENGINE_MEMBERSHIP_POST_TYPE,
				'type'           => 'array',
				'single'         => true,
				'show_in_rest'   => [
					'schema' => array(
						'additionalProperties' => true,
						'items'                => array(
							'type'       => 'object',
							'properties' => [
								'label' => array(
									'type' => 'string',
								),
								'icon'  => array(
									'type' => 'string',
								),
							],
						),
					),
				],
			)
		);

		// membership priority
		register_meta(
			'post',
			'_storeengine_membership_priority',
			array(
				'object_subtype' => STOREENGINE_MEMBERSHIP_POST_TYPE,
				'type'           => 'string',
				'single'         => true,
				'show_in_rest'   => [
					'schema' => [
						'type' => 'string',
					],
				],
			)
		);
	}

	public function permissions_check() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_forbidden_context',
				esc_html__( 'Sorry, you are not allowed to get membership data.', 'storeengine' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
	}
}
