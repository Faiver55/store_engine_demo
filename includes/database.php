<?php

namespace StoreEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use StoreEngine\Database\{CreateApiKeys,
	CreateAttributeTaxonomies,
	CreateCart,
	CreateCustomerLookup,
	CreateDownloadableProductPermissions,
	CreateDownloadLog,
	CreateIntegration,
	CreateLog,
	CreateOrderAddresses,
	CreateOrderItems,
	CreateOrderMetaTable,
	CreateOrderOperationalData,
	CreateOrderProductLookup,
	CreateOrderTable,
	CreatePaymentTokenMeta,
	CreatePaymentTokens,
	CreateProductVariationMetaTable,
	CreateShippingZoneLocations,
	CreateVariationsTermsRelationTable,
	CreateProductDownloadDirectories,
	CreateProductPriceTable,
	CreateProductVariationsTable,
	CreateShippingZoneMethods,
	CreateShippingZones,
	CreateTaxRateLocations,
	CreateTaxRates
};
use StoreEngine\Classes\enums\ProductTaxStatus;
use StoreEngine\database\CreateOrderItemMeta;
use StoreEngine\Traits\Singleton;
use StoreEngine\Classes\Attributes;
use StoreEngine\Utils\Helper;

class Database {

	use Singleton;

	protected function __construct() {
		$this->register_database_table_name();

		add_action( 'switch_blog', [ $this, 'wpdb_table_fix' ], 0 );

		add_action( 'init', [ $this, 'register_product_post_type' ], 5 );
		add_action( 'init', [ $this, 'register_coupon_post_type' ], 5 );

		add_action( 'init', [ $this, 'maybe_flush_rewrite_rules' ], 5 );
		add_action( 'storeengine/flush_rewrite_rules', [ __CLASS__, 'flush_rewrite_rules' ] );

		add_action( 'rest_api_init', [ $this, 'register_product_meta' ] );
		add_action( 'rest_api_init', [ $this, 'register_coupon_meta' ] );
	}

	public function maybe_flush_rewrite_rules() {
		if ( 'yes' === get_option( 'storeengine_required_rewrite_flush' ) ) {
			update_option( 'storeengine_required_rewrite_flush', 'no' );
			self::flush_rewrite_rules();
		}
	}

	public static function flush_rewrite_rules() {
		flush_rewrite_rules();
	}

	public function register_database_table_name() {
		global $wpdb;

		// @TODO add all the tables.
		$tables = [
			'payment_tokenmeta'   => 'storeengine_payment_tokenmeta',
			'order_itemmeta'      => 'storeengine_order_item_meta',
			'product_meta_lookup' => 'storeengine_product_meta_lookup',
			'tax_rate_classes'    => 'storeengine_tax_rate_classes',
			'reserved_stock'      => 'storeengine_reserved_stock',
			'store_orders'        => 'storeengine_orders',
			'ordermeta'           => 'storeengine_orders_meta',
		];

		/**
		 * @XXX make sure to add the meta types for handling cache last change.
		 *
		 * @see Hooks::handle_cache_last_changed()
		 * @see wp_cache_set_last_changed()
		 */

		foreach ( $tables as $name => $table ) {
			$wpdb->$name    = $wpdb->prefix . $table;
			$wpdb->tables[] = $table;
		}
	}

	public function wpdb_table_fix() {
		$this->register_database_table_name();
	}

