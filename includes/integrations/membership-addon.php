<?php

namespace StoreEngine\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

use StoreEngine\Addons\Membership\HelperAddon;
use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Classes\Data\IntegrationRepositoryData;
use StoreEngine\Classes\Integration;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\Product\SimpleProduct;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

class MembershipAddon extends AbstractIntegration {
	public function __construct() {
		parent::__construct();

		add_filter( 'storeengine/membership/before_content_protect', [ $this, 'stop_from_redirecting' ], 10, 2 );

		// AcademyLMS supports.
		add_filter( 'academy/templates/single_course/enroll_form', [ $this, 'change_enrollment_form' ], 20, 2 );
		add_filter( 'academy/course/get_course_type', [ $this, 'modify_course_type' ], 10, 2 );
		add_filter( 'academy/before_enroll_course_type', [ $this, 'modify_course_type' ], 10, 2 );
	}

	public function get_id() {
		return 'storeengine/membership-addon';
	}

	public function get_label() {
		return __( 'StoreEngine Membership', 'storeengine' );
	}

	public function get_logo() {
		return esc_url( STOREENGINE_ASSETS_URI . 'images/logo.svg' );
	}

	public function enabled() {
		return Helper::get_addon_active_status( 'membership' );
	}

	public function get_items_label() {
		return esc_html__( 'Select an Access Group', 'storeengine' );
	}

	public function get_items( array $args = [] ): array {
		$args   = wp_parse_args( $args, [
			'search' => '',
		] );
		$search = $args['search'];

		$access_group_query = new \WP_Query(
			[
				'post_type'   => STOREENGINE_MEMBERSHIP_POST_TYPE,
				'post_status' => 'publish',
				's'           => $search,
				'per_page'    => 10,
			]
		);

		$items = [];

		if ( $access_group_query->have_posts() ) {
			$items = array_map(
				function ( $post ) {
					return (object) [
						'value' => $post->ID,
						'label' => $post->post_title,
					];
				},
				$access_group_query->posts
			);
		}

		return $items;
	}

	public function get_item() {
		return [];
	}

	protected function handle_order_unpaid_status( Order $order, string $status ) {
		$this->update_unpaid_status( $order );
	}

	protected function handle_subscription_paid_status( Subscription $subscription, string $new_status ) {
		$user_id = $subscription->get_customer_id();
		foreach ( $subscription->get_items() as $subscription_item ) {
			$integration_repository = Helper::get_integration_repository_by_price_id( $this->get_id(), $subscription_item->get_price_id() );
			if ( ! $integration_repository ) {
				continue;
			}

			$integration = $integration_repository->integration;
			$this->update_user_meta( $user_id, $integration, 'completed' );
			$this->update_course_enroll_status( $this->generate_course_ids( $integration ), $user_id );
		}
	}

	protected function handle_subscription_unpaid_status( Subscription $subscription, string $new_status ) {
		$this->update_unpaid_status( $subscription );
	}

	protected function update_unpaid_status( Order $order ) {
		$user_id = $order->get_customer_id();
		foreach ( $order->get_items() as $order_item ) {
			$integration_repository = Helper::get_integration_repository_by_price_id( $this->get_id(), $order_item->get_price_id() );
			if ( ! $integration_repository ) {
				continue;
			}

			$integration_id = $integration_repository->integration->get_integration_id();
			$purchased      = get_user_meta( $user_id, '_storeengine_purchased_membership_ids', true ) ?? [];
			if ( empty( $purchased ) || ! in_array( $integration_id, $purchased, true ) ) {
				continue;
			}
			$purchased_index = array_search( $integration_id, $purchased );
			if ( $purchased_index !== false ) {
				unset( $purchased[ $purchased_index ] );
				update_user_meta( $user_id, '_storeengine_purchased_membership_ids', array_values( $purchased ) );
			}

			$user_meta = get_user_meta( $user_id, '_storeengine_memberships', true ) ?? [];
			if ( empty( $user_meta ) ) {
				continue;
			}
			$new_user_meta = [];
			foreach ( $user_meta as $user_meta_item ) {
				if ( $user_meta_item['customer_id'] === $user_id && $user_meta_item['price_id'] === $order_item->get_price_id() ) {
					continue;
				}
				$new_user_meta[] = $user_meta_item;
			}

			if ( count( $new_user_meta ) !== count( $user_meta ) ) {
				update_user_meta( $user_id, '_storeengine_memberships', $new_user_meta );
			}

			$access_group_meta = get_post_meta( $integration_id, '_storeengine_membership_content_protect_types', true );
			if ( ! is_array( $access_group_meta ) ) {
				continue;
			}

			$course_ids = [];
			foreach ( $access_group_meta['specifics'] ?? [] as $specific ) {
				$post_id = $this->get_post_id( $specific['value'] );
				if ( $post_id && 'academy_courses' === get_post_type( $post_id ) ) {
					$course_ids[] = $post_id;
					continue;
				}

				$arr = explode( '--single-', $specific['value'] );
				if ( 2 !== count( $arr ) ) {
					continue;
				}
				$term_id  = str_replace( 'tax-', '', $arr[0] );
				$taxonomy = $arr[1];
				$posts    = get_posts( [
					'post_type'      => 'academy_courses',
					'fields'         => 'ids',
					'posts_per_page' => - 1,
					'tax_query'      => [
						[
							'taxonomy' => $taxonomy,
							'field'    => 'term_id',
							'terms'    => $term_id,
						],
					],
				] );
				foreach ( $posts as $post ) {
					$course_ids[] = $post;
				}
			}

			$this->update_course_enroll_status( $course_ids, $order->get_customer_id(), 'on-hold' );
		}
	}

