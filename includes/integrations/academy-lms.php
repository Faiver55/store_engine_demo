<?php

namespace StoreEngine\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Classes\Integration;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Template;
use StoreEngine\Utils\Helper;

class AcademyLms extends AbstractIntegration {
	public function __construct() {
		parent::__construct();

		add_action( 'storeengine/integrations/created', [ $this, 'add_course_meta' ] );
		add_filter( 'academy/templates/single_course/enroll_form', [ $this, 'add_to_cart_button' ], 10, 2 );
		add_filter( 'academy/single/enroll_content_args', [ $this, 'modify_enroll_form_content_args' ], 10, 2 );
		add_filter( 'academy/template/loop/price_args', [ $this, 'modify_loop_price_args' ], 10, 2 );
		add_filter( 'academy/template/loop/footer_form', [ $this, 'modify_footer_form_args' ], 10, 2 );
		add_filter( 'academy/shortcode/storeengine_enroll_form_prices_args', [ $this, 'get_academy_course_prices_args' ] );
	}

	public function add_course_meta( Integration $integration ) {
		if ( 'storeengine/academylms' !== $integration->get_provider() ) {
			return;
		}

		update_post_meta( $integration->get_integration_id(), 'academy_course_type', 'paid' );
	}

	public function get_id() {
		return 'storeengine/academylms';
	}

	public function get_label() {
		return __( 'Academy LMS', 'storeengine' );
	}

	public function get_logo() {
		return esc_url( STOREENGINE_ASSETS_URI . 'images/integrations/academy-lms.svg' );
	}

	public function enabled() {
		return defined( 'ACADEMY_VERSION' );
	}

	public function get_items_label() {
		return esc_html__( 'Course Access', 'storeengine' );
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
		global $wpdb;
		foreach ( $order->get_items() as $order_item ) {
			$integration_repository = Helper::get_integration_repository_by_price_id( $this->get_id(), $order_item->get_price_id() );
			if ( ! $integration_repository ) {
				continue;
			}

			if ( ! \Academy\Helper::is_enrolled( $integration_repository->integration->get_integration_id(), $order->get_customer_id() ) ) {
				\Academy\Helper::do_enroll( $integration_repository->integration->get_integration_id(), $order->get_customer_id(), $order->get_id() );
			}

			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_status='on-hold' WHERE post_author=%d
                AND post_parent=%d
                AND post_type='academy_enrolled'", $order->get_customer_id(), $integration_repository->integration->get_integration_id() ) );
		}
	}

	protected function handle_subscription_paid_status( Subscription $subscription, string $new_status ) {
		foreach ( $subscription->get_items() as $subscription_item ) {
			$integration = Helper::get_integration_repository_by_price_id( $this->get_id(), $subscription_item->get_price_id() );
			if ( ! $integration ) {
				continue;
			}

			$integration = $integration->integration;
			$is_enrolled = \Academy\Helper::is_enrolled( $integration->get_integration_id(), $subscription->get_customer_id(), 'on-hold' );
			if ( $is_enrolled ) {
				wp_update_post( [
					'ID'          => $is_enrolled->ID,
					'post_status' => 'completed',
				] );

				return;
			}

			\Academy\Helper::do_enroll( $integration->get_integration_id(), $subscription->get_customer_id(), $subscription->get_parent_order_id() );
		}
	}

	protected function handle_subscription_unpaid_status( Subscription $subscription, string $new_status ) {
		global $wpdb;
		foreach ( $subscription->get_items() as $subscription_item ) {
			$integration_repository = Helper::get_integration_repository_by_price_id( $this->get_id(), $subscription_item->get_price_id() );
			if ( ! $integration_repository || ! \Academy\Helper::is_enrolled( $integration_repository->integration->get_integration_id(), $subscription->get_customer_id() ) ) {
				continue;
			}

			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_status='on-hold' WHERE post_author=%d
                AND post_parent=%d
                AND post_type='academy_enrolled'", $subscription->get_customer_id(), $integration_repository->integration->get_integration_id() ) );
		}
	}

