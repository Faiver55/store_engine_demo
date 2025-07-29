<?php

namespace StoreEngine\Integrations;

use StoreEngine\Classes\Integration;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Constants;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CourseBundle extends AbstractIntegration {
	public function __construct() {
		parent::__construct();

		add_action( 'academy_pro/templates/course_bundle/single_bundle_enroll_content', [
			$this,
			'single_enroll_content',
		] );
		add_action( 'academy_pro/templates/course_bundle/single_bundle_enroll_content', [
			$this,
			'single_bundle_enroll_content_form',
		] );
	}

	public function single_enroll_content() {
		// return if the monetized engine is not storeengine
		if ( 'storeengine' !== \Academy\Helper::monetization_engine() ) {
			return;
		}

		$bundle_id                 = get_the_ID();
		$price                     = '';
		$regular_price             = '';
		$sale_price                = '';
		$duration                  = \AcademyProCourseBundle\Helper::get_bundle_duration( $bundle_id );
		$total_lessons             = \AcademyProCourseBundle\Helper::get_bundle_lessons( $bundle_id );
		$total_enrolled            = $this->get_bundle_enrolled( $bundle_id );
		$max_students              = \AcademyProCourseBundle\Helper::get_max_students( $bundle_id );
		$total_enroll_count_status = \Academy\Helper::get_settings( 'is_enabled_course_single_enroll_count', true );
		$last_update               = get_the_modified_time( get_option( 'date_format' ), $bundle_id );

		if ( \Academy\Helper::is_active_storeengine() ) {
			$integration_repository = Helper::get_integration_repository_by_id( $this->get_id(), $bundle_id );
			if ( ! empty( $integration_repository ) ) {
				$integration_repository = current( $integration_repository );
				$price                  = $integration_repository->price;
				$sale_price             = $price->get_price();
				$price                  = $price->get_price_html();
			}
		}

		ob_start();

		\AcademyPro\Helper::get_template(
			'course-bundle/enroll/content.php',
			apply_filters(
				'academy_pro/single/bundle_content_args',
				array(
					'enrolled'                  => false,
					'is_paid'                   => true,
					'is_public'                 => false,
					'regular_price'             => $regular_price,
					'sale_price'                => $sale_price,
					'price'                     => $price,
					'duration'                  => $duration,
					'total_lessons'             => $total_lessons,
					'total_enroll_count_status' => $total_enroll_count_status,
					'total_enrolled'            => $total_enrolled,
					'max_students'              => $max_students,
					'last_update'               => $last_update,
				),
				$bundle_id
			)
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo apply_filters( 'academy/templates/single_course/enroll_content', ob_get_clean(), $bundle_id );
	}

	public function single_bundle_enroll_content_form() {
		global $post;

		$engine = \Academy\Helper::get_settings( 'monetization_engine' );

		if ( 'alms_course_bundle' !== get_post_type( get_the_ID() ) || 'storeengine' !== $engine ) {
			return;
		}

		$integrations = $this->get_integration_repository( $post->ID );
		if ( empty( $integrations ) || ! count( $integrations ) ) {
			return;
		}

		$purchased_bundles = maybe_unserialize( get_user_meta( get_current_user_id(), '_academy_pro_purchased_course_bundles', true ) );
		$purchased_bundles = ! empty( $purchased_bundles ) ? $purchased_bundles : [];

		if ( in_array( $post->ID, $purchased_bundles ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
			Template::get_template( 'integrations/academy-lms/course-bundle-continue.php' );

			return;
		}

		Template::get_template( 'integrations/academy-lms/add-to-cart.php', [
			'integrations'      => $integrations,
			'integration_count' => count( $integrations ),
		] );
	}

	public function get_id(): string {
		return 'storeengine/course-bundle';
	}

	public function get_label(): ?string {
		return __( 'Academy Course Bundle', 'storeengine' );
	}

	public function get_logo(): string {
		return esc_url( STOREENGINE_ASSETS_URI . 'images/integrations/academy-lms.svg' );
	}

	public function enabled(): bool {
		return defined( 'ACADEMY_VERSION' );
	}

	public function get_items_label() {
		return esc_html__( 'Select Course (Only paid courses)', 'storeengine' );
	}

	public function get_items( array $args = [] ): array {
		$args   = wp_parse_args( $args, [
			'search' => '',
		] );
		$search = $args['search'];

		$course_query = new \WP_Query(
			[
				'post_type'   => 'academy_courses',
				'post_status' => 'publish',
				's'           => $search,
				'per_page'    => 10,
				'meta_query'  => [
					'key'     => 'academy_course_type',
					'value'   => 'paid',
					'compare' => '=',
				],
			]
		);

		$items = [];

		if ( $course_query->have_posts() ) {
			$items = array_map(
				function ( $post ) {
					return (object) [
						'value' => $post->ID,
						'label' => $post->post_title,
					];
				},
				$course_query->posts
			);
		}

		return $items;
	}

	public function get_item() {
		return [];
	}

	protected function handle_order_unpaid_status( Order $order, string $status ) {
		$user_id    = $order->get_user_id();
		$course_ids = [];
		foreach ( $order->get_items() as $item ) {
			$integration_repository = Helper::get_integration_repository_by_price_id( $this->get_id(), $item->get_price_id() );
			if ( ! $integration_repository ) {
				continue;
			}
			$bundle_id = $integration_repository->integration->get_integration_id();

			$purchased_bundles = maybe_unserialize( get_user_meta( $user_id, '_academy_pro_purchased_course_bundles', true ) );
			$purchased_bundles = ! empty( $purchased_bundles ) ? $purchased_bundles : [];
			$is_purchased      = in_array( $bundle_id, $purchased_bundles ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict

			if ( ! $is_purchased ) {
				continue;
			}

			$courses = maybe_unserialize( get_post_meta( $bundle_id, 'academy_course_bundle_courses_ids', true ) );
			foreach ( $courses as $course ) {
				$course_ids[] = absint( $course['value'] );
			}

			$purchased_bundles = array_filter( $purchased_bundles, function ( $purchased_bundle_id ) use ( $bundle_id ) {
				return $purchased_bundle_id !== $bundle_id;
			} );
			update_user_meta( $user_id, '_academy_pro_purchased_course_bundles', maybe_serialize( $purchased_bundles ) );
		}

		if ( empty( $course_ids ) ) {
			return;
		}

		$course_ids_placeholder = implode( ',', array_fill( 0, count( $course_ids ), '%d' ) );

		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_status='on-hold' WHERE post_author=%d
                AND post_parent IN ($course_ids_placeholder)
                AND post_type='academy_enrolled'", $user_id, ...$course_ids ) );
	}

	protected function purchase_created( $integration, $order ) {
		$user_id           = $order->get_user_id();
		$bundle_id         = $integration->get_integration_id();
		$purchased_bundles = maybe_unserialize( get_user_meta( $user_id, '_academy_pro_purchased_course_bundles', true ) );
		$purchased_bundles = ! empty( $purchased_bundles ) ? $purchased_bundles : [];
		$is_purchased      = in_array( $bundle_id, $purchased_bundles ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict

		if ( ! $is_purchased ) {
			$courses = maybe_unserialize( get_post_meta( $bundle_id, 'academy_course_bundle_courses_ids', true ) );
			foreach ( $courses as $course ) {
				\Academy\Helper::do_enroll( $course['value'], $user_id );
			}
			$purchased_bundles[] = $bundle_id;
			update_user_meta( $user_id, '_academy_pro_purchased_course_bundles', maybe_serialize( $purchased_bundles ) );
		}
	}

	private function get_bundle_enrolled( int $bundle_id ): int {
		$integration_repositories = Helper::get_integration_repository_by_id( $this->get_id(), $bundle_id );

		$enrolled = 0;
		if ( empty( $integration_repositories ) ) {
			return $enrolled;
		}

		$price_ids = [];
		foreach ( $integration_repositories as $integration_repository ) {
			$price_ids[] = $integration_repository->price->get_id();
		}
		$price_ids_formatter = implode( ',', array_fill( 0, count( $price_ids ), '%d' ) );

		global $wpdb;
		$enrolled_ids = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
						FROM ( SELECT DISTINCT opl.order_id
							FROM
								{$wpdb->prefix}storeengine_order_product_lookup AS opl
								INNER JOIN {$wpdb->prefix}storeengine_orders AS o ON opl.order_id = o.id
							WHERE opl.price_id IN ($price_ids_formatter) AND o.status = 'completed'
						) AS subquery", ...$price_ids ) );

		return (int) $enrolled_ids;
	}
}