	protected function purchase_created( Integration $integration, Order $order ) {
		$user_id = $order->get_customer_id();
		$this->update_user_meta( $user_id, $integration, $order->get_status() );
		$this->update_course_enroll_status( $this->generate_course_ids( $integration ), $order->get_customer_id() );
	}

	protected function update_user_meta( int $user_id, Integration $integration, string $order_status ) {
		$integration_id = $integration->get_integration_id();
		$purchased      = get_user_meta( $user_id, '_storeengine_purchased_membership_ids', true );
		if ( ! is_array( $purchased ) ) {
			$purchased = [];
		}

		if ( ! in_array( $integration_id, $purchased, true ) ) {
			$purchased[] = $integration_id;
			update_user_meta( $user_id, '_storeengine_purchased_membership_ids', $purchased );
		}

		$user_meta = get_user_meta( $user_id, '_storeengine_memberships', true );
		if ( ! is_array( $user_meta ) ) {
			$user_meta = [];
		}
		$user_meta[] = [
			'customer_id'  => $user_id,
			'price_id'     => $integration->get_price_id(),
			'order_status' => $order_status,
		];
		update_user_meta( $user_id, '_storeengine_memberships', $user_meta );
	}

	protected function generate_course_ids( Integration $integration ) {
		$integration_id = $integration->get_integration_id();
		$access_meta    = get_post_meta( $integration_id, '_storeengine_membership_content_protect_types', true ) ?? [];
		if ( ! is_array( $access_meta ) ) {
			return [];
		}
		$items      = $access_meta['specifics'] ?? [];
		$course_ids = [];
		foreach ( $items as $item ) {
			$post_id = $this->get_post_id( $item['value'] );
			if ( $post_id && 'academy_courses' === get_post_type( $post_id ) ) {
				$course_ids[] = $post_id;
			}

			if ( ! $post_id ) {
				$item_arr = explode( '--single-', $item['value'] );
				if ( count( $item_arr ) !== 2 || 0 !== strpos( $item_arr[0], 'tax-' ) ) {
					continue;
				}
				$term_id  = (int) str_replace( 'tax-', '', $item_arr[0] );
				$taxonomy = $item_arr[1];

				$posts = get_posts( [
					'post_type'      => 'academy_courses',
					'fields'         => 'ids',
					'posts_per_page' => - 1,
					'tax_query'      => [
						[
							'taxonomy' => $taxonomy,
							'field'    => 'term_id',
							'terms'    => $term_id,
						],
					],
				] );
				foreach ( $posts as $post ) {
					$course_ids[] = $post;
				}
			}
		}

		return $course_ids;
	}

	protected function get_post_id( $str ) {
		if ( preg_match( '/^post-(\d+)-/', $str, $matches ) ) {
			return (int) $matches[1];
		}

		return false;
	}

	protected function update_course_enroll_status( array $course_ids, int $user_id, string $status = 'completed' ) {
		$course_ids_formatter = implode( ',', array_fill( 0, count( $course_ids ), '%d' ) );
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_status=%s WHERE post_author=%d
                AND post_parent IN ($course_ids_formatter)
                AND post_type='academy_enrolled'", $status, $user_id, ...$course_ids ) );
	}

	public function stop_from_redirecting( $membership_id, $post ) {
		if ( 'academy_courses' !== $post->post_type ) {
			return $membership_id;
		}

		return false;
	}

	public function change_enrollment_form( $form, $course_id ) {
		[ $integrations, $is_available_membership ] = $this->get_course_integrations( $course_id );

		if ( empty( $integrations ) || ! $is_available_membership ) {
			return $form;
		}

		$integrations = $this->get_only_membership_integrations( $integrations );

		$is_paid_course = 'paid' === $this->course_type( (int) $course_id );
		$is_enrolled    = $this->is_enrolled( $course_id );

		$is_purchased = $this->is_purchased_membership( $integrations );
		if ( $is_enrolled || $is_purchased ) {
			return $form;
		}

		return $this->generate_enrollment_form( $form, $this->is_only_membership( $course_id ), $integrations, $is_paid_course, $is_enrolled );
	}

