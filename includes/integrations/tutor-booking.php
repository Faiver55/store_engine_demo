<?php

namespace StoreEngine\Integrations;

use StoreEngine\Classes\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TutorBooking extends AbstractIntegration {
	public function __construct() {
		parent::__construct();

		add_action( 'storeengine/integrations/created', [ $this, 'add_course_meta' ] );
		add_filter( 'academy_pro_booking/storeengine/get_product_price', array( $this, 'get_product_price' ), 10, 2 );
	}

	public function add_course_meta( Integration $integration ) {
		if ( $this->get_id() !== $integration->get_provider() ) {
			return;
		}

		$course_id = $integration->get_integration_id();
		$product   = get_post_meta( $course_id, '_academy_booking_product_id', true );
		if ( empty( $product ) ) {
			update_post_meta( $course_id, '_academy_booking_product_id', $integration->get_product_id() );
			update_post_meta( $course_id, '_academy_booking_type', 'paid' );
			update_post_meta( $integration->get_product_id(), '_academy_booking_id', $course_id );
		}
	}

	public function get_product_price( $args, $booking_id ) {
		$prices = $this->get_integration_repository( $booking_id );

		$schedule_time = get_user_meta( $args['user_id'], 'booking_schdule_time_' . $booking_id, true );
		if ( $schedule_time !== $args['booked_schedule_date_time'] ) {
			add_user_meta( $args['user_id'], 'booking_schdule_time_' . $booking_id, $args['booked_schedule_date_time'] );
		}

		foreach ( $prices as $price ) {
			return $price->price->get_id();
		}

		return false;
	}

	public function get_id() {
		return 'storeengine/tutor-booking';
	}

	public function get_label() {
		return __( 'Academy Tutor Booking', 'storeengine' );
	}

	public function get_logo() {
		return esc_url( STOREENGINE_ASSETS_URI . 'images/integrations/academy-lms.svg' );
	}

	public function enabled() {
		return defined( 'ACADEMY_VERSION' );
	}

	public function get_items_label() {
		return esc_html__( 'Booking Access', 'storeengine' );
	}

	public function get_items( array $args = [] ): array {
		$args   = wp_parse_args( $args, [
			'search' => '',
		] );
		$search = $args['search'];

		$course_query = new \WP_Query(
			[
				'post_type'   => 'academy_booking',
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

	protected function purchase_created( $integration, $order ) {
		$schedule_time = get_user_meta( $order->get_customer_id(), 'booking_schdule_time_' . $integration->get_integration_id(), true );
		\AcademyProTutorBooking\Helper::do_booked( $integration->get_integration_id(), $order->get_customer_id(), $schedule_time, $order->get_id() );
	}
}