	public static function create_initial_custom_table() {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$charset_collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';

		CreateIntegration::up( $wpdb->prefix, $charset_collate );
		CreateProductPriceTable::up( $wpdb->prefix, $charset_collate );
		CreateAttributeTaxonomies::up( $wpdb->prefix, $charset_collate );
		CreateProductVariationsTable::up( $wpdb->prefix, $charset_collate );
		CreateProductVariationMetaTable::up( $wpdb->prefix, $charset_collate );
		CreateVariationsTermsRelationTable::up( $wpdb->prefix, $charset_collate );
		CreateOrderTable::up( $wpdb->prefix, $charset_collate );
		CreateOrderMetaTable::up( $wpdb->prefix, $charset_collate );
		CreateOrderProductLookup::up( $wpdb->prefix, $charset_collate );
		CreateOrderItems::up( $wpdb->prefix, $charset_collate );
		CreateOrderItemMeta::up( $wpdb->prefix, $charset_collate );
		CreateOrderOperationalData::up( $wpdb->prefix, $charset_collate );
		CreateOrderAddresses::up( $wpdb->prefix, $charset_collate );
		CreateShippingZoneMethods::up( $wpdb->prefix, $charset_collate );
		CreateShippingZones::up( $wpdb->prefix, $charset_collate );
		CreateShippingZoneLocations::up( $wpdb->prefix, $charset_collate );
		CreateApiKeys::up( $wpdb->prefix, $charset_collate );
		CreateCart::up( $wpdb->prefix, $charset_collate );
		CreatePaymentTokens::up( $wpdb->prefix, $charset_collate );
		CreatePaymentTokenMeta::up( $wpdb->prefix, $charset_collate );
		CreateTaxRateLocations::up( $wpdb->prefix, $charset_collate );
		CreateTaxRates::up( $wpdb->prefix, $charset_collate );
		CreateApiKeys::up( $wpdb->prefix, $charset_collate );
		CreateLog::up( $wpdb->prefix, $charset_collate );
		CreateCustomerLookup::up( $wpdb->prefix, $charset_collate );
		CreateDownloadableProductPermissions::up( $wpdb->prefix, $charset_collate );
		CreateDownloadLog::up( $wpdb->prefix, $charset_collate );
		CreateProductDownloadDirectories::up( $wpdb->prefix, $charset_collate );
		// Store DB Version
		update_option( 'storeengine_db_version', STOREENGINE_DB_VERSION, false );
	}

	public function register_product_post_type() {
		$permalinks   = Helper::get_permalink_structure();
		$shop_page_id = Helper::get_settings( 'shop_page' );
		$has_archive  = get_post( $shop_page_id ) ? urldecode( get_page_uri( $shop_page_id ) ) : 'shop';

		// Registering Product CPT
		register_post_type( Helper::PRODUCT_POST_TYPE, [
			'labels'                => [
				'name'               => esc_html__( 'Products', 'storeengine' ),
				'add_new'            => esc_html__( 'Add New Product', 'storeengine' ),
				'singular_name'      => esc_html__( 'Product', 'storeengine' ),
				'search_items'       => esc_html__( 'Search Products', 'storeengine' ),
				'parent_item_colon'  => esc_html__( 'Parent Products:', 'storeengine' ),
				'not_found'          => esc_html__( 'No Products found.', 'storeengine' ),
				'not_found_in_trash' => esc_html__( 'No Products found in Trash.', 'storeengine' ),
				'archives'           => esc_html__( 'Product archives', 'storeengine' ),
			],
			'public'                => true,
			'publicly_queryable'    => true,
			'show_ui'               => true,
			'show_in_menu'          => false,
			'show_in_admin_bar'     => false,
			'show_in_nav_menus'     => false,
			'hierarchical'          => true,
			'query_var'             => true,
			'delete_with_user'      => false,
			'supports'              => [
				'title',
				'editor',
				'author',
				'thumbnail',
				'excerpt',
				'trackbacks',
				'custom-fields',
				'comments',
				'post-formats',
			],
			'has_archive'           => $has_archive,
			'rewrite'               => [ 'slug' => $permalinks['product_rewrite_slug'] ],
			'show_in_rest'          => true,
			'rest_base'             => Helper::PRODUCT_POST_TYPE,
			'rest_namespace'        => STOREENGINE_PLUGIN_SLUG . '/v1',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
			'capability_type'       => 'post',
			'capabilities'          => [
				'edit_post'          => 'edit_storeengine_product',
				'read_post'          => 'read_storeengine_product',
				'delete_post'        => 'delete_storeengine_product',
				'edit_posts'         => 'edit_storeengine_products',
				'edit_others_posts'  => 'edit_storeengine_others_products',
				'publish_posts'      => 'publish_storeengine_products',
				'read_private_posts' => 'read_private_storeengine_products',
			],
		] );

		// Registering Product Category Taxonomy
		register_taxonomy( Helper::PRODUCT_CATEGORY_TAXONOMY, Helper::PRODUCT_POST_TYPE, [
			'hierarchical'          => true,
			'query_var'             => true,
			'public'                => true,
			'show_ui'               => false,
			'show_admin_column'     => false,
			'_builtin'              => true,
			'capabilities'          => [
				'manage_terms' => 'manage_categories',
				'edit_terms'   => 'edit_categories',
				'delete_terms' => 'delete_categories',
				'assign_terms' => 'assign_categories',
			],
			'show_in_rest'          => true,
			'rest_base'             => Helper::PRODUCT_CATEGORY_TAXONOMY,
			'rest_namespace'        => STOREENGINE_PLUGIN_SLUG . '/v1',
			'rest_controller_class' => 'WP_REST_Terms_Controller',
			'rewrite'               => [
				'slug'         => $permalinks['category_rewrite_slug'],
				'with_front'   => false,
				'hierarchical' => true,
			],
		] );

		// Registering Product Tag Taxonomy
		register_taxonomy( Helper::PRODUCT_TAG_TAXONOMY, Helper::PRODUCT_POST_TYPE, [
			'hierarchical'          => false,
			'query_var'             => true,
			'public'                => true,
			'show_ui'               => false,
			'show_admin_column'     => false,
			'_builtin'              => true,
			'capabilities'          => [
				'manage_terms' => 'manage_post_tags',
				'edit_terms'   => 'edit_post_tags',
				'delete_terms' => 'delete_post_tags',
				'assign_terms' => 'assign_post_tags',
			],
			'show_in_rest'          => true,
			'rest_base'             => Helper::PRODUCT_TAG_TAXONOMY,
			'rest_namespace'        => STOREENGINE_PLUGIN_SLUG . '/v1',
			'rest_controller_class' => 'WP_REST_Terms_Controller',
			'rewrite'               => [
				'slug'       => $permalinks['tag_rewrite_slug'],
				'with_front' => false,
			],
		] );

		// Registering Product Attributes As Taxonomy
		foreach ( ( new Attributes() )->get_all_names() as $slug => ['label' => $label, 'public' => $public] ) {
			self::register_attribute_taxonomy( $slug, $label, $public );
		}
	}