	protected function purchase_created( $integration, $order ) {
		$is_enrolled = \Academy\Helper::is_enrolled( $integration->get_integration_id(), $order->get_customer_id(), 'on-hold' );
		if ( $is_enrolled ) {
			wp_update_post( [
				'ID'          => $is_enrolled->ID,
				'post_status' => 'completed',
			] );

			return;
		}

		\Academy\Helper::do_enroll( $integration->get_integration_id(), $order->get_customer_id(), $order->get_id() );
	}

	public function add_to_cart_button( $html, $course_id ) {
		$course_type = \Academy\Helper::get_course_type( $course_id );
		if ( 'free' === $course_type || 'public' === $course_type ) {
			return $html;
		}

		$integrations = $this->get_integration_repository( $course_id );
		$user_id      = get_current_user_id();
		if ( empty( $integrations ) || ! count( $integrations ) || \Academy\Helper::is_enrolled( $course_id, $user_id, 'on-hold' ) ) {
			return $html;
		}

		ob_start();

		// Render cart.
		Template::get_template( 'integrations/academy-lms/add-to-cart.php', [
			'integrations'      => $integrations,
			'integration_count' => count( $integrations ),
		] );

		return ob_get_clean();
	}

	public function get_price_html( $course_id ): string {
		$integrations = $this->get_integration_repository( $course_id );

		if ( count( $integrations ) > 1 ) {
			return '<span class="academy-course-price"><span>' . __( 'Paid', 'storeengine' ) . '</span></span>';
		}

		return '<span class="academy-course-price"><span>' . current( $integrations )->price->get_price_html() . '</span></span>';
	}

	public function modify_enroll_form_content_args( $args, $course_id ) {
		$course_type = \Academy\Helper::get_course_type( $course_id );
		if ( 'free' === $course_type || $args['is_public'] || \Academy\Helper::is_enrolled( $course_id, get_current_user_id() ) ) {
			return $args;
		}

		$integration = $this->get_integration_repository( $course_id );

		if ( empty( $integration ) ) {
			return $args;
		}

		$prices_markup   = $this->get_price_html( $course_id );
		$args['is_paid'] = true;
		$args['price']   = '<div class="academy-course-type">' . $prices_markup . '</div>';

		return $args;
	}

	public function modify_loop_price_args( array $args, $course_id ): array {
		if ( 'public' === $args['course_type'] || 'free' === $args['course_type'] ) {
			return $args;
		}

		$integration = $this->get_integration_repository( $course_id );

		if ( empty( $integration ) ) {
			return $args;
		}

		return array_merge(
			$args,
			[
				'is_paid'     => true,
				'course_type' => 'paid',
				'price'       => '<div class="academy-course-type">' . $this->get_price_html( $course_id ) . '</div>',
			]
		);
	}

	public function modify_footer_form_args( $args, $course_id ) {
		if ( 'free' === $args['course_type'] || 'public' === $args['course_type'] ) {
			return $args;
		}
		$integration = $this->get_integration_repository( $course_id );
		if ( empty( $integration ) ) {
			return $args;
		}
		$args['is_storeengine_product'] = true;
		$args['price_qtn']              = count( $integration );
		$args['integration']            = $integration;

		return $args;
	}

	public function get_academy_course_prices_args( $course_id ) {
		$integrations = $this->get_integration_repository( (int) $course_id );
		$args         = [
			'price' => '',
			'link'  => '',
		];

		if ( empty( $integrations ) ) {
			return $args;
		}

		if ( count( $integrations ) > 1 ) {
			$prices = array_map(
				fn( $integration ) => intval( $integration->price->data['price'] ?? 0 ),
				$integrations
			);

			$args['price'] = min( $prices ) . ' - ' . max( $prices );
			return $args;
		}

		$args['price'] = current($integrations)->price->get_price_html();

		return $args;
	}

}
