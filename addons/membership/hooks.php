<?php

namespace StoreEngine\Addons\Membership;

use StoreEngine\Classes\Integration;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hooks {
	protected int $post_id = 0;

	public static function init() {
		$self = new self();
		add_filter( 'storeengine/backend_scripts_data', [ $self, 'add_user_roles' ] );
		add_action( 'wp_enqueue_scripts', [ $self, 'enqueue_scripts' ] );
		add_action( 'save_post', array( $self, 'set_post_id' ), 10, 3 );
		add_action( 'updated_post_meta', array( $self, 'set_user_meta_data' ), 10, 4 );
		add_filter( 'storeengine/admin_menu_list', [ $self, 'admin_menu_items' ] );
		add_filter( 'display_post_states', array( $self, 'add_display_post_states' ), 10, 2 );
		add_action( 'delete_post_storeengine_groups', [ $self, 'handle_access_group_deletion' ] );
	}

	public function admin_menu_items( array $items ): array {
		return array_merge( $items, [
			STOREENGINE_PLUGIN_SLUG . '-membership_rules' => [
				'title'      => __( 'Access Groups', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 80,
			],
		] );
	}

	public function enqueue_scripts() {
		wp_enqueue_style( 'storeengine-membership-styles', STOREENGINE_MEMBERSHIP_ASSETS_DIR . 'css/style.css', [], STOREENGINE_MEMBERSHIP_VERSION );
	}

	public function add_user_roles( $script_data ) {
		$script_data['user_roles'] = Helper::get_all_roles();
		$script_data['all_posts']  = $this->get_all_posts();

		return $script_data;
	}

	public function get_all_posts( array $arg = [] ) {
		$post_type = ! empty( $arg['postType'] ) ? $arg['postType'] : 'page';
		$postId    = ! empty( $arg['postId'] ) ? $arg['postId'] : 0;
		$keyword   = ! empty( $arg['keyword'] ) ? $arg['keyword'] : '';

		if ( $postId ) {
			$args = array(
				'post_type' => $post_type,
				'p'         => $postId,
			);
		} else {
			$args = array(
				'post_type'      => $post_type,
				'posts_per_page' => 10,
			);
			if ( ! empty( $keyword ) ) {
				$args['s'] = $keyword;
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				$args['author'] = get_current_user_id();
			}
		}
		$results = array();
		$posts   = get_posts( $args );
		if ( is_array( $posts ) ) {
			foreach ( $posts as $post ) {
				$results[] = array(
					'label' => $post->post_title,
					'value' => $post->ID,
				);
			}
		}

		return $results;
	}

	public function set_post_id( $post_id, $post_after, $post_before ) {
		if ( STOREENGINE_MEMBERSHIP_POST_TYPE !== $post_after->post_type ) {
			return;
		}

		// Clear cache
		wp_cache_delete( $post_id, 'post_meta' );

		$this->post_id = absint( $post_id );
	}

	public function set_user_meta_data( $meta_id, $object_id, $meta_key, $meta_value ) {
		$user_roles_meta = '_storeengine_membership_user_roles';

		if ( ( $this->post_id !== $object_id ) && ( $user_roles_meta !== $meta_key ) ) {
			return;
		}

		// Reset.
		$this->post_id = 0;

		$user_roles = get_post_meta( $object_id, $user_roles_meta, true );

		if ( ! empty( $user_roles ) ) {
			$roles = [];
			foreach ( $user_roles as $role ) {
				$roles[] = $role['value'];
			}

			$membership_user_ids = get_users( [
				'role__in' => $roles,
				'fields'   => 'ID',
			] );

			$non_membership_user_ids = get_users( [
				'role__not_in' => $roles,
				'fields'       => 'ID',
			] );

			$this->remove_non_membership_user_meta_data( $non_membership_user_ids );
			$this->add_membership_user_meta_data( $object_id, $membership_user_ids );
		}
	}

	public function remove_non_membership_user_meta_data( $non_membership_user_ids = [] ) {
		if ( empty( $non_membership_user_ids ) ) {
			return;
		}

		foreach ( $non_membership_user_ids as $user_id ) {
			$user_membership_meta_key   = '_storeengine_user_membership_data';
			$user_membership_meta_value = [];
			update_user_meta( $user_id, $user_membership_meta_key, $user_membership_meta_value );
		}
	}

	public function add_membership_user_meta_data( $object_id = '', $membership_user_ids = [] ) {
		if ( empty( $membership_user_ids ) || ( '' === $object_id ) ) {
			return;
		}

		$content_protects_meta      = '_storeengine_membership_content_protect_types';
		$membership_expiration_meta = '_storeengine_membership_expiration';
		$content_protects_data      = get_post_meta( $object_id, $content_protects_meta, true );
		$membership_expiration_data = get_post_meta( $object_id, $membership_expiration_meta, true );

		foreach ( $membership_user_ids as $user_id ) {
			$user_membership_meta_key   = '_storeengine_user_membership_data';
			$user_membership_meta_value = [
				$object_id => [
					'content_protect_types' => $content_protects_data,
					'expiration_date'       => $membership_expiration_data,
				],
			];
			update_user_meta( $user_id, $user_membership_meta_key, $user_membership_meta_value );
		}
	}

	public function add_display_post_states( $post_states, $post ) {
		if ( (int) Helper::get_settings( 'membership_pricing_page' ) === $post->ID ) {
			$post_states['storeengine_page_for_membership_pricing'] = __( 'StoreEngine Membership Pricing Page', 'storeengine' );
		}

		return $post_states;
	}

	public function handle_access_group_deletion( int $post_id ) {
		$integrations_repository = Helper::get_integration_repository_by_id( 'storeengine/membership-addon', $post_id );

		foreach ( $integrations_repository as $integration ) {
			$integration->integration->delete();
		}
	}
}
