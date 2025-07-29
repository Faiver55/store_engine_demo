<?php

namespace StoreEngine\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Integration;
use StoreEngine\Classes\Price;
use StoreEngine\Integrations;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\Analytics;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template as Template;
use WP_Error;
use WP_Query;

class Product extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [
			'get_product_list'              => [
				'callback'   => [ $this, 'get_product_list' ],
				'capability' => 'manage_options',
				'fields'     => [
					'search'         => 'string',
					'integration_id' => 'int',
					'provider'       => 'string',
				],
			],
			'get_product_prices'            => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_product_prices' ],
				'fields'     => [
					'product_id' => 'id',
				],
			],
			'create_product_price'          => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'create_product_price' ],
				'fields'     => [
					'product_id'            => 'id',
					'price_name'            => 'string',
					'price_type'            => 'string',
					'price'                 => 'string',
					'compare_price'         => 'string',
					'setup_fee'             => 'boolean',
					'setup_fee_name'        => 'string',
					'setup_fee_price'       => 'string',
					'setup_fee_type'        => 'string',
					'trial'                 => 'boolean',
					'trial_days'            => 'integer',
					'expire'                => 'boolean',
					'expire_days'           => 'integer',
					'payment_duration'      => 'integer',
					'payment_duration_type' => 'string',
					'upgradeable'           => 'boolean',
					'order'                 => 'integer',
				],
			],
			'update_product_price'          => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'update_product_price' ],
				'fields'     => [
					'id'                    => 'id',
					'product_id'            => 'id',
					'price_name'            => 'string',
					'price_type'            => 'string',
					'price'                 => 'string',
					'compare_price'         => 'string',
					'setup_fee'             => 'boolean',
					'setup_fee_name'        => 'string',
					'setup_fee_price'       => 'string',
					'setup_fee_type'        => 'string',
					'trial'                 => 'boolean',
					'trial_days'            => 'integer',
					'expire'                => 'boolean',
					'expire_days'           => 'integer',
					'payment_duration'      => 'integer',
					'payment_duration_type' => 'string',
					'upgradeable'           => 'boolean',
					'order'                 => 'integer',
				],
			],
			'delete_product_price'          => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'delete_product_price' ],
				'fields'     => [
					'id' => 'id',
				],
			],
			'top_products'                  => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'top_products' ],
			],
			'get_product_integration_items' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_product_integration_items' ],
				'fields'     => [
					'provider'   => 'string',
					'product_id' => 'id',
					'search'     => 'string',
				],
			],
			'create_product_integration'    => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'create_product_integration' ],
				'fields'     => [
					'provider'       => 'string',
					'price_id'       => 'id',
					'integration_id' => 'id',
					'course_ids'     => 'array',
					'product_id'     => 'id',
				],
			],
			'delete_product_integration'    => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'delete_product_integration' ],
				'fields'     => [
					'id'                 => 'id',
					'with_course_bundle' => 'string',
				],
			],
			'get_product_slug'              => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_product_slug' ],
				'fields'     => [
					'ID' => 'id',
				],
			],
			'update_product_slug'           => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'update_product_slug' ],
				'fields'     => [
					'ID'       => 'id',
					'new_slug' => 'string',
				],
			],
			'get_page_templates'            => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_page_templates' ],
			],
			'archive_product_filter'        => [
				'allow_visitor_action' => true,
				'callback'             => [ $this, 'archive_product_filter' ],
				'fields'               => [
					'search'   => 'string',
					'category' => 'array',
					'tags'     => 'array',
					'orderby'  => 'string',
					'paged'    => 'integer',
					'count'    => 'integer',
				],
			],
		];
	}

	public function get_product_list( array $payload ) {
		if ( ! isset( $payload['integration_id'] ) ) {
			wp_send_json_error( [
				'message' => __( 'integration_id is required', 'storeengine' ),
			] );
		}

		$search   = $payload['search'] ?? '';
		$provider = $payload['provider'] ?? 'storeengine/membership-addon';
		wp_send_json_success( Helper::get_product_list( $payload['integration_id'], $provider, $search ) );
	}

	protected function get_page_templates() {
		wp_send_json_success( wp_get_theme()->get_page_templates() );
	}

	protected function get_product_prices( $payload ) {
		if ( empty( $payload['product_id'] ) ) {
			wp_send_json_error( esc_html__( 'Product ID required.', 'storeengine' ) );
		}

		wp_send_json_success( Helper::get_prices_array_by_product_id( $payload['product_id'] ) );
	}

	protected function create_product_price( $payload ) {
		$payload = apply_filters( 'storeengine_before_create_product_price', $payload );

		$data = $this->validate_and_prepare_price_data( $payload );
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( $data->get_error_message() );
		}

		$price = new Price();
		$this->populate_price_props( $price, $data );
		$save = $price->save();

		if ( is_wp_error( $save ) ) {
			wp_send_json_error(
				sprintf(
				/* translators: %s: Error message. */
					__( 'Failed to create product price! Error: %s', 'storeengine' ),
					$save->get_error_message()
				)
			);
		}

		wp_send_json_success( [
			'id'            => $price->get_id(),
			'price_name'    => $price->get_name(),
			'price_type'    => $price->get_price_type(),
			'price'         => $price->get_price(),
			'compare_price' => $price->get_compare_price(),
			'product_id'    => $price->get_product_id(),
			'order'         => $price->get_order(),
			'settings'      => $price->get_settings(),
		] );
	}

	protected function update_product_price( $payload ) {
		if ( empty( $payload['id'] ) ) {
			wp_send_json_error( esc_html__( 'Price Id missing!', 'storeengine' ) );
		}

		$data = $this->validate_and_prepare_price_data( $payload );

		if ( is_wp_error( $data ) ) {
			wp_send_json_error( $data->get_error_message() );
		}

		try {
			$price = new Price( absint( $payload['id'] ) );
			$this->populate_price_props( $price, $data );
			$save = $price->save();

			if ( is_wp_error( $save ) ) {
				wp_send_json_error( $save->get_error_message() );
			}

			wp_send_json_success( [
				'id'            => $price->get_id(),
				'price_name'    => $price->get_name(),
				'price_type'    => $price->get_price_type(),
				'price'         => $price->get_price(),
				'compare_price' => $price->get_compare_price(),
				'product_id'    => $price->get_product_id(),
				'order'         => $price->get_order(),
				'settings'      => $price->get_settings(),
			] );
		} catch ( StoreEngineException $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	protected function populate_price_props( Price $price, array $data ) {
		$price->set_props( [
			'price_name'            => $data['price_name'],
			'price_type'            => $data['price_type'],
			'price'                 => (float) $data['price'],
			'compare_price'         => (float) $data['compare_price'] > 0 ? (float) $data['compare_price'] : null,
			'product_id'            => absint( $data['product_id'] ),
			'order'                 => absint( $data['order'] ),
			'setup_fee'             => (bool) ( $data['settings']['setup_fee'] ?: false ),
			'setup_fee_name'        => (string) ( $data['settings']['setup_fee_name'] ?: '' ),
			'setup_fee_price'       => (float) ( $data['settings']['setup_fee_price'] ?: 0 ),
			'setup_fee_type'        => (string) ( $data['settings']['setup_fee_type'] ?: 'fixed' ),
			'trial'                 => (bool) ( $data['settings']['trial'] ?: false ),
			'trial_days'            => absint( $data['settings']['trial_days'] ?: 0 ),
			'expire'                => (bool) ( $data['settings']['expire'] ?: false ),
			'expire_days'           => absint( $data['settings']['expire_days'] ?: 0 ),
			'payment_duration'      => absint( $data['settings']['payment_duration'] ?: 1 ),
			'payment_duration_type' => (string) ( $data['settings']['payment_duration_type'] ?: 'monthly' ),
			'upgradeable'           => (bool) ( $data['settings']['upgradeable'] ?: false ),
		] );
	}

	protected function validate_and_prepare_price_data( array $payload ) {
		if ( empty( $payload['product_id'] ) ) {
			return new WP_Error( 'invalid-product-id', __( 'Product Id missing', 'storeengine' ) );
		}

		if ( (float) $payload['price'] <= 0 ) {
			return new WP_Error( 'invalid-price', __( 'Price must be greater than 0', 'storeengine' ) );
		}

		if ( isset( $payload['compare_price'] ) && (float) $payload['compare_price'] > 0 && (float) $payload['compare_price'] < (float) $payload['price'] ) {
			return new WP_Error( 'invalid-compare-price', __( 'Compare price must be greater than price', 'storeengine' ) );
		}

		return [
			'price_name'    => $payload['price_name'],
			'price_type'    => $payload['price_type'],
			'price'         => $payload['price'],
			'compare_price' => $payload['compare_price'] ?? null,
			'product_id'    => $payload['product_id'],
			'settings'      => [
				'setup_fee'             => $payload['setup_fee'] ?? false,
				'setup_fee_name'        => $payload['setup_fee_name'] ?? '',
				'setup_fee_price'       => $payload['setup_fee_price'] ?? '',
				'setup_fee_type'        => $payload['setup_fee_type'] ?? '',
				'trial'                 => $payload['trial'] ?? false,
				'trial_days'            => $payload['trial_days'] ?? 7,
				'expire'                => $payload['expire'] ?? false,
				'expire_days'           => $payload['expire_days'] ?? 3,
				'payment_duration'      => $payload['payment_duration'] ?? 0,
				'payment_duration_type' => $payload['payment_duration_type'] ?? '',
				'upgradeable'           => $payload['upgradeable'] ?? false,
			],
			'order'         => $payload['order'] ?? 0,
		];
	}

	protected function delete_product_price( $payload ) {
		if ( empty( $payload['id'] ) ) {
			wp_send_json_error( esc_html__( 'Price Id missing!', 'storeengine' ) );
		}

		try {
			$price = new Price( $payload['id'] );

			if ( $price->delete() ) {
				do_action( 'storeengine/price_deleted', $payload['id'] );
				wp_send_json_success( true );
			}

			wp_send_json_error( esc_html__( 'Something went wrong! Failed to delete product price!', 'storeengine' ) );
		} catch ( StoreEngineException $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	public function top_products() {
		wp_send_json_success( ( new Analytics() )->get_top_products() );
	}

	public function get_product_integration_items( $payload ) {
		if ( empty( $payload['provider'] ) ) {
			wp_send_json_error( esc_html__( 'Provider missing!', 'storeengine' ) );
		}

		try {
			$integration = Integrations::get_instance()->get_integration( $payload['provider'] );
			wp_send_json_success( [
				'label' => $integration->get_items_label(),
				'items' => $integration->get_items( [
					'product_id' => $payload['product_id'] ?? 0,
					'search'     => $payload['search'] ?? '',
				] ),
			] );
		} catch ( StoreEngineException $exception ) {
			wp_send_json_error( $exception->getMessage() );
		}
	}

	public function create_product_integration( $payload ) {
		if ( empty( $payload['provider'] ) ) {
			wp_send_json_error( esc_html__( 'Provider is required', 'storeengine' ) );
		}
		if ( empty( $payload['product_id'] ) ) {
			wp_send_json_error( esc_html__( 'Product ID is required', 'storeengine' ) );
		}
		if ( empty( $payload['price_id'] ) ) {
			wp_send_json_error( esc_html__( 'Price ID is required', 'storeengine' ) );
		}
		if ( 'storeengine/course-bundle' !== $payload['provider'] && empty( $payload['integration_id'] ) ) {
			wp_send_json_error( esc_html__( 'Integration ID is required', 'storeengine' ) );
		}

		if ( 'storeengine/course-bundle' === $payload['provider'] ) {
			$course_ids = $payload['course_ids'] ?? [];
			if ( empty( $course_ids ) ) {
				wp_send_json_error( esc_html__( 'Please select at least one course.', 'storeengine' ) );
			}
			$has_bundle = get_post_meta( $payload['product_id'], '_academy_course_bundle_id', true );
			if ( ! empty( $has_bundle ) ) {
				wp_send_json_error( esc_html__( 'Each product can only be assigned to a single course bundle.', 'storeengine' ) );
			}

			$bundle_id = wp_insert_post( [
				'post_title'  => get_the_title( $payload['product_id'] ),
				'post_type'   => 'alms_course_bundle',
				'post_status' => 'publish',
				'meta_input'  => [
					'academy_course_bundle_courses_ids' => $course_ids,
				],
			] );

			if ( is_wp_error( $bundle_id ) ) {
				wp_send_json_error( $bundle_id->get_error_message() );
			}

			$payload['integration_id'] = $bundle_id;
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$has_integration = $wpdb->get_var(
			$wpdb->prepare( "
					SELECT id, product_id, price_id
					FROM
						{$wpdb->prefix}storeengine_integrations
					WHERE
						integration_id = %d
					AND provider = %s AND price_id = %d
			", $payload['integration_id'], $payload['provider'], $payload['price_id'] )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $has_integration ) {
			wp_send_json_error( esc_html__( 'Same integration already exists!', 'storeengine' ) );
		}

		$integration = new Integration();
		$integration->set_provider( $payload['provider'] );
		$integration->set_product_id( $payload['product_id'] );
		$integration->set_price_id( $payload['price_id'] );
		$integration->set_integration_id( $payload['integration_id'] );
		$integration->save();

		if ( 0 === $integration->get_id() ) {
			wp_send_json_error( esc_html__( 'Failed to create integration!', 'storeengine' ) );
		}

		$data = [
			'product_id'     => $integration->get_product_id(),
			'price_id'       => $integration->get_price_id(),
			'provider'       => $integration->get_provider(),
			'integration_id' => $integration->get_integration_id(),
		];

		if ( 'storeengine/course-bundle' === $payload['provider'] ) {
			$data['course_ids'] = $payload['course_ids'] ?? [];
		}

		wp_send_json_success( $data );
	}

	public function delete_product_integration( $payload ) {
		if ( empty( $payload['id'] ) ) {
			wp_send_json_error( esc_html__( 'Integration ID is required', 'storeengine' ) );
		}

		$integration = ( new Integration( $payload['id'] ) )->get();
		if ( ! $integration ) {
			wp_send_json_error( esc_html__( 'Integration not found!', 'storeengine' ) );
		}

		if ( 'storeengine/course-bundle' === $integration->get_provider() ) {
			delete_post_meta( $integration->get_product_id(), '_academy_course_bundle_id' );
			delete_post_meta( $integration->get_integration_id(), 'academy_course_bundle_product_id' );

			$with_course_bundle = $payload['with_course_bundle'] && Formatting::string_to_bool( $payload['with_course_bundle'] );
			if ( $with_course_bundle ) {
				wp_delete_post( $integration->get_integration_id(), true );
			}
		}

		if ( ! $integration->delete() ) {
			wp_send_json_error( esc_html__( 'Failed to delete integration!', 'storeengine' ) );
		}

		wp_send_json_success();
	}

	public function get_product_slug( $payload ) {
		if ( empty( $payload['ID'] ) ) {
			wp_send_json_error( esc_html__( 'Product ID is required!', 'storeengine' ) );
		}

		wp_send_json_success( Helper::get_sample_permalink_args( $payload['ID'] ) );
	}

	public function update_product_slug( $payload ) {
		if ( empty( $payload['ID'] ) ) {
			wp_send_json_error( esc_html__( 'Product ID is required!', 'storeengine' ) );
		}
		if ( empty( $payload['new_slug'] ) ) {
			wp_send_json_error( esc_html__( 'New slug is required!', 'storeengine' ) );
		}

		$payload['new_slug'] = sanitize_title( $payload['new_slug'] );

		if ( ! $payload['new_slug'] ) {
			wp_send_json_error( esc_html__( 'New slug is invalid!', 'storeengine' ) );
		}

		$post = get_post( $payload['ID'] );
		if ( ! $post ) {
			wp_send_json_error( __( 'Product not found', 'storeengine' ), 404 );
		}

		if ( $post->post_name === $payload['new_slug'] ) {
			wp_send_json_error( esc_html__( 'Product slug is not changed!', 'storeengine' ) );
		}

		$update = wp_update_post( [
			'ID'        => $post->ID,
			'post_name' => $payload['new_slug'],
		], true );

		if ( is_wp_error( $update ) ) {
			wp_send_json_error( $update->get_error_message() );
		}

		wp_send_json_success( Helper::get_sample_permalink_args( $payload['ID'], $payload['new_slug'] ) );
	}

	public function archive_product_filter( $payload ) {
		$search         = $payload['search'] ?? '';
		$category       = $payload['category'] ?? [];
		$tags           = $payload['tags'] ?? [];
		$orderby        = $payload['orderby'] ?? '';
		$paged          = ! empty( $payload['paged'] ) ? $payload['paged'] : 1;
		$per_row        = Helper::get_settings( 'product_archive_products_per_row' );
		$posts_per_page = ! empty( $payload['count'] ) ? absint( $payload['count'] ) : (int) Helper::get_settings( 'product_archive_products_per_page', 12 );

		$args       = Helper::prepare_product_search_query_args( [
			'search'         => $search,
			'category'       => $category,
			'tags'           => $tags,
			'paged'          => max( 1, $paged ),
			'orderby'        => $orderby,
			'posts_per_page' => $posts_per_page,
		] );
		$grid_class = Helper::get_responsive_column( $per_row );
		// phpcs:ignore WordPress.WP.DiscouragedFunctions.wp_reset_query_wp_reset_query -- maybe we can remove this.
		wp_reset_query();
		wp_reset_postdata();
		$products_query = new WP_Query( apply_filters( 'storeengine/product_filter_args', array_merge( $args, [
			'meta_query' => [
				'relation' => 'OR',
				[
					'key'     => '_storeengine_product_hide',
					'value'   => true,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_storeengine_product_hide',
					'value'   => true,
					'compare' => '!=',
				],
			],
		] ) ) );

		ob_start();
		?>
		<div class="storeengine-row">
			<?php
			if ( $products_query->have_posts() ) {
				// Load posts loop.
				while ( $products_query->have_posts() ) {
					$products_query->the_post();
					/**
					 * Hook: storeengine/templates/product_loop.
					 */
					do_action( 'storeengine/templates/product_loop' );
					Template::get_template( 'content-product.php', [ 'grid_class' => $grid_class ] );
				}
				Template::get_template( 'archive/pagination.php', [
					'paged'         => $paged,
					'max_num_pages' => $products_query->max_num_pages,
				] );
				// phpcs:ignore WordPress.WP.DiscouragedFunctions.wp_reset_query_wp_reset_query -- maybe we can remove this.
				wp_reset_query();
				wp_reset_postdata();
			} else {
				Template::get_template( 'archive/product-none.php' );
			}
			?>
		</div>
		<?php
		$markup = ob_get_clean();
		wp_send_json_success(
			[
				'markup'      => apply_filters( 'storeengine/product_filter_markup', $markup ),
				'found_posts' => $products_query->found_posts,
			]
		);
	}
}