	public function register_coupon_post_type() {
		register_post_type( Helper::COUPON_POST_TYPE, [
			'labels'                => [
				'name'               => esc_html__( 'Coupon', 'storeengine' ),
				'add_new'            => esc_html__( 'Add New Coupon', 'storeengine' ),
				'singular_name'      => esc_html__( 'Coupon', 'storeengine' ),
				'search_items'       => esc_html__( 'Search Coupon', 'storeengine' ),
				'parent_item_colon'  => esc_html__( 'Parent Coupons:', 'storeengine' ),
				'not_found'          => esc_html__( 'No Coupons found.', 'storeengine' ),
				'not_found_in_trash' => esc_html__( 'No Coupons found in Trash.', 'storeengine' ),
				'archives'           => esc_html__( 'Coupon archives', 'storeengine' ),
			],
			'public'                => true,
			'publicly_queryable'    => true,
			'show_ui'               => true,
			'show_in_menu'          => false,
			'show_in_admin_bar'     => false,
			'show_in_nav_menus'     => false,
			'hierarchical'          => true,
			'query_var'             => true,
			'delete_with_user'      => false,
			'supports'              => [ 'title', 'editor', 'author', 'custom-fields' ],
			'has_archive'           => true,
			'rewrite'               => [ 'slug' => 'coupon' ],
			'show_in_rest'          => true,
			'rest_base'             => Helper::COUPON_POST_TYPE,
			'rest_namespace'        => STOREENGINE_PLUGIN_SLUG . '/v1',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
			'capability_type'       => 'post',
			'capabilities'          => [
				'edit_post'          => 'edit_storeengine_coupon',
				'read_post'          => 'read_storeengine_coupon',
				'delete_post'        => 'delete_storeengine_coupon',
				'edit_posts'         => 'edit_storeengine_coupons',
				'edit_others_posts'  => 'edit_storeengine_others_coupons',
				'publish_posts'      => 'publish_storeengine_coupons',
				'read_private_posts' => 'read_private_storeengine_coupons',
			],
		] );
	}

