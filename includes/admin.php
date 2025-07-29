<?php

namespace StoreEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;

class Admin {

	public static function init() {
		$self = new self();

		// Load admin classes.
		Admin\Menu::init();
		Admin\Notices::init();

		// Dispatch insights.
		$self->dispatch_insights();

		// Add hooks.
		add_filter( 'allowed_redirect_hosts', array( $self, 'add_white_listed_redirect_hosts' ) );
		add_filter( 'display_post_states', array( $self, 'add_display_post_states' ), 10, 2 );
		add_filter( 'theme_page_templates', [ $self, 'load_page_templates' ] );

		add_action( 'admin_init', [ $self, 'prevent_admin_access' ] );
		add_action( 'current_screen', [ $self, 'conditional_loaded' ] );
	}

	public function add_white_listed_redirect_hosts( $hosts ) {
		$hosts[] = 'storeengine.pro';

		return $hosts;
	}

	public function add_display_post_states( $post_states, $post ) {
		if ( (int) Helper::get_settings( 'shop_page' ) === $post->ID ) {
			$post_states['storeengine_page_for_shop_page'] = __( 'StoreEngine Shop Page', 'storeengine' );
		}

		if ( (int) Helper::get_settings( 'cart_page' ) === $post->ID ) {
			$post_states['storeengine_page_for_cart_page'] = __( 'StoreEngine Cart Page', 'storeengine' );
		}

		if ( (int) Helper::get_settings( 'checkout_page' ) === $post->ID ) {
			$post_states['storeengine_page_for_checkout_page'] = __( 'StoreEngine Checkout Page', 'storeengine' );
		}

		if ( (int) Helper::get_settings( 'thankyou_page' ) === $post->ID ) {
			$post_states['storeengine_page_for_thankyou_page'] = __( 'StoreEngine Thank You Page', 'storeengine' );
		}

		if ( (int) Helper::get_settings( 'dashboard_page' ) === $post->ID ) {
			$post_states['storeengine_page_for_dashboard_page'] = __( 'StoreEngine Dashboard Page', 'storeengine' );
		}

		return $post_states;
	}

	public function load_page_templates( $templates ) {
		// Page editor error (non-fse theme) Updating failed. Invalid parameter(s): template
		// https://github.com/Automattic/sensei/issues/7215
		// https://github.com/Automattic/wp-calypso/issues/59570
		// https://forum.muffingroup.com/betheme/discussion/51757/creating-a-custom-template

		$templates['storeengine-canvas.php'] = esc_html__( 'StoreEngine Canvas', 'storeengine' );

		return $templates;
	}

	public function prevent_admin_access() {
		$prevent_access = false;

		// Do not interfere with admin-post or admin-ajax requests.
		$exempted_paths = [ 'admin-post.php', 'admin-ajax.php' ];

		if (
			apply_filters( 'storeengine/disable_admin_bar', true )
			&& isset( $_SERVER['SCRIPT_FILENAME'] )
			&& ! in_array( basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_FILENAME'] ) ) ), $exempted_paths, true )
		) {
			$has_cap     = false;
			$access_caps = [ 'edit_posts', 'manage_storeengine', 'view_admin_dashboard' ];


			foreach ( $access_caps as $access_cap ) {
				if ( current_user_can( $access_cap ) ) {
					$has_cap = true;
					break;
				}
			}

			if ( ! $has_cap ) {
				$prevent_access = true;
			}

			if ( 'storeengine_customer' === reset( wp_get_current_user()->roles ) ) {
				$prevent_access = true;
			}
		}

		if ( apply_filters( 'storeengine/prevent_admin_access', $prevent_access ) ) {
			wp_safe_redirect( Helper::get_dashboard_url() );
			exit;
		}
	}

	public function conditional_loaded() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		switch ( $screen->id ) {
			case 'storeengine_page_storeengine-get-pro':
				wp_safe_redirect( 'https://storeengine.pro/pricing' );
				exit;
		}

		if ( 'options-permalink' === $screen->id ) {
			Admin\PermalinkSettings::init();
		}
	}

	public function dispatch_insights() {
		Admin\Insights::init(
			'https://kodezen.com',
			STOREENGINE_PLUGIN_SLUG,
			'plugin',
			STOREENGINE_VERSION,
			[
				'logo'                 => STOREENGINE_ASSETS_URI . 'images/logo.svg', // default logo URL
				'optin_message'        => 'Help improve StoreEngine! Allow anonymous usage tracking?',
				'deactivation_message' => 'If you have a moment, please share why you are deactivating StoreEngine:',
				'deactivation_reasons' => [
					'no_longer_needed'               => [
						'label' => 'I no longer need the plugin',
					],
					'found_a_better_plugin'          => [
						'label'                     => 'I found a better plugin',
						'has_custom_reason'         => true,
						'custom_reason_placeholder' => 'Please share which plugin',
					],
					'couldnt_get_the_plugin_to_work' => [
						'label' => 'I couldn\'t get the plugin to work',
					],
					'temporary_deactivation'         => [
						'label' => 'It\'s a temporary deactivation',
					],
					'have_storeengine_pro'           => [
						'label'       => 'I have StoreEngine Pro',
						'toggle_text' => 'Wait! Don\'t deactivate StoreEngine. You have to activate both StoreEngine and StoreEngine Pro in order for the plugin to work.',
					],
					'other'                          => [
						'label'                     => 'Other',
						'has_custom_reason'         => true,
						'custom_reason_placeholder' => 'Please share the reason',
					],
				],
			]
		);
	}
}