	public function generate_enrollment_form( $form, $is_only_membership, $integrations, $is_paid_course, $is_enrolled ): string {
		if ( $is_paid_course && ! $is_enrolled && ! $is_only_membership ) {
			return $form . '<div class="storeengine-or-divider"><span>OR</span></div>' . $this->get_enrollment_template( $integrations );
		}

		return $form . $this->get_enrollment_template( $integrations );
	}

	public function get_enrollment_template( $integrations ) {
		ob_start();
		Template::get_template( 'integrations/academy-lms/membership-enrollment-form.php', [
			'product_id'   => current( $integrations )->price->get_product_id(),
			'integrations' => $integrations,
			'count'        => count( $integrations ),
		] );

		return ob_get_clean();
	}

	public function load_enrolled_template( $course_id ) {
		ob_start();
		Template::get_template( 'integrations/academy-lms/membership-reload-form.php', [
			'course_id' => $course_id,
		] );

		return ob_get_clean();
	}

	public function is_enrolled( $course_id, $customer_id = 0 ) {
		if ( ! $customer_id ) {
			$customer_id = get_current_user_id();
		}

		return \Academy\Helper::is_enrolled( $course_id, $customer_id, 'on-hold' );
	}

	public function modify_course_type( $course_type, $course_id ): string {
		[ $integrations ] = $this->get_course_integrations( $course_id );

		if ( count( $integrations ) > 0 ) {
			$course_type  = 'paid';
			$is_purchased = $this->is_purchased_membership( $integrations );
			if ( $is_purchased ) {
				return 'free';
			}
		}

		return $course_type;
	}

	protected function get_course_integrations( $course_id ): array {
		$all_plans = HelperAddon::get_all_plans( $course_id );

		$integrations = [];
		foreach ( $all_plans as $plan_id => $plan ) {
			$integrations = array_merge( $integrations, $this->get_integration_repository( $plan_id ) );
		}

		$is_available_membership = $this->is_available_membership( $integrations );

		return [ $integrations, $is_available_membership ];
	}

	public function course_type( $course_id ) {
		return \Academy\Helper::get_course_type( $course_id );
	}

	public function is_purchased_membership( $integrations ): bool {
		$subscription_price_ids = [];

		foreach ( $integrations as $integration ) {
			if ( Helper::is_purchase_the_membership( $integration->integration->get_product_id(), $integration->integration->get_price_id() ) ) {
				if ( 'subscription' === $integration->price->get_type() ) {
					$subscription_price_ids[] = $integration->price->get_id();
					continue;
				}

				return true;
			}
		}

		if ( ! empty( $subscription_price_ids ) ) {
			$subscription_price_ids_formatter = implode( ',', array_fill( 0, count( $subscription_price_ids ), '%d' ) );

			global $wpdb;
			$order_item_meta_table = $wpdb->prefix . 'storeengine_order_item_meta';
			$order_item_table      = $wpdb->prefix . 'storeengine_order_items';
			$order_table           = $wpdb->prefix . 'storeengine_orders';
			$result                = $wpdb->get_row(
				$wpdb->prepare( "
				SELECT o.id
					FROM
						$order_item_meta_table oim
						INNER JOIN $order_item_table oi ON oi.order_item_id = oim.order_item_id
						INNER JOIN $order_table o ON o.id = oi.order_id
					WHERE
						oim.meta_key = '_price_id'
						AND oim.meta_value IN ($subscription_price_ids_formatter)
						AND o.`type` = 'subscription'
						AND o.status = 'active'
					LIMIT 1
				", ...$subscription_price_ids )
			);

			if ( $result ) {
				return true;
			}
		}

		return false;
	}

	public function is_available_membership( $integrations, $is_only = false ) {
		$flag = 0;
		foreach ( $integrations as $integration_item ) {
			if ( $this->get_id() === $integration_item->integration->get_provider() ) {
				$flag ++;
			}
		}

		if ( $is_only ) {
			if ( (int) count( $integrations ) === $flag ) {
				return true;
			} else {
				return false;
			}
		}

		if ( $flag ) {
			return true;
		}

		return false;
	}

	/**
	 * @param $integrations
	 *
	 * @return IntegrationRepositoryData[]
	 */
	public function get_only_membership_integrations( $integrations ): array {
		$only_membership = [];
		foreach ( $integrations as $integration_item ) {
			if ( $this->get_id() === $integration_item->integration->get_provider() ) {
				$only_membership[] = $integration_item;
			}
		}

		return $only_membership;
	}

	protected function is_only_membership( int $course_id ) {
		global $wpdb;

		return ! $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}storeengine_integrations WHERE provider = 'storeengine/academylms' AND integration_id = %d",
				$course_id
			)
		);
	}
}
