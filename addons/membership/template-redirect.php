<?php

namespace StoreEngine\Addons\Membership;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TemplateRedirect {

	public $restriction_data = [];
	public $specified_date   = '';

	public static function init() {
		$self = new self();
		add_action( 'template_redirect', [ $self, 'processed_content' ] );
		add_action( 'admin_bar_menu', [ $self, 'add_admin_menu_bar_contents' ], 999 );
	}

	public function processed_content() {
		if ( is_home() || is_front_page() ) {
			return;
		}

		if ( is_user_logged_in() && current_user_can( 'administrator' ) ) {
			return;
		}

		// Restrict media access
		$this->restrict_media_access();

		global $user_ID, $post;

		$post_id = isset( $post->ID ) ? intval( $post->ID ) : false;

		$this->check_rules_mechanism( $user_ID, $post_id );
	}

	public function check_rules_mechanism( $user_id, $post_id ) {
		$all_plans = HelperAddon::get_all_plans( $post_id );
		$rule_id   = (int) ( ! empty( $all_plans ) ? array_keys( $all_plans )[0] : 0 );

		if ( $rule_id ) {
			global $post;

			if ( isset( $all_plans[ $rule_id ]['include']['rules'] ) && in_array( 'basic-global', $all_plans[ $rule_id ]['include']['rules'], true ) ) {
				if ( Helper::is_storeengine_page( $post_id ) ) {
					return;
				}
			}

			$is_protect = apply_filters( 'storeengine/membership/before_content_protect', $rule_id, $post );

			$purchased_membership = get_user_meta( $user_id, '_storeengine_purchased_membership_ids', true ) ?? [];

			if ( is_array( $purchased_membership ) && in_array( $rule_id, $purchased_membership, true ) ) {
				return;
			}

			if ( $is_protect ) {
				// Set the Authorization
				$authorization = get_post_meta( $rule_id, '_storeengine_membership_authorization', true ) ?? [];

				if ( ! empty( $authorization ) && 'redirect' === $authorization['type'] ) {
					$redirect_url = $authorization['redirect_url'] ?? home_url();

					$post->post_content = '<p>' . esc_html__( 'This content is restricted', 'storeengine' ) . '</p>';

					add_filter( 'comments_open', '__return_false' );
					add_filter( 'get_comments_number', '__return_false' );

					wp_safe_redirect( $redirect_url );

					exit;
				}

				$this->merge_restriction_contents( $authorization );

				$this->restriction_data['prices'] = array_map( fn( $integration_repository ) => $integration_repository->price, Helper::get_integration_repository_by_id( 'storeengine/membership-addon', $rule_id ) );

				add_filter( 'template_include', [ $this, 'restricted_page_template' ] );
			}
		}
	}

	public function merge_restriction_contents( array $data ) {
		$this->restriction_data = $data;
		if ( empty( $this->restriction_data['message'] ) ) {
			$this->restriction_data['message'] = __( 'To access this content, please purchase a plan. Once you’ve purchased, the full content will be unlocked instantly on this page.', 'storeengine' );
		}
	}

	public function restricted_page_template( $template ) {
		$this->restriction_data['page_title'] = apply_filters( 'storeengine_restricted_page_title', 'This content is restricted' );

		add_filter( 'the_content', [ $this, 'add_content_to_template' ] );

		return $template;
	}

	public function add_content_to_template( $content ) {
		$path = STOREENGINE_MEMBERSHIP_TEMPLATE_DIR . 'restricted-template.php';
		if ( ! file_exists( $path ) ) {
			return $content;
		}

		$args = [
			'page_title' => $this->restriction_data['page_title'],
			'message'    => $this->restriction_data['message'],
			'prices'     => $this->restriction_data['prices'],
		];
		ob_start();
		include $path;

		return ob_get_clean();
	}

	public function restrict_media_access() {
		if ( is_admin() ) {
			return;
		}

		$filepath = '';
		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$requested_file = basename( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
			$upload_dir     = wp_upload_dir();
			$filepath       = $upload_dir['basedir'] . '/' . $requested_file;
		}

		// Check if the file exists in the uploads directory
		if ( $filepath && file_exists( $filepath ) ) {
			if ( ! is_user_logged_in() ) {
				wp_safe_redirect( home_url() );
				exit;
			}
		}
	}

	public function add_admin_menu_bar_contents( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) || is_admin() ) {
			return;
		}

		global $post;
		$post_id   = isset( $post->ID ) ? intval( $post->ID ) : false;
		$all_plans = HelperAddon::get_all_plans( $post_id );

		$rule_id = ! empty( $all_plans ) ? array_keys( $all_plans )[0] : 0;

		$dynamic_plan_title = __( 'Post is not restricted', 'storeengine' );
		$dynamic_plan_url   = '';
		$dynamic_logo_url   = STOREENGINE_MEMBERSHIP_ASSETS_DIR . 'images/logo-grayscale.png';

		if ( $rule_id ) {
			$dynamic_plan_title = get_the_title( $rule_id );
			$dynamic_plan_url   = admin_url( "admin.php?page=storeengine-membership_rules&id={$rule_id}&action=edit" );
			$dynamic_logo_url   = STOREENGINE_ASSETS_URI . 'images/logo.svg';
		}

		$menu_id      = 'storeengine-plans';
		$all_plans_id = 'storeengine-membership-plans';
		$new_plan_id  = 'storeengine-membership-add-plan';

		$wp_admin_bar->add_node( array(
			'id'    => $menu_id,
			'title' => sprintf( '<img src="%1$s" alt="%2$s">', $dynamic_logo_url, esc_html__( 'Membership Plans', 'storeengine' ) ),
			// phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
			'meta'  => [
				'title' => __( 'Membership Plans', 'storeengine' ),
				'class' => $all_plans_id,
			],
		) );

		$wp_admin_bar->add_node( array(
			'id'     => $menu_id . '-' . $post_id,
			'title'  => $dynamic_plan_title,
			'parent' => $menu_id,
			'href'   => $dynamic_plan_url,
			'meta'   => [
				'title' => __( 'Membership Plans', 'storeengine' ),
				'class' => $all_plans_id . ' current',
			],
		) );

		$wp_admin_bar->add_node( array(
			'id'     => $all_plans_id,
			'title'  => __( 'All Plans', 'storeengine' ),
			'parent' => $menu_id,
			'href'   => admin_url( 'admin.php?page=storeengine-membership_rules' ),
			'meta'   => [
				'title' => __( 'All Membership Plans', 'storeengine' ),
			],
		) );

		$wp_admin_bar->add_node( array(
			'id'     => $new_plan_id,
			'title'  => __( 'Add New Plan', 'storeengine' ),
			'parent' => $menu_id,
			'href'   => admin_url( 'admin.php?page=storeengine-membership_rules&action=new' ),
			'meta'   => [
				'title' => __( 'Add New Membership Plans', 'storeengine' ),
			],
		) );
	}
}