	public function register_product_meta() {
		$product_meta = [
			'_storeengine_product_shipping_type'           => 'string',
			'_storeengine_product_physical_weight'         => 'string',
			'_storeengine_product_physical_weight_unit'    => 'string',
			'_storeengine_product_physical_length'         => 'string',
			'_storeengine_product_physical_width'          => 'string',
			'_storeengine_product_physical_height'         => 'string',
			'_storeengine_product_physical_dimension_unit' => 'string',
			'_storeengine_product_digital_auto_complete'   => 'boolean',
			'_storeengine_product_hide'                    => 'boolean',
		];

		foreach ( $product_meta as $meta_key => $product_meta_value_type ) {
			register_meta( 'post', $meta_key, [
				'object_subtype' => Helper::PRODUCT_POST_TYPE,
				'type'           => $product_meta_value_type,
				'single'         => true,
				'show_in_rest'   => true,
			] );
		}

		register_meta( 'post', '_storeengine_product_gallery_ids', [
			'object_subtype' => Helper::PRODUCT_POST_TYPE,
			'type'           => 'array',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'title'       => __( 'Gallery', 'storeengine' ),
					'description' => __( 'An array of image IDs for the product.', 'storeengine' ),
					'context'     => [ 'view', 'edit' ],
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
				],
			],
		] );
		register_meta( 'post', '_storeengine_product_tax_status', [
			'object_subtype' => Helper::PRODUCT_POST_TYPE,
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'title'       => __( 'Tax Status', 'storeengine' ),
					'description' => __( 'Product tax status.', 'storeengine' ),
					'context'     => [ 'view', 'edit' ],
					'type'        => 'string',
					'enum'        => [ ProductTaxStatus::TAXABLE, ProductTaxStatus::SHIPPING, ProductTaxStatus::NONE ],
					'default'     => ProductTaxStatus::TAXABLE,
				],
			],
		] );

		register_meta( 'post', '_storeengine_product_downloadable_files', [
			'object_subtype' => Helper::PRODUCT_POST_TYPE,
			'type'           => 'array',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'type'        => 'array',
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'id'      => [
								'type'        => 'string',
								'description' => __( 'The unique identifier for the downloadable file.', 'storeengine' ),
							],
							'name'    => [
								'type'        => 'string',
								'description' => __( 'The name of the downloadable file.', 'storeengine' ),
							],
							'file'    => [
								'type'        => 'string',
								'format'      => 'uri',
								'description' => __( 'The URL of the downloadable file.', 'storeengine' ),
							],
							'enabled' => [
								'type'        => 'boolean',
								'description' => __( 'Enable or disable the downloadable file.', 'storeengine' ),
							],
						],
					],
					'description' => __( 'An array of downloadable files for the product.', 'storeengine' ),
					'context'     => [ 'view', 'edit' ],
				],
			],
		] );

		register_meta( 'post', '_storeengine_upsell_ids', [
			'object_subtype' => Helper::PRODUCT_POST_TYPE,
			'type'           => 'array',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'type'        => 'array',
					'items'       => [
						'type' => 'integer',
					],
					'description' => 'An array of upsell product IDs for the product',
					'context'     => [ 'view', 'edit' ],
				],
			],
		] );

		register_meta( 'post', '_storeengine_crosssell_ids', [
			'object_subtype' => Helper::PRODUCT_POST_TYPE,
			'type'           => 'array',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'type'        => 'array',
					'items'       => [
						'type' => 'integer',
					],
					'description' => 'An array of cross-sell product IDs for the product',
					'context'     => [ 'view', 'edit' ],
				],
			],
		] );
	}

	public function register_coupon_meta() {
		$course_meta = [
			'_storeengine_coupon_name'                    => 'string',
			'_storeengine_coupon_type'                    => 'string',
			'_storeengine_coupon_amount'                  => 'number',
			'_storeengine_per_user_coupon_usage_limit'    => 'integer',
			'_storeengine_coupon_time_type'               => 'string',
			'_storeengine_coupon_is_one_usage_per_user'   => 'boolean',
			'_storeengine_coupon_is_total_usage_limit'    => 'string',
			'_storeengine_coupon_total_usage_limit'       => 'integer',
			'_storeengine_coupon_type_of_min_requirement' => 'string',
			'_storeengine_coupon_min_purchase_quantity'   => 'number',
			'_storeengine_coupon_min_purchase_amount'     => 'number',
			'_storeengine_coupon_who_can_use'             => 'string',
			'_storeengine_coupon_usage_count'             => 'number',
		];

		foreach ( $course_meta as $meta_key => $meta_value_type ) {
			register_meta( 'post', $meta_key, [
				'object_subtype' => Helper::COUPON_POST_TYPE,
				'type'           => $meta_value_type,
				'single'         => true,
				'show_in_rest'   => true,
			] );
		}

		// Coupon Used by.
		register_meta( 'post', '_storeengine_coupon_used_by', [
			'object_subtype' => Helper::COUPON_POST_TYPE,
			'type'           => 'number',
			'single'         => false,
			'show_in_rest'   => true,
		] );

		// Coupon Start Time
		register_meta( 'post', '_storeengine_coupon_start_date_time', [
			'object_subtype' => Helper::COUPON_POST_TYPE,
			'type'           => 'object',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'additionalProperties' => true,
					'items'                => [
						'type'       => 'object',
						'properties' => [
							'date'     => [ 'type' => 'string' ],
							'time'     => [ 'type' => 'string' ],
							'timezone' => [ 'type' => 'string' ],
						],
					],
				],
			],
		] );

		// Coupon End Time
		register_meta( 'post', '_storeengine_coupon_end_date_time', [
			'object_subtype' => Helper::COUPON_POST_TYPE,
			'type'           => 'object',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'additionalProperties' => true,
					'items'                => [
						'type'       => 'object',
						'properties' => [
							'date'     => [ 'type' => 'string' ],
							'time'     => [ 'type' => 'string' ],
							'timezone' => [ 'type' => 'string' ],
						],
					],
				],
			],
		] );
	}

	public static function register_attribute_taxonomy( string $name, string $label, bool $public ) {
		return register_taxonomy( Helper::get_attribute_taxonomy_name( $name ), Helper::PRODUCT_POST_TYPE, [
			'label'                 => $label,
			'labels'                => [
				/* translators: %s: attribute name */
				'name'              => sprintf( _x( 'Product %s', 'Product Attribute', 'storeengine' ), $label ),
				'singular_name'     => $label,
				/* translators: %s: attribute name */
				'search_items'      => sprintf( __( 'Search %s', 'storeengine' ), $label ),
				/* translators: %s: attribute name */
				'all_items'         => sprintf( __( 'All %s', 'storeengine' ), $label ),
				/* translators: %s: attribute name */
				'parent_item'       => sprintf( __( 'Parent %s', 'storeengine' ), $label ),
				/* translators: %s: attribute name */
				'parent_item_colon' => sprintf( __( 'Parent %s:', 'storeengine' ), $label ),
				/* translators: %s: attribute name */
				'edit_item'         => sprintf( __( 'Edit %s', 'storeengine' ), $label ),
				/* translators: %s: attribute name */
				'update_item'       => sprintf( __( 'Update %s', 'storeengine' ), $label ),
				/* translators: %s: attribute name */
				'add_new_item'      => sprintf( __( 'Add new %s', 'storeengine' ), $label ),
				/* translators: %s: attribute name */
				'new_item_name'     => sprintf( __( 'New %s', 'storeengine' ), $label ),
				/* translators: %s: attribute name */
				'not_found'         => sprintf( __( 'No &quot;%s&quot; found', 'storeengine' ), $label ),
				/* translators: %s: attribute name */
				'back_to_items'     => sprintf( __( '&larr; Back to "%s" attributes', 'storeengine' ), $label ),
			],
			'hierarchical'          => false,
			'update_count_callback' => '_update_post_term_count',
			'show_ui'               => false,
			'show_in_quick_edit'    => false,
			'show_in_menu'          => false,
			'meta_box_cb'           => false,
			'query_var'             => $public,
			'rewrite'               => ! $public ? false : apply_filters( 'storeengine/attribute/rewrite_rule', [
				'slug'       => Helper::get_attribute_taxonomy_name( $name ),
				'with_front' => false,
			] ),
			'sort'                  => false,
			'public'                => $public,
			'show_in_nav_menus'     => $public && apply_filters( 'storeengine/attribute/show_in_nav_menus', false, $name ),
			'capabilities'          => [
				'manage_terms' => 'manage_post_tags',
				'edit_terms'   => 'edit_post_tags',
				'delete_terms' => 'delete_post_tags',
				'assign_terms' => 'assign_post_tags',
			],
			'show_admin_column'     => false,
			'show_in_rest'          => true,
			'rest_base'             => Helper::get_attribute_taxonomy_name( $name ),
			'rest_namespace'        => STOREENGINE_PLUGIN_SLUG . '/v1',
			'rest_controller_class' => 'WP_REST_Terms_Controller',
		] );
	}
}
